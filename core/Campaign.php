<?php
namespace phpList;


use phpList\helper\Logger;
use phpList\helper\String;
use phpList\helper\Util;

class Campaign
{
    public $id = 0;
    public $subject;
    public $fromfield;
    public $tofield;
    public $replyto;
    public $message;
    public $textcampaign;
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
    public $subscriberselection;
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
    public $campaigndata;

    //non database variables
    public $embargo_in_past;
    private $template_object;

    public $lists = array();

    public function __construct()
    {
    }

    /**
     * Create a campaign object from an array from the database
     * @param $array
     * @return Campaign
     */
    private static function campaignFromArray($array)
    {
        $campaign = null;
        if(is_array($array)){
            $campaign = new Campaign();
            $campaign->id = $array['id'];
            $campaign->subject = $array['subject'];
            $campaign->fromfield = $array['fromfield'];
            $campaign->tofield = $array['tofield'];
            $campaign->replyto = $array['replyto'];
            $campaign->message = $array['message'];
            $campaign->textcampaign = $array['textmessage'];
            $campaign->footer = $array['footer'];
            $campaign->entered = new \DateTime($array['entered']);
            $campaign->modified = $array['modified'];
            $campaign->embargo = new \DateTime($array['embargo']);
            $campaign->repeatinterval = $array['repeatinterval'];
            $campaign->repeatuntil = new \DateTime($array['repeatuntil']);
            $campaign->requeueinterval = $array['requeueinterval'];
            $campaign->requeueuntil = $array['requeueuntil'];
            $campaign->status = $array['status'];
            $campaign->userselection = $array['userselection'];
            $campaign->sent = new \DateTime($array['sent']);
            $campaign->htmlformatted = $array['htmlformatted'];
            $campaign->sendformat = $array['sendformat'];
            $campaign->template = $array['template'];
            $campaign->processed = $array['processed'];
            $campaign->astext = $array['astext'];
            $campaign->ashtml = $array['ashtml'];
            $campaign->astextandhtml = $array['astextandhtml'];
            $campaign->aspdf = $array['aspdf'];
            $campaign->astextandpdf = $array['astextandpdf'];
            $campaign->viewed = $array['viewed'];
            $campaign->bouncecount = $array['bouncecount'];
            $campaign->sendstart = new \DateTime($array['sendstart']);
            $campaign->rsstemplate = $array['rsstemplate'];
            $campaign->owner = $array['owner'];
            $campaign->embargo_in_past = isset($array['inthepast']) ? $array['inthepast'] : false;
        }
        return $campaign;
    }

