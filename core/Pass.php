<?php

namespace phpList;

class Pass {

    /**
     * Encrypt a plaintext password using the best available algorithm
     * @todo: check php5.5 password api
     * @param string $plainPass Plain text password to encrypt
     * @param string $desiredAlgo Name of desiresd algo
     * @return string $encPass Encrypted password
     */
    public function encrypt( $plainPass, $desiredAlgo = 'sha256' )
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
