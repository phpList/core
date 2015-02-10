<?php
namespace phpList;

use Exception;
use phpList\entities\CampaignEntity;
use phpList\entities\MailingListEntity;
use phpList\helper\String;
use phpList\helper\Util;

class Campaign
{
    protected $config;
    protected $db;
    protected $mailing_list;
    protected $template;

    /**
     * @param Config $config
     * @param helper\Database $db
     * @param MailingList $mailing_list
     * @param Template $template
     */
    public function __construct(Config $config, helper\Database $db, MailingList $mailing_list, Template $template)
    {
        $this->config = $config;
        $this->db = $db;
        $this->mailing_list = $mailing_list;
        $this->template = $template;

    }

    /**
     * Create a campaign object from an array from the database
     * @param $array
     * @return CampaignEntity
     */
    private function campaignFromArray($array)
    {
        $campaign = null;
        if(is_array($array)){
            $campaign = new CampaignEntity();
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
            $campaign->subscriberselection = $array['userselection'];
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
        //load additional campaign data
        $this->loadCampaignData($campaign);

        return $campaign;
    }

    /**
     * Get a campaign by id (for an owner if provided)
     * @param int $id
     * @param int $owner
     * @return CampaignEntity
     */
    public function getCampaign($id, $owner = 0)
    {
        $condition = '';
        if ($owner != 0) {
            $condition = sprintf(' AND owner = %d', $owner);
        }
        $result = $this->db->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d %s',
                $this->config->getTableName('message'),
                $id,
                $condition
            )
        );
        return $this->campaignFromArray($result->fetch(\PDO::FETCH_ASSOC));
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
     * @return array CampaignEntity
     */
    public function getCampaignsBy($status, $subject = '', $owner = 0, $order = '', $offset = 0, $limit = 0)
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

        $result = $this->db->query(
            sprintf(
                'SELECT COUNT(*) FROM %
                WHERE %s %s',
                $this->config->getTableName('message'),
                $condition,
                $sortBySql
            )
        );
        $return['total'] = $result->fetch();

        $result = $this->db->query(
            sprintf(
                'SELECT * FROM %s
                WHERE %s %s
                LIMIT %d
                OFFSET %d',
                $this->config->getTableName('message'),
                $condition,
                $sortBySql,
                $limit,
                $offset
            )
        );

        while ($msg = $result->fetch(\PDO::FETCH_ASSOC)) {
            $return['messages'][] = $this->campaignFromArray($msg);
        }

