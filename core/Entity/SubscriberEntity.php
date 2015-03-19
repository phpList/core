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
    public $optedIn;
    public $bounceCount;

    /**
     * @var \DateTime
     */
    public $entered;

    /**
     * @var \DateTime
     */
    public $modified;

    public $uniqId;
    public $htmlEmail;
    public $subscribePage;
    public $rssFrequency;
    /**
     * @var plainPassInterface
     */
    public $plainPass;

    public $plainPasschanged;
    public $disabled;
    public $extraData;
    public $foreignKey;

    private $attributes;
    private $hasAttributes = false;

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
        $this->hasAttributes = true;
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
        return $this->hasAttributes;
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
