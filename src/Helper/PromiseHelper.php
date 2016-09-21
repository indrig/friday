<?php
namespace Friday\Helper;

use Friday\Promise\CancellationQueue;
use Friday\Promise\Exception\LengthException;
use Friday\Promise\FulfilledPromise;
use Friday\Promise\Promise;
use Friday\Promise\PromiseInterface;
use Friday\Promise\Queue\QueueInterface;
use Friday\Promise\Queue\SynchronousQueue;
use Friday\Promise\RejectedPromise;

class PromiseHelper
{
    public static function resolve($promiseOrValue = null)
    {
        if ($promiseOrValue instanceof PromiseInterface) {
            return $promiseOrValue;
        }

        if (method_exists($promiseOrValue, 'then')) {
            $canceller = null;

            if (method_exists($promiseOrValue, 'cancel')) {
                $canceller = [$promiseOrValue, 'cancel'];
            }

            return new Promise(function ($resolve, $reject) use ($promiseOrValue) {
                /**
                 * @var PromiseInterface $promiseOrValue
                 */
                $promiseOrValue->then($resolve, $reject);
            }, $canceller);
        }

        return new FulfilledPromise($promiseOrValue);
    }

    /**
     * @param mixed $promiseOrValue
     * @return PromiseInterface
     */
    public static function reject($promiseOrValue = null) : PromiseInterface
    {
        if ($promiseOrValue instanceof PromiseInterface) {
            return static::resolve($promiseOrValue)->then(function ($value) {
                return new RejectedPromise($value);
            });
        }

        return new RejectedPromise($promiseOrValue);
    }

    public static function all(array $promisesOrValues)
    {
        return static::map($promisesOrValues, function ($val) {
            return $val;
        });
    }

    public static function race(array $promisesOrValues)
    {
        if (!$promisesOrValues) {
            return static::resolve();
        }

        $cancellationQueue = new CancellationQueue();

        return new Promise(function ($resolve, $reject) use ($promisesOrValues, $cancellationQueue) {
            $fulfiller = function ($value) use ($cancellationQueue, $resolve) {
                $cancellationQueue();
                $resolve($value);
            };

            $rejecter = function ($reason) use ($cancellationQueue, $reject) {
                $cancellationQueue();
                $reject($reason);
            };

            foreach ($promisesOrValues as $promiseOrValue) {
                $cancellationQueue->enqueue($promiseOrValue);

                static::resolve($promiseOrValue)
                    ->done($fulfiller, $rejecter);
            }
        }, $cancellationQueue);
    }

    public static function any(array $promisesOrValues)
    {
        return static::some($promisesOrValues, 1)
            ->then(function ($val) {
                return array_shift($val);
            });
    }

    public static function some(array $promisesOrValues, $howMany)
    {
        if ($howMany < 1) {
            return static::resolve([]);
        }

        $len = count($promisesOrValues);

        if ($len < $howMany) {
            return static::reject(
                new LengthException(
                    sprintf(
                        'Input array must contain at least %d item%s but contains only %s item%s.',
                        $howMany,
                        1 === $howMany ? '' : 's',
                        $len,
                        1 === $len ? '' : 's'
                    )
                )
            );
        }

        $cancellationQueue = new CancellationQueue();

        return new Promise(function ($resolve, $reject) use ($len, $promisesOrValues, $howMany, $cancellationQueue) {
            $toResolve = $howMany;
            $toReject = ($len - $toResolve) + 1;
            $values = [];
            $reasons = [];

            foreach ($promisesOrValues as $i => $promiseOrValue) {
                $fulfiller = function ($val) use ($i, &$values, &$toResolve, $toReject, $resolve, $cancellationQueue) {
                    if ($toResolve < 1 || $toReject < 1) {
                        return;
                    }

                    $values[$i] = $val;

                    if (0 === --$toResolve) {
                        $cancellationQueue();
                        $resolve($values);
                    }
                };

                $rejecter = function ($reason) use ($i, &$reasons, &$toReject, $toResolve, $reject, $cancellationQueue) {
                    if ($toResolve < 1 || $toReject < 1) {
                        return;
                    }

                    $reasons[$i] = $reason;

                    if (0 === --$toReject) {
                        $cancellationQueue();
                        $reject($reasons);
                    }
                };

                $cancellationQueue->enqueue($promiseOrValue);

                static::resolve($promiseOrValue)
                    ->done($fulfiller, $rejecter);
            }
        }, $cancellationQueue);
    }

    public static function map(array $promisesOrValues, callable $mapFunc)
    {
        if (!$promisesOrValues) {
            return static::resolve([]);
        }

        $cancellationQueue = new CancellationQueue();

        return new Promise(function ($resolve, $reject) use ($promisesOrValues, $mapFunc, $cancellationQueue) {
            $toResolve = count($promisesOrValues);
            $values = [];

            foreach ($promisesOrValues as $i => $promiseOrValue) {
                $cancellationQueue->enqueue($promiseOrValue);

                static::resolve($promiseOrValue)
                    ->then($mapFunc)
                    ->done(
                        function ($mapped) use ($i, &$values, &$toResolve, $resolve) {
                            $values[$i] = $mapped;

                            if (0 === --$toResolve) {
                                $resolve($values);
                            }
                        },
                        $reject
                    );
            }
        }, $cancellationQueue);
    }

    public static function reduce(array $promisesOrValues, callable $reduceFunc, $initialValue = null)
    {
        $cancellationQueue = new CancellationQueue();

        return new Promise(function ($resolve, $reject) use ($promisesOrValues, $reduceFunc, $initialValue, $cancellationQueue) {
            $total = count($promisesOrValues);
            $i = 0;

            $wrappedReduceFunc = function ($current, $val) use ($reduceFunc, $cancellationQueue, $total, &$i) {
                $cancellationQueue->enqueue($val);
                /**
                 * @var PromiseInterface $current
                 */
                return $current
                    ->then(function ($c) use ($reduceFunc, $total, &$i, $val) {
                        return static::resolve($val)
                            ->then(function ($value) use ($reduceFunc, $total, &$i, $c) {
                                return $reduceFunc($c, $value, $i++, $total);
                            });
                    });
            };

            $cancellationQueue->enqueue($initialValue);

            array_reduce($promisesOrValues, $wrappedReduceFunc, static::resolve($initialValue))
                ->done($resolve, $reject);
        }, $cancellationQueue);
    }

    public static function queue(QueueInterface $queue = null)
    {
        static $globalQueue;

        if ($queue) {
            return ($globalQueue = $queue);
        }

        if (!$globalQueue) {
            $globalQueue = new SynchronousQueue();
        }

        return $globalQueue;
    }

// Internal functions
    public static function _checkTypehint(callable $callback, $object)
    {
        if (!is_object($object)) {
            return true;
        }

        if (is_array($callback)) {
            $callbackReflection = new \ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_object($callback) && !$callback instanceof \Closure) {
            $callbackReflection = new \ReflectionMethod($callback, '__invoke');
        } else {
            $callbackReflection = new \ReflectionFunction($callback);
        }

        $parameters = $callbackReflection->getParameters();

        if (!isset($parameters[0])) {
            return true;
        }

        $expectedException = $parameters[0];

        if (!$expectedException->getClass()) {
            return true;
        }

        return $expectedException->getClass()->isInstance($object);
    }
}
