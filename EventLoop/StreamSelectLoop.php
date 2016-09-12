<?php
namespace Friday\EventLoop;

use Friday\Base\Component;

/**
 * Class StreamSelectLoop
 * @package Friday\EventLoop
 */
class StreamSelectLoop extends Component implements LoopInterface
{
    const MICROSECONDS_PER_SECOND = 1000000;

    /**
     * @var NextTickQueue
     */
    private $nextTickQueue;

    /**
     * @var FutureTickQueue
     */
    private $futureTickQueue;
    /**
     * @var Timers
     */
    private $timers;

    /**
     * @var array
     */
    private $readStreams = [];

    /**
     * @var array
     */
    private $readListeners = [];

    /**
     * @var array
     */
    private $writeStreams = [];

    /**
     * @var array
     */
    private $writeListeners = [];
    /**
     * @var
     */
    private $running;

    public function init()
    {
        $this->nextTickQueue    = new NextTickQueue($this);
        $this->futureTickQueue  = new FutureTickQueue($this);
        $this->timers           = new Timers();
    }
    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $key = (int) $stream;
        if (!isset($this->readStreams[$key])) {
            $this->readStreams[$key] = $stream;
            $this->readListeners[$key] = $listener;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;
        if (!isset($this->writeStreams[$key])) {
            $this->writeStreams[$key] = $stream;
            $this->writeListeners[$key] = $listener;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream)
    {
        $key = (int) $stream;
        unset(
            $this->readStreams[$key],
            $this->readListeners[$key]
        );
    }
    /**
     * {@inheritdoc}
     */
    public function removeWriteStream(resource $stream)
    {
        $key = (int) $stream;
        unset(
            $this->writeStreams[$key],
            $this->writeListeners[$key]
        );
    }
    /**
     * {@inheritdoc}
     */
    public function removeStream(resource $stream)
    {
        $this->removeReadStream($stream);
        $this->removeWriteStream($stream);
    }
    /**
     * {@inheritdoc}
     */
    public function addTimer(float $interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, false);
        $this->timers->add($timer);
        return $timer;
    }
    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer(float $interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);
        $this->timers->add($timer);
        return $timer;
    }
    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        $this->timers->cancel($timer);
    }
    /**
     * {@inheritdoc}
     */
    public function isTimerActive(TimerInterface $timer) : bool
    {
        return $this->timers->contains($timer);
    }
    /**
     * {@inheritdoc}
     */
    public function nextTick(callable $listener)
    {
        $this->nextTickQueue->add($listener);
    }
    /**
     * {@inheritdoc}
     */
    public function futureTick(callable $listener)
    {
        $this->futureTickQueue->add($listener);
    }
    /**
     * {@inheritdoc}
     */
    public function tick()
    {
        $this->nextTickQueue->tick();
        $this->futureTickQueue->tick();
        $this->timers->tick();
        $this->waitForStreamActivity(0);
    }
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->running = true;
        while ($this->running) {
            $this->nextTickQueue->tick();
            $this->futureTickQueue->tick();
            $this->timers->tick();
            // Next-tick or future-tick queues have pending callbacks ...
            if (!$this->running || !$this->nextTickQueue->isEmpty() || !$this->futureTickQueue->isEmpty()) {
                $timeout = 0;
                // There is a pending timer, only block until it is due ...
            } elseif ($scheduledAt = $this->timers->getFirst()) {
                $timeout = $scheduledAt - $this->timers->getTime();
                if ($timeout < 0) {
                    $timeout = 0;
                } else {
                    $timeout *= self::MICROSECONDS_PER_SECOND;
                }
                // The only possible event is stream activity, so wait forever ...
            } elseif ($this->readStreams || $this->writeStreams) {
                $timeout = null;
                // There's nothing left to do ...
            } else {
                break;
            }
            $this->waitForStreamActivity($timeout);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }

    /**
     * Wait/check for stream activity, or until the next timer is due.
     */
    private function waitForStreamActivity($timeout)
    {
        $read  = $this->readStreams;
        $write = $this->writeStreams;
        $available = $this->streamSelect($read, $write, $timeout);
        if (false === $available) {
            // if a system call has been interrupted,
            // we cannot rely on it's outcome
            return;
        }
        foreach ($read as $stream) {
            $key = (int) $stream;
            if (isset($this->readListeners[$key])) {
                call_user_func($this->readListeners[$key], $stream, $this);
            }
        }
        foreach ($write as $stream) {
            $key = (int) $stream;
            if (isset($this->writeListeners[$key])) {
                call_user_func($this->writeListeners[$key], $stream, $this);
            }
        }
    }
    /**
     * Emulate a stream_select() implementation that does not break when passed
     * empty stream arrays.
     *
     * @param array        &$read   An array of read streams to select upon.
     * @param array        &$write  An array of write streams to select upon.
     * @param integer|null $timeout Activity timeout in microseconds, or null to wait forever.
     *
     * @return integer|false The total number of streams that are ready for read/write.
     * Can return false if stream_select() is interrupted by a signal.
     */
    protected function streamSelect(array &$read, array &$write, $timeout)
    {
        if ($read || $write) {
            $except = null;
            // suppress warnings that occur, when stream_select is interrupted by a signal
            return @stream_select($read, $write, $except, $timeout === null ? null : 0, $timeout);
        }
        $timeout && usleep($timeout);
        return 0;
    }
}