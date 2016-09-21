<?php
namespace Friday\Base;

/**
 * Class Looper
 *
 * @package Friday\Base
 */
class Looper {
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
     * @var Tasks
     */
    private $tasks;

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

    public function __construct()
    {

        $this->nextTickQueue    = new NextTickQueue($this);
        $this->futureTickQueue  = new FutureTickQueue($this);
        $this->tasks           = new Tasks();
    }

    /**
     * @param $stream
     * @param callable $listener
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
     * @param $stream
     * @param callable $listener
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
     * @param $stream
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
    public function removeWriteStream($stream)
    {
        $key = (int) $stream;
        unset(
            $this->writeStreams[$key],
            $this->writeListeners[$key]
        );
    }

    /**
     * @param $stream
     */
    public function removeStream($stream)
    {
        $this->removeReadStream($stream);
        $this->removeWriteStream($stream);
    }

    /**
     * Causes the task r to be added to the message queue.
     *
     * @param callable $callback Callback function for calling
     * @param mixed $data Arbitrary data associated with task
     * @return Task
     */
    public function task(callable $callback, $data = null){
        $task = new Task($this, $callback, Task::MIN_INTERVAL, false, $data);
        $this->tasks->add($task);
        return $task;
    }

    /**
     * Causes the callback to be added to the message queue, to be run at a specific time given.
     * @param callable $callback
     * @param float $time
     * @param mixed $data Arbitrary data associated with task
     * @return Task
     */
    public function taskAtTime(callable $callback, float $time, $data = null){
        $currentTime = microtime(true);

        $task = new Task($this, $callback, $time - $currentTime, false, $data);
        $this->tasks->add($task);
        return $task;
    }

    /**
     * Causes the callback to be added to the message queue, to be run after the specified amount of time elapses.
     *
     * @param callable $callback
     * @param float $delay
     * @param mixed $data Arbitrary data associated with task
     * @return Task
     */
    public function taskWithDelayed(callable $callback, float $delay, $data = null){
        $task = new Task($this, $callback, $delay, false, $data);
        $this->tasks->add($task);
        return $task;
    }

    /**
     * @param callable $callback
     * @param float $interval
     * @param null $data
     * @param mixed $data Arbitrary data associated with task
     * @return Task
     */
    public function taskPeriodic(callable $callback, float $interval, $data = null){
        $task = new Task($this, $callback, $interval, true, $data);
        $this->tasks->add($task);
        return $task;
    }


    /**
     * {@inheritdoc}
     */
    public function taskCancel(Task $timer)
    {
        $this->tasks->cancel($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function isTaskActive(Task $timer) : bool
    {
        return $this->tasks->contains($timer);
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
        $this->tasks->tick();
        $this->waitForStreamActivity(0);
    }
    /**
     * Run the message queue in this thread.
     */
    public function loop()
    {
        $this->running = true;
        while ($this->running) {
            $this->nextTickQueue->tick();
            $this->futureTickQueue->tick();
            $this->tasks->tick();
            // Next-tick or future-tick queues have pending callbacks ...
            if (!$this->running || !$this->nextTickQueue->isEmpty() || !$this->futureTickQueue->isEmpty()) {
                $timeout = 0;
                // There is a pending timer, only block until it is due ...
            } elseif ($scheduledAt = $this->tasks->getFirst()) {
                $timeout = $scheduledAt - $this->tasks->getTime();
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
     * Quits the looper.
     */
    public function quit()
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