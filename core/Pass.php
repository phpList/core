<?php

namespace phpList;

class Pass {

    /**
     * Encrypt a plaintext password using the best available algorithm
     * @todo: check php5.5 password api
     * @todo: upgrade default md5 hashing to something more secure
     * @param string $plainPass Plain text password to encrypt
     * @param string $desiredAlgo Name of desiresd algo
     * @return string $encPass Encrypted password
     */
    public function encrypt( $plainPass, $desiredAlgo = 'md5' )
    {
        // If no password was supplied, return empty
        // FIXME: Either log this event, or throw an exception, so client code
        // does not wrongly assume a secure password was generated
        if ( empty( $plainPass ) ) {
            return '';
        }

        if ( function_exists( 'hash' ) ) {
            if (
                ! in_array(
                    $desiredAlgo
                    , hash_algos()
                    , true
                )
            ) {
                ## fallback, not that secure, but better than none at all
                # NOTE: Suggest using PHPass lib
                $algo = 'md5';

                throw new \Exception( "Hashing algorithm '$desiredAlgo' is not available on this server" );
            } else {
                $algo = $desiredAlgo;
            }
            $encPass = hash( $algo, $plainPass );
        } else {
            $encPass = md5( $plainPass );
        }
        return $encPass;
    }
}
