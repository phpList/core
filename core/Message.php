<?php
/**
 * User: SaWey
 * Date: 5/12/13
 */

namespace phpList;


class Message {
    public $id;
    public $subject;
    public $fromfield;
    public $tofield;
    public $replyto;
    public $message;
    public $textmessage;
    public $footer;
    public $entered;
    public $modified;
    public $embargo;
    public $repeatinterval;
    public $repeatuntil;
    public $requeueinterval;
    public $requeueuntil;
    public $status;
    public $userselection;
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
    public $sendstart;
    public $rsstemplate;
    public $owner;
    public $messagedata;

    public $lists = array();

    public function __construct(){}

    private static function messageFromArray($array){
        $message = new Message();
        $message->id = $array['id'];
        $message->subject = $array['subject'];
        $message->fromfield = $array['fromfield'];
        $message->tofield = $array['tofield'];
        $message->replyto = $array['replyto'];
        $message->message = $array['message'];
        $message->textmessage = $array['textmessage'];
        $message->footer = $array['footer'];
        $message->entered = $array['entered'];
        $message->modified = $array['modified'];
        $message->embargo = $array['embargo'];
        $message->repeatinterval = $array['repeatinterval'];
        $message->repeatuntil = $array['repeatuntil'];
        $message->requeueinterval = $array['requeueinterval'];
        $message->requeueuntil = $array['requeueuntil'];
        $message->status = $array['status'];
        $message->userselection = $array['userselection'];
        $message->sent = $array['sent'];
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
        $message->sendstart = $array['sendstart'];
        $message->rsstemplate = $array['rsstemplate'];
        $message->owner = $array['owner'];
        return $message;
    }

    public static function getMessage($id, $owner = 0){
        $condition = '';
        if($owner != 0){
            $condition = sprintf(' AND owner = %d', $owner);
        }
        $result = phpList::DB()->Sql_Fetch_Assoc_Query(sprintf(
            'SELECT * FROM %s
            WHERE id = %d %s',
            Config::getTableName('message'), $id, $condition));
        return Message::messageFromArray($result);
    }

    public static function getMessagesBy($status, $subject = '', $owner = 0, $order = '', $offset = 0, $limit = 0){
        $return = array();

        $condition = 'status IN (';
        $condition .= is_array($status) ? implode(',', $status) : $status;
        $condition .= ') ';

        if($subject != ''){
            $condition .= ' AND subject LIKE "%'.phpList::DB()->Sql_Escape($subject).'%" ';
        }
        if($owner != 0){
            $condition .= sprintf(' AND owner = %d', $owner);
        }

        $sortBySql = '';
        switch ($order) {
            case 'sentasc': $sortBySql = ' ORDER BY sent ASC'; break;
            case 'sentdesc': $sortBySql = ' ORDER BY sent DESC'; break;
            case 'subjectasc': $sortBySql = ' ORDER BY subject ASC'; break;
            case 'subjectdesc': $sortBySql = ' ORDER BY subject DESC'; break;
            case 'enteredasc': $sortBySql = ' ORDER BY entered ASC'; break;
            case 'entereddesc': $sortBySql = ' ORDER BY entered DESC'; break;
            case 'embargoasc': $sortBySql = ' ORDER BY embargo ASC'; break;
            case 'embargodesc': $sortBySql = ' ORDER BY embargo DESC'; break;
            default:
                $sortBySql = ' ORDER BY embargo DESC, entered DESC';
        }

        $req = phpList::DB()->Sql_query(sprintf(
            'SELECT COUNT(*) FROM %
            WHERE %s %s',
            Config::getTableName('message'), $condition, $sortBySql));
        $return['total'] = phpList::DB()->Sql_Fetch_Row($req);

        $result = phpList::DB()->Sql_query(sprintf(
            'SELECT * FROM %s
            WHERE %s %s
            LIMIT %d
            OFFSET %d',
            Config::getTableName('message'), $condition, $sortBySql, $limit, $offset));

        while ($msg = phpList::DB()->Sql_Fetch_Assoc($result)) {
            $return['messages'][] = Message::messageFromArray($msg);
        }

        return $return;
    }

