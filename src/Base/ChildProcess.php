<?php
namespace Friday\Base;

use Friday;
use Friday\Base\Event\ExitEvent;
use Friday\Base\Exception\RuntimeException;
use Friday\Stream\Stream;

class ChildProcess extends Component{
    const EVENT_EXIT = 'exit';
    /**
     * @var Stream|null
     */
    public $stdin;
    /**
     * @var Stream|null
     */
    public $stdout;
    /**
     * @var Stream|null
     */
    public $stderr;

    /**
     * @var string|null
     */
    public $cmd;

    /**
     * @var string|null
     */
    public $cwd;
    public $env;
    public $options;
    public $enhanceSigchildCompatibility;
    private $pipes;
    private $process;
    private $status;
    private $exitCode;
    private $fallbackExitCode;
    private $stopSignal;
    private $termSignal;
    private static $sigchild;
    /**
     * Constructor.
     *
     * @param array  $config Options
     * @throws RuntimeException When proc_open() is not installed
     */
    public function __construct(array $config = [])
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('The Process class relies on proc_open(), which is not available on your PHP installation.');
        }

        parent::__construct($config);

        if (null !== $this->env) {
            $this->env = array();
            foreach ($this->env as $key => $value) {
                $this->env[(binary) $key] = (binary) $value;
            }
        }

        $this->enhanceSigchildCompatibility = $this->isSigchildEnabled();
    }
    /**
     * Start the process.
     *
     * After the process is started, the standard IO streams will be constructed
     * and available via public properties. STDIN will be paused upon creation.
     *
     * @param Looper $loop        Loop interface for stream construction
     * @param float         $interval    Interval to periodically monitor process state (seconds)
     * @throws RuntimeException If the process is already running or fails to start
     */
    public function start(Looper $loop = null, $interval = 0.1)
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Process is already running');
        }
        if($loop === null){
            $loop = Friday::$app->getLooper();
        }
        $cmd = $this->cmd;
        $fdSpec = array(
            array('pipe', 'r'), // stdin
            array('pipe', 'w'), // stdout
            array('pipe', 'w'), // stderr
        );
        // Read exit code through fourth pipe to work around --enable-sigchild
        if ($this->isSigchildEnabled() && $this->enhanceSigchildCompatibility) {
            $fdSpec[] = array('pipe', 'w');
            $cmd = sprintf('(%s) 3>/dev/null; code=$?; echo $code >&3; exit $code', $cmd);
        }
        $this->process = proc_open($cmd, $fdSpec, $this->pipes, $this->cwd, $this->env, $this->options);
        if (!is_resource($this->process)) {
            throw new RuntimeException('Unable to launch a new process.');
        }

        $closeCount = 0;
        $that = $this;
        $streamCloseHandler = function () use (&$closeCount, $loop, $interval, $that) {
            $closeCount++;
            if ($closeCount < 2) {
                return;
            }
            $loop->taskPeriodic(function (Task $task) use ($that) {
                if (!$that->isRunning()) {
                    $that->close();
                    $task->remove();
                    $that->trigger(static::EVENT_EXIT, new ExitEvent([
                        'code'          => $that->getExitCode(),
                        'termSignal'    => $that->getTermSignal()
                    ]));
                }
            }, $interval);
        };

        $this->stdin  = Friday::createObject([
            'class' => Stream::class,
            'stream' => $this->pipes[0]
        ]);
        $this->stdin->pause();

        $this->stdout = Friday::createObject([
            'class' => Stream::class,
            'stream' => $this->pipes[1]
        ]);
        $this->stdout->on('close', $streamCloseHandler);

        $this->stderr = Friday::createObject([
            'class' => Stream::class,
            'stream' => $this->pipes[2]
        ]);
        $this->stderr->on('close', $streamCloseHandler);
        // legacy PHP < 5.4 SEGFAULTs for unbuffered, non-blocking reads
        // work around by enabling read buffer again
        if (PHP_VERSION_ID < 50400) {
            stream_set_read_buffer($this->pipes[1], 1);
            stream_set_read_buffer($this->pipes[2], 1);
        }

    }
    /**
     * Close the process.
     *
     * This method should only be invoked via the periodic timer that monitors
     * the process state.
     */
    public function close()
    {
        if ($this->process === null) {
            return;
        }
        $this->stdin->close();
        $this->stdout->close();
        $this->stderr->close();
        if ($this->isSigchildEnabled() && $this->enhanceSigchildCompatibility) {
            $this->pollExitCodePipe();
            $this->closeExitCodePipe();
        }
        $exitCode = proc_close($this->process);
        $this->process = null;
        if ($this->exitCode === null && $exitCode !== -1) {
            $this->exitCode = $exitCode;
        }
        if ($this->exitCode === null && $this->status['exitcode'] !== -1) {
            $this->exitCode = $this->status['exitcode'];
        }
        if ($this->exitCode === null && $this->fallbackExitCode !== null) {
            $this->exitCode = $this->fallbackExitCode;
            $this->fallbackExitCode = null;
        }
    }
    /**
     * Terminate the process with an optional signal.
     *
     * @param int $signal Optional signal (default: SIGTERM)
     * @return boolean Whether the signal was sent successfully
     */
    public function terminate($signal = null)
    {
        if ($signal !== null) {
            return proc_terminate($this->process, $signal);
        }
        return proc_terminate($this->process);
    }
    /**
     * Get the command string used to launch the process.
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->cmd;
    }
    /**
     * Return whether sigchild compatibility is enabled.
     *
     * @return boolean
     */
    public final function getEnhanceSigchildCompatibility()
    {
        return $this->enhanceSigchildCompatibility;
    }
    /**
     * Enable or disable sigchild compatibility mode.
     *
     * Sigchild compatibility mode is required to get the exit code and
     * determine the success of a process when PHP has been compiled with
     * the --enable-sigchild option.
     *
     * @param boolean $enhance
     * @return self
     * @throws RuntimeException If the process is already running
     */
    public final function setEnhanceSigchildCompatibility($enhance)
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Process is already running');
        }
        $this->enhanceSigchildCompatibility = (bool) $enhance;
        return $this;
    }
    /**
     * Get the exit code returned by the process.
     *
     * This value is only meaningful if isRunning() has returned false. Null
     * will be returned if the process is still running.
     *
     * Null may also be returned if the process has terminated, but the exit
     * code could not be determined (e.g. sigchild compatibility was disabled).
     *
     * @return int|null
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }
    /**
     * Get the process ID.
     *
     * @return int|null
     */
    public function getPid()
    {
        $status = $this->getCachedStatus();
        return $status !== null ? $status['pid'] : null;
    }
    /**
     * Get the signal that caused the process to stop its execution.
     *
     * This value is only meaningful if isStopped() has returned true. Null will
     * be returned if the process was never stopped.
     *
     * @return int|null
     */
    public function getStopSignal()
    {
        return $this->stopSignal;
    }
    /**
     * Get the signal that caused the process to terminate its execution.
     *
     * This value is only meaningful if isTerminated() has returned true. Null
     * will be returned if the process was never terminated.
     *
     * @return int|null
     */
    public function getTermSignal()
    {
        return $this->termSignal;
    }
    /**
     * Return whether the process is still running.
     *
     * @return boolean
     */
    public function isRunning()
    {
        if ($this->process === null) {
            return false;
        }
        $status = $this->getFreshStatus();
        return $status !== null ? $status['running'] : false;
    }
    /**
     * Return whether the process has been stopped by a signal.
     *
     * @return boolean
     */
    public function isStopped()
    {
        $status = $this->getFreshStatus();
        return $status !== null ? $status['stopped'] : false;
    }
    /**
     * Return whether the process has been terminated by an uncaught signal.
     *
     * @return boolean
     */
    public function isTerminated()
    {
        $status = $this->getFreshStatus();
        return $status !== null ? $status['signaled'] : false;
    }
    /**
     * Return whether PHP has been compiled with the '--enable-sigchild' option.
     *
     * @see \Symfony\Component\Process\Process::isSigchildEnabled()
     * @return bool
     */
    public final static function isSigchildEnabled()
    {
        if (null !== self::$sigchild) {
            return self::$sigchild;
        }
        ob_start();
        phpinfo(INFO_GENERAL);
        return self::$sigchild = false !== strpos(ob_get_clean(), '--enable-sigchild');
    }
    /**
     * Check the fourth pipe for an exit code.
     *
     * This should only be used if --enable-sigchild compatibility was enabled.
     */
    private function pollExitCodePipe()
    {
        if ( ! isset($this->pipes[3])) {
            return;
        }
        $r = array($this->pipes[3]);
        $w = $e = null;
        $n = @stream_select($r, $w, $e, 0);
        if (1 !== $n) {
            return;
        }
        $data = fread($r[0], 8192);
        if (strlen($data) > 0) {
            $this->fallbackExitCode = (int) $data;
        }
    }
    /**
     * Close the fourth pipe used to relay an exit code.
     *
     * This should only be used if --enable-sigchild compatibility was enabled.
     */
    private function closeExitCodePipe()
    {
        if ( ! isset($this->pipes[3])) {
            return;
        }
        fclose($this->pipes[3]);
        unset($this->pipes[3]);
    }
    /**
     * Return the cached process status.
     *
     * @return array
     */
    private function getCachedStatus()
    {
        if ($this->status === null) {
            $this->updateStatus();
        }
        return $this->status;
    }
    /**
     * Return the updated process status.
     *
     * @return array
     */
    private function getFreshStatus()
    {
        $this->updateStatus();
        return $this->status;
    }
    /**
     * Update the process status, stop/term signals, and exit code.
     *
     * Stop/term signals are only updated if the process is currently stopped or
     * signaled, respectively. Otherwise, signal values will remain as-is so the
     * corresponding getter methods may be used at a later point in time.
     */
    private function updateStatus()
    {
        if ($this->process === null) {
            return;
        }
        $this->status = proc_get_status($this->process);
        if ($this->status === false) {
            throw new \UnexpectedValueException('proc_get_status() failed');
        }
        if ($this->status['stopped']) {
            $this->stopSignal = $this->status['stopsig'];
        }
        if ($this->status['signaled']) {
            $this->termSignal = $this->status['termsig'];
        }
        if (!$this->status['running'] && -1 !== $this->status['exitcode']) {
            $this->exitCode = $this->status['exitcode'];
        }
    }
}