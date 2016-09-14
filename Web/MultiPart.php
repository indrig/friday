<?php
namespace Friday\Web;

use Friday\Base\BaseObject;

/**
 * Class MultiPart
 * @package Friday\Web
 *
 * @property bool $isFile
 *
 */
class MultiPart extends BaseObject {
    public $headers;

    public $filename;

    public $name;

    public $value;

    public $hasErrors = false;

    public $bodyOffset = 0;

    /**
     * @param string $content
     * @param bool $isFull
     *
     * @return static|null
     */
    public static function create(string $buffer, bool $isFull){
        if(false === $headerEndPosition = mb_strpos($buffer, "\r\n\r\n", 0, '8bit')){
            return null;
        }

        $part              = new MultiPart();
        $part->bodyOffset  = $headerEndPosition+4;

        $headersStrings    = mb_substr(ltrim($buffer), 0, $headerEndPosition-2, '8bit');

        $partHeaderLines   = explode("\r\n", $headersStrings);

        $part->headers = [];
        foreach ($partHeaderLines as $headerLine) {
            list($name, $value) = explode(':', $headerLine, 2);
            $part->headers[strtolower($name)] = ltrim($value, ' ');
        }

        if (isset($part->headers['content-disposition'])) {
            $disposition = static::parseHeaderValue($part->headers['content-disposition']);

            if (isset($disposition['name'])) {
                $part->name = $disposition['name'];

                if(isset($disposition['filename'])) {
                    $part->filename = $disposition['filename'];
                }

                if($part->isFile){

                } else {
                    if($isFull) {
                        $part->valueFromBuffer($buffer);
                    }
                }
            } else {
                $part->hasErrors = true;
            }
        } else {
            $part->hasErrors = true;
        }

        return $part;
    }

    /**
     * @param $headerValue
     *
     * @return array
     */
    protected static function parseHeaderValue($headerValue){
        $result = [];
        $items = explode(';', $headerValue);

        foreach ($items as $index => $item){
            if($index === 0) {
                $result[] = $item;
            } else {
                $pair = explode('=', ltrim($item), 2);
                if(count($pair) === 2) {
                    $result[$pair[0]] = trim($pair[1], '"');
                }
            }
        }

        return $result;
    }

    /**
     *
     */
    public function getIsFile(){
        return $this->filename !== null;
    }

    public function valueFromBuffer($buffer){
        $this->value = mb_substr($buffer, $this->bodyOffset, -2, '8bit');
    }
}