    public function uniqueViews(){
        return phpList::DB()->Sql_Fetch_Row_Query(sprintf(
            'SELECT COUNT(userid)
            FROM %s
            WHERE viewed IS NOT NULL
            AND status = \'sent\'
            AND messageid = %d',
            Config::getTableName('usermessage'), $this->id));
    }

    public function clicks(){
        return phpList::DB()->Sql_Fetch_Row_Query(sprintf(
            'SELECT SUM(clicked)
            FROM %s
            WHERE messageid = %d',
            Config::getTableName('linktrack_ml'), $this->id));
    }

    public function attachments(){
        $result = phpList::DB()->Sql_Query(sprintf(
            'SELECT * FROM %s AS m_a, %s as a
            WHERE m_a.attachmentid = a.id
            AND m_a.messageid = ',
            Config::getTableName('message_attachment'), Config::getTableName('attachment'), $this->id));

        $attachments = array();
        while($row = phpList::DB()->Sql_Fetch_Row($result)){
            array_push($attachments,(object)$row[0]);
        }

        return $attachments;
    }

    public function isHTMLFormatted(){
        return strip_tags($this->message) != $this->message;
    }

    public function listsDone(){
        $result = phpList::DB()->Sql_Query(sprintf(
            'SELECT l.* FROM %s AS lm, %s AS l
            WHERE lm.messageid = %d
            AND lm.listid = l.id',
            Config::getTableName('listmessage'), Config::getTableName('list'), $this->id));

        $lists_done = array();
        while ($lst = phpList::DB()->Sql_Fetch_Row($result)) {
            array_push($lists_done,$lst);
        }

        return $lists_done;
    }

    public function listsExcluded(){
        $lists_excluded = array();
        if($this->getDataItem('excludelist')){
            $result = phpList::DB()->Sql_Query(sprintf('SELECT * FROM %s WHERE id IN (%s)',
                Config::getTableName('list'), Config::getTableName('list'), implode(',',$this->getDataItem('excludelist'))));

            while ($lst = phpList::DB()->Sql_Fetch_Row($result)) {
                array_push($lists_done,$lst);
            }
        }

        return $lists_excluded;
    }

    public function hasClickTrackLinks(){
        return preg_match('/lt\.php\?id=[\w%]{22}/', $this->message, $regs) ||
                preg_match('/lt\.php\?id=[\w%]{16}/', $this->message, $regs) ||
                    (CLICKTRACK_LINKMAP && //TODO: Change this constant to use config
                        (preg_match('#' . CLICKTRACK_LINKMAP . '/[\w%]{22}#', $this->message) ||
                            preg_match('#' . CLICKTRACK_LINKMAP . '/[\w%]{16}#', $this->message)));
    }


    public static function purgeDrafts($owner){
        $todelete = array();

        $ownerselect_and = sprintf(' AND owner = %d',$owner);
        $result = phpList::DB()->Sql_Query(sprintf(
            'SELECT * FROM %s
            WHERE status = "draft"
            AND (subject = "" OR subject = "(no subject)")
            %s',
            Config::getTableName('message'),$ownerselect_and));
        while($row = phpList::DB()->Sql_Fetch_Row($result)){
            array_push($todelete,$row[0]);
        }

        foreach ($todelete as $delete) {
            (object) $delete->Delete();
        }
    }

    public function create($owner){
        $query = ' INSERT INTO %s (subject, status, entered, sendformat, embargo, repeatuntil, owner, template, tofield, replyto,footer)
                    VALUES("(no subject)", "draft", CURRENT_TIMESTAMP, "HTML", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, %s, %s, "", "", %s )';

        $query = sprintf($query, Config::getTableName('message'), $owner, Config::fromDB('defaultmessagetemplate'), Config::fromDB('messagefooter'));
        phpList::DB()->Sql_Query($query);
        $this->id = phpList::DB()->Sql_Insert_Id();
    }

