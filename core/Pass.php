<?php

namespace phpList;

class Pass
{
    protected $config;

    /**
     * @param Config $config
     * @param helper\Database $db
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Encrypt a plaintext password using the best available algorithm
     *
     * @todo: check php5.5 password api
     * @todo: upgrade default md5 hashing to something more secure
     *
     * @param string $plainPass Plain text password to encrypt
     * @param string $desiredAlgo Name of desiresd algo
     *
     * @return string $encPass Encrypted password
     */
    public function encrypt($plainPass, $desiredAlgo = 'sha256')
    {
        // If no password was supplied, return empty
        // FIXME: Either log this event, or throw an exception, so client code
        // does not wrongly assume a secure password was generated
        if (empty($plainPass)) {
            return '';
        }

        // If no hashing algo was specified
        if ($desiredAlgo == null) {
            // Fetch default hashing algo from config file
            $desiredAlgo = $this->config->get('ENCRYPTION_ALGO');
        }

        if (function_exists('hash')) {
            if (! in_array(
                $desiredAlgo,
                hash_algos(),
                true
            )
            ) {
                ## fallback, not that secure, but better than none at all
                # NOTE: Suggest using PHPass lib
                $algo = 'md5';

                throw new \Exception("Hashing algorithm '$desiredAlgo' is not available on this server");
            } else {
                $algo = $desiredAlgo;
            }
            // Hash the password using desired algo
            $encPass = hash($algo, $plainPass);
        } else {
            //. Hash the password using a fallback default
            $encPass = md5($plainPass);
        }
        return $encPass;
    }
}