        return $return;
    }

    /**
     * Get the campaigns that need to be processed
     * @return array Campaign
     */
    public function getCampaignsToQueue()
    {
        $campaignlimit = '';
        ## limit the number of campaigns to work on
        if (is_numeric($this->config->get('MAX_PROCESS_MESSAGE'))) {
            $campaignlimit = sprintf(' limit %d ', $this->config->get('MAX_PROCESS_MESSAGE'));
        }
        $campaigns = array();
        $result = $this->db->query(
            sprintf(
                'SELECT id FROM %s
                WHERE status NOT IN ("draft", "sent", "prepared", "suspended")
                AND embargo < CURRENT_TIMESTAMP
                ORDER BY entered',
                $this->config->getTableName('message'),
                $campaignlimit
            )
        );
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $campaigns[] = $this->campaignFromArray($row);
        }
        return $campaigns;
    }

    /**
     * Get the number of views for this campaign
     * @param entities\CampaignEntity $campaign
     * @return int
     */
    public function getUniqueViews(CampaignEntity $campaign)
    {
        return $this->db->query(
            sprintf(
                'SELECT COUNT(userid)
                FROM %s
                WHERE viewed IS NOT NULL
                AND status = \'sent\'
                AND messageid = %d',
                $this->config->getTableName('usermessage'),
                $campaign->id
            )
        )->fetch();
    }

    /**
     * Get the number of clicks for this campaign
     * @param entities\CampaignEntity $campaign
     * @return int
     */
    public function getClicks(CampaignEntity $campaign)
    {
        return $this->db->query(
            sprintf(
                'SELECT SUM(clicked)
                FROM %s
                WHERE messageid = %d',
                $this->config->getTableName('linktrack_ml'),
                $campaign->id
            )
        )->fetch();
    }

    /**
     * Get attachments from this campaign
     * @param entities\CampaignEntity $campaign
     * @return array Attachment
     */
    public function getAttachments(CampaignEntity $campaign)
    {
        $result = $this->db->query(
            sprintf(
                'SELECT * FROM %s AS m_a, %s as a
                WHERE m_a.attachmentid = a.id
                AND m_a.messageid = %d',
                $this->config->getTableName('message_attachment'),
                $this->config->getTableName('attachment'),
                $campaign->id
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
     * @param entities\CampaignEntity $campaign
     * @return Template
     */
    public function getTemplateObject(CampaignEntity &$campaign){
        if($campaign->template_object == null){
            $campaign->template_object = $this->template->getTemplate($campaign->template);
        }
        return $campaign->template_object;
    }

    /**
     * Get lists which have this campaign
     * @param entities\CampaignEntity $campaign
     * @return array
     */
    public function listsDone(CampaignEntity $campaign)
    {
        $result = $this->db->query(
            sprintf(
                'SELECT l.* FROM %s AS lm, %s AS l
                WHERE lm.messageid = %d
                AND lm.listid = l.id',
                $this->config->getTableName('listmessage'),
                $this->config->getTableName('list'),
                $campaign->id
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
     * @param entities\CampaignEntity $campaign
     * @return array
     */
    public function listsExcluded(CampaignEntity $campaign)
    {
        $lists_excluded = array();
        if ($campaign->excludelist) {
            $result = $this->db->query(
                sprintf(
                    'SELECT * FROM %s WHERE id IN (%s)',
                    $this->config->getTableName('list'),
                    implode(',', $campaign->excludelist)
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
     * @param entities\CampaignEntity $campaign
     * @return bool
     */
    public function hasClickTrackLinks(CampaignEntity $campaign)
    {
        return preg_match('/lt\.php\?id=[\w%]{22}/', $campaign->message, $regs) ||
        preg_match('/lt\.php\?id=[\w%]{16}/', $campaign->message, $regs) ||
        ($this->config->get('CLICKTRACK_LINKMAP') && //TODO: Change this constant to use config
            (preg_match('#' . $this->config->get('CLICKTRACK_LINKMAP') . '/[\w%]{22}#', $campaign->message) ||
                preg_match('#' . $this->config->get('CLICKTRACK_LINKMAP') . '/[\w%]{16}#', $campaign->message)));
    }

    /**
     * Purge the drafts (from given owner if provided)
     * @param int $owner
     */
    public function purgeDrafts($owner)
    {
        $this->db->query(
            sprintf(
                'DELETE FROM %s
                WHERE status = "draft"
                AND (subject = "" OR subject = "(no subject)")
                AND owner = %d',
                $this->config->getTableName('message'),
                $owner
            )
        );
    }

    /**
     * Save this campaign to the database, calls update() when it already exists
     * Will be filled with default values
     * @param CampaignEntity $campaign
     * @param $owner
     */
    public function save(CampaignEntity &$campaign, $owner)
    {
        if ($campaign->id != 0) {
            $this->update($campaign);
        } else {
            $query = ' INSERT INTO %s (subject, status, entered, sendformat, embargo, repeatuntil, owner, template, tofield, replyto,footer)
                        VALUES("(no subject)", "draft", CURRENT_TIMESTAMP, "HTML", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, %s, %s, "", "", %s )';

            $query = sprintf(
                $query,
                $this->config->getTableName('message'),
                $owner,
                $this->config->get('defaultmessagetemplate'),
                $this->config->get('messagefooter')
            );
            $this->db->query($query);
            $campaign->id = $this->db->insertedId();
        }
    }

    /**
     * Update this campaign's info in the database
     * @param CampaignEntity $campaign
     */
    public function update(CampaignEntity $campaign)
    {
        $query = sprintf(
            'UPDATE %s SET subject = "%s", fromfield = "%s", tofield = "%s", replyto = "%s", embargo = "%s",
                     repeatinterval = "%s", repeatuntil = "%s", message = "%s", textmessage = "%s", footer = "%s", status = "%s",
                     htmlformatted = "%s", sendformat  =  "%s", template  =  "%s", requeueinterval = "%s", requeueuntil = "%s"
                     WHERE id = %d',
            $this->config->getTableName('message'),
            $campaign->subject,
            $campaign->fromfield,
            $campaign->tofield,
            $campaign->replyto,
            $campaign->embargo->format('Y-m-d H:i'),
            $campaign->repeatinterval,
            $campaign->repeatuntil->format('Y-m-d H:i'),
            $campaign->message,
            $campaign->textmessage,
            $campaign->footer,
            $campaign->status,
            $campaign->isHTMLFormatted() ? '1' : '0',
            $campaign->sendformat,
            $campaign->template,
            $campaign->requeueinterval,
            $campaign->requeueuntil->format('Y-m-d H:i'),
            $campaign->id
        );

        $this->db->query($query);
    }

    /**
     * Delete this campaign from the database
     * @param entities\CampaignEntity $campaign
     * @return int
     */
    public function delete(CampaignEntity $campaign)
    {
        $tables = array(
            $this->config->getTableName('message') => 'id',
            $this->config->getTableName('usermessage') => 'id',
            $this->config->getTableName('listmessage') => 'id'
        );
        return $this->db->deleteFromArray($tables, $campaign->id);
    }

    /**
     * Add an attachment to this campaign
     * @param CampaignEntity $campaign
     * @param Attachment $attachment
     * @return int
     */
    public function addAttachment(CampaignEntity $campaign, Attachment $attachment)
    {
        $this->db->query(
            sprintf(
                'INSERT INTO %s (messageid,attachmentid) VALUES(%d,%d)',
                $this->config->getTableName('message_attachment'),
                $campaign->id,
                $attachment->id
            )
        );
        return $this->db->insertedId();
    }

    /**
     * Remove an attachment from this campaign
     * @param CampaignEntity $campaign
     * @param Attachment $attachment
     */
    public function removeAttachment(CampaignEntity $campaign, Attachment $attachment)
    {
        $this->db->query(
            sprintf(
                'DELETE FROM %s
                WHERE id = %d
                AND messageid = %d',
                $this->config->getTableName('message_attachment'),
                $attachment->id,
                $campaign->id
            )
        );

        $attachment->removeIfOrphaned();
    }

    /**
     * Put the campaign back on the queue
     * @param CampaignEntity $campaign
     * @throws \Exception
     */
    public function requeue(CampaignEntity $campaign)
    {
        $result = $this->db->query(
            sprintf(
                'UPDATE %s SET status = \'submitted\', sendstart = null, embargo = "%s"
                WHERE id = %d'
            ),
            $this->config->getTableName('message'),
            $campaign->embargo->format('Y-m-d H:i'),
            $campaign->id
        );
        $success = $result->rowCount();

        # only send it again to subscribers if we are testing, otherwise only to new subscribers
        if ($this->config->get('TEST')) {
            $this->db->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE messageid = %d',
                    $this->config->getTableName('usermessage'),
                    $campaign->id
                )
            );
        }
        if ($success) {
            $this->db->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE id = %d
                    AND (name = "start_notified" OR name = "end_notified")',
                    $this->config->getTableName('messagedata'),
                    $campaign->id
                )
            );

            if ($campaign->finishsending->getTimestamp() < time()) {
                throw new Exception('This campaign is scheduled to stop sending in the past. No mails will be sent.');
            }
        } else {
            throw new Exception('Could not resend this campaign');
        }
    }

    /**
     * Check for campaigns with a requeueinterval and requeue if needed
     */
    public function checkCampaignsToRequeue()
    {
        $result = $this->db->query(
            sprintf(
                'SELECT *, embargo < now() AS inthepast FROM %s
                WHERE requeueinterval > 0
                AND requeueuntil > now()
                AND status = "sent"',
                $this->config->getTableName('message')
            )
        );
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $campaign = $this->campaignFromArray($row);
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
            $this->requeue($campaign);
        }
    }


    /**
     * Add campaign to a mailing list
     * @param CampaignEntity $campaign
     * @param $list_id
     */
    public function addToList(CampaignEntity $campaign, $list_id)
    {
        $this->db->query(
            sprintf(
                'INSERT INTO %s (messageid,listid,entered)
                VALUES(%d,%d,CURRENT_TIMESTAMP)',
                $this->config->getTableName('listmessage'),
                $campaign->id,
                $list_id
            )
        );
    }

    /**
     * Remove campaign from a mailing list
     * @param CampaignEntity $campaign
     * @param $list_id
     */
    public function removeFromList(CampaignEntity $campaign, $list_id)
    {
        $this->db->query(
            sprintf(
                'DELETE FROM %s
                WHERE messageid = "%d" AND listid = "%d"',
                $this->config->getTableName('listmessage'),
                $campaign->id,
                $list_id
            )
        );
    }

    /**
     * Update the status of a campaign
     * @param CampaignEntity $campaign
     * @param string $status
     */
    public function setStatus(CampaignEntity $campaign, $status)
    {
        switch($status){
            case 'inprocess':
                $this->db->query(
                sprintf(
                    'UPDATE %s SET status = "inprocess", sendstart = CURRENT_TIMESTAMP
                    WHERE sendstart IS null
                    AND id = %d',
                    $this->config->getTableName('message'),
                    $campaign->id
                ));
                break;
            case 'sent':
                $this->db->query(
                sprintf(
                    'UPDATE %s set status = "sent", sent = CURRENT_TIMESTAMP
                    WHERE id = %d',
                    $this->config->getTableName('message'),
                    $campaign->id
                ));
                break;
            default:
                $this->db->query(
                    sprintf(
                        'UPDATE %s SET status = "%s"
                        WHERE id = %d',
                        $this->config->getTableName('message'),
                        $status,
                        $campaign->id
                    )
                );
                break;
        }
    }

    /**
     * Suspend a campaign from sending
     * @param CampaignEntity $campaign
     * @param int $owner_id
     * @return int
     */
    public function suspend(CampaignEntity $campaign, $owner_id)
    {
        $result = $this->db->query(
            sprintf(
                'UPDATE %s SET status = "suspended"
                WHERE id = %d
                AND (status = "inprocess" OR status = "submitted")
                AND owner = %d',
                $this->config->getTableName('message'),
                $campaign->id,
                $owner_id
            )
        );

        return $result->rowCount();
    }

    /**
     * Suspend all campaigns from sending
     * @param int $owner
     * @return int
     */
    public function suspendAll($owner)
    {
        $result = $this->db->query(
            sprintf(
                'UPDATE %s SET status = "suspended"
                WHERE (status = "inprocess"
                OR status = "submitted")
                AND owner = %d',
                $this->config->getTableName('message'),
                $owner
            )
        );

        return $result->rowCount();
    }

    /**
     * Mark campaign from provided owner as sent
     * @param entities\CampaignEntity $campaign
     * @param int $owner_id
     * @return int
     */
    public function markSent(CampaignEntity $campaign, $owner_id)
    {
        $result = $this->db->query(
            sprintf(
                'UPDATE %s SET status = "sent"
                WHERE id = %d
                AND (status = "suspended")
                AND owner = %d',
                $this->config->getTableName('message'),
                $campaign->id,
                $owner_id
            )
        );

        return $result->rowCount();
    }

    /**
     * Mark all campaigns from provided owner as sent
     * @param int $owner_id
     * @return int
     */
    public function markAllSent($owner_id)
    {
        $result = $this->db->query(
            sprintf(
                'UPDATE %s SET status = "sent"
                WHERE (status = "suspended")
                AND owner = %d',
                $this->config->getTableName('message'),
                $owner_id
            )
        );

        return $result->rowCount();
    }

    /**
     * Set a data item for this campaign
     * @param entities\CampaignEntity $campaign
     * @param string $name
     * @param string $value
     */
    public function setDataItem(CampaignEntity $campaign, $name, $value)
    {
        if ($name == 'PHPSESSID') return;
        if ($name == session_name()) return;

        //TODO: setMessagData should probably not be used to add the campaign to a list
        if ($name == 'targetlist' && is_array($value)) {
            $this->db->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE messageid = %d',
                    $this->config->getTableName('listmessage'),
                    $campaign->id
                )
            );

            if (!empty($value['all']) || !empty($value['allactive'])) {
                //TODO: configure subselect to be used?
                //TODO: remove static call
                $lists = $this->mailing_list->getAllLists();
                //$res = $this->db->Sql_query('select * from '. $GLOBALS['tables']['list']. ' '.$GLOBALS['subselect']);
                /**
                 * @var $list MailingListEntity
                 */
                foreach ($lists as $list) {
                    if ($list->active || !empty($value['all'])) {
                        $this->addToList($campaign, $list->id);
                    }
                }
            } else {
                foreach ($value as $listid => $val) {
                    $this->addToList($campaign, $listid);
                }
            }
        }
        if (is_array($value) || is_object($value)) {
            $value = 'SER:' . serialize($value);
        }

        $this->db->replaceQuery(
            $this->config->getTableName('messagedata'),
            array('id' => $campaign->id, 'name' => $name, 'data' => $value),
            array('name', 'id')
        );
    }

    /**
     * Reset this campaign's statistics
     * @param CampaignEntity $campaign
     */
    public function resetCampaignStatistics(CampaignEntity $campaign)
    {
        ## remove the "forward" entries, but only if they are for one (this) campaign
        $delete = array();
        $result = $this->db->query(
            sprintf(
                'SELECT forwardid FROM %s
                WHERE messageid = %d',
                $this->config->getTableName('linktrack_uml_click'),
                $campaign->id
            )
        );
        while ($fwdid = $result->fetch()) {
            $count = $this->db->query(
                sprintf(
                    'SELECT COUNT(*) FROM %s
                    WHERE id = %d',
                    $this->config->getTableName('linktrack_forward'),
                    $fwdid[0]
                )
            )->fetch();
            if ($count[0] < 2) {
                $delete[] = $fwdid[0];
            }
        }
        if (sizeof($delete)) {
            $this->db->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE id IN (%s)',
                    $this->config->getTableName('linktrack_forward'),
                    join(',', $delete)
                )
            );
        }

        $tables = array(
            $this->config->getTableName('linktrack_uml_click') => 'messageid',
            $this->config->getTableName('usermessage') => 'messageid'
        );
        $this->db->deleteFromArray($tables, $campaign->id);
    }

    /**
     * Exclude subscribers from given list(s) to receive this mailing
     * @param entities\CampaignEntity $campaign
     * @param string|array $list
     */
    public function excludeSubscribersOnList(CampaignEntity $campaign, $list)
    {
        if(is_array($list)){
            $where = ' WHERE listid IN (' . join(',', $list) .')';
        }else{
            $where = sprintf(' WHERE listid = %d', $list);
        }
        $result = $this->db->query(
            sprintf(
                'SELECT userid FROM %s %s',
                $this->config->getTableName('listuser'),
                $where
            )
        );
        while ($subscriber_id = $result->fetch()) {
            $this->db->query(sprintf(
                    'REPLACE INTO %s SET
                    entered = CURRENT_TIMESTAMP,
                    userid = %d,
                    messageid = %d,
                    status = "excluded"',
                    $this->config->getTableName('usermessage'),
                    $subscriber_id,
                    $campaign->id
                ));
        }
    }

    /**
     * Update the status of a campaign going out to a subscriber
     * @param entities\CampaignEntity $campaign
     * @param int $subscriber_id
     * @param string $status
     */
    public function updateSubscriberCampaignStatus(CampaignEntity $campaign, $subscriber_id, $status){
        $this->db->query(sprintf(
                'REPLACE INTO %s (entered, useris, messageid, status)
                VALUES(CURRENT_TIMESTAMP, %d, %d, "%s")',
                $this->config->getTableName('usermessage'),
                $subscriber_id,
                $campaign->id,
                $status
            ));
    }

    /**
     * Increment the processed counter
     * @param CampaignEntity $campaign
     */
    public function incrementProcessedAmount(CampaignEntity $campaign)
    {
        $this->db->query(sprintf(
                'UPDATE %s SET processed = processed + 1
                WHERE id = %d',
                $this->config->getTableName('message'),
                $campaign->id
            ));
    }

    /**
     * Duplicate a campaign and reschedule
     * @param CampaignEntity $campaign
     */
    public function repeatCampaign(CampaignEntity $campaign)
    {
        #  if (!USE_REPETITION && !USE_rss) return;

        ## do not repeat when it has already been done
        if ($campaign->repeatuntil->getTimestamp() < time() && (!empty($campaign->repeatedid) || $campaign->repeatinterval == 0)) return;

        # get the future embargo, either "repeat" minutes after the old embargo
        # or "repeat" after this very moment to make sure that we're not sending the
        # campaign every time running the queue when there's no embargo set.
        $new_embargo = $campaign->embargo->add(\DateInterval::createFromDateString($campaign->repeatinterval . ' minutes'));
        $now = (new \DateTime());
        $new_embargo2 = $now->add(\DateInterval::createFromDateString($campaign->repeatinterval . ' minutes'));
        $is_fututre = ($new_embargo->getTimestamp() > time());

        # copy the new campaign
        $new_campaign = $campaign;
        $new_campaign->id = 0;
        $this->update($new_campaign);
        //also need to copy the campaign data for this one
        $this->db->query(sprintf(
                'INSERT INTO %s(name, id, data)
                    SELECT name, %d, data
                    FROM %s
                    WHERE id = %d',
                $this->config->getTableName('messagedata'),
                $new_campaign->id, /*New campaign id to copy to*/
                $this->config->getTableName('messagedata'),
                $campaign->id
            ));

        # check whether the new embargo is not on an exclusion
        if ($this->config->get('repeat_exclude', false) !== false) {
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
                    //TODO: remove static call
                    phpList::log()->notice('Unable to find new embargo date too many exclusions? for campaign ' . $new_campaign->id);
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
        $this->update($new_campaign);



        # lists
        $this->db->query(sprintf(
                'INSERT INTO %s(messageid,listid,entered)
                    SELECT %d, listid, CURRENT_TIMESTAMP
                    FROM %s
                    WHERE messageid = %d',
                $this->config->getTableName('listmessage'),
                $new_campaign->id, /*New campaign id to copy to*/
                $this->config->getTableName('listmessage'),
                $campaign->id
            ));


        # attachments
        $attachments = $this->getAttachments($campaign);
        foreach($attachments as $attachment){
            $attachment->id = 0;
            if(is_file($attachment->remotefile)){
                $attachment->file = '';
            }
            $this->addAttachment($new_campaign, $attachment);
        }
        //TODO: remove static call
        phpList::log()->notice("Campaign {$campaign->id} was successfully rescheduled as campaign {$new_campaign->id}");
        ## remember we duplicated, in order to avoid doing it again (eg when requeuing)
        $this->setDataItem($campaign, 'repeatedid', $new_campaign->id);
    }

    /**
     * @param $date
     * @return bool
     */
    private function excludedDateForRepetition($date) {
        if ($this->config->get('repeat_exclude', false) !== false){
            return false;
        }
        foreach ($this->config->get('repeat_exclude') as $exclusion) {
            $formatted_value = $this->db->query(sprintf(
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

    /**
     * Load additional data for this campaign
     * @param CampaignEntity $campaign
     */
    private function loadCampaignData(CampaignEntity &$campaign)
    {
        $default = array(
            ## can add some more from below
            'google_track' => $this->config->get('always_add_googletracking')
        );

        ## when loading an old campaign that hasn't got data stored in campaign data, load it from the campaign table
        $prevMsgData = $this->db->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                $this->config->getTableName('message'),
                $campaign->id
            )
        )->fetch(\PDO::FETCH_ASSOC);


        $campaigndata = array(
            'template' => $this->config->get('defaultmessagetemplate'),
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
            'finishsending' => date_create(time() + $this->config->get('DEFAULT_MESSAGEAGE')),
            'fromfield' => '',
            'subject' => '',
            'forwardsubject' => '',
            'footer' => $this->config->get('messagefooter'),
            'forwardfooter' => $this->config->get('forwardfooter'),
            'status' => '',
            'tofield' => '',
            'replyto' => '',
            'targetlist' => '',
            'criteria_match' => '',
            'sendurl' => '',
            'sendmethod' => 'inputhere', ## make a config
            'testtarget' => '',
            'notify_start' => $this->config->get('notifystart_default'),
            'notify_end' => $this->config->get('notifyend_default'),
            'google_track' => $default['google_track'] == 'true' || $default['google_track'] === true || $default['google_track'] == '1',
            'excludelist' => array(),
        );
        if (is_array($prevMsgData)) {
            foreach ($prevMsgData as $key => $val) {
                $campaigndata[$key] = $val;
            }
        }

        $result = $this->db->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                $this->config->getTableName('messagedata'),
                $campaign->id
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

        $result = $this->db->query(
            sprintf(
                'SELECT list.name,list.id
                FROM %s AS listmessage, %s AS list
                WHERE listmessage.messageid = %d
                AND listmessage.listid = list.id',
                $this->config->getTableName('listmessage'),
                $this->config->getTableName('list'),
                $campaign->id
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
            $this->config->get('message_from_address', $this->config->get('admin_address'))
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

        $campaign->campaigndata = $campaigndata;
    }

} 