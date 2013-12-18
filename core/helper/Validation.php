<?php
/**
 * User: SaWey
 * Date: 14/12/13
 */

namespace phpList;


class Validation
{

    static function validateEmail($email)
    {
        /* TODO: this used to be if(!empty($GLOBALS["config"]["dont_require_validemail"]
        * probably better to only have one constant to set email validation?
         *
         */
        if (Config::EMAIL_ADDRESS_VALIDATION_LEVEL == 0) {
            return 1;
        }

        //Config::setRunningConfig('check_for_host', false);
        //TODO: Michiel: original phplist would never execute this because check_for_host is set to false, why?
        $validhost = false;
        if (!empty($email) && Config::get('check_for_host')) {
            if (strpos($email, '@')) {
                list($username, $domaincheck) = explode('@', $email);
                # checking for an MX is not sufficient
                #    $mxhosts = array();
                #    $validhost = getmxrr ($domaincheck,$mxhosts);
                $validhost = checkdnsrr($domaincheck, "MX") || checkdnsrr($domaincheck, "A");
            }
        } else {
            $validhost = true;
        }
        return $validhost && phpList::isEmail($email);
    }

    static function isEmail($email)
    {
        if (Config::EMAIL_ADDRESS_VALIDATION_LEVEL == 0) {
            return 1;
        }

        $email = trim($email);

        ## do some basic validation first
        # quite often emails have two @ signs
        $ats = substr_count($email, '@');
        if ($ats != 1) {
            return false;
        }

        ## fail on emails starting or ending "-" or "." in the pre-at, seems to happen quite often, probably cut-n-paste errors
        if (preg_match('/^-/', $email) ||
            preg_match('/-@/', $email) ||
            preg_match('/\.@/', $email) ||
            preg_match('/^\./', $email) ||
            preg_match('/^\-/', $email)
        ) {
            return false;
        }
        $tlds = Config::get('internet_tlds');
        if (empty($tlds)) {
            $tlds = 'ac|ad|ae|aero|af|ag|ai|al|am|an|ao|aq|ar|arpa|as|asia|at|au|aw|az|ba|bb|bd|be|bf|bg|bh|bi|biz|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cat|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|com|coop|cr|cs|cu|cv|cx|cy|cz|de|dev|dj|dk|dm|do|dz|ec|edu|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gov|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|home|hr|ht|hu|id|ie|il|im|in|info|int|io|iq|ir|is|it|jm|je|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|loc|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mil|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|museum|mv|mw|mx|my|mz|na|name|nc|ne|net|nf|ng|ni|nl|no|np|nr|nt|nu|nz|om|org|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|pro|ps|pt|pw|py|qa|re|ro|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tel|tf|tg|th|tj|tk|tm|tn|to|tp|tr|travel|tt|tv|tw|tz|ua|ug|uk|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw';
        }

        switch (Config::EMAIL_ADDRESS_VALIDATION_LEVEL) {
            case 0: # No email address validation.
                return true;
                break;

            case 2: # RFC821 email validation without escaping and quoting of local part
            case 3: # RFC821 email validation.
                # $email is a valid address as defined by RFC821
                # Except:
                #   Length of domainPart is not checked
                #   Not accepted are CR and LF even if escaped by \
                #   Not accepted is Folding
                #   Not accepted is literal domain-part (eg. [1.0.0.127])
                #   Not accepted is comments (eg. (this is a comment)@example.com)
                # Extra:
                #   topLevelDomain can only be one of the defined ones
                $escapedChar = "\\\\[\\x01-\\x09\\x0B-\\x0C\\x0E-\\x7F]"; # CR and LF excluded for safety reasons
                $unescapedChar = "[a-zA-Z0-9!#$%&'*\+\-\/=?^_`{|}~]";
                if (Config::EMAIL_ADDRESS_VALIDATION_LEVEL == 2) {
                    $char = "$unescapedChar";
                } else {
                    $char = "($unescapedChar|$escapedChar)";
                };
                $dotString = "$char((\.)?$char){0,63}";

                $qtext = "[\\x01-\\x09\\x0B-\\x0C\\x0E-\\x21\\x23-\\x5B\\x5D-\\x7F]"; # All but <LF> x0A, <CR> x0D, quote (") x22 and backslash (\) x5c
                $qchar = "$qtext|$escapedChar";
                $quotedString = "\"($qchar){1,62}\"";
                if (Config::EMAIL_ADDRESS_VALIDATION_LEVEL == 2) {
                    $localPart = "$dotString"; # without escaping and quoting of local part
                } else {
                    $localPart = "($dotString|$quotedString)";
                };
                $topLevelDomain = "(" . $tlds . ")";
                $domainLiteral = "((([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))";

                $domainPart = "([a-zA-Z0-9](-?[a-zA-Z0-9])*(\.[a-zA-Z](-?[a-zA-Z0-9])*)*\.$topLevelDomain|$domainLiteral)";
                $validEmailPattern = "/^$localPart@$domainPart$/i"; # result: /^(([a-zA-Z0-9!#$%&'*\+\-\/=?^_`{|}~]|\\[\x01-\x09\x0B-\x0C\x0E-\x7F])((\.)?([a-zA-Z0-9!#$%&'*\+\-\/=?^_`{|}~]|\\[\x01-\x09\x0B-\x0C\x0E-\x7F])){0,63}|"([\x01-\x09\x0B-\x0C\x0E-\x21\x23-\x5B\x5D-\x7F]|\\[\x01-\x09\x0B-\x0C\x0E-\x7F]){1,62}")@([a-zA-Z0-9](-?[a-zA-Z0-9])*(\.[a-zA-Z](-?[a-zA-Z0-9])*)*\.(ac|ad|ae|aero|af|ag|ai|al|am|an|ao|aq|ar|arpa|as|at|au|aw|az|ba|bb|bd|be|bf|bg|bh|bi|biz|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cat|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|com|coop|cr|cs|cu|cv|cx|cy|cz|de|dev|dj|dk|dm|do|dz|ec|edu|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gov|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|home|hr|ht|hu|id|ie|il|im|in|info|int|io|iq|ir|is|it|jm|je|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|loc|ls|lt|lu|lv|ly|ma|mc|md|mg|mh|mil|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|museum|mv|mw|mx|my|mz|na|name|nc|ne|net|nf|ng|ni|nl|no|np|nr|nt|nu|nz|om|org|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|pro|ps|pt|pw|py|qa|quipu|re|ro|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw)|((([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])))$/i

                return preg_match($validEmailPattern, $email);
                break;

            default: # 10.4 style email validation

                # hmm, it seems people are starting to have emails with & and ' or ` chars in the name

                $pattern = "/^[\&\'-_.[:alnum:]]+@((([[:alnum:]]|[[:alnum:]][[:alnum:]-]*[[:alnum:]])\.)+('.$tlds.')|(([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))$/i";

                return preg_match($pattern, $email);
                break;
        }

        //this point should never be reached
        return false;
    }
} 