<?php
namespace Friday\Base;

use SplObjectStorage;
use SplPriorityQueue;

class Tasks
{
    /**
     * @var float
     */
    private $time;

    /**
     * @var SplObjectStorage
     */
    private $tasks;

    /**
     * @var SplPriorityQueue
     */
    private $scheduler;

    public function __construct()
    {
        $this->tasks       = new SplObjectStorage();
        $this->scheduler    = new SplPriorityQueue();
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
     * @param Task $timer
     */
    public function add(Task $timer)
    {
        $interval = $timer->getInterval();
        $scheduledAt = $interval + $this->getTime();
        $this->tasks->attach($timer, $scheduledAt);
        $this->scheduler->insert($timer, -$scheduledAt);
    }

    /**
     * @param Task $timer
     * @return bool
     */
    public function contains(Task $timer) : bool
    {
        return $this->tasks->contains($timer);
    }

    /**
     * @param Task $timer
     */
    public function cancel(Task $timer)
    {
        $this->tasks->detach($timer);
    }

    /**
     * @return Task
     */
    public function getFirst()
    {
        while ($this->scheduler->count()) {
            $timer = $this->scheduler->top();
            if ($this->tasks->contains($timer)) {
                return $this->tasks[$timer];
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
        return count($this->tasks) === 0;
    }

    /**
     *
     */
    public function tick()
    {
        $time = $this->updateTime();
        $timers = $this->tasks;
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