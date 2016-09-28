<?php
namespace Friday\Asset;
use Friday\Web\AssetBundle;

/**
 * This asset bundle provides the base javascript files for the Yii Framework.
 */
class FridayAsset extends AssetBundle
{
    public $sourcePath = '@friday/asset/friday';
    public $js = [
        'friday.js',
    ];
    public $depends = [
        'Friday\Asset\JqueryAsset',
    ];
}
