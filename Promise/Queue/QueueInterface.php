<?php
namespace Friday\Promise\Queue;

interface QueueInterface
{
    public function enqueue(callable $task);
}
