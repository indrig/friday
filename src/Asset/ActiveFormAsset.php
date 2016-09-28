<?php
namespace Friday\Asset;

use Friday\Web\AssetBundle;


class ActiveFormAsset extends AssetBundle
{
    public $sourcePath = '@friday/asset';
    public $js = [
        'friday.activeForm.js',
    ];
    public $depends = [
        'Friday\Asset\FridayAsset',
    ];
}
