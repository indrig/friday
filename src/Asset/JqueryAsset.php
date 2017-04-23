<?php
namespace Friday\Asset;
use Friday\Web\AssetBundle;

/**
 * This asset bundle provides the [jquery javascript library](http://jquery.com/)
 */
class JqueryAsset extends AssetBundle
{
    public $sourcePath = '@friday/asset/jquery';


    public function init()
    {
        $this->js[] = FRIDAY_DEBUG ? 'jquery-3.2.1.js' : 'jquery-3.2.1.min.js';
    }
}
