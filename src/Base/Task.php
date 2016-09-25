<?php
namespace Friday\Base;

class Task{
    const MIN_INTERVAL = 0.000001;
    /**
     * @var Looper
     */
    protected $looper;

    /**
     * @var float
     */
    protected $interval;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var bool
     */
    protected $periodic;

    /**
     * @var mixed
     */
    protected $data;
    /**
     * Constructor initializes the fields of the Timer
     *
     * @param Looper $loop  The loop with which this task is associated
     * @param float         $interval The interval after which this timer will execute, in seconds
     * @param callable      $callback The callback that will be executed when this timer elapses
     * @param bool          $periodic Whether the time is periodic
     * @param mixed         $data     Arbitrary data associated with task
     */
    public function __construct(Looper $loop, callable $callback, float $interval= Task::MIN_INTERVAL, bool $periodic = false, $data = null)
    {
        if ($interval < self::MIN_INTERVAL) {
            $interval = self::MIN_INTERVAL;
        }

        $this->looper   = $loop;
        $this->interval = $interval;
        $this->callback = $callback;
        $this->periodic = $periodic;
        $this->data     = $data;
    }
    /**
     * {@inheritdoc}
     */
    public function getLooper() : Looper
    {
        return $this->looper;
    }
    /**
     * {@inheritdoc}
     */
    public function getInterval() : float
    {
        return $this->interval;
    }
    /**
     * {@inheritdoc}
     */
    public function getCallback() : callable
    {
        return $this->callback;
    }

    /**
     * Set arbitrary data associated with task
     *
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }
    /**
     * Get arbitrary data associated with task
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function isPeriodic() : bool
    {
        return $this->periodic;
    }
    /**
     * {@inheritdoc}
     */
    public function isActive() : bool
    {
        return $this->looper->isTaskActive($this);
    }
    /**
     * {@inheritdoc}
     */
    public function remove()
    {
        $this->looper->taskCancel($this);
    }
}