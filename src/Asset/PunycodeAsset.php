<?php
namespace Friday\Validator\Asset;

use Friday\Web\AssetBundle;


/**
 * This asset bundle provides the javascript files needed for the [[EmailValidator]]s client validation.
 */
class PunycodeAsset extends AssetBundle
{
    public $sourcePath = '@friday/asset/punycode';

    public function init()
    {
        $this->js[] = FRIDAY_DEBUG ? 'punycode.js' : 'punycode.min.js';
    }
}
