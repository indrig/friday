<?php
namespace Friday\Web;

use Friday\Base\BaseObject;

/**
 * JsExpression marks a string as a JavaScript expression.
 *
 * When using [[\Friday\Helper\Json::encode()]] or [[\Friday\Helper\Json::htmlEncode()]] to encode a value, JsonExpression objects
 * will be specially handled and encoded as a JavaScript expression instead of a string.
 */
class JsExpression extends BaseObject
{
    /**
     * @var string the JavaScript expression represented by this object
     */
    public $expression;


    /**
     * Constructor.
     * @param string $expression the JavaScript expression represented by this object
     * @param array $config additional configurations for this object
     */
    public function __construct($expression, $config = [])
    {
        $this->expression = $expression;
        parent::__construct($config);
    }

    /**
     * The PHP magic function converting an object into a string.
     * @return string the JavaScript expression.
     */
    public function __toString()
    {
        return $this->expression;
    }
}
