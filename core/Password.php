<?php
namespace phpList;

class Password implements interfaces\PasswordInterface {
    private $password = '';
    protected $config;

    /**
     * Default constructor
     * When passing the password in the constructor, we do not encrypt it
     * @param Config $config
     * @param $password
     */
    public function __construct(Config $config, $password = null)
    {
        $this->config = $config;
        $this->password = $password;
    }

    /**
     * Set and encrypt the password
     * @param string $password
     */
    public function setEncryptedPassword($password)
    {
        $this->password = $this->encryptPass($password);
    }

    /**
     * Get the encrypted password
     * @return string
     */
    public function getEncryptedPassword()
    {
        return $this->password;
    }

    /**
     * Encrypt the password
     * todo: check php5.5 password api
     * @param $pass
     * @return string
     */
    private function encryptPass($pass)
    {
        if (empty($pass)) {
            return '';
        }

        if (function_exists('hash')) {
            if (!in_array($this->config->get('ENCRYPTION_ALGO'), hash_algos(), true)) {
                ## fallback, not that secure, but better than none at all
                $algo = 'md5';
            } else {
                $algo = $this->config->get('ENCRYPTION_ALGO');
            }
            return hash($algo, $pass);
        } else {
            return md5($pass);
        }
    }
}