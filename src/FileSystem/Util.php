<?php
namespace Friday\FileSystem;

use Friday\Promise\RejectedPromise;
use Friday\Promise\Util as PromiseUtil;

class Util {


    /**
     * @param AdapterInterface $adapter
     * @param array $options
     * @param string $fallback
     * @return CallInvokerInterface
     */
    public static function getInvoker(AdapterInterface $adapter, array $options, $key, $fallback)
    {
        if (isset($options[$key]) && $options[$key] instanceof CallInvokerInterface) {
            return $options[$key];
        }

        return new $fallback($adapter);
    }

    /**
     * @param array $options
     * @return int
     */
    public static function getOpenFileLimit(array $options)
    {
        if (isset($options['open_file_limit'])) {
            return (int)$options['open_file_limit'];
        }

        return OpenFileLimiter::DEFAULT_LIMIT;
    }

    /**
     * @param array $typeDetectors
     * @param array $node
     * @return \Friday\Promise\PromiseInterface
     */
    public static function detectType(array $typeDetectors, array $node)
    {
        $promiseChain = new RejectedPromise();
        foreach ($typeDetectors as $detector) {
            $promiseChain = $promiseChain->otherwise(function () use ($node, $detector) {
                return $detector->detect($node);
            });
        }

        return $promiseChain->then(function ($callable) use ($node) {
            return PromiseUtil::resolve($callable($node['path']));
        });
    }

}