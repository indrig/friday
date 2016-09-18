<?php

namespace Friday\Filesystem;

use Friday\Promise\PromiseInterface;
use Friday\Promise\Util as PromiseUtil;
trait WoolTrait
{
    protected $fd;

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function mkdir(array $payload)
    {
        if (
            @mkdir(
                $payload['path'],
                (new PermissionFlagResolver())->resolve($payload['mode'])
            )
        ) {
            return PromiseUtil::resolve([]);
        }

        return PromiseUtil::reject([]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function rmdir(array $payload)
    {
        if (rmdir($payload['path'])) {
            return PromiseUtil::resolve([]);
        }

        return PromiseUtil::reject([]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function unlink(array $payload)
    {
        if (unlink($payload['path'])) {
            return PromiseUtil::resolve([]);
        }

        return PromiseUtil::reject([]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function chmod(array $payload)
    {
        if (chmod($payload['path'], $payload['mode'])) {
            return PromiseUtil::resolve([]);
        }

        return PromiseUtil::reject([]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function chown(array $payload)
    {
        return PromiseUtil::resolve([]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function stat(array $payload)
    {
        if (!file_exists($payload['path'])) {
            return PromiseUtil::reject([]);
        }

        $stat = lstat($payload['path']);
        return PromiseUtil::resolve([
            'dev'     => $stat['dev'],
            'ino'     => $stat['ino'],
            'mode'    => $stat['mode'],
            'nlink'   => $stat['nlink'],
            'uid'     => $stat['uid'],
            'size'    => $stat['size'],
            'gid'     => $stat['gid'],
            'rdev'    => $stat['rdev'],
            'blksize' => $stat['blksize'],
            'blocks'  => $stat['blocks'],
            'atime'   => $stat['atime'],
            'mtime'   => $stat['mtime'],
            'ctime'   => $stat['ctime'],
        ]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function readdir(array $payload)
    {
        $list = [];
        foreach (scandir($payload['path'], $payload['flags']) as $node) {
            $path = $payload['path'] . DIRECTORY_SEPARATOR . $node;
            if ($node == '.' || $node == '..' || (!is_dir($path) && !is_file($path))) {
                continue;
            }

            $list[] = [
                'type' => is_dir($path) ? 'dir' : 'file',
                'name' => $node,
            ];
        }
        return PromiseUtil::resolve($list);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function open(array $payload)
    {
        $this->fd = @fopen($payload['path'], $payload['flags']);
        return PromiseUtil::resolve([
            'result' => (string)$this->fd,
        ]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function touch(array $payload)
    {
        return PromiseUtil::resolve([
            touch($payload['path']),
        ]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function read(array $payload)
    {
        fseek($this->fd, $payload['offset']);
        return PromiseUtil::resolve([
            'chunk' => fread($this->fd, $payload['length']),
        ]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function write(array $payload)
    {
        fseek($this->fd, $payload['offset']);
        return PromiseUtil::resolve([
            'written' => fwrite($this->fd, $payload['chunk'], $payload['length']),
        ]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function close(array $payload)
    {
        $closed = fclose($this->fd);
        $this->fd = null;
        return PromiseUtil::resolve([
            $closed,
        ]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function rename(array $payload)
    {
        if (rename($payload['from'], $payload['to'])) {
            return PromiseUtil::resolve([]);
        }

        return PromiseUtil::reject([]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function readlink(array $payload)
    {
        return PromiseUtil::resolve([
            'path' => readlink($payload['path']),
        ]);
    }

    /**
     * @param array $payload
     * @return PromiseInterface
     */
    public function symlink(array $payload)
    {
        return PromiseUtil::resolve([
            'result' => symlink($payload['from'], $payload['to']),
        ]);
    }
}
