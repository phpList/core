<?php
namespace phpList;


class EmailAddress {

    private function isValid( $emailAddress, $tldWhitelist = null, $validationLevel = 3 )
    {
        $validationLevel = $this->config->get( 'emailAddress_validationLevel' );

        if ( $validationLevel == 0 ) {
            return true;
        }

        //$this->config->setRunningConfig('check_for_host', false);
        //TODO: Michiel: original phplist would never execute this because check_for_host is set to false, why?
        //$validhost = false;
        //if (!empty($email) && $this->config->get('check_for_host', false) !== false) {
        //    if (strpos($email, '@')) {
        //        list($subscribername, $domaincheck) = explode('@', $email);
        //        # checking for an MX is not sufficient
        //        #    $mxhosts = array();
        //        #    $validhost = getmxrr ($domaincheck,$mxhosts);
        //        $validhost = checkdnsrr($domaincheck, "MX") || checkdnsrr($domaincheck, "A");
        //    }
        //    if(!$valid_host){
        //        return false;
        //    }
        //}


        $emailAddress = trim( $emailAddress );

        ## do some basic validation first
        # quite often emails have two @ signs
        $ats = substr_count($emailAddress, '@');
        if ($ats != 1) {
            return false;
        }

        ## fail on emails starting or ending "-" or "." in the pre-at, seems to happen quite often, probably cut-n-paste errors
        if (preg_match('/^-/', $emailAddress) ||
            preg_match('/-@/', $emailAddress) ||
            preg_match('/\.@/', $emailAddress) ||
            preg_match('/^\./', $emailAddress) ||
            preg_match('/^\-/', $emailAddress)
        ) {
            return false;
        }

        if ( empty( $tldWhitelist ) ) {
            # Version 2014061300, Last Updated Fri Jun 13 07:07:01 2014 utc
            $tldWhitelist = 'ac|academy|accountants|actor|ad|ae|aero|af|ag|agency|ai|airforce|al|am|an|ao|aq|ar|archi|army|arpa|as|asia|associates|at|attorney|au|audio|autos|aw|ax|axa|az|ba|bar|bargains|bayern|bb|bd|be|beer|berlin|best|bf|bg|bh|bi|bid|bike|bio|biz|bj|black|blackfriday|blue|bm|bn|bo|boutique|br|bs|bt|build|builders|buzz|bv|bw|by|bz|ca|cab|camera|camp|capital|cards|care|career|careers|cash|cat|catering|cc|cd|center|ceo|cf|cg|ch|cheap|christmas|church|ci|citic|ck|cl|claims|cleaning|clinic|clothing|club|cm|cn|co|codes|coffee|college|cologne|com|community|company|computer|condos|construction|consulting|contractors|cooking|cool|coop|country|cr|credit|creditcard|cruises|cu|cv|cw|cx|cy|cz|dance|dating|de|degree|democrat|dental|dentist|desi|diamonds|digital|directory|discount|dj|dk|dm|dnp|do|domains|dz|ec|edu|education|ee|eg|email|engineer|engineering|enterprises|equipment|er|es|estate|et|eu|eus|events|exchange|expert|exposed|fail|farm|feedback|fi|finance|financial|fish|fishing|fitness|fj|fk|flights|florist|fm|fo|foo|foundation|fr|frogans|fund|furniture|futbol|ga|gal|gallery|gb|gd|ge|gf|gg|gh|gi|gift|gives|gl|glass|global|globo|gm|gmo|gn|gop|gov|gp|gq|gr|graphics|gratis|gripe|gs|gt|gu|guide|guitars|guru|gw|gy|hamburg|haus|hiphop|hiv|hk|hm|hn|holdings|holiday|homes|horse|host|house|hr|ht|hu|id|ie|il|im|immobilien|in|industries|info|ink|institute|insure|int|international|investments|io|iq|ir|is|it|je|jetzt|jm|jo|jobs|jp|juegos|kaufen|ke|kg|kh|ki|kim|kitchen|kiwi|km|kn|koeln|kp|kr|kred|kw|ky|kz|la|land|lawyer|lb|lc|lease|li|life|lighting|limited|limo|link|lk|loans|london|lr|ls|lt|lu|luxe|luxury|lv|ly|ma|maison|management|mango|market|marketing|mc|md|me|media|meet|menu|mg|mh|miami|mil|mk|ml|mm|mn|mo|mobi|moda|moe|monash|mortgage|moscow|motorcycles|mp|mq|mr|ms|mt|mu|museum|mv|mw|mx|my|mz|na|nagoya|name|navy|nc|ne|net|neustar|nf|ng|nhk|ni|ninja|nl|no|np|nr|nu|nyc|nz|okinawa|om|onl|org|pa|paris|partners|parts|pe|pf|pg|ph|photo|photography|photos|pics|pictures|pink|pk|pl|plumbing|pm|pn|post|pr|press|pro|productions|properties|ps|pt|pub|pw|py|qa|qpon|quebec|re|recipes|red|rehab|reise|reisen|ren|rentals|repair|report|republican|rest|reviews|rich|rio|ro|rocks|rodeo|rs|ru|ruhr|rw|ryukyu|sa|saarland|sb|sc|schule|sd|se|services|sexy|sg|sh|shiksha|shoes|si|singles|sj|sk|sl|sm|sn|so|social|software|sohu|solar|solutions|soy|space|sr|st|su|supplies|supply|support|surgery|sv|sx|sy|systems|sz|tattoo|tax|tc|td|technology|tel|tf|tg|th|tienda|tips|tirol|tj|tk|tl|tm|tn|to|today|tokyo|tools|town|toys|tp|tr|trade|training|travel|tt|tv|tw|tz|ua|ug|uk|university|uno|us|uy|uz|va|vacations|vc|ve|vegas|ventures|versicherung|vet|vg|vi|viajes|villas|vision|vn|vodka|vote|voting|voto|voyage|vu|wang|watch|webcam|website|wed|wf|wien|wiki|works|ws|wtc|wtf|xn--3bst00m|xn--3ds443g|xn--3e0b707e|xn--45brj9c|xn--4gbrim|xn--55qw42g|xn--55qx5d|xn--6frz82g|xn--6qq986b3xl|xn--80adxhks|xn--80ao21a|xn--80asehdb|xn--80aswg|xn--90a3ac|xn--c1avg|xn--cg4bki|xn--clchc0ea0b2g2a9gcd|xn--czr694b|xn--czru2d|xn--d1acj3b|xn--fiq228c5hs|xn--fiq64b|xn--fiqs8s|xn--fiqz9s|xn--fpcrj9c3d|xn--fzc2c9e2c|xn--gecrj9c|xn--h2brj9c|xn--i1b6b1a6a2e|xn--io0a7i|xn--j1amh|xn--j6w193g|xn--kprw13d|xn--kpry57d|xn--l1acc|xn--lgbbat1ad8j|xn--mgb9awbf|xn--mgba3a4f16a|xn--mgbaam7a8h|xn--mgbab2bd|xn--mgbayh7gpa|xn--mgbbh1a71e|xn--mgbc0a9azcg|xn--mgberp4a5d4ar|xn--mgbx4cd0ab|xn--ngbc5azd|xn--nqv7f|xn--nqv7fs00ema|xn--o3cw4h|xn--ogbpf8fl|xn--p1ai|xn--pgbs0dh|xn--q9jyb4c|xn--rhqv96g|xn--s9brj9c|xn--ses554g|xn--unup4y|xn--wgbh1c|xn--wgbl6a|xn--xkc2al3hye2a|xn--xkc2dl3a5ee0h|xn--yfro4i67o|xn--ygbi2ammx|xn--zfr164b|xxx|xyz|yachts|ye|yokohama|yt|za|zm|zone|zw';
        }

        switch ( $validationLevel ) {
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
                if ($validationLevel == 2) {
                    $char = "$unescapedChar";
                } else {
                    $char = "($unescapedChar|$escapedChar)";
                };
                $dotString = "$char((\.)?$char){0,63}";

                $qtext = "[\\x01-\\x09\\x0B-\\x0C\\x0E-\\x21\\x23-\\x5B\\x5D-\\x7F]"; # All but <LF> x0A, <CR> x0D, quote (") x22 and backslash (\) x5c
                $qchar = "$qtext|$escapedChar";
                $quotedString = "\"($qchar){1,62}\"";
                if ($validationLevel == 2) {
                    $localPart = "$dotString"; # without escaping and quoting of local part
                } else {
                    $localPart = "($dotString|$quotedString)";
                };
                $topLevelDomain = "(" . $tldWhitelist . ")";
                $domainLiteral = "((([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))";

                $domainPart = "([a-zA-Z0-9](-?[a-zA-Z0-9])*(\.[a-zA-Z](-?[a-zA-Z0-9])*)*\.$topLevelDomain|$domainLiteral)";
                $validEmailPattern = "/^$localPart@$domainPart$/i"; # result: /^(([a-zA-Z0-9!#$%&'*\+\-\/=?^_`{|}~]|\\[\x01-\x09\x0B-\x0C\x0E-\x7F])((\.)?([a-zA-Z0-9!#$%&'*\+\-\/=?^_`{|}~]|\\[\x01-\x09\x0B-\x0C\x0E-\x7F])){0,63}|"([\x01-\x09\x0B-\x0C\x0E-\x21\x23-\x5B\x5D-\x7F]|\\[\x01-\x09\x0B-\x0C\x0E-\x7F]){1,62}")@([a-zA-Z0-9](-?[a-zA-Z0-9])*(\.[a-zA-Z](-?[a-zA-Z0-9])*)*\.(ac|ad|ae|aero|af|ag|ai|al|am|an|ao|aq|ar|arpa|as|at|au|aw|az|ba|bb|bd|be|bf|bg|bh|bi|biz|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cat|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|com|coop|cr|cs|cu|cv|cx|cy|cz|de|dev|dj|dk|dm|do|dz|ec|edu|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gov|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|home|hr|ht|hu|id|ie|il|im|in|info|int|io|iq|ir|is|it|jm|je|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|loc|ls|lt|lu|lv|ly|ma|mc|md|mg|mh|mil|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|museum|mv|mw|mx|my|mz|na|name|nc|ne|net|nf|ng|ni|nl|no|np|nr|nt|nu|nz|om|org|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|pro|ps|pt|pw|py|qa|quipu|re|ro|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw)|((([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])))$/i

                return ( preg_match( $validEmailPattern, $emailAddress ) > 0 );
                break;

            default: # 10.4 style email validation

                # hmm, it seems people are starting to have emails with & and ' or ` chars in the name

                $pattern = "/^[\&\'-_.[:alnum:]]+@((([[:alnum:]]|[[:alnum:]][[:alnum:]-]*[[:alnum:]])\.)+('.$tldWhitelist.')|(([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))$/i";

                return (preg_match( $pattern, $emailAddress) > 0) ;
                break;
        }
    }
}
