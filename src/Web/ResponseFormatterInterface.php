<?php
namespace Friday\Web;

/**
 * ResponseFormatterInterface specifies the interface needed to format a response before it is sent out.
 */
interface ResponseFormatterInterface
{
    /**
     * Formats the specified response.
     * @param Response $response the response to be formatted.
     */
    public function format($response);
}
