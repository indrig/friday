<?php
namespace Friday\I18n;
use Friday\Base\Component;

/**
 * GettextFile is the base class for representing a Gettext message file.
 */
abstract class GetTextFile extends Component
{
    /**
     * Loads messages from a file.
     * @param string $filePath file path
     * @param string $context message context
     * @return array message translations. Array keys are source messages and array values are translated messages:
     * source message => translated message.
     */
    abstract public function load($filePath, $context);

    /**
     * Saves messages to a file.
     * @param string $filePath file path
     * @param array $messages message translations. Array keys are source messages and array values are
     * translated messages: source message => translated message. Note if the message has a context,
     * the message ID must be prefixed with the context with chr(4) as the separator.
     */
    abstract public function save($filePath, $messages);
}
