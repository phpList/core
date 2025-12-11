<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageDataRepository;

class MessageDataLoader
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly MessageDataRepository $messageDataRepository,
        private readonly int $defaultMessageAge,
    ) {
    }

    public function __invoke(Message $message): array
    {
        $defaultFrom = $this->configProvider->getValue(ConfigOption::MessageFromAddress)
                ?? $this->configProvider->getValue(ConfigOption::AdminAddress);

        $finishSending = time() + $this->defaultMessageAge;

        $messageData = [
            'template'       => $this->configProvider->getValue(ConfigOption::DefaultMessageTemplate),
            'sendformat'     => 'HTML',
            'message'        => '',
            'forwardmessage' => '',
            'textmessage'    => '',
            'rsstemplate'    => '',
            'embargo'        => [
                'year'   => date('Y'),
                'month'  => date('m'),
                'day'    => date('d'),
                'hour'   => date('H'),
                'minute' => date('i'),
            ],
            'repeatinterval' => 0,
            'repeatuntil'    => [
                'year'   => date('Y'),
                'month'  => date('m'),
                'day'    => date('d'),
                'hour'   => date('H'),
                'minute' => date('i'),
            ],
            'requeueinterval' => 0,
            'requeueuntil'    => [
                'year'   => date('Y'),
                'month'  => date('m'),
                'day'    => date('d'),
                'hour'   => date('H'),
                'minute' => date('i'),
            ],
            'finishsending' => [
                'year'   => date('Y', $finishSending),
                'month'  => date('m', $finishSending),
                'day'    => date('d', $finishSending),
                'hour'   => date('H', $finishSending),
                'minute' => date('i', $finishSending),
            ],
            'fromfield'      => '',
            'subject'        => '',
            'forwardsubject' => '',
            'footer'         => $this->configProvider->getValue(ConfigOption::MessageFooter),
            'forwardfooter'  => $this->configProvider->getValue(ConfigOption::ForwardFooter),
            'status'         => '',
            'tofield'        => '',
            'replyto'        => '',
            'targetlist'     => [],
            'criteria_match' => '',
            'sendurl'        => '',
            'sendmethod'     => 'inputhere',
            'testtarget'     => '',
            'notify_start'   => $this->configProvider->getValue(ConfigOption::NotifyStartDefault),
            'notify_end'     => $this->configProvider->getValue(ConfigOption::NotifyEndDefault),
            'google_track'   => filter_var(
                value: $this->configProvider->getValue(ConfigOption::AlwaysAddGoogleTracking),
                filter: FILTER_VALIDATE_BOOL
            ),
            'excludelist'    => [],
            'sentastest'     => 0,
        ];
        // todo: set correct values from entity
        $nonEmptyFields = array_filter(
            get_object_vars($message),
            fn($v) => $v !== null && $v !== '',
        );
        foreach ($nonEmptyFields as $key => $val) {
            $messageData[$key] = $val;
        }

        $messageData['subject'] = $messageData['subject'] === '(no title)' ? '(no subject)' : $messageData['subject'];

        $storedMessageData = $this->messageDataRepository->getForMessage($message->getId());
        foreach ($storedMessageData as $storedMessageDatum) {
            if (str_starts_with($storedMessageDatum->getData(), 'SER:')) {
                $unserialized = unserialize(substr($storedMessageDatum->getData(), 4));
                $data = array_walk_recursive($unserialized, 'stripslashes');
            } else {
                $data = stripslashes($storedMessageDatum->getData());
            }
            if (!in_array($storedMessageDatum->getName(), ['astext', 'ashtml', 'astextandhtml', 'aspdf', 'astextandpdf']))
            {
                //# don't overwrite counters in the message table from the data table
                $messageData[stripslashes($storedMessageDatum->getName())] = $data;
            }
        }

        foreach (array('embargo', 'repeatuntil', 'requeueuntil') as $dateField) {
            if (!is_array($messageData[$dateField])) {
                $messageData[$dateField] = [
                    'year'   => date('Y'),
                    'month'  => date('m'),
                    'day'    => date('d'),
                    'hour'   => date('H'),
                    'minute' => date('i'),
                ];
            }
        }

        foreach($message->getListMessages() as $listMessage) {
            $messageData['targetlist'][$listMessage->getListId()] = 1;
        }

        //# backwards, check that the content has a url and use it to fill the sendurl
        if (empty($messageData['sendurl'])) {
            //# can't do "ungreedy matching, in case the URL has placeholders, but this can potentially throw problems
            if (!empty($messageData['message']) && preg_match('/\[URL:(.*)\]/i', $messageData['message'], $regs)) {
                $messageData['sendurl'] = $regs[1];
            }
        }
        if (empty($messageData['sendurl']) && !empty($messageData['message'])) {
            // if there's a message and no url, make sure to show the editor, and not the URL input
            $messageData['sendmethod'] = 'inputhere';
        }

        //## parse the from field into it's components - email and name
        if (preg_match('/([^ ]+@[^ ]+)/', $messageData['fromfield'], $regs)) {
            // if there is an email in the from, rewrite it as "name <email>"
            $messageData['fromname'] = str_replace($regs[0], '', $messageData['fromfield']);
            $messageData['fromemail'] = $regs[0];
            // if the email has < and > take them out here
            $messageData['fromemail'] = str_replace('<', '', $messageData['fromemail']);
            $messageData['fromemail'] = str_replace('>', '', $messageData['fromemail']);
            // make sure there are no quotes around the name
            $messageData['fromname'] = str_replace('"', '', ltrim(rtrim($messageData['fromname'])));
        } elseif (strpos($messageData['fromfield'], ' ')) {
            // if there is a space, we need to add the email
            $messageData['fromname'] = $messageData['fromfield'];
            //  $cached[$messageid]["fromemail"] = "listmaster@$domain";
            $messageData['fromemail'] = $defaultFrom;
        } else {
            $messageData['fromemail'] = $defaultFrom;
            $messageData['fromname'] = $messageData['fromfield'];
        }
        // disallow an email address in the name
        if (preg_match('/([^ ]+@[^ ]+)/', $messageData['fromname'], $regs)) {
            $messageData['fromname'] = str_replace($regs[0], '', $messageData['fromname']);
        }
        // clean up
        $messageData['fromemail'] = str_replace(',', '', $messageData['fromemail']);
        $messageData['fromname'] = str_replace(',', '', $messageData['fromname']);

        $messageData['fromname'] = trim($messageData['fromname']);

        // erase double spacing
        while (strpos($messageData['fromname'], '  ')) {
            $messageData['fromname'] = str_replace('  ', ' ', $messageData['fromname']);
        }

        //# if the name ends up being empty, copy the email
        if (empty($messageData['fromname'])) {
            $messageData['fromname'] = $messageData['fromemail'];
        }

        if (isset($messageData['targetlist']['unselect'])) {
            unset($messageData['targetlist']['unselect']);
        }
        if (isset($messageData['excludelist']['unselect'])) {
            unset($messageData['excludelist']['unselect']);
        }

        if (empty($messageData['campaigntitle'])) {
            if ($messageData['subject'] != '(no subject)') {
                $messageData['campaigntitle'] = $messageData['subject'];
            } else {
                $messageData['campaigntitle'] = '(no title)';
            }
        }
        //# copy subject to title
        if ($messageData['campaigntitle'] == '(no title)' && $messageData['subject'] != '(no subject)') {
            $messageData['campaigntitle'] = $messageData['subject'];
        }

        return $messageData;
    }
}
