<?php
/**
 * User: SaWey
 * Date: 16/12/13
 */

namespace phpList;

if (!class_exists('PHPmailer')) {
    //https://github.com/Synchro/PHPMailer/tags
    //TODO: if using composer, we can ommit this I think
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
}

class phpListMailer extends \PHPMailer
{
    public $WordWrap = 75;
    public $encoding = 'base64';
    public $messageid = 0;
    public $destinationemail = '';
    public $estimatedsize = 0;
    public $mailsize = 0;
    private $inBlast = false;
    public $image_types = array(
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpe' => 'image/jpeg',
        'bmp' => 'image/bmp',
        'png' => 'image/png',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'swf' => 'application/x-shockwave-flash'
    );

    public $LE = "\n";
    public $Hello = '';
    public $timeStamp = '';
    public $TextEncoding = '7bit';


    function PHPlistMailer($messageid, $email, $inBlast = true, $exceptions = false)
    {
        parent::__construct($exceptions);
        parent::SetLanguage('en', dirname(__FILE__) . '/phpmailer/language/');
        $this->addCustomHeader('X-phpList-version: ' . Config::VERSION);
        $this->addCustomHeader('X-MessageID: $messageid');
        $this->addCustomHeader('X-ListMember: $email');

        ## amazon SES doesn't like this
        /*
         * http://mantis.phplist.com/view.php?id=15562
         * Interesting, https://mantis.phplist.com/view.php?id=16688
         * says Gmail wants it. Everyone's confused.
         *
         * Also, are we "Precedence: bulk, or Precedence: list"
         *
         * I guess this should become configurable, to leave the choice up to the installation,
         * but what would be our default?
         *
              if (!USE_AMAZONSES) {
        #        $this->addCustomHeader("Precedence: bulk");
              }
              *
              * ok, decided:
        */

        if (Config::get('USE_AMAZONSES', false) === false && Config::get('USE_PRECEDENCE_HEADER', false) !== false) {
            $this->addCustomHeader('Precedence: bulk');
        }

        $newwrap = Config::get('wordwrap');
        if ($newwrap) {
            $this->WordWrap = $newwrap;
        }
        if (Config::get('SMTP_TIMEOUT', false) !== false) {
            $this->Timeout = sprintf('%d', Config::get('SMTP_TIMEOUT'));
        }

        $this->destinationemail = $email;
        $this->SingleTo = false;
        $this->CharSet = 'UTF-8'; # Config::get("html_charset");
        $this->inBlast = $inBlast;
        ### hmm, would be good to sort this out differently, but it'll work for now
        ## don't send test message using the blast server
        //TODO: maybe remove $_GET from this class
        if (isset($_GET['page']) && $_GET['page'] == 'send') {
            $this->inBlast = false;
        }

        if ($this->inBlast && Config::PHPMAILER_BLASTHOST != '' && is_numeric(Config::PHPMAILER_BLASTPORT)) {
            $this->checkSMTP(Config::PHPMAILER_BLASTHOST, Config::PHPMAILER_BLASTPORT);

        } elseif (!$this->inBlast && Config::PHPMAILER_USE_TESTHOST) {
            $this->checkSMTP(Config::PHPMAILER_TESTHOST, Config::PHPMAILER_PORT);

        } elseif (Config::PHPMAILER_HOST != '') {
            $this->checkSMTP(Config::PHPMAILER_HOST, Config::PHPMAILER_PORT);

        } else {
            $this->isMail();
        }

        if (Config::MESSAGE_ENVELOPE != '') {
            $this->Sender = Config::MESSAGE_ENVELOPE;
            $this->addCustomHeader('Bounces-To: ' . Config::MESSAGE_ENVELOPE);
            ## one to work on at a later stage
            #$this->addCustomHeader('Return-Receipt-To: '.Config::MESSAGE_ENVELOPE);
        }
        ## when the email is generated from a webpage (quite possible :-) add a "received line" to identify the origin
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $this->add_timestamp();
        }
        $this->messageid = $messageid;
    }

    private function checkSMTP($host, $port)
    {
        $this->Helo = Config::get('website');
        $this->Host = $host;
        $this->Port = $port;
        if (Config::PHPMAILER_USE_SMTP && Config::PHPMAILER_SMTPUSER != '') {
            $this->Username = Config::PHPMAILER_USE_SMTP;
            $this->Password = Config::PHPMAILER_SMTPPASSWORD;
            $this->SMTPAuth = true;
        }
        $this->Mailer = 'smtp';

        if (Config::PHPMAILER_SECURE != '') {
            $this->SMTPSecure = Config::PHPMAILER_SECURE;
        }
    }

    function add_html($html, $text = '', $templateid = 0)
    {
        $this->Body = $html;
        $this->IsHTML(true);
        if ($text) {
            $this->add_text($text);
        }
        $this->Encoding = Config::get('HTMLEMAIL_ENCODING');
        $this->find_html_images($templateid);
    }

    function add_timestamp()
    {
        #0013076:
        # Add a line like Received: from [10.1.2.3] by website.example.com with HTTP; 01 Jan 2003 12:34:56 -0000
        # more info: http://www.spamcop.net/fom-serve/cache/369.html
        $ip_address = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['REMOTE_HOST'])) {
            $ip_domain = $_SERVER['REMOTE_HOST'];
        } else {
            $ip_domain = gethostbyaddr($ip_address);
        }
        $hostname = $_SERVER['HTTP_HOST'];
        $request_time = date('r', $_SERVER['REQUEST_TIME']);
        $sTimeStamp = "from $ip_domain [$ip_address] by $hostname with HTTP; $request_time";
        $this->addTimeStamp($sTimeStamp);
    }

    function addTimeStamp($sTimeStamp)
    {
        $this->timeStamp = $sTimeStamp;
    }

    function add_text($text)
    {
        $this->TextEncoding = Config::get('TEXTEMAIL_ENCODING');
        if (!$this->Body) {
            $this->IsHTML(false);
            $this->Body = html_entity_decode($text, ENT_QUOTES, 'UTF-8'); #$text;
        } else {
            $this->AltBody = html_entity_decode($text, ENT_QUOTES, 'UTF-8'); #$text;
        }
    }

    function append_text($text)
    {
        if ($this->AltBody) {
            $this->AltBody .= html_entity_decode($text, ENT_QUOTES, 'UTF-8'); #$text;
        } else {
            $this->Body .= html_entity_decode($text . "\n", ENT_QUOTES, 'UTF-8'); #$text;
        }
    }

    function build_message()
    {
    }

    function CreateHeader()
    {
        $parentheader = parent::CreateHeader();
        if (!empty($this->timeStamp)) {
            $header = 'Received: ' . $this->timeStamp . $this->LE . $parentheader;
        } else {
            $header = $parentheader;
        }
        return $header;
    }

    function CreateBody()
    {
        $body = parent::CreateBody();
        /*
              if ($this->ContentType != 'text/plain') {
                foreach ($GLOBALS['plugins'] as $plugin) {
                  $plreturn =  $plugin->mimeWrap($this->messageid,$body,$this->header,$this->ContentTypeHeader,$this->destinationemail);
                  if (is_array($plreturn) && sizeof($plreturn) == 3) {
                    $this->header = $plreturn[0];
                    $body = $plreturn[1];
                    $this->ContentTypeHeader = $plreturn[2];
                  }
                }
              }
        */
        return $body;
    }

    function compatSend($to_name = "", $to_addr, $from_name, $from_addr, $subject = '', $headers = '', $envelope = '')
    {
        if (!empty($from_addr) && method_exists($this, 'SetFrom')) {
            $this->SetFrom($from_addr, $from_name);
        } else {
            $this->From = $from_addr;
            $this->FromName = $from_name;
        }
        if (Config::DEBUG) {
            # make sure we are not sending out emails to real users
            # when developing
            $this->AddAddress(Config::DEVELOPER_EMAIL);
            if (Config::DEVELOPER_EMAIL != $to_addr) {
                $this->Body = 'X-Originally to: ' . $to_addr . "\n\n" . $this->Body;
            }
        } else {
            $this->AddAddress($to_addr);
        }
        $this->Subject = $subject;
        if ($this->Body) {
            ## allow plugins to add header lines
            /*foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                #    print "Checking Destination for ".$plugin->name."<br/>";
                $pluginHeaders = $plugin->messageHeaders($this);
                if ($pluginHeaders && sizeof($pluginHeaders)) {
                    foreach ($pluginHeaders as $headerItem => $headerValue) {
                        ## @@TODO, do we need to sanitise them?
                        $this->addCustomHeader($headerItem.': '.$headerValue);
                    }
                }
            }*/
            if (!parent::Send()) {
                Logger::logEvent(s('Error sending email to %s', $to_addr) . ' ' . $this->ErrorInfo);
                return 0;
            }
            #
        } else {
            Logger::logEvent(s('Error, empty message-body sending email to %s', $to_addr));
            return 0;
        }
        return 1;
    }

    function Send()
    {
        if (!parent::Send()) {
            Logger::logEvent("Error sending email to " /*.$to_addr*/);
            return 0;
        }
        return 1;
    }

    function add_attachment($contents, $filename, $mimetype)
    {
        ## phpmailer 2.x
        if (method_exists($this, 'AddStringAttachment')) {
            $this->AddStringAttachment($contents, $filename, 'base64', $mimetype);
        } else {
            ## old phpmailer
            // Append to $attachment array
            $cur = count($this->attachment);
            $this->attachment[$cur][0] = base64_encode($contents);
            $this->attachment[$cur][1] = $filename;
            $this->attachment[$cur][2] = $filename;
            $this->attachment[$cur][3] = $this->encoding;
            $this->attachment[$cur][4] = $mimetype;
            $this->attachment[$cur][5] = false; // isStringAttachment
            $this->attachment[$cur][6] = 'attachment';
            $this->attachment[$cur][7] = 0;
        }
    }

    function find_html_images($templateid)
    {
        #if (!$templateid) return;
        ## no template can be templateid 0, find the powered by image
        $templateid = sprintf('%d', $templateid);

        // Build the list of image extensions
        $extensions = array();
        while (list($key,) = each($this->image_types)) {
            $extensions[] = $key;
        }
        $html_images = array();
        $filesystem_images = array();

        preg_match_all('/"([^"]+\.(' . implode('|', $extensions) . '))"/Ui', $this->Body, $images);

        for ($i = 0; $i < count($images[1]); $i++) {
            if ($this->image_exists($templateid, $images[1][$i])) {
                $html_images[] = $images[1][$i];
                $this->Body = str_replace($images[1][$i], basename($images[1][$i]), $this->Body);
            }
            ## addition for filesystem images
            if (Config::get('EMBEDUPLOADIMAGES')) {
                if ($this->filesystem_image_exists($images[1][$i])) {
                    $filesystem_images[] = $images[1][$i];
                    $this->Body = str_replace($images[1][$i], basename($images[1][$i]), $this->Body);
                }
            }
            ## end addition
        }
        if (!empty($html_images)) {
            // If duplicate images are embedded, they may show up as attachments, so remove them.
            $html_images = array_unique($html_images);
            sort($html_images);
            for ($i = 0; $i < count($html_images); $i++) {
                if ($image = $this->get_template_image($templateid, $html_images[$i])) {
                    $content_type = $this->image_types[strtolower(
                        substr($html_images[$i], strrpos($html_images[$i], '.') + 1)
                    )];
                    $cid = $this->add_html_image($image, basename($html_images[$i]), $content_type);
                    if (!empty($cid)) {
                        $this->Body = str_replace(basename($html_images[$i]), "cid:$cid", $this->Body);
                    }
                }
            }
        }
        ## addition for filesystem images
        if (!empty($filesystem_images)) {
            // If duplicate images are embedded, they may show up as attachments, so remove them.
            $filesystem_images = array_unique($filesystem_images);
            sort($filesystem_images);
            for ($i = 0; $i < count($filesystem_images); $i++) {
                if ($image = $this->get_filesystem_image($filesystem_images[$i])) {
                    $content_type = $this->image_types[strtolower(
                        substr($filesystem_images[$i], strrpos($filesystem_images[$i], '.') + 1)
                    )];
                    $cid = $this->add_html_image($image, basename($filesystem_images[$i]), $content_type);
                    if (!empty($cid)) {
                        $this->Body = str_replace(basename($filesystem_images[$i]), "cid:$cid", $this->Body); #@@@
                    }
                }
            }
        }
        ## end addition
    }

    function add_html_image($contents, $name = '', $content_type = 'application/octet-stream')
    {
        ## in phpMailer 2 and up we cannot use AddStringAttachment, because that doesn't use a cid
        ## we can't write to "attachment" either, because it's private

        /* one way to do it, is using a temporary file, but that'll have
         * quite an effect on performance and also isn't backward compatible,
         * because EncodeFile would need to be reverted to the default

        file_put_contents('/tmp/'.$name,base64_decode($contents));
        $cid = md5(uniqid(time()));
        $this->AddEmbeddedImage('/tmp/'.$name, $cid, $name,'base64', $content_type);
        */

        /* So, for now the only way to get this working in phpMailer 2 or up is to make
         * the attachment array public or add the AddEmbeddedImageString method
         * we need to add instructions how to patch phpMailer for that.
         * find out here whether it's been done and give an error if not
         *
         * it's been added to phpMailer 5.2.2
         * http://code.google.com/a/apache-extras.org/p/phpmailer/issues/detail?id=119
         *
         *
         */

        /* @@TODO additional optimisation:
         *
         * - we store the image base64 encoded
         * - then we decode it to pass it back to phpMailer
         * - when then encodes it again
         * - best would be to take out a step in there, but that would require more modifications
         * to phpMailer
         */

        $cid = md5(uniqid(time()));
        if (method_exists($this, 'AddEmbeddedImageString')) {
            $this->AddEmbeddedImageString(base64_decode($contents), $cid, $name, $this->encoding, $content_type);
        } elseif (method_exists($this, 'AddStringEmbeddedImage')) {
            ## PHPMailer 5.2.5 and up renamed the method
            ## https://github.com/Synchro/PHPMailer/issues/42#issuecomment-16217354
            $this->AddStringEmbeddedImage(base64_decode($contents), $cid, $name, $this->encoding, $content_type);
        } elseif (isset($this->attachment) && is_array($this->attachment)) {
            // Append to $attachment array
            $cur = count($this->attachment);
            $this->attachment[$cur][0] = base64_decode($contents);
            $this->attachment[$cur][1] = ''; #$filename;
            $this->attachment[$cur][2] = $name;
            $this->attachment[$cur][3] = 'base64';
            $this->attachment[$cur][4] = $content_type;
            $this->attachment[$cur][5] = true; // isStringAttachment
            $this->attachment[$cur][6] = "inline";
            $this->attachment[$cur][7] = $cid;
        } else {
            Logger::logEvent("phpMailer needs patching to be able to use inline images from templates");
            return false;
        }
        return $cid;
    }

    ## addition for filesystem images
    function filesystem_image_exists($filename)
    {
        ##  find the image referenced and see if it's on the server
        $imageroot = Config::get('uploadimageroot');
#      cl_output('filesystem_image_exists '.$docroot.' '.$filename);

        $elements = parse_url($filename);
        $localfile = basename($elements['path']);

        $localfile = urldecode($localfile);
        #     cl_output('CHECK'.$localfile);

        return
            is_file(
                $_SERVER['DOCUMENT_ROOT'] . Config::PAGEROOT . '/' . Config::UPLOADIMAGES_DIR . '/image/' . $localfile
            )
            || is_file(
                $_SERVER['DOCUMENT_ROOT'] . Config::PAGEROOT . '/' . Config::UPLOADIMAGES_DIR . '/' . $localfile
            )
            ## commandline
            || is_file('../' . Config::UPLOADIMAGES_DIR . '/image/' . $localfile)
            || is_file('../' . Config::UPLOADIMAGES_DIR . '/' . $localfile);
    }

    function get_filesystem_image($filename)
    {
        ## get the image contents
        $localfile = basename(urldecode($filename));
#      cl_output('get file system image'.$filename.' '.$localfile);
        if (Config::UPLOADIMAGES_DIR != '') {
            #       print 'UPLOAD';
            $imageroot = Config::get('uploadimageroot');
            if (is_file($imageroot . $localfile)) {
                return base64_encode(file_get_contents($imageroot . $localfile));
            } else {
                $uploadimageroot = '';
                if (is_file($_SERVER['DOCUMENT_ROOT'] . $localfile)) {
                    ## save the document root to be able to retrieve the file later from commandline
                    $uploadimageroot = $_SERVER['DOCUMENT_ROOT'];
                } elseif (is_file(
                    $_SERVER['DOCUMENT_ROOT'] . '/' . Config::UPLOADIMAGES_DIR . '/image/' . $localfile
                )
                ) {
                    $uploadimageroot = $_SERVER['DOCUMENT_ROOT'] . '/' . Config::UPLOADIMAGES_DIR . '/image/';
                } elseif (is_file($_SERVER['DOCUMENT_ROOT'] . '/' . Config::UPLOADIMAGES_DIR . '/' . $localfile)) {
                    $uploadimageroot = $_SERVER['DOCUMENT_ROOT'] . '/' . Config::UPLOADIMAGES_DIR . '/';
                }
                if ($uploadimageroot != '') {
                    Config::setDBConfig('uploadimageroot', $uploadimageroot, 0, 1);
                    return base64_encode(file_get_contents($uploadimageroot . $localfile));
                }
            }
        } elseif (is_file(
            $_SERVER['DOCUMENT_ROOT'] . Config::PAGEROOT . '/' . Config::UPLOADIMAGES_DIR . '/' . $localfile
        )
        ) {
            $elements = parse_url($filename);
            $localfile = basename($elements['path']);
            return base64_encode(
                file_get_contents(
                    $_SERVER['DOCUMENT_ROOT'] . Config::PAGEROOT . '/' . Config::UPLOADIMAGES_DIR . '/' . $localfile
                )
            );
        } elseif (is_file(
            $_SERVER['DOCUMENT_ROOT'] . Config::PAGEROOT . '/' . Config::UPLOADIMAGES_DIR . '/image/' . $localfile
        )
        ) {
            return base64_encode(
                file_get_contents(
                    $_SERVER['DOCUMENT_ROOT'] . Config::PAGEROOT . '/' . Config::UPLOADIMAGES_DIR . '/image/' . $localfile
                )
            );
        } elseif (is_file('../' . Config::UPLOADIMAGES_DIR . '/' . $localfile)) { ## commandline
            return base64_encode(file_get_contents('../' . Config::UPLOADIMAGES_DIR . '/' . $localfile));
        } elseif (is_file('../' . Config::UPLOADIMAGES_DIR . '/image/' . $localfile)) {
            return base64_encode(file_get_contents('../' . Config::UPLOADIMAGES_DIR . '/image/' . $localfile));
        }
        return '';
    }

    ## end addition

    function image_exists($templateid, $filename)
    {
        if (basename($filename) == 'powerphplist.png') {
            $templateid = 0;
        }
        $query = sprintf(
            'SELECT * FROM %s
            WHERE template = %s
            AND (filename = "%s" or filename = "%s")',
            Config::getTableName('templateimage'),
            $templateid,
            $filename,
            basename($filename)
        );
        $rs = phpList::DB()->query($query);
        return phpList::DB()->numRows($rs);
    }

    function get_template_image($templateid, $filename)
    {
        if (basename($filename) == 'powerphplist.png') $templateid = 0;
        $query = sprintf(
            'SELECT data FROM %s
            WHERE template = %s
            AND (filename = "%s" or filename = "%s")',
            Config::getTableName('templateimage'),
            $templateid,
            $filename,
            basename($filename)
        );

        $req = phpList::DB()->fetchRowQuery($query);
        return $req[0];
    }

    function EncodeFile($path, $encoding = "base64")
    {
        # as we already encoded the contents in $path, return $path
        return chunk_split($path, 76, $this->LE);
    }

    function AmazonSESSend($messageheader, $messagebody)
    {
        //TODO: put environment vars in config
        $messageheader = preg_replace('/' . $this->LE . '$/', '', $messageheader);
        $messageheader .= $this->LE . "Subject: " . $this->EncodeHeader($this->Subject) . $this->LE;

        #print nl2br(htmlspecialchars($messageheader));      exit;

        $date = date('r');
        $aws_signature = base64_encode(hash_hmac('sha256', $date, Config::get('AWS_SECRETKEY'), true));

        $requestheader = array(
            'Host: email.us-east-1.amazonaws.com',
            'Content-Type: application/x-www-form-urlencoded',
            'Date: ' . $date,
            'X-Amzn-Authorization: AWS3-HTTPS AWSAccessKeyId=' . Config::AWS_ACCESSKEYID . ',Algorithm=HMACSHA256,Signature=' . $aws_signature,
        );

        /*
         *    using the SendEmail call
              $requestdata = array(
                'Action' => 'SendEmail',
                'Source' => $this->Sender,
                'Destination.ToAddresses.member.1' => $this->destinationemail,
                'Message.Subject.Data' => $this->Subject,
                'Message.Body.Text.Data' => $messagebody,
              );
        */
        #     print '<hr/>Rawmessage '.nl2br(htmlspecialchars($messageheader. $this->LE. $this->LE.$messagebody));

        $rawmessage = base64_encode($messageheader . $this->LE . $this->LE . $messagebody);
        #   $rawmessage = str_replace('=','',$rawmessage);

        $requestdata = array(
            'Action' => 'SendRawEmail',
            'Destinations.member.1' => $this->destinationemail,
            'RawMessage.Data' => $rawmessage,
        );

        $header = '';
        foreach ($requestheader as $param) {
            $header .= $param . $this->LE;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, Config::AWS_POSTURL);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $requestheader);
        #    print('<br/>Sending header '.htmlspecialchars($header).'<hr/>');

        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
        curl_setopt(
            $curl,
            CURLOPT_USERAGENT,
            Config::get('NAME') . " (phpList version " . Config::VERSION . ", http://www.phplist.com/)"
        );
        curl_setopt($curl, CURLOPT_POST, 1);

        ## this generates multipart/form-data, and that crashes the API, so don't use
        #      curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);

        $data = '';
        foreach ($requestdata as $param => $value) {
            $data .= $param . '=' . urlencode($value) . '&';
        }
        $data = substr($data, 0, -1);
        #    print('Sending data '.htmlspecialchars($data).'<hr/>');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $res = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        #    print('Curl status '.$status);
        if ($status != 200) {
            $error = curl_error($curl);
            Logger::logEvent('Amazon SES status ' . $status . ' ' . strip_tags($res) . ' ' . $error);
        }
        curl_close($curl);
        #     print('Got remote admin response '.htmlspecialchars($res).'<br/>');
        return $status == 200;
    }

    function MailSend($header, $body)
    {
        $this->mailsize = strlen($header . $body);

        ## use Amazon, if set up, @@TODO redo with latest PHPMailer
        ## https://github.com/PHPMailer/PHPMailer/commit/57b183bf6a203cb69231bc3a235a00905feff75b

        if (Config::USE_AMAZONSES) {
            $header .= "To: " . $this->destinationemail . $this->LE;
            return $this->AmazonSESSend($header, $body);
        }

        ## we don't really use multiple to's so pass that on to phpmailer, if there are any
        if (!$this->SingleTo || !Config::get('USE_LOCAL_SPOOL')) {
            return parent::MailSend($header, $body);
        }
        if (!is_dir(Config::get('USE_LOCAL_SPOOL')) || !is_writable(Config::get('USE_LOCAL_SPOOL'))) {
            ## if local spool is not set, send the normal way
            return parent::MailSend($header, $body);
        }
        $fname = tempnam(Config::get('USE_LOCAL_SPOOL'), 'msg');
        file_put_contents($fname, $header . "\n" . $body);
        file_put_contents($fname . '.S', $this->Sender);
        return true;
    }


    public static function getTopSmtpServer($domain)
    {
        getmxrr($domain, $mxhosts, $weight);
        $thgiew = array_flip($weight);
        ksort($thgiew);
        return $mxhosts[array_shift($thgiew)];
    }

    public static function sendMail($to, $subject, $message, $skipblacklistcheck = 0)
    {
        if (Config::TEST)
            return 1;

        # do a quick check on mail injection attempt, @@@ needs more work
        if (preg_match("/\n/", $to)) {
            //TODO: convert to new logger
            Logger::logEvent('Error: invalid recipient, containing newlines, email blocked');
            return 0;
        }
        if (preg_match("/\n/", $subject)) {
            Logger::logEvent('Error: invalid subject, containing newlines, email blocked');
            return 0;
        }

        if (!$to) {
            Logger::logEvent("Error: empty To: in message with subject $subject to send");
            return 0;
        } elseif (!$subject) {
            Logger::logEvent("Error: empty Subject: in message to send to $to");
            return 0;
        }

        if (!$skipblacklistcheck && User::isBlackListed($to)) {
            Logger::logEvent("Error, $to is blacklisted, not sending");
            User::blacklistUser($to);
            User::addUserHistory(
                User::getUserByEmail($to)->id,
                'Marked Blacklisted',
                'Found user in blacklist while trying to send an email, marked black listed'
            );
            return 0;
        }
        return PHPlistMailer::sendMailPhpMailer($to, $subject, $message);
    }


    public static function constructSystemMail($message, $subject = '')
    {
        $hasHTML = strip_tags($message) != $message;
        $htmlcontent = '';

        if ($hasHTML) {
            $message = stripslashes($message);
            $textmessage = String::HTML2Text($message);
            $htmlmessage = $message;
        } else {
            $textmessage = $message;
            $htmlmessage = $message;
            #  $htmlmessage = str_replace("\n\n","\n",$htmlmessage);
            $htmlmessage = nl2br($htmlmessage);
            ## make links clickable:
            preg_match_all('~https?://[^\s<]+~i', $htmlmessage, $matches);
            for ($i = 0; $i < sizeof($matches[0]); $i++) {
                $match = $matches[0][$i];
                $htmlmessage = str_replace($match, '<a href="' . $match . '">' . $match . '</a>', $htmlmessage);
            }
        }
        ## add li-s around the lists
        if (preg_match('/<ul>\s+(\*.*)<\/ul>/imsxU', $htmlmessage, $listsmatch)) {
            $lists = $listsmatch[1];
            $listsHTML = '';
            preg_match_all('/\*([^\*]+)/', $lists, $matches);
            for ($i = 0; $i < sizeof($matches[0]); $i++) {
                $listsHTML .= '<li>' . $matches[1][$i] . '</li>';
            }
            $htmlmessage = str_replace($listsmatch[0], '<ul>' . $listsHTML . '</ul>', $htmlmessage);
        }

        $htmltemplate = '';
        $templateid = Config::get('systemmessagetemplate');
        if (!empty($templateid)) {
            $template = Template::getTemplate($templateid);
            $htmltemplate = stripslashes($template->template);
        }
        if (strpos($htmltemplate, '[CONTENT]')) {
            $htmlcontent = str_replace('[CONTENT]', $htmlmessage, $htmltemplate);
            $htmlcontent = str_replace('[SUBJECT]', $subject, $htmlcontent);
            $htmlcontent = str_replace('[FOOTER]', '', $htmlcontent);
            if (!Config::EMAILTEXTCREDITS) {
                $phpListPowered = preg_replace(
                    '/src=".*power-phplist.png"/',
                    'src="powerphplist.png"',
                    $GLOBALS['PoweredByImage']
                );
            } else {
                //TODO: make sure not to forget to add this to config
                $phpListPowered = Config::get('PoweredByText');
            }
            if (strpos($htmlcontent, '[SIGNATURE]')) {
                $htmlcontent = str_replace('[SIGNATURE]', $phpListPowered, $htmlcontent);
            } elseif (strpos($htmlcontent, '</body>')) {
                $htmlcontent = str_replace('</body>', $phpListPowered . '</body>', $htmlcontent);
            } else {
                $htmlcontent .= $phpListPowered;
            }
        }
        return array($htmlcontent, $textmessage);
    }

    public static function sendMailPhpMailer($to, $subject, $message)
    {
        # global function to capture sending emails, to avoid trouble with
        # older (and newer!) php versions
        $fromemail = Config::get('message_from_address');
        $fromname = Config::get('message_from_name');
        /*$message_replyto_address = Config::get('message_replyto_address');
        if ($message_replyto_address)
            $reply_to = $message_replyto_address;
        else
            $reply_to = $from_address;*/
        $destinationemail = '';

        #  print "Sending $to from $fromemail<br/>";
        if (Config::get('DEVVERSION')) {
            $message = "To: $to\n$message";
            if (Config::DEBUG && Config::DEVELOPER_EMAIL != '') {
                $destinationemail = Config::DEVELOPER_EMAIL;
            } else {
                print 'Error: Running DEV version, but DEVELOPER_EMAIL not set';
            }
        } else {
            $destinationemail = $to;
        }
        list($htmlmessage, $textmessage) = phpListMailer::constructSystemMail($message, $subject);

        $mail = new phpListMailer('systemmessage', $destinationemail, false);
        if (!empty($htmlmessage)) {
            $mail->add_html($htmlmessage, $textmessage, Config::get('systemmessagetemplate'));
            ## In the above phpMailer strips all tags, which removes the links which are wrapped in < and > by HTML2text
            ## so add it again
            $mail->add_text($textmessage);
        }
        $mail->add_text($textmessage);
        # 0008549: message envelope not passed to php mailer,
        $mail->Sender = Config::MESSAGE_ENVELOPE;

        ## always add the List-Unsubscribe header
        $removeurl = Config::get('unsubscribeurl');
        $sep = strpos($removeurl, '?') === false ? '?' : '&';
        $mail->addCustomHeader('List-Unsubscribe: <' . $removeurl . $sep . 'email=' . $to . '&jo=1>');

        return $mail->compatSend("", $destinationemail, $fromname, $fromemail, $subject);
    }

    public static function sendMailDirect($destinationemail, $subject, $message)
    {
        $GLOBALS['smtpError'] = '';
        ## try to deliver directly, so that any error (eg user not found) can be sent back to the
        ## subscriber, so they can fix it
        //TODO: fix this, now using Config::DEVELOPER_EMAIL
        //unset($GLOBALS['developer_email']);

        list($htmlmessage, $textmessage) = phpListMailer::constructSystemMail($message, $subject);
        $mail = new phpListMailer('systemmessage', $destinationemail, false, true);

        list($dummy, $domain) = explode('@', $destinationemail);

        #print_r ($mxhosts);exit;
        $smtpServer = phpListMailer::getTopSmtpServer($domain);
        $fromemail = Config::get('message_from_address');
        $fromname = Config::get('message_from_name');
        $mail->Host = $smtpServer;
        $mail->Helo = Config::get('website');
        $mail->Port = 25;
        $mail->Mailer = 'smtp';
        if (!empty($htmlmessage)) {
            $mail->add_html($htmlmessage, $textmessage, Config::get('systemmessagetemplate'));
            $mail->add_text($textmessage);
        }
        $mail->add_text($textmessage);
        try {
            $mail->Send('', $destinationemail, $fromname, $fromemail, $subject);
        } catch (\Exception $e) {
            //TODO: replace globals
            $GLOBALS['smtpError'] = $e->getMessage();
            return false;
        }
        return true;
    }

    public static function sendAdminCopy($subject, $message, $lists = array())
    {
        $sendcopy = Config::get('send_admin_copies');
        if ($sendcopy) {
            //$lists = cleanArray($lists);
            $mails = array();
            if (sizeof($lists) && Config::get('SEND_LISTADMIN_COPY')) {
                foreach ($lists as $list) {
                    $tmp_list = MailingList::getList($list);
                    $mails[] = Admin::getAdmin($tmp_list->id)->email;
                }
            }
            ## hmm, do we want to be exclusive? Either listadmin or main ones
            ## could do all instead
            if (!sizeof($mails)) {
                $admin_mail = Config::get('admin_address');

                if ($c = Config::get('admin_addresses')) {
                    $mails = explode(',', $c);
                }
                $mails[] = $admin_mail;
            }
            $sent = array();
            foreach ($mails as $admin_mail) {
                $admin_mail = trim($admin_mail);
                if (!isset($sent[$admin_mail]) && !empty($admin_mail)) {
                    phpListMailer::sendMail(
                        $admin_mail,
                        $subject,
                        $message,
                        PHPlistMailer::systemMessageHeaders($admin_mail)
                    );
                    Logger::logEvent(s('Sending admin copy to') . ' ' . $admin_mail);
                    $sent[$admin_mail] = 1;
                }
            }
        }
    }

    private function systemMessageHeaders($useremail = "")
    {
        $from_address = Config::get('message_from_address');
        $from_name = Config::get('message_from_name');
        if ($from_name)
            $additional_headers = "From: \"$from_name\" <$from_address>\n";
        else
            $additional_headers = "From: $from_address\n";
        $message_replyto_address = Config::get('message_replyto_address');
        if ($message_replyto_address)
            $additional_headers .= "Reply-To: $message_replyto_address\n";
        else
            $additional_headers .= "Reply-To: $from_address\n";
        $v = Config::VERSION;
        $additional_headers .= "X-Mailer: phplist version $v (www.phplist.com)\n";
        $additional_headers .= "X-MessageID: systemmessage\n";
        if ($useremail)
            $additional_headers .= "X-User: $useremail\n";
        return $additional_headers;
    }

    public static function sendReport($subject,$message) {
        $report_addresses = explode(',',Config::get('report_address'));
        foreach ($report_addresses as $address) {
            phpListMailer::sendMail($address, Config::get('installation_name').' '.$subject,$message);
        }
        /*TODO: enable plugins
        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
            $plugin->sendReport(Config::get('installation_name').' '.$subject,$message);
        }*/
    }

    public static function sendError($message,$to,$subject) {
        /*TODO: enable plugins
        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
            $plugin->sendReport(Config::get('installation_name').'Error: '.$subject,$message);
        }*/
        //  Error($msg);
    }


}
