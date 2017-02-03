<?php
namespace phpList\Entity;

use phpList\interfaces\EmailAddressInterface;
use phpList\interfaces\plainPassInterface;

class AdminEntity
{

    // Required attributes
    public $username;

    // Option attributes
    public $id;
    public $namelc;
    public $email;
    public $encPass;
    public $created;
    public $modified;
    public $modifiedby;
    public $plainPass;
    public $passchanged;
    public $superuser;
    public $disabled;
    public $privileges;

    public function __construct($username)
    {
        $this->username = $username;
    }

    /**
     * Set attributes
     * @param $key
     * @param $value
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
        $this->has_attributes = true;
    }

    /**
     * Get a subscriber attribute
     * @param string $attribute
     * @return string|null
     */
    public function getAttribute($attribute)
    {
        if (empty($this->attributes) ||!isset($this->attributes[$attribute])) {
            return null;
        } else {
            return $this->attributes[$attribute];
        }
    }

    /**
     * Get a subscriber attribute
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Check if attributes have been loaded already
     * @return bool
     */
    public function hasAttributes()
    {
        return $this->has_attributes;
    }
}
