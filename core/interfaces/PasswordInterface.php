<?php
namespace phpList\interfaces;

interface PasswordInterface
{
    /**
     * Set and encrypt the password
     *
     * @param string $password
     */
    public function setEncryptedPassword($password);

    /**
     * Get the encrypted password
     *
     * @return string
     */
    public function getEncryptedPassword();
}
