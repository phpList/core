<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Provider;

class DefaultConfigProvider
{
    /**
     * Holds all default configuration values
     * @var array
     */
    private static array $defaults = [];

    private static function init(): void
    {
        if (!empty(self::$defaults)) {
            return;
        }

        self::$defaults = array(
            'admin_address' => array(
                'value'       => 'webmaster@[DOMAIN]',
                'description' => s('Person in charge of this system (one email address)'),
                'type'        => 'email',
                'allowempty'  => false,
                'category'    => 'general',
            ),
            'organisation_name' => array(
                'value'       => '',
                'description' => s('Name of the organisation'),
                'type'        => 'text',
                'allowempty'  => true,
                'allowtags'   => '<b><i><u><strong><em><h1><h2><h3><h4>',
                'allowJS'     => false,
                'category'    => 'general',
            ),
            'organisation_logo' => array(
                'value'       => '',
                'description' => s('Logo of the organisation'),
                'infoicon'    => true,
                'type'        => 'image',
                'allowempty'  => true,
                'category'    => 'general',
            ),
            'date_format' => array(
                'value'       => 'j F Y',
                'description' => s('Date format'),
                'infoicon'    => true,
                'type'        => 'text',
                'allowempty'  => false,
                'category'    => 'general',
            ),
            'rc_notification' => array(
                'value'       => 0,
                'description' => s('Show notification for Release Candidates'),
                'type'        => 'boolean',
                'allowempty'  => true,
                'category'    => 'security',
            ),
            'remote_processing_secret' => array(
                'value'       => bin2hex(random_bytes(10)),
                'description' => s('Secret for remote processing'),
                'type'        => 'text',
                'category'    => 'security',
            ),
            'notify_admin_login' => array(
                'value'       => 1,
                'description' => s('Notify admin on login from new location'),
                'type'        => 'boolean',
                'category'    => 'security',
                'allowempty'  => true,
            ),
            'admin_addresses' => array(
                'value'       => '',
                'description' => s('List of email addresses to CC in system messages (separate by commas)'),
                'type'        => 'emaillist',
                'allowempty'  => true,
                'category'    => 'reporting',
            ),
            'campaignfrom_default' => array(
                'value'       => '',
                'description' => s("Default for 'From:' in a campaign"),
                'type'        => 'text',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'notifystart_default' => array(
                'value'       => '',
                'description' => s("Default for 'address to alert when sending starts'"),
                'type'        => 'email',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'notifyend_default' => array(
                'value'       => '',
                'description' => s("Default for 'address to alert when sending finishes'"),
                'type'        => 'email',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'always_add_googletracking' => array(
                'value'       => '0',
                'description' => s('Always add analytics tracking code to campaigns'),
                'type'        => 'boolean',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'analytic_tracker' => array(
                'values'       => array('google' => 'Google Analytics', 'matomo' => 'Matomo'),
                'value'        => 'google',
                'description'  => s('Analytics tracking code to add to campaign URLs'),
                'type'         => 'select',
                'allowempty'   => false,
                'category'     => 'campaign',
            ),
            'report_address' => array(
                'value'       => 'listreports@[DOMAIN]',
                'description' => s('Who gets the reports (email address, separate multiple emails with a comma)'),
                'type'        => 'emaillist',
                'allowempty'  => true,
                'category'    => 'reporting',
            ),
            'message_from_address' => array(
                'value'       => 'noreply@[DOMAIN]',
                'description' => s('From email address for system messages'),
                'type'        => 'email',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'message_from_name' => array(
                'value'       => s('Webmaster'),
                'description' => s('Name for system messages'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'message_replyto_address' => array(
                'value'       => 'noreply@[DOMAIN]',
                'description' => s('Reply-to email address for system messages'),
                'type'        => 'email',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'hide_single_list' => array(
                'value'       => '1',
                'description' => s('If there is only one visible list, should it be hidden in the page and automatically subscribe users who sign up'),
                'type'        => 'boolean',
                'allowempty'  => true,
                'category'    => 'subscription-ui',
            ),
            'list_categories' => array(
                'value'       => '',
                'description' => s('Categories for lists. Separate with commas.'),
                'infoicon'    => true,
                'type'        => 'text',
                'allowempty'  => true,
                'category'    => 'list-organisation',
            ),

            'displaycategories' => array(
                'value'       => 0,
                'description' => s('Display list categories on subscribe page'),
                'type'        => 'boolean',
                'allowempty'  => false,
                'category'    => 'list-organisation',
            ),
            'textline_width' => array(
                'value'       => '40',
                'description' => s('Width of a textline field (numerical)'),
                'type'        => 'integer',
                'min'         => 20,
                'max'         => 150,
                'category'    => 'subscription-ui',
            ),
            'textarea_dimensions' => array(
                'value'       => '10,40',
                'description' => s('Dimensions of a textarea field (rows,columns)'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'subscription-ui',
            ),
            'send_admin_copies' => array(
                'value'       => '0',
                'description' => s('Send notifications about subscribe, update and unsubscribe'),
                'type'        => 'boolean',
                'allowempty'  => true,
                'category'    => 'reporting',
            ),
            'defaultsubscribepage' => array(
                'value'       => 1,
                'description' => s('The default subscribe page when there are multiple'),
                'type'        => 'integer',
                'min'         => 1,
                'max'         => 999,
                'allowempty'  => true,
                'category'    => 'subscription',
            ),
            'defaultmessagetemplate' => array(
                'value'       => 0,
                'description' => s('The default HTML template to use when sending a message'),
                'type'        => 'text',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'systemmessagetemplate' => array(
                'value'       => 0,
                'description' => s('The HTML wrapper template for system messages'),
                'type'        => 'integer',
                'min'         => 0,
                'max'         => 999, // or max(id) from template
                'allowempty'  => true,
                'category'    => 'transactional',
            ),
            'subscribeurl' => array(
                'value'       => $GLOBALS['public_scheme']."://[WEBSITE]$pageroot/?p=subscribe",
                'description' => s('URL where subscribers can sign up'),
                'type'        => 'url',
                'allowempty'  => 0,
                'category'    => 'subscription',
            ),
            'unsubscribeurl' => array(
                'value'       => $GLOBALS['public_scheme']."://[WEBSITE]$pageroot/?p=unsubscribe",
                'description' => s('URL where subscribers can unsubscribe'),
                'type'        => 'url',
                'allowempty'  => 0,
                'category'    => 'subscription',
            ),
            'blacklisturl' => array(
                'value'       => $GLOBALS['public_scheme']."://[WEBSITE]$pageroot/?p=donotsend",
                'description' => s('URL where unknown users can unsubscribe (do-not-send-list)'),
                'type'        => 'url',
                'allowempty'  => 0,
                'category'    => 'subscription',
            ),
            'confirmationurl' => array(
                'value'       => $GLOBALS['public_scheme']."://[WEBSITE]$pageroot/?p=confirm",
                'description' => s('URL where subscribers have to confirm their subscription'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'subscription',
            ),
            'preferencesurl' => array(
                'value'       => $GLOBALS['public_scheme']."://[WEBSITE]$pageroot/?p=preferences",
                'description' => s('URL where subscribers can update their details'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'subscription',
            ),
            'forwardurl' => array(
                'value'       => $GLOBALS['public_scheme']."://[WEBSITE]$pageroot/?p=forward",
                'description' => s('URL for forwarding messages'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'subscription',
            ),
            'vcardurl' => array(
                'value'       => $GLOBALS['public_scheme']."://[WEBSITE]$pageroot/?p=vcard",
                'description' => s('URL for downloading vcf card'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'subscription',
            ),
            'ajax_subscribeconfirmation' => array(
                'value'       => s('<h3>Thanks, you have been added to our newsletter</h3><p>You will receive an email to confirm your subscription. Please click the link in the email to confirm</p>'),
                'description' => s('Text to display when subscription with an AJAX request was successful'),
                'type'        => 'textarea',
                'allowempty'  => true,
                'category'    => 'subscription',
            ),
            'subscribesubject' => array(
                'value'       => s('Request for confirmation'),
                'description' => s('Subject of the message subscribers receive when they sign up'),
                'infoicon'        => true,
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'subscribemessage' => array(
                'value' =>
                    ' You have been subscribed to the following newsletters:

[LISTS]


Please click the following link to confirm it\'s really you:

[CONFIRMATIONURL]


In order to provide you with this service we\'ll need to

Transfer your contact information to [DOMAIN]
Store your contact information in your [DOMAIN] account
Send you emails from [DOMAIN]
Track your interactions with these emails for marketing purposes

If this is not correct, or you do not agree, simply take no action and delete this message.'
            ,
                'description' => s('Message subscribers receive when they sign up'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'unsubscribesubject' => array(
                'value'       => s('Goodbye from our Newsletter'),
                'description' => s('Subject of the message subscribers receive when they unsubscribe'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'unsubscribemessage' => array(
                'value' =>
                    'Goodbye from our Newsletter, sorry to see you go.

You have been unsubscribed from our newsletters.

This is the last email you will receive from us. Our newsletter system, phpList,
will refuse to send you any further messages, without manual intervention by our administrator.

If there is an error in this information, you can re-subscribe:
please go to [SUBSCRIBEURL] and follow the steps.

Thank you'
            ,
                'description' => s('Message subscribers receive when they unsubscribe'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'confirmationsubject' => array(
                'value'       => s('Welcome to our Newsletter'),
                'description' => s('Subject of the message subscribers receive after confirming their email address'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'confirmationmessage' => array(
                'value' =>
                    'Welcome to our Newsletter

Please keep this message for later reference.

Your email address has been added to the following newsletter(s):
[LISTS]

To update your details and preferences please go to [PREFERENCESURL].
If you do not want to receive any more messages, please go to [UNSUBSCRIBEURL].

Thank you'
            ,
                'description' => s('Message subscribers receive after confirming their email address'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'updatesubject' => array(
                'value'       => s('[notify] Change of List-Membership details'),
                'description' => s('Subject of the message subscribers receive when they have changed their details'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            // the message that is sent when a user updates their information.
            // just to make sure they approve of it.
            // confirmationinfo is replaced by one of the options below
            // userdata is replaced by the information in the database
            'updatemessage' => array(
                'value' =>
                    'This message is to inform you of a change of your details on our newsletter database

You are currently member of the following newsletters:

[LISTS]

[CONFIRMATIONINFO]

The information on our system for you is as follows:

[USERDATA]

If this is not correct, please update your information at the following location:

[PREFERENCESURL]

Thank you'
            ,
                'description' => s('Message subscribers receive when they have changed their details'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            // this is the text that is placed in the [!-- confirmation --] location of the above
            // message, in case the email is sent to their new email address and they have changed
            // their email address
            'emailchanged_text' => array(
                'value' => '
  When updating your details, your email address has changed.
  Please confirm your new email address by visiting this webpage:

  [CONFIRMATIONURL]

  ',
                'description' => s('Part of the message that is sent to their new email address when subscribers change their information, and the email address has changed'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            // this is the text that is placed in the [!-- confirmation --] location of the above
            // message, in case the email is sent to their old email address and they have changed
            // their email address
            'emailchanged_text_oldaddress' => array(
                'value' =>
                    'Please Note: when updating your details, your email address has changed.

A message has been sent to your new email address with a URL
to confirm this change. Please visit this website to activate
your membership.'
            ,
                'description' => s('Part of the message that is sent to their old email address when subscribers change their information, and the email address has changed'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'personallocation_subject' => array(
                'value'       => s('Your personal location'),
                'description' => s('Subject of message when subscribers request their personal location'),
                'type'        => 'text',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'messagefooter' => array(
                'value' => '--

    <div class="footer" style="text-align:left; font-size: 75%;">
      <p>This message was sent to [EMAIL] by [FROMEMAIL].</p>
      <p>To forward this message, please do not use the forward button of your email application, because this message was made specifically for you only. Instead use the <a href="[FORWARDURL]">forward page</a> in our newsletter system.<br/>
      To change your details and to choose which lists to be subscribed to, visit your personal <a href="[PREFERENCESURL]">preferences page</a>.<br/>
      Or you can <a href="[UNSUBSCRIBEURL]">opt-out completely</a> from all future mailings.</p>
    </div>

  ',
                'description' => s('Default footer for sending a campaign'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'campaign',
            ),
            'forwardfooter' => array(
                'value' => '
     <div class="footer" style="text-align:left; font-size: 75%;">
      <p>This message has been forwarded to you by [FORWARDEDBY].</p>
      <p>You have not been automatically subscribed to this newsletter.</p>
      <p>If you think this newsletter may interest you, you can <a href="[SUBSCRIBEURL]">Subscribe</a> and you will receive our next newsletter directly to your inbox.</p>
      <p>You can also <a href="[BLACKLISTURL]">opt out completely</a> from receiving any further email from our newsletter application, phpList.</p>
    </div>
  ',
                'description' => s('Footer used when a message has been forwarded'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'campaign',
            ),
            'personallocation_message' => array(
                'value' =>

                    'You have requested your personal location to update your details from our website.
The location is below. Please make sure that you use the full line as mentioned below.
Sometimes email programmes can wrap the line into multiple lines.

Your personal location is:
[PREFERENCESURL]

Thank you.'
            ,
                'description' => s('Message to send when they request their personal location'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'transactional',
            ),
            'remoteurl_append' => array(
                'value'       => '',
                'description' => s('String to always append to remote URL when using send-a-webpage'),
                'type'        => 'text',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'wordwrap' => array(
                'value'       => '75',
                'description' => s('Width for Wordwrap of Text messages'),
                'type'        => 'text',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'html_email_style' => array(
                'value'       => '',
                'description' => s('CSS for HTML messages without a template'),
                'type'        => 'textarea',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'alwayssendtextto' => array(
                'value'       => '',
                'description' => s('Domains that only accept text emails, one per line'),
                'type'        => 'textarea',
                'allowempty'  => true,
                'category'    => 'campaign',
            ),
            'tld_last_sync' => array(
                'value'       => '0',
                'description' => s('last time TLDs were fetched'),
                'type'        => 'text',
                'allowempty'  => true,
                'category'    => 'system',
                'hidden'      => true,
            ),
            'internet_tlds' => array(
                'value'       => '',
                'description' => s('Top level domains'),
                'type'        => 'textarea',
                'allowempty'  => true,
                'category'    => 'system',
                'hidden'      => true,
            ),
            'pageheader' => array(
                'value'       => '<h1>Welcome</h1>',
                'description' => s('Header of public pages.'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'subscription-ui',
            ),
            'pagefooter' => array(
                'value'       => '<p>Footer text</p>',
                'description' => s('Footer of public pages'),
                'type'        => 'textarea',
                'allowempty'  => 0,
                'category'    => 'subscription-ui',
            ),
        );
    }

    /**
     * Get a single default config item by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::init();
        return self::$defaults[$key] ?? $default;
    }

    /**
     * Check if a config key exists
     */
    public static function has(string $key): bool
    {
        self::init();
        return isset(self::$defaults[$key]);
    }
}