    public function save(){
        $embargo = $this->getDataItem('embargo');
        $repeatuntil = $this->getDataItem('repeatuntil');
        $requeueuntil = $this->getDataItem('requeueuntil');

        $query = sprintf('UPDATE %s SET subject = "%s", fromfield = "%s", tofield = "%s", replyto = "%s", embargo = "%s",
         repeatinterval = "%s", repeatuntil = "%s", message = "%s", textmessage = "%s", footer = "%s", status = "%s",
         htmlformatted = "%s", sendformat  =  "%s", template  =  "%s", requeueinterval = "%s", requeueuntil = "%s"
         WHERE id = %d',
            Config::getTableName('message'),
            $this->subject,
            $this->fromfield,
            $this->tofield,
            $this->replyto,
            sprintf('%04d-%02d-%02d %02d:%02d',
                $embargo['year'], $embargo['month'], $embargo['day'], $embargo['hour'], $embargo['minute']),
            $this->repeatinterval,
            sprintf('%04d-%02d-%02d %02d:%02d',
                $repeatuntil['year'], $repeatuntil['month'], $repeatuntil['day'], $repeatuntil['hour'], $repeatuntil['minute']),
            $this->message,
            $this->textmessage,
            $this->footer,
            $this->status,
            $this->isHTMLFormatted() ? '1' : '0',
            $this->sendformat,
            $this->template,
            $this->requeueinterval,
            sprintf('%04d-%02d-%02d %02d:%02d',
                $requeueuntil['year'], $requeueuntil['month'], $requeueuntil['day'], $requeueuntil['hour'], $requeueuntil['minute']),
            $this->id);

        phpList::DB()->Sql_Query($query);
    }

    public function delete() {
        phpList::DB()->Sql_query(sprintf('DELETE FROM %s WHERE id = %d', Config::getTableName('message'), $this->id));
        $suc6 = phpList::DB()->Sql_Affected_Rows();

        phpList::DB()->Sql_query(sprintf('DELETE FROM %s WHERE id = %d', Config::getTableName('usermessage'), $this->id));
        phpList::DB()->Sql_query(sprintf('DELETE FROM %s WHERE id = %d', Config::getTableName('listmessage'), $this->id));

        return $suc6;
    }

    public function addAttachment($attachment){
        $attachmentid = phpList::DB()->Sql_Insert_Id();
        phpList::DB()->Sql_query(sprintf('INSERT INTO %s (messageid,attachmentid) VALUES(%d,%d)',
            Config::getTableName('message_attachment'), $this->id, $attachment->id));
    }

    public function removeAttachment($attachment){
        Sql_Query(sprintf(
            'DELETE FROM %s
            WHERE id = %d
            AND messageid = %d',
            Config::getTableName('message_attachment'), $attachment->id, $this->id));

        $attachment->RemoveIfOrphaned();
    }

