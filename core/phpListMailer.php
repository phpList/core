<?php
namespace phpList;

use phpList\helper\StringClass;
use phpList\helper\Util;
use PHPMailer;

class phpListMailer extends \PHPMailer
{
    public $WordWrap = 75;
    public $encoding = 'base64';
    public $message_id = 0;
    public $destinationemail = '';
    public $estimatedsize = 0;
    public $mailsize = 0;
    private $inBlast = false;
    public $image_types = [
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpe' => 'image/jpeg',
        'bmp' => 'image/bmp',
        'png' => 'image/png',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'swf' => 'application/x-shockwave-flash',
    ];

    public $LE = "\n";
    public $Hello = '';
    public $timeStamp = '';
    public $TextEncoding = '7bit';

    public function __construct($message_id, $email, $inBlast = true, $exceptions = false)
    {
        parent::__construct($exceptions);
        parent::SetLanguage('en', dirname(__FILE__) . '/phpmailer/language/');
        $this->addCustomHeader('X-phpList-version: ' . PHPLIST_VERSION);
        $this->addCustomHeader('X-MessageID: $message_id');
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
        }
        ## when the email is generated from a webpage (quite possible :-) add a "received line" to identify the origin
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $this->addTimestamp();
        }
        $this->messageid = $message_id;
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

    private function addHtml($html, $text = '', $templateid = 0)
    {
        $this->Body = $html;
        $this->IsHTML(true);
        if ($text) {
            $this->addText($text);
        }
        $this->Encoding = Config::get('HTMLEMAIL_ENCODING');
        $this->findHtmlImages($templateid);
    }

    private function addTimestamp()
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
        $this->timeStamp = $sTimeStamp;
    }

    private function addText($text)
    {
        $this->TextEncoding = Config::get('TEXTEMAIL_ENCODING');
        if (!$this->Body) {
            $this->IsHTML(false);
            $this->Body = html_entity_decode($text, ENT_QUOTES, 'UTF-8'); #$text;
        } else {
            $this->AltBody = html_entity_decode($text, ENT_QUOTES, 'UTF-8'); #$text;
        }
    }

    private function appendText($text)
    {
        if ($this->AltBody) {
            $this->AltBody .= html_entity_decode($text, ENT_QUOTES, 'UTF-8'); #$text;
        } else {
            $this->Body .= html_entity_decode($text . "\n", ENT_QUOTES, 'UTF-8'); #$text;
        }
    }

    public function CreateHeader()
    {
        $parentheader = parent::CreateHeader();
        if (!empty($this->timeStamp)) {
            $header = 'Received: ' . $this->timeStamp . $this->LE . $parentheader;
        } else {
            $header = $parentheader;
        }
        return $header;
    }

    public function CreateBody()
    {
        $body = parent::CreateBody();
        return $body;
    }

    public function compatSend($to_name = '', $to_addr, $from_name, $from_addr, $subject = '', $headers = '', $envelope = '')
    {
        if (!empty($from_addr) && method_exists($this, 'SetFrom')) {
            $this->SetFrom($from_addr, $from_name);
        } else {
            $this->From = $from_addr;
            $this->FromName = $from_name;
        }
        if (DEBUG) {
            # make sure we are not sending out emails to real subscribers
            # when developing
            $this->AddAddress(PHPLIST_DEVELOPER_EMAIL);
            if (PHPLIST_DEVELOPER_EMAIL != $to_addr) {
                $this->Body = 'X-Originally to: ' . $to_addr . "\n\n" . $this->Body;
            }
        } else {
            $this->AddAddress($to_addr);
        }
        $this->Subject = $subject;
        if ($this->Body) {
            if (!parent::Send()) {
                phpList::log()->notice(s('Error sending email to %s', $to_addr) . ' ' . $this->ErrorInfo);
                return 0;
            }
            #
        } else {
            phpList::log()->notice(s('Error, empty message-body sending email to %s', $to_addr));
            return 0;
        }
        return 1;
    }

    public function Send()
    {
        if (!parent::Send()) {
            phpList::log()->notice('Error sending email to ' /*.$to_addr*/);
            return 0;
        }
        return 1;
    }

    private function addAnAttachment($contents, $filename, $mimetype)
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

    private function findHtmlImages($templateid)
    {
        ## no template can be templateid 0, find the powered by image
        $templateid = sprintf('%d', $templateid);

        // Build the list of image extensions
        $extensions = [];
        while (list($key) = each($this->image_types)) {
            $extensions[] = $key;
        }
        $html_images = [];
        $filesystem_images = [];

        preg_match_all('/"([^"]+\.(' . implode('|', $extensions) . '))"/Ui', $this->Body, $images);

        for ($i = 0; $i < count($images[1]); $i++) {
            if ($this->imageExists($templateid, $images[1][$i])) {
                $html_images[] = $images[1][$i];
                $this->Body = str_replace($images[1][$i], basename($images[1][$i]), $this->Body);
            }
            ## addition for filesystem images
            if (Config::get('EMBEDUPLOADIMAGES')) {
                if ($this->filesystemImageExists($images[1][$i])) {
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
                if ($image = $this->getTemplateImages($templateid, $html_images[$i])) {
                    $content_type = $this->image_types[strtolower(
                        substr($html_images[$i], strrpos($html_images[$i], '.') + 1)
                    )];
                    $cid = $this->addHtmlImage($image, basename($html_images[$i]), $content_type);
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
                if ($image = $this->getFilesystemImage($filesystem_images[$i])) {
                    $content_type = $this->image_types[strtolower(
                        substr($filesystem_images[$i], strrpos($filesystem_images[$i], '.') + 1)
                    )];
                    $cid = $this->addHtmlImage($image, basename($filesystem_images[$i]), $content_type);
                    if (!empty($cid)) {
                        $this->Body = str_replace(basename($filesystem_images[$i]), "cid:$cid", $this->Body); #@@@
                    }
                }
            }
        }
        ## end addition
    }

    private function addHtmlImage($contents, $name = '', $content_type = 'application/octet-stream')
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
            $this->attachment[$cur][6] = 'inline';
            $this->attachment[$cur][7] = $cid;
        } else {
            phpList::log()->notice('phpMailer needs patching to be able to use inline images from templates');
            return false;
        }
        return $cid;
    }

    ## addition for filesystem images
    private function filesystemImageExists($filename)
    {
        ##  find the image referenced and see if it's on the server
        $imageroot = Config::get('uploadimageroot');

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

    private function getFilesystemImage($filename)
    {
        ## get the image contents
        $localfile = basename(urldecode($filename));
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

    private function imageExists($templateid, $filename)
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
        return phpList::DB()->query($query)->rowCount();
    }

    private function getTemplateImages($templateid, $filename)
    {
        if (basename($filename) == 'powerphplist.png') {
            $templateid = 0;
        }
        $result = phpList::DB()->query(sprintf(
            'SELECT data FROM %s
            WHERE template = %s
            AND (filename = "%s" or filename = "%s")',
            Config::getTableName('templateimage'),
            $templateid,
            $filename,
            basename($filename)
        ));

        return $result->fetchColumn(0);
    }

    public function EncodeFile($path, $encoding = 'base64')
    {
        # as we already encoded the contents in $path, return $path
        return chunk_split($path, 76, $this->LE);
    }

    private function AmazonSESSend($message_header, $message_body)
    {
        //TODO: put environment vars in config
        $message_header = preg_replace('/' . $this->LE . '$/', '', $message_header);
        $message_header .= $this->LE . 'Subject: ' . $this->EncodeHeader($this->Subject) . $this->LE;

        #print nl2br(htmlspecialchars($message_header));      exit;

        $date = date('r');
        $aws_signature = base64_encode(hash_hmac('sha256', $date, Config::get('AWS_SECRETKEY'), true));

        $requestheader = [
            'Host: email.us-east-1.amazonaws.com',
            'Content-Type: application/x-www-form-urlencoded',
            'Date: ' . $date,
            'X-Amzn-Authorization: AWS3-HTTPS AWSAccessKeyId=' . Config::AWS_ACCESSKEYID . ',Algorithm=HMACSHA256,Signature=' . $aws_signature,
        ];

        $rawmessage = base64_encode($message_header . $this->LE . $this->LE . $message_body);

        $requestdata = [
            'Action' => 'SendRawEmail',
            'Destinations.member.1' => $this->destinationemail,
            'RawMessage.Data' => $rawmessage,
        ];

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
            Config::get('NAME') . ' (phpList version ' . PHPLIST_VERSION . ', https://www.phplist.com/)'
        );
        curl_setopt($curl, CURLOPT_POST, 1);

        $data = '';
        foreach ($requestdata as $param => $value) {
            $data .= $param . '=' . urlencode($value) . '&';
        }
        $data = substr($data, 0, -1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $res = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            $error = curl_error($curl);
            phpList::log()->notice('Amazon SES status ' . $status . ' ' . strip_tags($res) . ' ' . $error);
        }
        curl_close($curl);
        return $status == 200;
    }

    public function MailSend($header, $body)
    {
        $this->mailsize = strlen($header . $body);

        ## use Amazon, if set up, @@TODO redo with latest PHPMailer
        ## https://github.com/PHPMailer/PHPMailer/commit/57b183bf6a203cb69231bc3a235a00905feff75b

        if (Config::USE_AMAZONSES) {
            $header .= 'To: ' . $this->destinationemail . $this->LE;
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
        if (Config::TEST) {
            return 1;
        }

        # do a quick check on mail injection attempt, @@@ needs more work
        if (preg_match("/\n/", $to)) {
            //TODO: convert to new logger
            phpList::log()->notice('Error: invalid recipient, containing newlines, email blocked');
            return 0;
        }
        if (preg_match("/\n/", $subject)) {
            phpList::log()->notice('Error: invalid subject, containing newlines, email blocked');
            return 0;
        }

        if (!$to) {
            phpList::log()->notice("Error: empty To: in message with subject $subject to send");
            return 0;
        } elseif (!$subject) {
            phpList::log()->notice("Error: empty Subject: in message to send to $to");
            return 0;
        }

        if (!$skipblacklistcheck && Util::isEmailBlacklisted($to)) {
            phpList::log()->notice("Error, $to is blacklisted, not sending");
            Util::blacklistSubscriberByEmail($to);
            Subscriber::addHistory(
                'Marked Blacklisted',
                'Found subscriber in blacklist while trying to send an email, marked black listed',
                Subscriber::getSubscriberByEmailAddress($to)->id
            );
            return 0;
        }
        return phpListMailer::sendMailPhpMailer($to, $subject, $message);
    }

    public static function constructSystemMail($message, $subject = '')
    {
        $hasHTML = strip_tags($message) != $message;
        $htmlcontent = '';

        if ($hasHTML) {
            $message = stripslashes($message);
            $textmessage = StringClass::HTML2Text($message);
            $htmlmessage = $message;
        } else {
            $textmessage = $message;
            $htmlmessage = $message;
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
        return [$htmlcontent, $textmessage];
    }

    public static function sendMailPhpMailer($to, $subject, $message)
    {
        # global function to capture sending emails, to avoid trouble with
        # older (and newer!) php versions
        $fromemail = Config::get('message_from_address');
        $fromname = Config::get('message_from_name');
        $destinationemail = '';

        #  print "Sending $to from $fromemail<br/>";
        if (Config::get('DEVVERSION')) {
            $message = "To: $to\n$message";
            if (DEBUG && PHPLIST_DEVELOPER_EMAIL != '') {
                $destinationemail = PHPLIST_DEVELOPER_EMAIL;
            } else {
                phpList::log()->critical('Error: Running DEV version, but DEVELOPER_EMAIL not set', ['page' => 'phpListMailer']);
            }
        } else {
            $destinationemail = $to;
        }
        list($htmlmessage, $textmessage) = phpListMailer::constructSystemMail($message, $subject);

        $mail = new phpListMailer('systemmessage', $destinationemail, false);
        if (!empty($htmlmessage)) {
            $mail->addHtml($htmlmessage, $textmessage, Config::get('systemmessagetemplate'));
            ## In the above phpMailer strips all tags, which removes the links which are wrapped in < and > by HTML2text
            ## so add it again
            $mail->addText($textmessage);
        }
        $mail->addText($textmessage);
        # 0008549: message envelope not passed to php mailer,
        $mail->Sender = Config::MESSAGE_ENVELOPE;

        ## always add the List-Unsubscribe header
        $removeurl = Config::get('unsubscribeurl');
        $sep = strpos($removeurl, '?') === false ? '?' : '&';
        $mail->addCustomHeader('List-Unsubscribe: <' . $removeurl . $sep . 'email=' . $to . '&jo=1>');

        return $mail->compatSend('', $destinationemail, $fromname, $fromemail, $subject);
    }

    public static function sendMailDirect($destinationemail, $subject, $message)
    {
        $GLOBALS['smtpError'] = '';
        ## try to deliver directly, so that any error (eg subscriber not found) can be sent back to the
        ## subscriber, so they can fix it
        //TODO: fix this, now using PHPLIST_DEVELOPER_EMAIL

        list($htmlmessage, $textmessage) = phpListMailer::constructSystemMail($message, $subject);
        $mail = new phpListMailer('systemmessage', $destinationemail, false, true);

        list($dummy, $domain) = explode('@', $destinationemail);

        $smtpServer = phpListMailer::getTopSmtpServer($domain);
        $fromemail = Config::get('message_from_address');
        $fromname = Config::get('message_from_name');
        $mail->Host = $smtpServer;
        $mail->Helo = Config::get('website');
        $mail->Port = 25;
        $mail->Mailer = 'smtp';
        if (!empty($htmlmessage)) {
            $mail->addHtml($htmlmessage, $textmessage, Config::get('systemmessagetemplate'));
            $mail->addText($textmessage);
        }
        $mail->addText($textmessage);
        try {
            $mail->Send('', $destinationemail, $fromname, $fromemail, $subject);
        } catch (\Exception $e) {
            //TODO: replace globals
            $GLOBALS['smtpError'] = $e->getMessage();
            return false;
        }
        return true;
    }

    public static function sendAdminCopy($subject, $message, $lists = [])
    {
        $sendcopy = Config::get('send_admin_copies');
        if ($sendcopy) {
            $mails = [];
            if (sizeof($lists) && Config::get('SEND_LISTADMIN_COPY')) {
                foreach ($lists as $list) {
                    $tmp_list = MailingList::getListById($list);
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
            $sent = [];
            foreach ($mails as $admin_mail) {
                $admin_mail = trim($admin_mail);
                if (!isset($sent[$admin_mail]) && !empty($admin_mail)) {
                    phpListMailer::sendMail(
                        $admin_mail,
                        $subject,
                        $message,
                        phpListMailer::systemMessageHeaders($admin_mail)
                    );
                    phpList::log()->notice(s('Sending admin copy to') . ' ' . $admin_mail);
                    $sent[$admin_mail] = 1;
                }
            }
        }
    }

    private function systemMessageHeaders($subscriberemail = '')
    {
        $from_address = Config::get('message_from_address');
        $from_name = Config::get('message_from_name');
        if ($from_name) {
            $additional_headers = "From: \"$from_name\" <$from_address>\n";
        } else {
            $additional_headers = "From: $from_address\n";
        }
        $message_replyto_address = Config::get('message_replyto_address');
        if ($message_replyto_address) {
            $additional_headers .= "Reply-To: $message_replyto_address\n";
        } else {
            $additional_headers .= "Reply-To: $from_address\n";
        }
        $v = PHPLIST_VERSION;
        $additional_headers .= "X-Mailer: phplist version $v (www.phplist.com)\n";
        $additional_headers .= "X-MessageID: systemmessage\n";
        if ($subscriberemail) {
            $additional_headers .= "X-User: $subscriberemail\n";
        }
        return $additional_headers;
    }

    public static function sendReport($subject, $message)
    {
        $report_addresses = explode(',', Config::get('report_address'));
        foreach ($report_addresses as $address) {
            phpListMailer::sendMail($address, Config::get('installation_name') . ' ' . $subject, $message);
        }
    }

    public static function sendError($message, $to, $subject)
    {
    }
}
