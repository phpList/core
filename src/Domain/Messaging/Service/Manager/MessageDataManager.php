<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use HTMLPurifier;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\MessageData;
use PhpList\Core\Domain\Messaging\Repository\MessageDataRepository;

class MessageDataManager
{
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

        // todo: remove this once we have a proper way to handle targetlists
//        if ($name === 'targetlist' && is_array($value)) {
//            $this->listMessageRepository->removeAllListAssociationsForMessage($campaign);
//
//            if (!empty($value['all']) || !empty($value['allactive'])) {
//                // todo:  should be with $GLOBALS['subselect'] filter for access control
//                foreach ($this->subscriberListRepository->getAllActive() as $list) {
//                    $listMessage = (new ListMessage())
//                        ->setMessage($campaign)
//                        ->setList($list);
//                    $this->listMessageRepository->persist($listMessage);
//                }
//                // once we used "all" to set all, unset it, to avoid confusion trying to unselect lists
//                unset($value['all']);
//            } else {
//                foreach ($value as $listId => $val) {
//                    // see #16940 - ignore a list called "unselect" which is there to allow unselecting all
//                    if ($listId !== 'unselect') {
//                        $list = $this->subscriberListRepository->find($listId);
//                        $listMessage = (new ListMessage())
//                            ->setMessage($campaign)
//                            ->setList($list);
//                        $this->listMessageRepository->persist($listMessage);
//                    }
//                }
//            }
//        }

        if (is_array($value) || is_object($value)) {
            $value = 'SER:' . serialize($value);
        }

        $entity = $this->getOrCreateMessageDataEntity($campaign, $name);
        $entity->setData($value !== null ? (string) $value : null);
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