    public function requeue(){
        phpList::DB()->Sql_Query(sprintf(
                'UPDATE %s SET status = \'submitted\', sendstart = null
                WHERE id = %d'),
            Config::getTableName('message'), $this->id);
        $suc6 = phpList::DB()->Sql_Affected_Rows();

        # only send it again to users if we are testing, otherwise only to new users
        if (Config::TEST)
            phpList::DB()->Sql_query(sprintf(
                'DELETE FROM %s
                WHERE messageid = %d',
                Config::getTableName('usermessage'), $this->id));
        if ($suc6) {
            phpList::DB()->Sql_Query(sprintf(
                'DELETE FROM %s
                WHERE id = %d
                AND (name = "start_notified" OR name = "end_notified")',
                Config::getTableName('messagedata'),$this->id));

            $finishSending = mktime($this->messagedata['finishsending']['hour'],
                                    $this->messagedata['finishsending']['minute'],0,
                                    $this->messagedata['finishsending']['month'],
                                    $this->messagedata['finishsending']['day'],
                                    $this->messagedata['finishsending']['year']);

            if ($finishSending < time()) {
                throw new \Exception('This campaign is scheduled to stop sending in the past. No mails will be sent.');
           }
        }else{
            throw new \Exception('Could not resend this message');
        }
    }

    public function addToList($list_id){
        phpList::DB()->Sql_query(sprintf(
            'INSERT INTO %s (messageid,listid,entered)
            VALUES(%d,%d,CURRENT_TIMESTAMP)',
            Config::getTableName('listmessage'), $this->id, $list_id));
    }

    public function setStatus($status){
        phpList::DB()->Sql_query(sprintf(
            'UPDATE %s SET status = "%s"
            WHERE id = %d',
            Config::getTableName('message'), $status, $this->id));
    }

    public function suspend($owner){
        $result = phpList::DB()->Sql_query(sprintf(
            'UPDATE %s SET status = "suspended"
            WHERE id = %d
            AND (status = "inprocess" OR status = "submitted")
            AND owner = %d',
            Config::getTableName('message'),$this->id, $owner));

        return phpList::DB()->Sql_Affected_Rows();
    }

    public static function suspendAll($owner){
        $result = phpList::DB()->Sql_query(sprintf(
            'UPDATE %s SET status = "suspended"
            WHERE (status = "inprocess"
            OR status = "submitted")
            AND owner = %d',
            Config::getTableName('message'), $owner));

        return phpList::DB()->Sql_Affected_Rows();
    }

    public function markSent($owner){
        $result = phpList::DB()->Sql_query(sprintf(
            'UPDATE %s SET status = "sent"
            WHERE id = %d
            AND (status = "suspended")
            AND owner = %d',
            Config::getTableName('message'),$this->id, $owner));

        return phpList::DB()->Sql_Affected_Rows();
    }

    public static function markAllSent($owner){
        $result = phpList::DB()->Sql_query(sprintf(
            'UPDATE %s SET status = "sent"
            WHERE (status = "suspended")
            AND owner = %d',
            Config::getTableName('message'),$owner));

        return phpList::DB()->Sql_Affected_Rows();
    }

    //TODO: something
    public function getDataItem($item){
        if(isset($this->messagedata[$item])){
            return $this->messagedata[$item];
        }

        $default = array(
            'from' => Config::fromDB('message_from_address', Config::fromDB('admin_address')),
            ## can add some more from below
            'google_track' => Config::fromDB('always_add_googletracking'),
        );

        ## when loading an old message that hasn't got data stored in message data, load it from the message table
        $prevMsgData = phpList::DB()->Sql_Fetch_Assoc_Query(sprintf(
            'SELECT * FROM %s
            WHERE id = %d',
            Config::getTableName('message'), $this->id));

        $finishSending = time() + Config::DEFAULT_MESSAGEAGE;

        $messagedata = array(
            'template' => Config::fromDB('defaultmessagetemplate'),
            'sendformat' => 'HTML',
            'message' => '',
            'forwardmessage' => '',
            'textmessage' => '',
            'rsstemplate' => '',
            'embargo' => array('year' => date('Y'),'month' => date('m'),'day' => date('d'),'hour' => date('H'),'minute' => date('i')),
            'repeatinterval' => 0,
            'repeatuntil' =>  array('year' => date('Y'),'month' => date('m'),'day' => date('d'),'hour' => date('H'),'minute' => date('i')),
            'requeueinterval' => 0,
            'requeueuntil' =>  array('year' => date('Y'),'month' => date('m'),'day' => date('d'),'hour' => date('H'),'minute' => date('i')),
            'finishsending' => array('year' => date('Y',$finishSending),'month' => date('m',$finishSending),'day' => date('d',$finishSending),'hour' => date('H',$finishSending),'minute' => date('i',$finishSending)),
            'fromfield' => '',
            'subject' => '',
            'forwardsubject' => '',
            'footer' => Config::fromDB('messagefooter'),
            'forwardfooter' => Config::fromDB('forwardfooter'),
            'status' => '',
            'tofield' => '',
            'replyto' => '',
            'targetlist' => '',
            'criteria_match' => '',
            'sendurl' => '',
            'sendmethod' => 'inputhere', ## make a config
            'testtarget' => '',
            'notify_start' =>  Config::fromDB('notifystart_default'),
            'notify_end' =>  Config::fromDB('notifyend_default'),
            'google_track' => $default['google_track'] == 'true' || $default['google_track'] === true || $default['google_track'] == '1',
            'excludelist' => array(),
        );
        if (is_array($prevMsgData)) {
            foreach ($prevMsgData as $key => $val) {
                $messagedata[$key] = $val;
            }
        }

        $msgdata_req = phpList::DB()->Sql_Query(sprintf('SELECT * FROM %s WHERE id = %d', Config::getTableName('messagedata'),$this->id));
        while ($row = phpList::DB()->Sql_Fetch_Assoc($msgdata_req)) {
            if (strpos($row['data'],'SER:') === 0) {
                $data = substr($row['data'],4);
                $data = @unserialize(stripslashes($data));
            } else {
                $data = stripslashes($row['data']);
            }
            if (!in_array($row['name'],array('astext','ashtml','astextandhtml','aspdf','astextandpdf'))) { ## don't overwrite counters in the message table from the data table
                $messagedata[stripslashes($row['name'])] = $data;
            }
        }

        foreach (array('embargo','repeatuntil','requeueuntil') as $datefield) {
            if (!is_array($messagedata[$datefield])) {
                $messagedata[$datefield] = array('year' => date('Y'),'month' => date('m'),'day' => date('d'),'hour' => date('H'),'minute' => date('i'));
            }
        }

        // Load lists that were targetted with message...
        $result = phpList::DB()->Sql_Query(sprintf('SELECT list.name,list.id
                                        FROM %s AS listmessage, %s AS list
                                        WHERE listmessage.messageid = %d
                                        AND listmessage.listid = list.id',
                                        Config::getTableName('listmessage'),
                                        Config::getTableName('list'),
                                        $this->id));

        while ($lst = Sql_fetch_array($result)) {
            $messagedata['targetlist'][$lst['id']] = 1;
        }

        ## backwards, check that the content has a url and use it to fill the sendurl
        if (empty($messagedata['sendurl'])) {

            ## can't do "ungreedy matching, in case the URL has placeholders, but this can potentially
            ## throw problems
            if (preg_match('/\[URL:(.*)\]/i',$messagedata['message'],$regs)) {
                $messagedata['sendurl'] = $regs[1];
            }
        }
        if (empty($messagedata['sendurl']) && !empty($messagedata['message'])) {
            # if there's a message and no url, make sure to show the editor, and not the URL input
            $messagedata['sendmethod'] = 'inputhere';
        }

        ### parse the from field into it's components - email and name
        if (preg_match("/([^ ]+@[^ ]+)/",$messagedata['fromfield'],$regs)) {
            # if there is an email in the from, rewrite it as "name <email>"
            $messagedata['fromname'] = str_replace($regs[0],"",$messagedata['fromfield']);
            $messagedata['fromemail'] = $regs[0];
            # if the email has < and > take them out here
            $messagedata['fromemail'] = str_replace('<','',$messagedata['fromemail']);
            $messagedata['fromemail'] = str_replace('>','',$messagedata['fromemail']);
            # make sure there are no quotes around the name
            $messagedata['fromname'] = str_replace('"','',ltrim(rtrim($messagedata['fromname'])));
        } elseif (strpos($messagedata['fromfield'],' ')) {
            # if there is a space, we need to add the email
            $messagedata['fromname'] = $messagedata['fromfield'];
            #  $cached[$messageid]['fromemail'] = 'listmaster@$domain';
            $messagedata['fromemail'] = $default['from'];
        } else {
            $messagedata['fromemail'] = $default['from'];
            $messagedata['fromname'] = $messagedata["fromfield"] ;
        }
        $messagedata["fromname"] = trim($messagedata["fromname"]);

        # erase double spacing
        while (strpos($messagedata['fromname'],'  ')) {
            $messagedata['fromname'] = str_replace('  ',' ',$messagedata['fromname']);
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

        if(!isset($this->messagedata[$item])){
            throw new \Exception('Data item not found');
        }
        return $this->messagedata[$item];
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