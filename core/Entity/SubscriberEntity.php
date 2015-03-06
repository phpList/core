<?php
namespace phpList\Entity;

use phpList\interfaces\EmailAddressInterface;
use phpList\interfaces\plainPassInterface;

class SubscriberEntity {
    public $id;

    /**
     * @var EmailAddressInterface
     */
    public $emailAddress;

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
     * @var plainPassInterface
     */
    public $plainPass;

    public $plainPasschanged;
    public $disabled;
    public $extradata;
    public $foreignkey;

    private $attributes;
    private $has_attributes = false;

    public function __construct( $emailAddress, $plainPass )
    {
        $this->emailAddress = $emailAddress;
        $this->plainPass = $plainPass;
    }

    /**
     * Set attributes for this subscriber
     * @param $key
     * @param $value
     */
    public function setAttribute( $key, $value ) {
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
