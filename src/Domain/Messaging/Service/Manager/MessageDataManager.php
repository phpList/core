<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use HTMLPurifier;
use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\MessageData;
use PhpList\Core\Domain\Messaging\Repository\MessageDataRepository;

class MessageDataManager
{
    /**
     * The only settings storeMessageData() accepts. Everything else in messagedata is either kept on the message
     * itself (subject, from field, schedule, ...) or maintained by the system (status, sizes, ETA, statistics,
     * ...) and must not be overwritten by a caller-supplied value.
     */
    public const WRITABLE_SETTINGS = [
        'google_track',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'pk_source',
        'pk_medium',
        'pk_campaign',
        'notify_start',
        'notify_end',
        'excludelist',
    ];

    public function __construct(
        private readonly MessageDataRepository $messageDataRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HTMLPurifier $purifier,
    ) {
    }

    /**
     * Mirrors the legacy setMessageData behavior with safe sanitization and persistence.
     *
     * @param Message $campaign
     * @param string $name
     * @param mixed $value
     */
    public function setMessageData(Message $campaign, string $name, mixed $value): void
    {
        if ($name === 'PHPSESSID' || $name === session_name()) {
            return;
        }

        $value = $this->normalizeValueByName($name, $value);

        if (is_array($value) || is_object($value)) {
            $value = 'SER:' . serialize($value);
        }

        $entity = $this->getOrCreateMessageDataEntity($campaign, $name);
        $entity->setData($value !== null ? (string) $value : null);
    }

    /**
     * Stores a set of campaign settings the way phplist3's storemessage.php does for the campaign form.
     *
     * Checkboxes that are not submitted when unchecked must be listed in $checkboxes: they are all reset to '0'
     * first and then set again by $data when they were submitted. Every entry of $data is stored under its own
     * name, booleans as '1' / '0'.
     *
     * Only the names in WRITABLE_SETTINGS are accepted; nothing is stored when any other name is given.
     * The campaign must already have an id, so a new campaign has to be flushed first.
     *
     * @param array<string, mixed> $data setting name => value
     * @param string[] $checkboxes names of the checkbox settings that are part of this save
     * @throws InvalidArgumentException when a name is not in WRITABLE_SETTINGS
     */
    public function storeMessageData(Message $campaign, array $data, array $checkboxes = []): void
    {
        $notWritable = array_diff([...array_keys($data), ...$checkboxes], self::WRITABLE_SETTINGS);
        if ($notWritable !== []) {
            throw new InvalidArgumentException(
                'Message data cannot be set for: ' . implode(', ', array_map('strval', $notWritable))
            );
        }

        foreach ($checkboxes as $name) {
            $this->setMessageData($campaign, $name, '0');
        }

        foreach ($data as $name => $value) {
            $this->setMessageData($campaign, (string) $name, is_bool($value) ? ($value ? '1' : '0') : $value);
        }
    }

    private function normalizeValueByName(string $name, mixed $value)
    {
        return match ($name) {
            'subject', 'campaigntitle' => is_string($value) ? strip_tags($value) : $value,
            'message' => is_string($value) ? $this->purifier->purify($value) : $value,
            'excludelist' => is_array($value) ? array_filter($value, fn ($val) => is_numeric($val)) : $value,
            'footer' => is_string($value) ? preg_replace('/<!--.*?-->/', '', $value) : $value,
            default => $value,
        };
    }

    private function getOrCreateMessageDataEntity(Message $campaign, string $name)
    {
        $entity = $this->messageDataRepository->findByIdAndName($campaign->getId(), $name);
        if (!$entity instanceof MessageData) {
            $entity = (new MessageData())
                ->setId($campaign->getId())
                ->setName($name);
            $this->entityManager->persist($entity);
        }

        return $entity;
    }
}
