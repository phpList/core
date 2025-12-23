<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageDataRepository;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;

class MessageDataLoader
{
    private const AS_FORMAT_FIELDS = ['astext', 'ashtml', 'astextandhtml', 'aspdf', 'astextandpdf'];
    private const SCHEDULE_FIELDS = ['embargo', 'repeatuntil', 'requeueuntil'];

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly MessageDataRepository $messageDataRepository,
        private readonly MessageRepository $messageRepository,
        private readonly int $defaultMessageAge,
    ) {
    }

    public function __invoke(Message $message): array
    {
        $defaultFrom = $this->configProvider->getValue(ConfigOption::MessageFromAddress)
                ?? $this->configProvider->getValue(ConfigOption::AdminAddress);

        $messageData = $this->buildDefaultMessageData();

        $this->mergeNonEmptyFields($messageData, $message);
        $this->mergeStoredMessageData($messageData, $message);
        $this->normaliseScheduleFields($messageData);
        $this->populateTargetLists($messageData, $message);
        $this->ensureSendUrlAndMethod($messageData);
        $this->normaliseFromField($messageData, $defaultFrom);
        $this->cleanupSelections($messageData);
        $this->deriveCampaignTitle($messageData);

        return $messageData;
    }

    private function getDateArray(?int $timestamp = null): array
    {
        return [
            'year' => date('Y', $timestamp),
            'month' => date('m', $timestamp),
            'day' => date('d', $timestamp),
            'hour' => date('H', $timestamp),
            'minute' => date('i', $timestamp),
        ];
    }

    private function buildDefaultMessageData(): array
    {
        $finishSending = time() + $this->defaultMessageAge;

        return [
            'template' => $this->configProvider->getValue(ConfigOption::DefaultMessageTemplate),
            'sendformat' => 'HTML',
            'message' => '',
            'forwardmessage' => '',
            'textmessage' => '',
            'rsstemplate' => '',
            'embargo' => $this->getDateArray(),
            'repeatinterval' => 0,
            'repeatuntil' => $this->getDateArray(),
            'requeueinterval' => 0,
            'requeueuntil' => $this->getDateArray(),
            'finishsending' => $this->getDateArray($finishSending),
            'fromfield' => '',
            'subject' => '',
            'forwardsubject' => '',
            'footer' => $this->configProvider->getValue(ConfigOption::MessageFooter),
            'forwardfooter' => $this->configProvider->getValue(ConfigOption::ForwardFooter),
            'status' => '',
            'tofield' => '',
            'replyto' => '',
            'targetlist' => [],
            'criteria_match' => '',
            'sendurl' => '',
            'sendmethod' => 'inputhere',
            'testtarget' => '',
            'notify_start' => $this->configProvider->getValue(ConfigOption::NotifyStartDefault),
            'notify_end' => $this->configProvider->getValue(ConfigOption::NotifyEndDefault),
            'google_track' => filter_var(
                value: $this->configProvider->getValue(ConfigOption::AlwaysAddGoogleTracking),
                filter: FILTER_VALIDATE_BOOL
            ),
            'excludelist' => [],
            'sentastest' => 0,
        ];
    }

    private function mergeNonEmptyFields(array &$messageData, Message $message): void
    {
        $nonEmptyFields = $this->messageRepository->getNonEmptyFields($message->getId());
        foreach ($nonEmptyFields as $key => $val) {
            $messageData[$key] = $val;
        }

        if ($messageData['subject'] === '(no title)') {
            $messageData['subject'] = '(no subject)';
        }
    }

    private function mergeStoredMessageData(array &$messageData, Message $message): void
    {
        $storedMessageData = $this->messageDataRepository->getForMessage($message->getId());
        foreach ($storedMessageData as $storedMessageDatum) {
            if (str_starts_with($storedMessageDatum->getData(), 'SER:')) {
                $unserialized = unserialize(substr($storedMessageDatum->getData(), 4), ['allowed_classes' => false]);
                array_walk_recursive($unserialized, function (&$val) {
                    $val = stripslashes($val);
                });

                $data = $unserialized;
            } else {
                $data = stripslashes($storedMessageDatum->getData());
            }
            if (!in_array($storedMessageDatum->getName(), self::AS_FORMAT_FIELDS)) {
                //# don't overwrite counters in the message table from the data table
                $messageData[stripslashes($storedMessageDatum->getName())] = $data;
            }
        }
    }

    private function normaliseScheduleFields(array &$messageData): void
    {
        foreach (self::SCHEDULE_FIELDS as $dateField) {
            if (!is_array($messageData[$dateField])) {
                $messageData[$dateField] = $this->getDateArray();
            }
        }
    }

    private function populateTargetLists(array &$messageData, Message $message): void
    {
        foreach($message->getListMessages() as $listMessage) {
            $messageData['targetlist'][$listMessage->getListId()] = 1;
        }
    }

    private function ensureSendUrlAndMethod(array &$messageData): void
    {
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
    }

    private function normaliseFromField(array &$messageData, ?string $defaultFrom): void
    {
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
        } elseif (str_contains($messageData['fromfield'], ' ')) {
            // if there is a space, we need to add the email
            $messageData['fromname'] = $messageData['fromfield'];
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
    }

    private function cleanupSelections(array &$messageData): void
    {
        if (isset($messageData['targetlist']['unselect'])) {
            unset($messageData['targetlist']['unselect']);
        }
        if (isset($messageData['excludelist']['unselect'])) {
            unset($messageData['excludelist']['unselect']);
        }
    }

    private function deriveCampaignTitle(array &$messageData): void
    {
        if (empty($messageData['campaigntitle'])) {
            if ($messageData['subject'] != '(no subject)') {
                $messageData['campaigntitle'] = $messageData['subject'];
            } else {
                $messageData['campaigntitle'] = '(no title)';
            }
        }
        //# copy subject to title
        if ($messageData['campaigntitle'] === '(no title)' && $messageData['subject'] !== '(no subject)') {
            $messageData['campaigntitle'] = $messageData['subject'];
        }
    }
}
