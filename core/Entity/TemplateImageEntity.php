<?php
namespace phpList\entities;


class TemplateImageEntity {
    public $id;
    public $template;
    public $mimetype;
    public $filename;
    public $data;
    public $width;
    public $height;

    /**
     * Create new image
     * @param $template_id
     * @param $mime
     * @param $filename
     * @param $data
     * @param $width
     * @param $height
     */
    public function __construct($template_id, $mime, $filename, $data, $width, $height)
    {
        $this->template = $template_id;
        $this->mimetype = $mime;
        $this->filename = $filename;
        $this->data = $data;
        $this->width = $width;
        $this->height = $height;
    }
} 