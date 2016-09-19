<?php
namespace Friday\Cache;

use Friday;
use Friday\Helper\AliasHelper;
use Friday\Helper\FileHelper;
use Friday\Promise\ExtendedPromiseInterface;
use Friday\Promise\Util as PromiseUtil;

/**
 * FileCache implements a cache component using files.
 *
 * For each data value being cached, FileCache will store it in a separate file.
 * The cache files are placed under [[cachePath]]. FileCache will perform garbage collection
 * automatically to remove expired cache files.
 *
 * Please refer to [[Cache]] for common cache operations that are supported by FileCache.
 *
 */
class FileCache extends AbstractCache
{
    /**
     * @var string the directory to store cache files. You may use path alias here.
     * If not set, it will use the "cache" subdirectory under the application runtime path.
     */
    public $cachePath = '@runtime/cache';
    /**
     * @var string cache file suffix. Defaults to '.bin'.
     */
    public $cacheFileSuffix = '.bin';
    /**
     * @var integer the level of sub-directories to store cache files. Defaults to 1.
     * If the system has huge number of cache files (e.g. one million), you may use a bigger value
     * (usually no bigger than 3). Using sub-directories is mainly to ensure the file system
     * is not over burdened with a single directory having too many files.
     */
    public $directoryLevel = 3;
    /**
     * @var integer the probability (parts per million) that garbage collection (GC) should be performed
     * when storing a piece of data in the cache. Defaults to 10, meaning 0.001% chance.
     * This number should be between 0 and 1000000. A value 0 means no GC will be performed at all.
     */
    public $gcProbability = 10;
    /**
     * @var integer the permission to be set for newly created cache files.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * If not set, the permission will be determined by the current environment.
     */
    public $fileMode;
    /**
     * @var integer the permission to be set for newly created directories.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * Defaults to 0775, meaning the directory is read-writable by owner and group,
     * but read-only for other users.
     */
    public $dirMode = 0775;
    /**
     * Initializes this component by ensuring the existence of the cache path.
     */
    public function init()
    {
        parent::init();
        $this->cachePath = AliasHelper::getAlias($this->cachePath);
        if (!is_dir($this->cachePath)) {
            FileHelper::createDirectory($this->cachePath, $this->dirMode, true);
        }
    }

    /**
     * @inheritdoc
     */
    public function exists($key) : ExtendedPromiseInterface
    {
        $cacheFile = $this->getCacheFile($this->buildKey($key));
        return PromiseUtil::resolve(@filemtime($cacheFile) > time());
    }

    /**
     * @inheritdoc
     */
    protected function getValue($key) : ExtendedPromiseInterface
    {

        $cacheFile = $this->getCacheFile($key);

        if (@filemtime($cacheFile) > time()) {

            $fp = @fopen($cacheFile, 'r');
            if ($fp !== false) {
                @flock($fp, LOCK_SH);
                $cacheValue = @stream_get_contents($fp);
                @flock($fp, LOCK_UN);
                @fclose($fp);
                return PromiseUtil::resolve($cacheValue);
            }
        }
        return PromiseUtil::resolve(false);
    }

    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $duration) : ExtendedPromiseInterface
    {
        $this->gc();
        $cacheFile = $this->getCacheFile($key);
        if ($this->directoryLevel > 0) {
            @FileHelper::createDirectory(dirname($cacheFile), $this->dirMode, true);
        }

        if (@file_put_contents($cacheFile, $value, LOCK_EX) !== false) {
            if ($this->fileMode !== null) {
                @chmod($cacheFile, $this->fileMode);
            }
            if ($duration <= 0) {
                $duration = 31536000; // 1 year
            }
            return PromiseUtil::resolve(@touch($cacheFile, $duration + time()));
        } else {
            $error = error_get_last();
            Friday::warning("Unable to write cache file '{$cacheFile}': {$error['message']}", __METHOD__);
            return PromiseUtil::resolve(false);
        }
    }

    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $duration) : ExtendedPromiseInterface
    {
        $cacheFile = $this->getCacheFile($key);
        if (@filemtime($cacheFile) > time()) {
            return PromiseUtil::resolve(false);
        }
        return $this->setValue($key, $value, $duration);
    }

    /**
     * @inheritdoc
     */
    protected function deleteValue($key) : ExtendedPromiseInterface
    {
        $cacheFile = $this->getCacheFile($key);
        return PromiseUtil::resolve(@unlink($cacheFile));
    }

    /**
     * @inheritdoc
     */
    protected function getCacheFile($key)
    {
        if ($this->directoryLevel > 0) {
            $base = $this->cachePath;
            for ($i = 0; $i < $this->directoryLevel; ++$i) {
                if (($prefix = substr($key, $i + $i, 2)) !== false) {
                    $base .= DIRECTORY_SEPARATOR . $prefix;
                }
            }
            return $base . DIRECTORY_SEPARATOR . $key . $this->cacheFileSuffix;
        } else {
            return $this->cachePath . DIRECTORY_SEPARATOR . $key . $this->cacheFileSuffix;
        }
    }
    /**
     * @inheritdoc
     */
    protected function flushValues() : ExtendedPromiseInterface
    {
        return $this->gc(true, false);
    }
    /**
     * Removes expired cache files.
     * @param boolean $force whether to enforce the garbage collection regardless of [[gcProbability]].
     * Defaults to false, meaning the actual deletion happens with the probability as specified by [[gcProbability]].
     * @param boolean $expiredOnly whether to removed expired cache files only.
     * If false, all cache files under [[cachePath]] will be removed.
     *
     * @return ExtendedPromiseInterface
     */
    public function gc($force = false, $expiredOnly = true) : ExtendedPromiseInterface
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            return $this->gcRecursive($this->cachePath, $expiredOnly);
        }

        return PromiseUtil::resolve();
    }
    /**
     * Recursively removing expired cache files under a directory.
     * This method is mainly used by [[gc()]].
     * @param string $path the directory under which expired cache files are removed.
     * @param boolean $expiredOnly whether to only remove expired cache files. If false, all files
     * under `$path` will be removed.
     *
     * @return ExtendedPromiseInterface
     */
    protected function gcRecursive($path, $expiredOnly) : ExtendedPromiseInterface
    {

        if (($handle = opendir($path)) !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file[0] === '.') {
                    continue;
                }
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                if (is_dir($fullPath)) {
                    $this->gcRecursive($fullPath, $expiredOnly);
                    if (!$expiredOnly) {
                        if (!@rmdir($fullPath)) {
                            $error = error_get_last();
                            Friday::warning("Unable to remove directory '{$fullPath}': {$error['message']}", __METHOD__);
                        }
                    }
                } elseif (!$expiredOnly || $expiredOnly && @filemtime($fullPath) < time()) {
                    if (!@unlink($fullPath)) {
                        $error = error_get_last();
                        Friday::warning("Unable to remove file '{$fullPath}': {$error['message']}", __METHOD__);
                    }
                }
            }
            closedir($handle);
        }

        return PromiseUtil::resolve();
    }
}