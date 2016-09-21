<?php
namespace Friday\Promise;

class RejectedPromise implements ExtendedPromiseInterface, CancellablePromiseInterface
{
    private $reason;

    public function __construct($reason = null)
    {
        if ($reason instanceof PromiseInterface) {
            throw new \InvalidArgumentException('You cannot create Friday\Promise\RejectedPromise with a promise. Use Friday\Promise\Util::reject($promiseOrValue) instead.');
        }

        $this->reason = $reason;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null) : PromiseInterface
    {
        if (null === $onRejected) {
            return $this;
        }

        return new Promise(function (callable $resolve, callable $reject) use ($onRejected) {
            Util::queue()->enqueue(function () use ($resolve, $reject, $onRejected) {
                try {
                    $resolve($onRejected($this->reason));
                } catch (\Throwable $exception) {
                    $reject($exception);
                } catch (\Exception $exception) {
                    $reject($exception);
                }
            });
        });
    }

    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        Util::queue()->enqueue(function () use ($onRejected) {
            if (null === $onRejected) {
                throw UnhandledRejectionException::resolve($this->reason);
            }

            $result = $onRejected($this->reason);

            if ($result instanceof self) {
                throw UnhandledRejectionException::resolve($result->reason);
            }

            if ($result instanceof ExtendedPromiseInterface) {
                $result->done();
            }
        });
    }

    public function otherwise(callable $onRejected)
    {
        if (!Util::_checkTypehint($onRejected, $this->reason)) {
            return $this;
        }

        return $this->then(null, $onRejected);
    }

    public function always(callable $onFulfilledOrRejected)
    {
        return $this->then(null, function ($reason) use ($onFulfilledOrRejected) {
            return Util::resolve($onFulfilledOrRejected())->then(function () use ($reason) {
                return new RejectedPromise($reason);
            });
        });
    }

    public function cancel()
    {
    }
}
