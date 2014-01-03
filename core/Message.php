<?php
/**
 * User: SaWey
 * Date: 5/12/13
 */

namespace phpList;


class Message
{
    public $id = 0;
    public $subject;
    public $fromfield;
    public $tofield;
    public $replyto;
    public $message;
    public $textmessage;
    public $footer;
    /**
     * @var \DateTime
     */
    public $entered;
    public $modified;
    /**
     * @var \DateTime
     */
    public $embargo;
    public $repeatinterval;
    /**
     * @var \DateTime
     */
    public $repeatuntil;
    public $requeueinterval;
    /**
    * @var \DateTime
    */
    public $requeueuntil;
    public $status;
    public $userselection;
    /**
     * @var \DateTime
     */
    public $sent;
    public $htmlformatted;
    public $sendformat;
    public $template;
    public $processed;
    public $astext;
    public $ashtml;
    public $astextandhtml;
    public $aspdf;
    public $astextandpdf;
    public $viewed;
    public $bouncecount;
    /**
     * @var \DateTime
     */
    public $sendstart;
    public $rsstemplate;
    public $owner;
    public $messagedata;

    //non database variables
    public $embargo_in_past;
    private $template_object;

    public $lists = array();

    public function __construct()
    {
    }

    /**
     * Create a message object from an array from the database
     * @param $array
     * @return Message
     */
    private static function messageFromArray($array)
    {
        $message = null;
        if(is_array($array)){
            $message = new Message();
            $message->id = $array['id'];
            $message->subject = $array['subject'];
            $message->fromfield = $array['fromfield'];
            $message->tofield = $array['tofield'];
            $message->replyto = $array['replyto'];
            $message->message = $array['message'];
            $message->textmessage = $array['textmessage'];
            $message->footer = $array['footer'];
            $message->entered = new \DateTime($array['entered']);
            $message->modified = $array['modified'];
            $message->embargo = new \DateTime($array['embargo']);
            $message->repeatinterval = $array['repeatinterval'];
            $message->repeatuntil = new \DateTime($array['repeatuntil']);
            $message->requeueinterval = $array['requeueinterval'];
            $message->requeueuntil = $array['requeueuntil'];
            $message->status = $array['status'];
            $message->userselection = $array['userselection'];
            $message->sent = new \DateTime($array['sent']);
            $message->htmlformatted = $array['htmlformatted'];
            $message->sendformat = $array['sendformat'];
            $message->template = $array['template'];
            $message->processed = $array['processed'];
            $message->astext = $array['astext'];
            $message->ashtml = $array['ashtml'];
            $message->astextandhtml = $array['astextandhtml'];
            $message->aspdf = $array['aspdf'];
            $message->astextandpdf = $array['astextandpdf'];
            $message->viewed = $array['viewed'];
            $message->bouncecount = $array['bouncecount'];
            $message->sendstart = new \DateTime($array['sendstart']);
            $message->rsstemplate = $array['rsstemplate'];
            $message->owner = $array['owner'];
            $message->embargo_in_past = isset($array['inthepast']) ? $array['inthepast'] : false;
        }
        return $message;
    }

