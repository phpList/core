<?php
/**
 * User: SaWey
 * Date: 6/02/15
 */

namespace phpList\Entity;

class CampaignEntity
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
    public $template_object;

    public $lists = array();

    public function __construct()
    {
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
     * Get a campaigndata item
     * @param string $item
     * @return mixed
     * @throws \Exception
     */
    public function __get($item)
    {
        if (isset($this->campaigndata[$item])) {
            return $this->campaigndata[$item];
        } else {
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
        return isset($this->campaigndata[$item]);
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
}
