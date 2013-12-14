<?php
/**
 * User: SaWey
 * Date: 5/12/13
 */

namespace phpList;


//Include core files
include('../core/helper/IDatabase.php');
include('../core/helper/Language.php');
include('../core/helper/MySQLi.php');
include('../core/helper/Validation.php');
include('../core/Config.php');
include('../core/User.php');
include('../core/Message.php');
include('../core/List.php');
include('../core/Attachment.php');
include('../core/Template.php');

class phpList {
    /**
     * @return IDatabase
     * @throws \Exception
     */
    static function DB(){
        switch(Config::DATABASE_MODULE){
            case 'mysqli':
                return MySQLi::Instance();
            break;
            default:
                throw new \Exception("DB Module not available");
        }
    }

    static function encryptPass($pass) {
        if (empty($pass)) return '';

        if (function_exists('hash')) {
            if(!in_array(Config::ENCRYPTION_ALGO, hash_algos(), true)) {
                ## fallback, not that secure, but better than none at all
                $algo = 'md5';
            } else {
                $algo = ENCRYPTION_ALGO;
            }
            return hash($algo,$pass);
        } else {
            return md5($pass);
        }
    }

} 