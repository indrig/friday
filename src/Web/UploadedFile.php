<?php
namespace Friday\Web;

use Friday\Base\BaseObject;

/**
 * Class UploadedFile
 * @package Friday\Web
 *
 * @property string $extension
 */
class UploadedFile extends BaseObject {
    public $filename;

    public $content;

    public $size;

    public $contentType;

    /**
     * @return string file extension
     */
    public function getExtension()
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
    }

}