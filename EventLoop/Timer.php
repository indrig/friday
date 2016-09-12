<?php
namespace Friday\EventLoop;


class Timer implements TimerInterface
{
    const MIN_INTERVAL = 0.000001;
    /**
     * @var LoopInterface
     */
    protected $loop;

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
     * @param LoopInterface $loop     The loop with which this timer is associated
     * @param float         $interval The interval after which this timer will execute, in seconds
     * @param callable      $callback The callback that will be executed when this timer elapses
     * @param bool          $periodic Whether the time is periodic
     * @param mixed         $data     Arbitrary data associated with timer
     */
    public function __construct(LoopInterface $loop, float $interval, callable $callback, bool $periodic = false, mixed $data = null)
    {
        if ($interval < self::MIN_INTERVAL) {
            $interval = self::MIN_INTERVAL;
        }
        $this->loop = $loop;
        $this->interval = (float) $interval;
        $this->callback = $callback;
        $this->periodic = (bool) $periodic;
        $this->data = null;
    }
    /**
     * {@inheritdoc}
     */
    public function getLoop() : LoopInterface
    {
        return $this->loop;
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
     * {@inheritdoc}
     */
    public function setData($data)
    {
        $this->data = $data;
    }
    /**
     * {@inheritdoc}
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
        return $this->loop->isTimerActive($this);
    }
    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->loop->cancelTimer($this);
    }
}