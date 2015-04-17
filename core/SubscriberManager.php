<?php
namespace phpList;

use phpList\Subscriber;
use phpList\Entity\SubscriberEntity;
use phpList\helper\String;

class SubscriberManager
{
    protected $config;
    protected $emailUtil;
    protected $pass;
    protected $subscriberModel;

    /**
     * Used for looping over all usable subscriber attributes
     * for replacing in messages, urls etc. (@see fetchUrl)
     * @var array
     */
    public static $DB_ATTRIBUTES = array(
        'id', 'email', 'confirmed', 'blacklisted', 'optedin', 'bouncecount',
        'entered', 'modified', 'uniqid', 'htmlemail', 'subscribepage', 'rssfrequency',
        'extradata', 'foreignkey'
    );

    /**
     * @param Config $config
     * @param helper\Database $db
     */
    public function __construct( Config $config, EmailUtil $emailUtil, Pass $pass, model\SubscriberModel $subscriberModel )
    {
        $this->config = $config;
        $this->emailUtil = $emailUtil;
        $this->pass = $pass;
        $this->subscriberModel = $subscriberModel;
    }

    /**
    * Get subscriber by id
    * @param int $id
    * @return SubscriberEntity
    */
    public function getSubscriberById( $id )
    {
        $result = $this->subscriberModel->getSubscriberById( $id );

        if ( ! $result ) {
            throw new \Exception( 'No subscriber found with ID: ' . $id );
        }

        return $this->subscriberEntityFromArray( $result );
    }

    /**
    * Get subscriber by username
    * @param int $id
    * @return SubscriberEntity
    */
    public function getSubscriberByUsername( $username )
    {
        $result = $this->subscriberModel->getSubscriberByUsername( $username );

        return $this->subscriberEntityFromArray( $result );
    }

    /**
     * Get subscriber by email address
     * @param string $email_address
     * @return SubscriberEntity
     */
    public function getSubscriberByEmailAddress( $emailAddress )
    {
        return $this->getSubscriberBy( 'email', $emailAddress );
    }

    /**
     * Get Subscriber object from foreign key value
     * @param string $fk
     * @return SubscriberEntity
     */
    public function getSubscriberByForeignKey( $fk )
    {
        return $this->getSubscriberBy( 'foreignkey', $fk );
    }

    /**
     * Get Subscriber object from unique id value
     * @param string $unique_id
     * @return SubscriberEntity
     */
    public function getSubscriberByUniqueId( $unique_id )
    {
        $result = $this->getSubscriberBy( 'uniqueid', $unique_id );
        return $this->subscriberEntityFromArray( $result );
    }

    /**
     * Get a Subscriber by searching for a value in a given column
     * @param string $column
     * @param string $value
     * @return SubscriberEntity
     */
    private function getSubscriberBy( $column, $value )
    {
        $result = $this->db->prepare(
            sprintf(
                'SELECT * FROM %s
                WHERE :key = :value',
                $this->config->getTableName( 'Subscriber', true )
            )
        );
        $result->bindValue( ':key',$column );
        $result->bindValue( ':value',$value );
        $result->execute();

        return $this->subscriberFromArray( $result->fetch( \PDO::FETCH_ASSOC ) );
    }

    /**
     * Add new Subscriber to database
     * @param $emailAddress
     * @param $password
     * @return int $newScrId DB ID of the newly inserted subscriber
     * @throws \InvalidArgumentException
     */
    public function add( \phpList\Entity\SubscriberEntity $scrEntity )
    {
        // Hash the password before saving
        $scrEntity->encPass = $this->pass->encrypt( $scrEntity->plainPass, $this->config->get( 'ENCRYPTION_ALGO' ) );

        // Check the address is valid
        // TODO: Reintroduce the validation level and tlds from config file
        // TODO: Move validation out of here and into client code
        if ( ! $this->emailUtil->isValid( $scrEntity->emailAddress ) ) {
            throw new \Exception( 'Cannot insert subscriber with invalid email address: "' . $scrEntity->emailAddress . '"' );
        }

        // Save subscriber to db
        $newSubscriberId = $this->subscriberModel->save(
            $scrEntity->emailAddress
            , $scrEntity->encPass
        );

        return $newSubscriberId;
    }

    /**
     * Save Subscriber info to database
     * when the password has to be updates, use @updatePassword
     * @param SubscriberEntity $subscriber
     */
    public function update( SubscriberEntity $subscriber )
    {
        $query = sprintf(
            'UPDATE %s SET
                email = "%s",
                confirmed = "%s",
                blacklisted = "%s",
                optedin = "%s",
                modified = CURRENT_TIMESTAMP,
                htmlemail = "%s",
                extradata = "%s"',
            $this->config->getTableName('user', true),
            $subscriber->emailAddress,
            $subscriber->confirmed,
            $subscriber->blacklisted,
            $subscriber->optedin,
            $subscriber->htmlemail,
            $subscriber->extradata
        );

        $this->db->query($query);
    }

    /**
     * Remove subscriber from database
     */
    public function delete( $id )
    {
        $results = $this->subscriberModel->delete( $id );

        // TODO: Add a check of $results to ensure delete was successful before
        // returning (bool) true
        return true;
    }

    /**
     * Get the number of subscribers who's unique id has not been set
     * @return int
     */
    public function checkUniqueIds()
    {
        $result = $this->db->query(
            sprintf(
                'SELECT id FROM %s
                WHERE uniqid IS NULL
                OR uniqid = ""',
                $this->config->getTableName('user', true)
            )
        );
        $to_do = $result->rowCount();
        if ($to_do > 0) {
            while ($col = $result->fetchColumn(0)) {
                $this->giveUniqueId($col);
            }
        }
        return $to_do;
    }


