<?php
namespace phpList\interfaces;

interface EmailAddressInterface
{
    /**
     * Set the email address and perform validation on it
     * @param string $email_address
     */
    public function setAddress($email_address);

    /**
     * Get the email address
     * @return string
     */
    public function getAddress();
}
