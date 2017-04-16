<?php
namespace Friday\Base;

/**
 * Response represents the response of an [[Application]] to a [[Request]].
 */
class Response extends Component
{
    /**
     * @var integer the exit status. Exit statuses should be in the range 0 to 254.
     * The status 0 means the program terminates successfully.
     */
    public $exitStatus = 0;


    /**
     * Sends the response to client.
     */
    public function send()
    {
    }

    /**
     * Removes all existing output buffers.
     */
    public function clearOutputBuffers()
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
        }
    }
}
