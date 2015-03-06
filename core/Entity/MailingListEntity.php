<?php
namespace phpList\entities;


class MailingListEntity {
    public $id = 0;
    public $name;
    public $description;
    public $entered;
    public $listorder;
    public $prefix;
    public $rssfeed;
    public $modified;
    public $active;
    public $owner;
    public $category;

    /**
     * Default constructor
     * @param string $name
     * @param string $description
     * @param int $listorder
     * @param int $owner
     * @param bool $active
     * @param int $category
     * @param string $prefix
     */
    public function __construct($name, $description, $listorder, $owner, $active, $category, $prefix)
    {
        $this->name = $name;
        $this->description = $description;
        $this->listorder = $listorder;
        $this->owner = $owner;
        $this->active = $active;
        $this->category = $category;
        $this->prefix = $prefix;
    }
} 