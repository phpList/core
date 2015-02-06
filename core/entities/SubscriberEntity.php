<?php
namespace phpList\entities;

use phpList\helper\Validation;
use phpList\helper\Util;

class SubscriberEntity {
    public $id;
    private $email_address;

    /**
     * @param string $email_address
     * @throws \InvalidArgumentException
     */
    public function setEmailAddress($email_address)
    {
        if(!Validation::validateEmail($email_address)){
            throw new \InvalidArgumentException('Invalid email address provided');
        }
        $this->email_address = $email_address;
    }

    /**
     * @return string
     */
    public function getEmailAddress()
    {
        return $this->email_address;
    }
    public $confirmed;
    public $blacklisted;
    public $optedin;
    public $bouncecount;
    public $entered;
    public $modified;
    public $uniqid;
    public $htmlemail;
    public $subscribepage;
    public $rssfrequency;
    private $password;

    /**
     * Set password and encrypt it
     * For existing subscribers, password will be written to database
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = Util::encryptPass($password);
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }


    public $passwordchanged;
    public $disabled;
    public $extradata;
    public $foreignkey;

    private $attributes;
    private $has_attributes = false;

    /**
     * @param string $email_address
     * @param string $password (can be passed when reading from database)
     * @throws \InvalidArgumentException
     */
    public function __construct($email_address, $password = null)
    {
        $this->setEmailAddress($email_address);

        if($password != null){
            $this->password = $password;
        }
    }

    /**
     * Set attributes for this subscriber
     * @param $key
     * @param $value
     */
    public function setAttribute($key, $value){
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
    public function hasAttributes(){
        return $this->has_attributes;
    }


    /**
     * Check if this subscriber is allowed to send mails to
     * @return bool
     */
    public function allowsReceivingMails()
    {
        $confirmed = $this->confirmed && !$this->disabled;
        return (!$this->blacklisted && $confirmed);
    }
} 