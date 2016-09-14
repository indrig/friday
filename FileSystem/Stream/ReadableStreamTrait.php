<?php

namespace React\Filesystem\Stream;

use React\Stream\Util;
use React\Stream\WritableStreamInterface;

trait ReadableStreamTrait
{
    protected $size;
    protected $readCursor;
    protected $chunkSize = 8192;
    protected $pause = true;

    public function resume()
    {
        $this->pause = false;

        if ($this->size === null) {
            $this->getFilesystem()->stat($this->getPath())->then(function ($info) {
                $this->size = $info['size'];
                $this->readCursor = 0;

                $this->readChunk();
            });
            return;
        }

        $this->readChunk();
    }

    public function pause()
    {
        $this->pause = true;
    }

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        if ($this === $dest) {
            throw new \Exception('Can\'t pipe stream into itself!');
        }

        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function isReadable()
    {
        return !$this->isClosed();
    }

    protected function readChunk()
    {
        if ($this->pause) {
            return;
        }

        $this->performRead($this->calculateChunkSize());
    }

    protected function calculateChunkSize()
    {
        if ($this->readCursor + $this->chunkSize > $this->size) {
            return $this->size - $this->readCursor;
        }

        return $this->chunkSize;
    }

    protected function performRead($chunkSize)
    {
        $this->getFilesystem()->read($this->getFileDescriptor(), $chunkSize, $this->readCursor)->then(function ($data) use ($chunkSize) {
            // If chunk size can be set make sure to copy it before running this operation so
            // that used can't change it mid operation and cause funkyness.
            $this->readCursor += $chunkSize;
            $this->emit('data', [
                $data,
                $this,
            ]);

            if ($this->readCursor < $this->size) {
                $this->readChunk();
            } else {
                $this->emit('end', [
                    $this,
                ]);
            }
        });
    }

    abstract function close();
}
