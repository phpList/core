<?php
namespace phpList\Entity;


class TemplateEntity {
    public $id = 0;
    public $title;
    public $template;
    public $listorder;

    /**
     * @param $title
     * @param $template
     * @param $listorder
     */
    public function __construct($title, $template, $listorder)
    {
        $this->title = $title;
        $this->template = stripslashes($template);
        $this->listorder = $listorder;
    }
} 