    /**
     * Get a message by id (for an owner if provided)
     * @param int $id
     * @param int $owner
     * @return Message
     */
    public static function getMessage($id, $owner = 0)
    {
        $condition = '';
        if ($owner != 0) {
            $condition = sprintf(' AND owner = %d', $owner);
        }
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d %s',
                Config::getTableName('message'),
                $id,
                $condition
            )
        );
        return Message::messageFromArray($result);
    }

    /**
     * Get an array of Messages by searching its status and subject
     * When $owner is provided, only returns the messages for the given owner
     * @param string|array $status
     * @param string $subject
     * @param int $owner
     * @param string $order
     * @param int $offset
     * @param int $limit
     * @return array Message
     */
    public static function getMessagesBy($status, $subject = '', $owner = 0, $order = '', $offset = 0, $limit = 0)
    {
        $return = array();

        $condition = 'status IN (';
        $condition .= is_array($status) ? implode(',', $status) : $status;
        $condition .= ') ';

        if ($subject != '') {
            $condition .= ' AND subject LIKE "%' . String::sqlEscape($subject) . '%" ';
        }
        if ($owner != 0) {
            $condition .= sprintf(' AND owner = %d', $owner);
        }

        switch ($order) {
            case 'sentasc':
                $sortBySql = ' ORDER BY sent ASC';
                break;
            case 'sentdesc':
                $sortBySql = ' ORDER BY sent DESC';
                break;
            case 'subjectasc':
                $sortBySql = ' ORDER BY subject ASC';
                break;
            case 'subjectdesc':
                $sortBySql = ' ORDER BY subject DESC';
                break;
            case 'enteredasc':
                $sortBySql = ' ORDER BY entered ASC';
                break;
            case 'entereddesc':
                $sortBySql = ' ORDER BY entered DESC';
                break;
            case 'embargoasc':
                $sortBySql = ' ORDER BY embargo ASC';
                break;
            case 'embargodesc':
                $sortBySql = ' ORDER BY embargo DESC';
                break;
            default:
                $sortBySql = ' ORDER BY embargo DESC, entered DESC';
        }

        $req = phpList::DB()->query(
            sprintf(
                'SELECT COUNT(*) FROM %
                WHERE %s %s',
                Config::getTableName('message'),
                $condition,
                $sortBySql
            )
        );
        $return['total'] = phpList::DB()->fetchRow($req);

        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE %s %s
                LIMIT %d
                OFFSET %d',
                Config::getTableName('message'),
                $condition,
                $sortBySql,
                $limit,
                $offset
            )
        );

        while ($msg = phpList::DB()->fetchAssoc($result)) {
            $return['messages'][] = Message::messageFromArray($msg);
        }

        return $return;
    }

    /**
     * Get the messages that need to be processed
     * @return array Message
     */
    public static function getMessagesToQueue()
    {
        $messagelimit = '';
        ## limit the number of campaigns to work on
        if (is_numeric(Config::MAX_PROCESS_MESSAGE)) {
            $messagelimit = sprintf(' limit %d ', Config::MAX_PROCESS_MESSAGE);
        }
        $messages = array();
        $query = phpList::DB()->query(
            sprintf(
                'SELECT id FROM %s
                WHERE status NOT IN ("draft", "sent", "prepared", "suspended")
                AND embargo < CURRENT_TIMESTAMP
                ORDER BY entered',
                Config::getTableName('message'),
                $messagelimit
            )
        );
        while ($row = phpList::DB()->fetchAssocQuery($query)) {
            $messages[] = Message::messageFromArray($row);
        }
        return $messages;
    }

    /**
     * Get the number of views for this message
     * @return int
     */
    public function getUniqueViews()
    {
        return phpList::DB()->fetchRowQuery(
            sprintf(
                'SELECT COUNT(userid)
                FROM %s
                WHERE viewed IS NOT NULL
                AND status = \'sent\'
                AND messageid = %d',
                Config::getTableName('usermessage'),
                $this->id
            )
        );
    }

    /**
     * Get the number of clicks for this message
     * @return int
     */
    public function getClicks()
    {
        return phpList::DB()->fetchRowQuery(
            sprintf(
                'SELECT SUM(clicked)
                FROM %s
                WHERE messageid = %d',
                Config::getTableName('linktrack_ml'),
                $this->id
            )
        );
    }

    /**
     * Get attachments from this message
     * @return array Attachment
     */
    public function getAttachments()
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s AS m_a, %s as a
                WHERE m_a.attachmentid = a.id
                AND m_a.messageid = %d',
                Config::getTableName('message_attachment'),
                Config::getTableName('attachment'),
                $this->id
            )
        );

        $attachments = array();
        while ($row = phpList::DB()->fetchAssoc($result)) {
            $attachment = new Attachment($row['filename'], $row['remotefile'], $row['mimetype'], $row['description'], $row['size']);
            $attachment->id = $row['id'];
            $attachments[] = $attachment;
        }

        return $attachments;
    }

    /**
     * Get the template for this message if set
     * @return Template
     */
    public function getTemplateObject(){
        if($this->template_object == null){
            $this->template_object = Template::getTemplate($this->template);
        }
        return $this->template_object;
    }



    /**
     * Does the message contain HTML formatting
     * @return bool
     */
    public function isHTMLFormatted()
    {
        return strip_tags($this->message) != $this->message;
    }

    /**
     * @return array
     */
    public function listsDone()
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT l.* FROM %s AS lm, %s AS l
                WHERE lm.messageid = %d
                AND lm.listid = l.id',
                Config::getTableName('listmessage'),
                Config::getTableName('list'),
                $this->id
            )
        );

        $lists_done = array();
        while ($lst = phpList::DB()->fetchRow($result)) {
            $lists_done[] = $lst;
        }

        return $lists_done;
    }

    /**
     * @return array
     */
    public function listsExcluded()
    {
        $lists_excluded = array();
        if ($this->excludelist) {
            $result = phpList::DB()->query(
                sprintf(
                    'SELECT * FROM %s WHERE id IN (%s)',
                    Config::getTableName('list'),
                    implode(',', $this->excludelist)
                )
            );

            while ($lst = phpList::DB()->fetchRow($result)) {
                $lists_done[] = $lst;
            }
        }

        return $lists_excluded;
    }

    /**
     * Does the message contain Clicktrack links
     * @return bool
     */
    public function hasClickTrackLinks()
    {
        return preg_match('/lt\.php\?id=[\w%]{22}/', $this->message, $regs) ||
        preg_match('/lt\.php\?id=[\w%]{16}/', $this->message, $regs) ||
        (Config::get('CLICKTRACK_LINKMAP') && //TODO: Change this constant to use config
            (preg_match('#' . Config::get('CLICKTRACK_LINKMAP') . '/[\w%]{22}#', $this->message) ||
                preg_match('#' . Config::get('CLICKTRACK_LINKMAP') . '/[\w%]{16}#', $this->message)));
    }

    /**
     * Purge the drafts (from given owner if provided)
     * @param int $owner
     */
    public static function purgeDrafts($owner)
    {
        $ownerselect_and = sprintf(' AND owner = %d', $owner);
        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE status = "draft"
                AND (subject = "" OR subject = "(no subject)")
                %s',
                Config::getTableName('message'),
                $ownerselect_and
            )
        );
    }

    /**
     * Save this message to the database, calls update() when it already exists
     * Will be filled with default values
     * @param int $owner
     */
    public function save($owner)
    {
        if ($this->id != 0) {
            $this->update();
        } else {
            $query = ' INSERT INTO %s (subject, status, entered, sendformat, embargo, repeatuntil, owner, template, tofield, replyto,footer)
                        VALUES("(no subject)", "draft", CURRENT_TIMESTAMP, "HTML", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, %s, %s, "", "", %s )';

            $query = sprintf(
                $query,
                Config::getTableName('message'),
                $owner,
                Config::get('defaultmessagetemplate'),
                Config::get('messagefooter')
            );
            phpList::DB()->query($query);
            $this->id = phpList::DB()->insertedId();
        }
    }

    /**
     * Update this message's info in the database
     */
    public function update()
    {
        $query = sprintf(
            'UPDATE %s SET subject = "%s", fromfield = "%s", tofield = "%s", replyto = "%s", embargo = "%s",
                     repeatinterval = "%s", repeatuntil = "%s", message = "%s", textmessage = "%s", footer = "%s", status = "%s",
                     htmlformatted = "%s", sendformat  =  "%s", template  =  "%s", requeueinterval = "%s", requeueuntil = "%s"
                     WHERE id = %d',
            Config::getTableName('message'),
            $this->subject,
            $this->fromfield,
            $this->tofield,
            $this->replyto,
            $this->embargo->format('Y-m-d H:i'),
            $this->repeatinterval,
            $this->repeatuntil->format('Y-m-d H:i'),
            $this->message,
            $this->textmessage,
            $this->footer,
            $this->status,
            $this->isHTMLFormatted() ? '1' : '0',
            $this->sendformat,
            $this->template,
            $this->requeueinterval,
            $this->requeueuntil->format('Y-m-d H:i'),
            $this->id
        );

        phpList::DB()->query($query);
    }

    /**
     * Delete this message from the database
     * @return int
     */
    public function delete()
    {
        $tables = array(
            Config::getTableName('message') => 'id',
            Config::getTableName('usermessage') => 'id',
            Config::getTableName('listmessage') => 'id'
        );
        phpList::DB()->deleteFromArray($tables, $this->id);

        return phpList::DB()->affectedRows();
    }

    /**
     * Add an attachment to this message
     * @param Attachment $attachment
     * @return int
     */
    public function addAttachment($attachment)
    {
        phpList::DB()->query(
            sprintf(
                'INSERT INTO %s (messageid,attachmentid) VALUES(%d,%d)',
                Config::getTableName('message_attachment'),
                $this->id,
                $attachment->id
            )
        );
        return phpList::DB()->insertedId();
    }

    /**
     * Remove an attachment from this message
     * @param Attachment $attachment
     */
    public function removeAttachment($attachment)
    {
        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE id = %d
                AND messageid = %d',
                Config::getTableName('message_attachment'),
                $attachment->id,
                $this->id
            )
        );

        $attachment->RemoveIfOrphaned();
    }

    /**
     * Put the message back on the queue
     * @throws \Exception
     */
    public function requeue()
    {
        phpList::DB()->query(
            sprintf(
                'UPDATE %s SET status = \'submitted\', sendstart = null, embargo = "%s"
                WHERE id = %d'
            ),
            Config::getTableName('message'),
            $this->embargo->format('Y-m-d H:i'),
            $this->id
        );
        $suc6 = phpList::DB()->affectedRows();

        # only send it again to users if we are testing, otherwise only to new users
        if (Config::TEST) {
            phpList::DB()->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE messageid = %d',
                    Config::getTableName('usermessage'),
                    $this->id
                )
            );
        }
        if ($suc6) {
            phpList::DB()->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE id = %d
                    AND (name = "start_notified" OR name = "end_notified")',
                    Config::getTableName('messagedata'),
                    $this->id
                )
            );

            if ($this->finishsending->getTimestamp() < time()) {
                throw new \Exception('This campaign is scheduled to stop sending in the past. No mails will be sent.');
            }
        } else {
            throw new \Exception('Could not resend this message');
        }
    }

    /**
     * Check for messages with a requeueinterval and requeue if needed
     */
    public static function checkMessagesToRequeue()
    {
        $req = phpList::DB()->query(
            sprintf(
                'SELECT *, embargo < now() AS inthepast FROM %s
                WHERE requeueinterval > 0
                AND requeueuntil > now()
                AND status = "sent"',
                Config::getTableName('message')
            )
        );
        while ($row = phpList::DB()->fetchAssocQuery($req)) {
            $message = Message::messageFromArray($row);
            if ($message->embargo_in_past) {
                $now = new \DateTime('now');
                $message->embargo = $now->add(
                    \DateInterval::createFromDateString($message->repeatinterval . 'minutes')
                );
            } else {
                $message->embargo->add(
                    \DateInterval::createFromDateString($message->repeatinterval . 'minutes')
                );
            }
            $message->requeue();
        }
    }


    /**
     * Add message to a mailing list
     * @param int $list_id
     */
    public function addToList($list_id)
    {
        phpList::DB()->query(
            sprintf(
                'INSERT INTO %s (messageid,listid,entered)
                VALUES(%d,%d,CURRENT_TIMESTAMP)',
                Config::getTableName('listmessage'),
                $this->id,
                $list_id
            )
        );
    }

    /**
     * Update the status of a message
     * @param string $status
     */
    public function setStatus($status)
    {
        switch($status){
            case 'inprocess':
                phpList::DB()->query(
                sprintf(
                    'UPDATE %s SET status = "inprocess", sendstart = CURRENT_TIMESTAMP
                    WHERE sendstart IS null
                    AND id = %d',
                    Config::getTableName('message'),
                    $this->id
                ));
                break;
            case 'sent':
                phpList::DB()->query(
                sprintf(
                    'UPDATE %s set status = "sent", sent = CURRENT_TIMESTAMP
                    WHERE id = %d',
                    Config::getTableName('message'),
                    $this->id
                ));
                break;
            default:
                phpList::DB()->query(
                    sprintf(
                        'UPDATE %s SET status = "%s"
                        WHERE id = %d',
                        Config::getTableName('message'),
                        $status,
                        $this->id
                    )
                );
                break;
        }
    }

    /**
     * Suspend a message from sending
     * @param int $owner
     * @return int
     */
    public function suspend($owner)
    {
        phpList::DB()->query(
            sprintf(
                'UPDATE %s SET status = "suspended"
                WHERE id = %d
                AND (status = "inprocess" OR status = "submitted")
                AND owner = %d',
                Config::getTableName('message'),
                $this->id,
                $owner
            )
        );

        return phpList::DB()->affectedRows();
    }

    /**
     * Suspend all messages from sending
     * @param int $owner
     * @return int
     */
    public static function suspendAll($owner)
    {
        phpList::DB()->query(
            sprintf(
                'UPDATE %s SET status = "suspended"
                WHERE (status = "inprocess"
                OR status = "submitted")
                AND owner = %d',
                Config::getTableName('message'),
                $owner
            )
        );

        return phpList::DB()->affectedRows();
    }

    /**
     * Mark message from provided owner as sent
     * @param int $owner
     * @return int
     */
    public function markSent($owner)
    {
        phpList::DB()->query(
            sprintf(
                'UPDATE %s SET status = "sent"
                WHERE id = %d
                AND (status = "suspended")
                AND owner = %d',
                Config::getTableName('message'),
                $this->id,
                $owner
            )
        );

        return phpList::DB()->affectedRows();
    }

    /**
     * Mark all messages from provided owner as sent
     * @param int $owner
     * @return int
     */
    public static function markAllSent($owner)
    {
        phpList::DB()->query(
            sprintf(
                'UPDATE %s SET status = "sent"
                WHERE (status = "suspended")
                AND owner = %d',
                Config::getTableName('message'),
                $owner
            )
        );

        return phpList::DB()->affectedRows();
    }

    /**
     * Get a messagedata item
     * @param string $item
     * @return mixed
     * @throws \Exception
     */
    public function __get($item)
    {
        if (isset($this->messagedata[$item])) {
            return $this->messagedata[$item];
        }

        $default = array(
            'from' => Config::get('message_from_address', Config::get('admin_address')),
            ## can add some more from below
            'google_track' => Config::get('always_add_googletracking'),
        );

        ## when loading an old message that hasn't got data stored in message data, load it from the message table
        $prevMsgData = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('message'),
                $this->id
            )
        );

        $messagedata = array(
            'template' => Config::get('defaultmessagetemplate'),
            'sendformat' => 'HTML',
            'message' => '',
            'forwardmessage' => '',
            'textmessage' => '',
            'rsstemplate' => '',
            'embargo' => new \DateTime(),
            'repeatinterval' => 0,
            'repeatuntil' => new \DateTime(),
            'requeueinterval' => 0,
            'requeueuntil' => new \DateTime(),
            'finishsending' => date_create(time() + Config::DEFAULT_MESSAGEAGE),
            'fromfield' => '',
            'subject' => '',
            'forwardsubject' => '',
            'footer' => Config::get('messagefooter'),
            'forwardfooter' => Config::get('forwardfooter'),
            'status' => '',
            'tofield' => '',
            'replyto' => '',
            'targetlist' => '',
            'criteria_match' => '',
            'sendurl' => '',
            'sendmethod' => 'inputhere', ## make a config
            'testtarget' => '',
            'notify_start' => Config::get('notifystart_default'),
            'notify_end' => Config::get('notifyend_default'),
            'google_track' => $default['google_track'] == 'true' || $default['google_track'] === true || $default['google_track'] == '1',
            'excludelist' => array(),
        );
        if (is_array($prevMsgData)) {
            foreach ($prevMsgData as $key => $val) {
                $messagedata[$key] = $val;
            }
        }

        $msgdata_req = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('messagedata'),
                $this->id
            )
        );

        while ($row = phpList::DB()->fetchAssoc($msgdata_req)) {
            if (strpos($row['data'], 'SER:') === 0) {
                $data = substr($row['data'], 4);
                $data = @unserialize(stripslashes($data));
            } else {
                $data = stripslashes($row['data']);
            }
            if (!in_array(
                $row['name'],
                array('astext', 'ashtml', 'astextandhtml', 'aspdf', 'astextandpdf')
            )
            ) { ## don't overwrite counters in the message table from the data table
                $messagedata[stripslashes($row['name'])] = $data;
            }
        }

        /*Is a DateTime object now
         * foreach (array('embargo','repeatuntil','requeueuntil') as $datefield) {
            if (!is_array($messagedata[$datefield])) {
                $messagedata[$datefield] = array('year' => date('Y'),'month' => date('m'),'day' => date('d'),'hour' => date('H'),'minute' => date('i'));
            }
        }*/

        // Load lists that were targetted with message...

        $result = phpList::DB()->query(
            sprintf(
                'SELECT list.name,list.id
                                                        FROM %s AS listmessage, %s AS list
                                                        WHERE listmessage.messageid = %d
                                                        AND listmessage.listid = list.id',
                Config::getTableName('listmessage'),
                Config::getTableName('list'),
                $this->id
            )
        );

        while ($lst = phpList::DB()->fetchArray($result)) {
            $messagedata['targetlist'][$lst['id']] = 1;
        }

        ## backwards, check that the content has a url and use it to fill the sendurl
        if (empty($messagedata['sendurl'])) {

            ## can't do "ungreedy matching, in case the URL has placeholders, but this can potentially
            ## throw problems
            if (preg_match('/\[URL:(.*)\]/i', $messagedata['message'], $regs)) {
                $messagedata['sendurl'] = $regs[1];
            }
        }
        if (empty($messagedata['sendurl']) && !empty($messagedata['message'])) {
            # if there's a message and no url, make sure to show the editor, and not the URL input
            $messagedata['sendmethod'] = 'inputhere';
        }

        ### parse the from field into it's components - email and name
        if (preg_match("/([^ ]+@[^ ]+)/", $messagedata['fromfield'], $regs)) {
            # if there is an email in the from, rewrite it as "name <email>"
            $messagedata['fromname'] = str_replace($regs[0], "", $messagedata['fromfield']);
            $messagedata['fromemail'] = $regs[0];
            # if the email has < and > take them out here
            $messagedata['fromemail'] = str_replace('<', '', $messagedata['fromemail']);
            $messagedata['fromemail'] = str_replace('>', '', $messagedata['fromemail']);
            # make sure there are no quotes around the name
            $messagedata['fromname'] = str_replace('"', '', ltrim(rtrim($messagedata['fromname'])));
        } elseif (strpos($messagedata['fromfield'], ' ')) {
            # if there is a space, we need to add the email
            $messagedata['fromname'] = $messagedata['fromfield'];
            #  $cached[$messageid]['fromemail'] = 'listmaster@$domain';
            $messagedata['fromemail'] = $default['from'];
        } else {
            $messagedata['fromemail'] = $default['from'];
            $messagedata['fromname'] = $messagedata["fromfield"];
        }
        $messagedata["fromname"] = trim($messagedata["fromname"]);

        # erase double spacing
        while (strpos($messagedata['fromname'], '  ')) {
            $messagedata['fromname'] = str_replace('  ', ' ', $messagedata['fromname']);
        }

        ## if the name ends up being empty, copy the email
        if (empty($messagedata['fromname'])) {
            $messagedata['fromname'] = $messagedata['fromemail'];
        }

        if (!empty($messagedata['targetlist']['unselect'])) {
            unset($messagedata['targetlist']['unselect']);
        }
        if (!empty($messagedata['excludelist']['unselect'])) {
            unset($messagedata['excludelist']['unselect']);
        }

        $this->messagedata = $messagedata;

        if (isset($this->messagedata[$item])) {
            return $this->messagedata[$item];
        }else{
            return null;
        }
    }

    /**
     * Check if a data item has been set
     * @param string $item
     * @return bool
     */
    public function __isset($item)
    {
        if (!isset($this->messagedata[$item])) {
            //try one last time to load from db
            return ($this->__get($item) != null && $this->__get($item) != '');
        }else{
            return true;
        }
    }

    /**
     * Set a temporary message data item
     * Only used in current object context
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->messagedata[$name] = $value;
    }

    /**
     * Set a data item for this message
     * @param string $name
     * @param string $value
     */
    public function setDataItem($name, $value)
    {
        if ($name == 'PHPSESSID') return;
        if ($name == session_name()) return;

        //TODO: setMessagData should probably not be used to add the message to a list
        if ($name == 'targetlist' && is_array($value)) {
            phpList::DB()->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE messageid = %d',
                    Config::getTableName('listmessage'),
                    $this->id
                )
            );

            if (!empty($value['all']) || !empty($value['allactive'])) {
                //TODO: configure subselect to be used?
                $lists = MailingList::getAllLists();
                //$res = phpList::DB()->Sql_query('select * from '. $GLOBALS['tables']['list']. ' '.$GLOBALS['subselect']);
                /**
                 * @var $list MailingList
                 */
                foreach ($lists as $list) {
                    if ($list->active || !empty($value['all'])) {
                        $this->addToList($list->id);
                    }
                }
            } else {
                foreach ($value as $listid => $val) {
                    $this->addToList($listid);
                }
            }
        }
        if (is_array($value) || is_object($value)) {
            $value = 'SER:' . serialize($value);
        }

        phpList::DB()->replaceQuery(
            Config::getTableName('messagedata'),
            array('id' => $this->id, 'name' => $name, 'data' => $value),
            array('name', 'id')
        );
    }

    /**
     * Reset this message's statistics
     */
    public function resetMessageStatistics()
    {
        ## remove the "forward" entries, but only if they are for one (this) message
        $delete = array();
        $req = phpList::DB()->query(
            sprintf(
                'SELECT forwardid FROM %s
                WHERE messageid = %d',
                Config::getTableName('linktrack_uml_click'),
                $this->id
            )
        );
        while ($fwdid = phpList::DB()->fetchRow($req)) {
            $count = phpList::DB()->fetchRowQuery(
                sprintf(
                    'SELECT COUNT(*) FROM %s
                    WHERE id = %d',
                    Config::getTableName('linktrack_forward'),
                    $fwdid[0]
                )
            );
            if ($count[0] < 2) {
                $delete[] = $fwdid[0];
            }
        }
        if (sizeof($delete)) {
            phpList::DB()->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE id IN (%s)',
                    Config::getTableName('linktrack_forward'),
                    join(',', $delete)
                )
            );
        }

        $tables = array(
            Config::getTableName('linktrack_uml_click') => 'messageid',
            Config::getTableName('usermessage') => 'messageid'
        );
        phpList::DB()->deleteFromArray($tables, $this->id);
    }

    /**
     * Exclude users from given list(s) to receive this mailing
     * @param string|array $list
     */
    public function excludeUsersOnList($list)
    {
        //could do MailingList::getListUsers, but might be slower on big lists
        if(is_array($list)){
            $where = ' WHERE listid IN (' . join(',', $list) .')';
        }else{
            $where = sprintf(' WHERE listid = %d', $list);
        }
        $result = phpList::DB()->query(
            sprintf(
                'SELECT userid FROM %s %s',
                Config::getTableName('listuser'),
                $where
            )
        );
        while ($user_id = phpList::DB()->fetchRow($result)) {
            phpList::DB()->query(sprintf(
                    'REPLACE INTO %s SET
                    entered = CURRENT_TIMESTAMP,
                    userid = %d,
                    messageid = %d,
                    status = "excluded"',
                    Config::getTableName('usermessage'),
                    $user_id,
                    $this->id
                ));
        }
    }

    /**
     * Update the status of a message going out to a user
     * @param int $user_id
     * @param string $status
     */
    public function updateUserMessageStatus($user_id, $status){
        phpList::DB()->query(sprintf(
                'REPLACE INTO %s (entered, useris, messageid, status)
                VALUES(CURRENT_TIMESTAMP, %d, %d, "%s")',
                Config::getTableName('usermessage'),
                $user_id,
                $this->id,
                $status
            ));
    }

    /**
     * Increment the processed counter
     */
    public function incrementProcessedAmount()
    {
        phpList::DB()->query(sprintf(
                'UPDATE %s SET processed = processed + 1
                WHERE id = %d',
                Config::getTableName('message'),
                $this->id
            ));
    }

    /**
     * Duplicate a message and reschedule
     */
    public function repeatMessage()
    {
        #  if (!USE_REPETITION && !USE_rss) return;

        ## do not repeat when it has already been done
        if ($this->repeatuntil->getTimestamp() < time() && (!empty($this->repeatedid) || $this->repeatinterval == 0)) return;

        # get the future embargo, either "repeat" minutes after the old embargo
        # or "repeat" after this very moment to make sure that we're not sending the
        # message every time running the queue when there's no embargo set.
        $new_embargo = $this->embargo->add(\DateInterval::createFromDateString($this->repeatinterval . ' minutes'));
        $now = (new \DateTime());
        $new_embargo2 = $now->add(\DateInterval::createFromDateString($this->repeatinterval . ' minutes'));
        $is_fututre = ($new_embargo->getTimestamp() > time());

        # copy the new message
        $new_message = $this;
        $new_message->id = 0;
        $new_message->update();
        //also need to copy the message data for this one
        phpList::DB()->query(sprintf(
                'INSERT INTO %s(name, id, data)
                    SELECT name, %d, data
                    FROM %s
                    WHERE id = %d',
                Config::getTableName('messagedata'),
                $new_message->id, /*New message id to copy to*/
                Config::getTableName('messagedata'),
                $this->id
            ));

        # check whether the new embargo is not on an exclusion
        if (Config::get('repeat_exclude', false) !== false) {
            $loopcnt = 0;
            $repeatinterval = 0;
            while ($this->excludedDateForRepetition($new_embargo)) {
                $repeatinterval += $new_message->repeatinterval;
                $loopcnt++;
                $new_embargo = $new_message->embargo->add(\DateInterval::createFromDateString($repeatinterval . ' minutes'));
                $now = (new \DateTime());
                $new_embargo2 = $now->add(\DateInterval::createFromDateString($repeatinterval . ' minutes'));
                $is_fututre = ($new_embargo->getTimestamp() > time());

                if ($loopcnt > 15) {
                    Logger::logEvent('Unable to find new embargo date too many exclusions? for message ' . $new_message->id);
                    return;
                }
            }
        }
        # correct some values
        if (!$is_fututre) {
            $new_embargo = $new_embargo2;
        }

        $new_message->embargo = $new_embargo;
        $new_message->status = 'submitted';
        $new_message->sent = '';
        foreach (array("processed","astext","ashtml","astextandhtml","aspdf","astextandpdf","viewed", "bouncecount") as $item) {
            $new_message->$item = 0;
        }
        $new_message->update();



        # lists
        phpList::DB()->query(sprintf(
                'INSERT INTO %s(messageid,listid,entered)
                    SELECT %d, listid, CURRENT_TIMESTAMP
                    FROM %s
                    WHERE messageid = %d',
                Config::getTableName('listmessage'),
                $new_message->id, /*New message id to copy to*/
                Config::getTableName('listmessage'),
                $this->id
            ));


        # attachments
        $attachments = $this->getAttachments();
        foreach($attachments as $attachment){
            $attachment->id = 0;
            if(is_file($attachment->remotefile)){
                $attachment->file = '';
            }
            $new_message->addAttachment($attachment);
        }
        Logger::logEvent("Message {$this->id} was successfully rescheduled as message {$new_message->id}");
        ## remember we duplicated, in order to avoid doing it again (eg when requeuing)
        $this->setDataItem('repeatedid', $new_message->id);
    }

    /**
     * @param $date
     * @return bool
     */
    private function excludedDateForRepetition($date) {
        if (Config::get('repeat_exclude', false) !== false){
            return false;
        }
        foreach (Config::get('repeat_exclude') as $exclusion) {
            $formatted_value = phpList::DB()->fetchRowQuery(sprintf(
                    'SELECT date_format("%s","%s")',
                    $date,
                    $exclusion['format']
                ));
            foreach ($exclusion['values'] as $disallowed) {
                if ($formatted_value[0] == $disallowed) {
                    return true;
                }
            }
        }
        return false;
    }

    /*
     * Cannot find a reference to created variables in phpList, so marked as useless
    public function EstimateSize(){
        if (is_array($this->GetDataItem('excludelist')) && sizeof($this->GetDataItem('excludelist'))) {
            $exclude = sprintf(' AND listuser.listid NOT IN (%s)', implode(',', $this->GetDataItem('excludelist')));
        } else {
            $exclude = '';
        }

        $htmlcnt = phpList::DB()->Sql_Fetch_Row_Query(sprintf('SELECT COUNT(DISTINCT userid) FROM %s listuser,%s u WHERE u.htmlemail AND u.id = listuser.userid AND listuser.listid IN (%s) %s',
            Config::GetTableName('listuser'), Config::GetTableName('user'), join(',', array_keys($lists)), $exclude), 1);
        $textcnt = phpList::DB()->Sql_Fetch_Row_Query(sprintf('SELECT COUNT(DISTINCT userid) FROM %s listuser,%s user WHERE !user.htmlemail AND user.id = listuser.userid AND listuser.listid IN (%s) %s',
            Config::GetTableName('listuser'), Config::GetTableName('user'), join(',', array_keys($lists)), $exclude), 1);
    }*/

} 