    /**
     * Get a campaign by id (for an owner if provided)
     * @param int $id
     * @param int $owner
     * @return Campaign
     */
    public static function getCampaign($id, $owner = 0)
    {
        $condition = '';
        if ($owner != 0) {
            $condition = sprintf(' AND owner = %d', $owner);
        }
        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d %s',
                Config::getTableName('message'),
                $id,
                $condition
            )
        );
        return Campaign::campaignFromArray($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Get an array of Campaigns by searching its status and subject
     * When $owner is provided, only returns the campaigns for the given owner
     * @param string|array $status
     * @param string $subject
     * @param int $owner
     * @param string $order
     * @param int $offset
     * @param int $limit
     * @return array Campaign
     */
    public static function getCampaignsBy($status, $subject = '', $owner = 0, $order = '', $offset = 0, $limit = 0)
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

        $result = phpList::DB()->query(
            sprintf(
                'SELECT COUNT(*) FROM %
                WHERE %s %s',
                Config::getTableName('message'),
                $condition,
                $sortBySql
            )
        );
        $return['total'] = $result->fetch();

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

        while ($msg = $result->fetch(\PDO::FETCH_ASSOC)) {
            $return['messages'][] = Campaign::campaignFromArray($msg);
        }

        return $return;
    }

    /**
     * Get the campaigns that need to be processed
     * @return array Campaign
     */
    public static function getCampaignsToQueue()
    {
        $campaignlimit = '';
        ## limit the number of campaigns to work on
        if (is_numeric(Config::MAX_PROCESS_MESSAGE)) {
            $campaignlimit = sprintf(' limit %d ', Config::MAX_PROCESS_MESSAGE);
        }
        $campaigns = array();
        $result = phpList::DB()->query(
            sprintf(
                'SELECT id FROM %s
                WHERE status NOT IN ("draft", "sent", "prepared", "suspended")
                AND embargo < CURRENT_TIMESTAMP
                ORDER BY entered',
                Config::getTableName('message'),
                $campaignlimit
            )
        );
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $campaigns[] = Campaign::campaignFromArray($row);
        }
        return $campaigns;
    }

    /**
     * Get the number of views for this campaign
     * @return int
     */
    public function getUniqueViews()
    {
        return phpList::DB()->query(
            sprintf(
                'SELECT COUNT(userid)
                FROM %s
                WHERE viewed IS NOT NULL
                AND status = \'sent\'
                AND messageid = %d',
                Config::getTableName('usermessage'),
                $this->id
            )
        )->fetch();
    }

    /**
     * Get the number of clicks for this campaign
     * @return int
     */
    public function getClicks()
    {
        return phpList::DB()->query(
            sprintf(
                'SELECT SUM(clicked)
                FROM %s
                WHERE messageid = %d',
                Config::getTableName('linktrack_ml'),
                $this->id
            )
        )->fetch();
    }

    /**
     * Get attachments from this campaign
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
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $attachment = new Attachment($row['filename'], $row['remotefile'], $row['mimetype'], $row['description'], $row['size']);
            $attachment->id = $row['id'];
            $attachments[] = $attachment;
        }

        return $attachments;
    }

    /**
     * Get the template for this campaign if set
     * @return Template
     */
    public function getTemplateObject(){
        if($this->template_object == null){
            $this->template_object = Template::getTemplate($this->template);
        }
        return $this->template_object;
    }



    /**
     * Does the campaign contain HTML formatting
     * @return bool
     */
    public function isHTMLFormatted()
    {
        return strip_tags($this->message) != $this->message;
    }

    /**
     * Get lists which have this campaign
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
        while ($lst = $result->fetch(\PDO::FETCH_ASSOC)) {
            $lists_done[] = $lst;
        }

        return $lists_done;
    }

    /**
     * Get lists which are excluded to send this campaign to
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

            while ($lst = $result->fetch(\PDO::FETCH_ASSOC)) {
                $lists_done[] = $lst;
            }
        }

        return $lists_excluded;
    }

    /**
     * Does the campaign contain Clicktrack links
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
     * Save this campaign to the database, calls update() when it already exists
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
     * Update this campaign's info in the database
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
     * Delete this campaign from the database
     * @return int
     */
    public function delete()
    {
        $tables = array(
            Config::getTableName('message') => 'id',
            Config::getTableName('usermessage') => 'id',
            Config::getTableName('listmessage') => 'id'
        );
        return phpList::DB()->deleteFromArray($tables, $this->id);
    }

    /**
     * Add an attachment to this campaign
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
     * Remove an attachment from this campaign
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
     * Put the campaign back on the queue
     * @throws \Exception
     */
    public function requeue()
    {
        $result = phpList::DB()->query(
            sprintf(
                'UPDATE %s SET status = \'submitted\', sendstart = null, embargo = "%s"
                WHERE id = %d'
            ),
            Config::getTableName('message'),
            $this->embargo->format('Y-m-d H:i'),
            $this->id
        );
        $success = $result->rowCount();

        # only send it again to subscribers if we are testing, otherwise only to new subscribers
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
        if ($success) {
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
            throw new \Exception('Could not resend this campaign');
        }
    }

    /**
     * Check for campaigns with a requeueinterval and requeue if needed
     */
    public static function checkCampaignsToRequeue()
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT *, embargo < now() AS inthepast FROM %s
                WHERE requeueinterval > 0
                AND requeueuntil > now()
                AND status = "sent"',
                Config::getTableName('message')
            )
        );
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $campaign = Campaign::campaignFromArray($row);
            if ($campaign->embargo_in_past) {
                $now = new \DateTime('now');
                $campaign->embargo = $now->add(
                    \DateInterval::createFromDateString($campaign->repeatinterval . 'minutes')
                );
            } else {
                $campaign->embargo->add(
                    \DateInterval::createFromDateString($campaign->repeatinterval . 'minutes')
                );
            }
            $campaign->requeue();
        }
    }


    /**
     * Add campaign to a mailing list
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
     * Update the status of a campaign
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
     * Suspend a campaign from sending
     * @param int $owner
     * @return int
     */
    public function suspend($owner)
    {
        $result = phpList::DB()->query(
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

        return $result->rowCount();
    }

    /**
     * Suspend all campaigns from sending
     * @param int $owner
     * @return int
     */
    public static function suspendAll($owner)
    {
        $result = phpList::DB()->query(
            sprintf(
                'UPDATE %s SET status = "suspended"
                WHERE (status = "inprocess"
                OR status = "submitted")
                AND owner = %d',
                Config::getTableName('message'),
                $owner
            )
        );

        return $result->rowCount();
    }

    /**
     * Mark campaign from provided owner as sent
     * @param int $owner
     * @return int
     */
    public function markSent($owner)
    {
        $result = phpList::DB()->query(
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

        return $result->rowCount();
    }

    /**
     * Mark all campaigns from provided owner as sent
     * @param int $owner
     * @return int
     */
    public static function markAllSent($owner)
    {
        $result = phpList::DB()->query(
            sprintf(
                'UPDATE %s SET status = "sent"
                WHERE (status = "suspended")
                AND owner = %d',
                Config::getTableName('message'),
                $owner
            )
        );

        return $result->rowCount();
    }

    /**
     * Get a campaigndata item
     * @param string $item
     * @return mixed
     * @throws \Exception
     */
    public function __get($item)
    {
        if (isset($this->campaigndata[$item])) {
            return $this->campaigndata[$item];
        }

        $default = array(
            ## can add some more from below
            'google_track' => Config::get('always_add_googletracking')
        );

        ## when loading an old campaign that hasn't got data stored in campaign data, load it from the campaign table
        $prevMsgData = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('message'),
                $this->id
            )
        )->fetch(\PDO::FETCH_ASSOC);


        $campaigndata = array(
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
                $campaigndata[$key] = $val;
            }
        }

        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('messagedata'),
                $this->id
            )
        );

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
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
            ) { ## don't overwrite counters in the campaign table from the data table
                $campaigndata[stripslashes($row['name'])] = $data;
            }
        }

        /*Is a DateTime object now
         * foreach (array('embargo','repeatuntil','requeueuntil') as $datefield) {
            if (!is_array($campaigndata[$datefield])) {
                $campaigndata[$datefield] = array('year' => date('Y'),'month' => date('m'),'day' => date('d'),'hour' => date('H'),'minute' => date('i'));
            }
        }*/

        // Load lists that were targetted with campaign...

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

        while ($lst = $result->fetch(\PDO::FETCH_ASSOC)) {
            $campaigndata['targetlist'][$lst['id']] = 1;
        }

        ## backwards, check that the content has a url and use it to fill the sendurl
        if (empty($campaigndata['sendurl'])) {

            ## can't do "ungreedy matching, in case the URL has placeholders, but this can potentially
            ## throw problems
            if (preg_match('/\[URL:(.*)\]/i', $campaigndata['message'], $regs)) {
                $campaigndata['sendurl'] = $regs[1];
            }
        }
        if (empty($campaigndata['sendurl']) && !empty($campaigndata['message'])) {
            # if there's a message and no url, make sure to show the editor, and not the URL input
            $campaigndata['sendmethod'] = 'inputhere';
        }

        ### parse the from field into it's components - email and name
        $parsed_email = Util::parseEmailAndName(
                            $campaigndata['fromname'],
                            Config::get('message_from_address', Config::get('admin_address'))
                        );

        $campaigndata['fromname'] = $parsed_email['name'];
        $campaigndata['fromemail'] = $parsed_email['email'];

        ## if the name ends up being empty, copy the email
        if (empty($campaigndata['fromname'])) {
            $campaigndata['fromname'] = $campaigndata['fromemail'];
        }

        if (!empty($campaigndata['targetlist']['unselect'])) {
            unset($campaigndata['targetlist']['unselect']);
        }
        if (!empty($campaigndata['excludelist']['unselect'])) {
            unset($campaigndata['excludelist']['unselect']);
        }

        $this->campaigndata = $campaigndata;

        if (isset($this->campaigndata[$item])) {
            return $this->campaigndata[$item];
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
        if (!isset($this->campaigndata[$item])) {
            //try one last time to load from db
            return ($this->__get($item) != null && $this->__get($item) != '');
        }else{
            return true;
        }
    }

    /**
     * Set a temporary campaign data item
     * Only used in current object context
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->campaigndata[$name] = $value;
    }

    /**
     * Set a data item for this campaign
     * @param string $name
     * @param string $value
     */
    public function setDataItem($name, $value)
    {
        if ($name == 'PHPSESSID') return;
        if ($name == session_name()) return;

        //TODO: setMessagData should probably not be used to add the campaign to a list
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
     * Reset this campaign's statistics
     */
    public function resetCampaignStatistics()
    {
        ## remove the "forward" entries, but only if they are for one (this) campaign
        $delete = array();
        $result = phpList::DB()->query(
            sprintf(
                'SELECT forwardid FROM %s
                WHERE messageid = %d',
                Config::getTableName('linktrack_uml_click'),
                $this->id
            )
        );
        while ($fwdid = $result->fetch()) {
            $count = phpList::DB()->query(
                sprintf(
                    'SELECT COUNT(*) FROM %s
                    WHERE id = %d',
                    Config::getTableName('linktrack_forward'),
                    $fwdid[0]
                )
            )->fetch();
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
     * Exclude subscribers from given list(s) to receive this mailing
     * @param string|array $list
     */
    public function excludeSubscribersOnList($list)
    {
        //could do MailingList::getListSubscribers, but might be slower on big lists
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
        while ($subscriber_id = $result->fetch()) {
            phpList::DB()->query(sprintf(
                    'REPLACE INTO %s SET
                    entered = CURRENT_TIMESTAMP,
                    userid = %d,
                    messageid = %d,
                    status = "excluded"',
                    Config::getTableName('usermessage'),
                    $subscriber_id,
                    $this->id
                ));
        }
    }

    /**
     * Update the status of a campaign going out to a subscriber
     * @param int $subscriber_id
     * @param string $status
     */
    public function updateSubscriberCampaignStatus($subscriber_id, $status){
        phpList::DB()->query(sprintf(
                'REPLACE INTO %s (entered, useris, messageid, status)
                VALUES(CURRENT_TIMESTAMP, %d, %d, "%s")',
                Config::getTableName('usermessage'),
                $subscriber_id,
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
     * Duplicate a campaign and reschedule
     */
    public function repeatCampaign()
    {
        #  if (!USE_REPETITION && !USE_rss) return;

        ## do not repeat when it has already been done
        if ($this->repeatuntil->getTimestamp() < time() && (!empty($this->repeatedid) || $this->repeatinterval == 0)) return;

        # get the future embargo, either "repeat" minutes after the old embargo
        # or "repeat" after this very moment to make sure that we're not sending the
        # campaign every time running the queue when there's no embargo set.
        $new_embargo = $this->embargo->add(\DateInterval::createFromDateString($this->repeatinterval . ' minutes'));
        $now = (new \DateTime());
        $new_embargo2 = $now->add(\DateInterval::createFromDateString($this->repeatinterval . ' minutes'));
        $is_fututre = ($new_embargo->getTimestamp() > time());

        # copy the new campaign
        $new_campaign = $this;
        $new_campaign->id = 0;
        $new_campaign->update();
        //also need to copy the campaign data for this one
        phpList::DB()->query(sprintf(
                'INSERT INTO %s(name, id, data)
                    SELECT name, %d, data
                    FROM %s
                    WHERE id = %d',
                Config::getTableName('messagedata'),
                $new_campaign->id, /*New campaign id to copy to*/
                Config::getTableName('messagedata'),
                $this->id
            ));

        # check whether the new embargo is not on an exclusion
        if (Config::get('repeat_exclude', false) !== false) {
            $loopcnt = 0;
            $repeatinterval = 0;
            while ($this->excludedDateForRepetition($new_embargo)) {
                $repeatinterval += $new_campaign->repeatinterval;
                $loopcnt++;
                $new_embargo = $new_campaign->embargo->add(\DateInterval::createFromDateString($repeatinterval . ' minutes'));
                $now = (new \DateTime());
                $new_embargo2 = $now->add(\DateInterval::createFromDateString($repeatinterval . ' minutes'));
                $is_fututre = ($new_embargo->getTimestamp() > time());

                if ($loopcnt > 15) {
                    Logger::logEvent('Unable to find new embargo date too many exclusions? for campaign ' . $new_campaign->id);
                    return;
                }
            }
        }
        # correct some values
        if (!$is_fututre) {
            $new_embargo = $new_embargo2;
        }

        $new_campaign->embargo = $new_embargo;
        $new_campaign->status = 'submitted';
        $new_campaign->sent = '';
        foreach (array("processed","astext","ashtml","astextandhtml","aspdf","astextandpdf","viewed", "bouncecount") as $item) {
            $new_campaign->$item = 0;
        }
        $new_campaign->update();



        # lists
        phpList::DB()->query(sprintf(
                'INSERT INTO %s(messageid,listid,entered)
                    SELECT %d, listid, CURRENT_TIMESTAMP
                    FROM %s
                    WHERE messageid = %d',
                Config::getTableName('listmessage'),
                $new_campaign->id, /*New campaign id to copy to*/
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
            $new_campaign->addAttachment($attachment);
        }
        Logger::logEvent("Campaign {$this->id} was successfully rescheduled as campaign {$new_campaign->id}");
        ## remember we duplicated, in order to avoid doing it again (eg when requeuing)
        $this->setDataItem('repeatedid', $new_campaign->id);
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
            $formatted_value = phpList::DB()->query(sprintf(
                    'SELECT date_format("%s","%s")',
                    $date,
                    $exclusion['format']
                ))->fetch();
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