<?php
namespace Friday\Asset;

use Friday\Web\AssetBundle;

/**
 * This asset bundle provides the javascript files for client validation.
 */
class ValidationAsset extends AssetBundle
{
    public $sourcePath = '@friday/asset';
    public $js = [
        'friday.validation.js',
    ];
    public $depends = [
        'Friday\Asset\YiiAsset',
    ];
}
