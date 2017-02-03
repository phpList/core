<?php

namespace phpList\Entity;

class ListEntity
{
    public $name;
    public $description;
    public $active;
    public $listorder;
    public $prefix;
    public $owner;
    public $id;
    public $rssfeed;
    public $category;

    /**
     * Get a list attribute
     *
     * @param string $attribute
     *
     * @return string|null
     */
    public function getAttribute($attribute)
    {
        if (empty($this->attributes) || !isset($this->attributes[$attribute])) {
            return null;
        } else {
            return $this->attributes[$attribute];
        }
    }

    /**
     * Get a list attribute
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Check if attributes have been loaded already
     *
     * @return bool
     */
    public function hasAttributes()
    {
        return $this->hasAttributes;
    }
}
