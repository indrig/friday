<?php
namespace Friday\Web;

use Evenement\EventEmitter;
use Exception;
use Friday\Base\Component;

/**
 * @event headers
 * @event error
 */
class RequestHeaderParser extends Component
{
    private $buffer = '';
    private $maxSize = 4096;

    public function feed($data)
    {
        if (strlen($this->buffer) + strlen($data) > $this->maxSize) {
            $this->trigger('error', array(new \OverflowException("Maximum header size of {$this->maxSize} exceeded."), $this));

            return;
        }

        $this->buffer .= $data;

        if (false !== strpos($this->buffer, "\r\n\r\n")) {
            try {
                $this->parseAndEmitRequest();
            } catch (Exception $exception) {
                $this->trigger('error', [$exception]);
            }
        }
    }

    protected function parseAndEmitRequest()
    {
        list($request, $bodyBuffer) = $this->parseRequest($this->buffer);
        $this->trigger('headers', array($request, $bodyBuffer));
    }

    public function parseRequest($data)
    {
        list($headers, $bodyBuffer) = explode("\r\n\r\n", $data, 2);

        $psrRequest = g7\parse_request($headers);

        $parsedQuery = [];
        $queryString = $psrRequest->getUri()->getQuery();
        if ($queryString) {
            parse_str($queryString, $parsedQuery);
        }

        $headers = array_map(function($val) {
            if (1 === count($val)) {
                $val = $val[0];
            }

            return $val;
        }, $psrRequest->getHeaders());

        $request = new Request([
            'method' =>  $psrRequest->getMethod(),
            'path' =>              $psrRequest->getUri()->getPath(),
            'query' =>              $parsedQuery,
            'httpVersion' =>              $psrRequest->getProtocolVersion(),
            'headers' => $headers

        ]);

        return array($request, $bodyBuffer);
    }
}
