<?php
namespace Friday\EventLoop;

use SplObjectStorage;
use SplPriorityQueue;

class Timers
{
    /**
     * @var float
     */
    private $time;

    /**
     * @var SplObjectStorage
     */
    private $timers;

    /**
     * @var SplPriorityQueue
     */
    private $scheduler;

    public function __construct()
    {
        $this->timers   = new SplObjectStorage();
        $this->scheduler = new SplPriorityQueue();
    }

    /**
     * @return float
     */
    public function updateTime() : float
    {
        return $this->time = microtime(true);
    }

    /**
     * @return float
     */
    public function getTime() : float
    {
        return $this->time ?: $this->updateTime();
    }

    /**
     * @param TimerInterface $timer
     */
    public function add(TimerInterface $timer)
    {
        $interval = $timer->getInterval();
        $scheduledAt = $interval + $this->getTime();
        $this->timers->attach($timer, $scheduledAt);
        $this->scheduler->insert($timer, -$scheduledAt);
    }

    /**
     * @param TimerInterface $timer
     * @return bool
     */
    public function contains(TimerInterface $timer) : bool
    {
        return $this->timers->contains($timer);
    }

    /**
     * @param TimerInterface $timer
     */
    public function cancel(TimerInterface $timer)
    {
        $this->timers->detach($timer);
    }

    /**
     * @return Timer
     */
    public function getFirst()
    {
        while ($this->scheduler->count()) {
            $timer = $this->scheduler->top();
            if ($this->timers->contains($timer)) {
                return $this->timers[$timer];
            }
            $this->scheduler->extract();
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isEmpty() : bool
    {
        return count($this->timers) === 0;
    }

    /**
     *
     */
    public function tick()
    {
        $time = $this->updateTime();
        $timers = $this->timers;
        $scheduler = $this->scheduler;
        while (!$scheduler->isEmpty()) {
            $timer = $scheduler->top();
            if (!isset($timers[$timer])) {
                $scheduler->extract();
                $timers->detach($timer);
                continue;
            }
            if ($timers[$timer] >= $time) {
                break;
            }
            $scheduler->extract();
            call_user_func($timer->getCallback(), $timer);
            if ($timer->isPeriodic() && isset($timers[$timer])) {
                $timers[$timer] = $scheduledAt = $timer->getInterval() + $time;
                $scheduler->insert($timer, -$scheduledAt);
            } else {
                $timers->detach($timer);
            }
        }
    }
}