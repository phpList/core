<?php
namespace phpList\entities;

use phpList\interfaces\EmailAddressInterface;
use phpList\interfaces\PasswordInterface;

class SubscriberEntity {
    public $id;

    /**
     * @var EmailAddressInterface
     */
    public $email_address;

    public $confirmed;
    public $blacklisted;
    public $optedin;
    public $bouncecount;

    /**
     * @var \DateTime
     */
    public $entered;

    /**
     * @var \DateTime
     */
    public $modified;

    public $uniqid;
    public $htmlemail;
    public $subscribepage;
    public $rssfrequency;
    /**
     * @var PasswordInterface
     */
    public $password;

    public $passwordchanged;
    public $disabled;
    public $extradata;
    public $foreignkey;

    private $attributes;
    private $has_attributes = false;

    /**
     * @param \phpList\interfaces\EmailAddressInterface $email_address
     * @param \phpList\interfaces\PasswordInterface $password (can be passed when reading from database)
     */
    public function __construct(EmailAddressInterface $email_address, PasswordInterface $password)
    {
        $this->email_address = $email_address;
        $this->password = $password;
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