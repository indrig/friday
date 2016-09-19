<?php

namespace Friday\Stream;

// TODO: move to a trait

use Friday\Stream\Event\ContentEvent;
use Friday\Stream\Event\PipeEvent;

class Util
{
    public static function pipe(ReadableStreamInterface $source, WritableStreamInterface $destination, array $options = array())
    {
        $destination->trigger(StreamInterface::EVENT_PIPE, new PipeEvent([
            'source' => $source
        ]));

        $source->on(StreamInterface::EVENT_CONTENT, function (ContentEvent $event) use ($source, $destination) {
            $feedMore = $destination->write($event->content);

            if (false === $feedMore) {
                $source->pause();
            }
        });

        $destination->on(StreamInterface::EVENT_DRAIN, function () use ($source) {
            $source->resume();
        });

        $end = isset($options['end']) ? $options['end'] : true;
        if ($end && $source !== $destination) {
            $source->on(StreamInterface::EVENT_END, function () use ($destination) {
                $destination->end();
            });
        }
    }

    /**
     * @param $source
     * @param $target
     * @param array $events
     */
    public static function forwardEvents(ReadableStreamInterface $source, WritableStreamInterface $target, array $events)
    {
        foreach ($events as $eventName) {
            $source->on($eventName, function ($event) use ($eventName, $target) {
                $target->trigger($eventName, $event);
            });
        }
    }
}
