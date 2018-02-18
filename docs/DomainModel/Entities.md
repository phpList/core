# Domain Entities

## Identity Context

### Administrator
Table name: `phplist_admin`

An **administrator** can log in to the system and is allowed to administer
selected **subscriber lists** (as the owner), send **campaigns** to these
subscriber lists and edit **subscribers**.

Administrators are not subscribers. If administrators would like to subscribe
to subscriber lists, they need to have a separate subscriber account.

### AdministratorAttribute
Table name: `phplist_adminattribute` or `phplist_admin_attribute`

This is similar to a subscriber attribute: It allows you to have details of
administrators. These can then be used in campaigns. Basically, you can add
placeholders for administrator attributes in campaigns.

### AdministratorPasswordRequest
Table name: `phplist_admin_password_request`

This is used to handle "forgot password" requests.

### AdministratorToken
Table name: `phplist_admintoken`

This table contains the API tokens for **administrators**. Those API tokens are
used for access to the REST API. In the web frontend, they are also used for
CSRF protection.


## SubscriptionContext

### Attribute
Table name: `phplist_user_attribute`

An **attribute** is a field for subscribers. This entity does not
contain the values for this attribute for each individual subscribe, but
only the name of the attribute and an ID. 

### AttributeValue
Table name: `phplist_user_user_attribute`

An **attributeValue** contains the value for a particular **attribute** for a
particular **subscriber**.

### SubscribePage
Table name: `phplist_subscribepage`

*subscribePages** allow setting up a selection of subscriber lists, attributes
and language, and some other settings to control the content for the page that
can be used to subscribe to the system. As a result, you can e.g., have
different pages per language, which allows you to translate all the content
for each language.

### SubscribePageData
Table name: `phplist_subscribepage_data`

This is where the data for a subscribe page is stored as key-value pair.

### Subscriber
Table name: `phplist_user_user`

A subscriber can subscribe to multiple **subscriber lists** and can receive
email messages from **campaigns** for those subscriber lists.

Each subscriber has a (unique) email address.

To manage their list subscriptions and preferences, subscribers don't
generally authenticate, except with a token, although you can switch on
requiring passwords (off by default).

A subscriber can have some **attributeValues**.

### SubscriberHistory
Table name: `phplist_user_user_history`

This contains records of changes to a **subscriber** account. In phpList 3,
the trail is accessible on the subscriber history page, e.g.,
/admin/?page=userhistory&id=1. It includes when and how they were subscribed
and reasons for their blacklisting.

### Subscription
Table name: `phplist_listuser`

A **subscription** means that a **subscriber** is subscribed to a
**subscriber list**.

### Subscriber-Campaign Connection
Table name: `phplist_usermessage`

This association is the core of everything. A subscriber can be member of
multiple subscriber lists, and a campaign can be sent to multiple subscriber
lists, but this association ensures that a subscriber always only receives
one copy of a campaign, regardless of other associations.

Should we use a named association for this? What should it be named?

### SuppressionList
Table name: `phplist_user_blacklist`

When a *subscriber* should be considered removed from the system, they are
added here. Any email address mentioned should never receive any further
emails, unless they actively go through the subscription and confirmation
process again, when they will be removed from this list.

### SuppressionListData

Table name: `phplist_user_blacklist_data`

This is some more additional info on a SuppressionList.


## Messaging Context


### Attachment
Table name: `phplist_attachment`

An attachment represents a file attached to exactly one **campaign**.

### Bounce
Table name: `phplist_boune`

### BounceRegEx
Table name: `phplist_bounceregex`

### Campaign
Table name: `phplist_message`

A **campaign** is a non-personalized message on a **subscriber list** (or
potentially multiple subscriber lists). The campaign has been created by an
**administrator** owner and sends out email messages to multiple
**subscribers**. It is stored to which subscribers a campaign has been sent.

### CampaignBounce
Table name: `phplist_message_bounce`

### CampaignData
Table name: `phplist_messagedata`

This contains additional miscellaneous attributes for **campaigns**, such as
Google tracking IDs, special relationships to **subscriber lists**, and alias
titles.

### CampaignForward
Table name: `phplist_message_forward`

This tracks details of **campaigns** which were forwarded by a recipient
**subscriber** to someone else via an email message.

### SubscriberList
Table name: `phplist_list`

A **subscriber list** is something to which **subscribers** can subscribe in
order to receive email messages from **campaigns**. Each subscriber list has
exactly one **administrator** owner.

### Template
Table name: `phplist_template`

This stores **campaign** templates. The blob contains HTML which the campaign
content is inserted into, using special tags (AKA placeholders).

### TemplateImage
Table name: `phplist_templateimage`

This contains images used in **templates**. The blob contains the image.


## System Context

### Configuration
Table name: `phplist_config`

A **configuration** item is a single value in a configuration registry.

### Internationalization
Table name: `phplist_i18n`

Some background on the i18n system is here:
https://resources.phplist.com/translations/start
Translations are automatically imported from a repository and then accessed
from the database and cached in the browser.

### LogEntry
Table name: `phplist_eventlog`

An event **log entry** stores something that has happened on the system. This
can be anything that seems interesting for someone to review.

### SendProcess
Table name: `phplist_sendprocess`

This is really a "process locking" table.

### UrlCache
Table name: `phplist_urlcache`

When sending a web page, for performance reasons, the page is stored here and
only refetched when expired from cache. But effectively any URL that is
requested by the system, which includes the list of languages (and the last
time they were updated),
[list of top level domains](https://phplist.com/files/tlds-alpha-by-domain.txt),
[the MD5 for that](https://phplist.com/files/tlds-alpha-by-domain.txt.md5),
etc. etc.


## Tracking Context

### LinkTrackForward
Table name: `phplist_linktrack_forward`

All links in a **campaign** are converted and stored here. Links are not
duplicated.

### LinkTrackMessageLink
Table name: `phplist_linktrack_ml`

Once there is a **linkTrackForward**, associations to **campaigns** can be
stored here to mark that a link is used in a campaign.

### LinkTrackUmlClick
Table name: `phplist_linktrack_uml_click`

When a **subscriber** clicks on a link in a message, this click will be
recorded here.


## Unused entities

* LinkTrack, table name: `phplist_linktrack`
* LinkTrackSubscriberClick, table name: `phplist_linktrack_userclick`
* SubscriberStatistics, table name: `phplist_userstats`