    /**
     * Create a SubscriberEntity object from database values
     * @param $array
     * @return SubscriberEntity
     */
    private function subscriberEntityFromArray( array $array )
    {
        // FIXME: Move this object instantiation to DI.
        $scrEntity = new SubscriberEntity();
        
        $scrEntity->emailAddress = $array['email'];
        $scrEntity->plainPass = $array['password'];
        $scrEntity->id = $array['id'];
        $scrEntity->confirmed = $array['confirmed'];
        $scrEntity->blacklisted = $array['blacklisted'];
        $scrEntity->optedin = $array['optedin'];
        $scrEntity->bouncecount = $array['bouncecount'];
        $scrEntity->entered = $array['entered'];
        $scrEntity->modified = $array['modified'];
        $scrEntity->uniqid = $array['uniqid'];
        $scrEntity->htmlemail = $array['htmlemail'];
        $scrEntity->subscribepage = $array['subscribepage'];
        $scrEntity->rssfrequency = $array['rssfrequency'];
        $scrEntity->encPass = $array['password'];
        $scrEntity->emailAddress = $array['email'];
        $scrEntity->passwordchanged = $array['passwordchanged'];
        $scrEntity->disabled = $array['disabled'];
        $scrEntity->extradata = $array['extradata'];
        $scrEntity->foreignkey = $array['foreignkey'];

        return $scrEntity;
    }

    public function updatePass( $plainPass, Entity\SubscriberEntity $scrEntity )
    {
        // Hash password
        $encPass = $this->pass->encrypt( $plainPass, $this->config->get( 'ENCRYPTION_ALGO' ) );
        // Update the password
        $this->subscriberModel->updatePass( $scrEntity->id, $encPass );
    }

    /**
     * Load this subscribers attributes from the database
     * @param SubscriberEntity $subscriber
     */
    public function loadAttributes( SubscriberEntity &$scrEntity )
    {
        $result = $this->db->query(
            sprintf(
                'SELECT a.id, a.name, ua.value
                FROM %s AS a
                    INNER JOIN %s AS ua
                    ON a.id = ua.attributeid
                WHERE ua.userid = %d
                ORDER BY listorder',
                $this->config->getTableName('attribute', true),
                $this->config->getTableName('user_attribute', true),
                $scrEntity->id
            )
        );

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $scrEntity->setAttribute($row['id'], $row['value']);
            $scrEntity->setAttribute($row['name'], $row['name']);
        }
    }

    /**
     * Get all subscriber attributes
     * @param SubscriberEntity $subscriber
     * @return array
     */
    public function getCleanAttributes(SubscriberEntity $scrEntity)
    {
        if (!$scrEntity->hasAttributes()) {
            $this->loadAttributes($scrEntity);
        }

        $clean_attributes = array();
        foreach ($scrEntity->getAttributes() as $key => $val) {
            ## in the help, we only list attributes with "strlen < 20"
            if (strlen($key) < 20) {
                $clean_attributes[String::cleanAttributeName($key)] = $val;
            }
        }
        return $clean_attributes;
    }

    /**
     * Add an attribute to the database for this subscriber
     * @param entities\SubscriberEntity $subscriber
     * @param $attribute_id
     * @param string $value
     * @internal param int $id
     */
    public function addAttribute(SubscriberEntity $scrEntity, $attribute_id, $value)
    {
        $this->db->query(
            sprintf(
                'REPLACE INTO %s
                (userid,attributeid,value)
                VALUES(%d,%d,"%s")',
                $this->config->getTableName('user_attribute'),
                $scrEntity->id,
                $attribute_id,
                $this->db->sqlEscape($value)
            )
        );
    }

    /**
     * Add a history item for this subscriber
     * @param string $msg
     * @param string $detail
     * @param string $subscriber_id
     */
    public function addHistory($msg, $detail, $subscriber_id)
    {
        if (empty($detail)) { ## ok duplicated, but looks better :-)
            $detail = $msg;
        }

        $sysinfo = "";
        $sysarrays = array_merge($_ENV, $_SERVER);
        if (is_array($this->config->get('SUBSCRIBER_HISTORY_SYSTEMINFO'))) {
            foreach ($this->config->get('SUBSCRIBER_HISTORY_SYSTEMINFO') as $key) {
                if ($sysarrays[$key]) {
                    $sysinfo .= "\n$key = $sysarrays[$key]";
                }
            }
        } else {
            $default = array('HTTP_USER_AGENT', 'HTTP_REFERER', 'REMOTE_ADDR', 'REQUEST_URI');
            foreach ($sysarrays as $key => $val) {
                if (in_array($key, $default))
                    $sysinfo .= "\n" . strip_tags($key) . ' = ' . htmlspecialchars($val);
            }
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '';
        }
        $this->db->query(
            sprintf(
                'INSERT INTO %s
                (ip,userid,date,summary,detail,systeminfo)
                VALUES("%s",%d,now(),"%s","%s","%s")',
                $this->config->getTableName('user_history', true),
                $ip,
                $subscriber_id,
                addslashes($msg),
                addslashes(htmlspecialchars($detail)),
                $sysinfo
            )
        );
    }

    /**
     * Get the unique id from a subscriber
     * @param int $subscriber_id
     * @return int unique_id
     */
    public function getUniqueId($subscriber_id)
    {
        $result = $this->db->query(
            sprintf(
                'SELECT uniqid FROM %s
                WHERE id = %d',
                $this->config->getTableName('user', true),
                $subscriber_id
            )
        );
        return $result->fetch();
    }
}
