<?php
namespace phpList\entities;


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
        if ($this->id != null) {
            $this->updatePassword();
        }
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

    /**
     * @param string $email_address
     * @throws \InvalidArgumentException
     */
    public function __construct($email_address)
    {
        $this->setEmailAddress($email_address);
    }
} 