<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use InvalidArgumentException;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Messaging\Model\Dto\CreateMessageDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageMetadataDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Service\Manager\ListMessageManager;
use PhpList\Core\Domain\Messaging\Service\Manager\MessageManager;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;

/**
 * Creates a campaign and queues it (status "submitted"), as phplist3's `-p send` did on the commandline.
 *
 * @SuppressWarnings("CouplingBetweenObjects")
 */
class CliCampaignCreator
{
    public function __construct(
        private readonly MessageManager $messageManager,
        private readonly ListMessageManager $listMessageManager,
        private readonly SubscriberListRepository $listRepository,
        private readonly AdministratorRepository $administratorRepository,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    public function create(
        string $subject,
        string $body,
        array $listReferences,
        ?string $from = null,
        ?int $adminId = null,
    ): Message {
        $lists = $this->resolveLists($listReferences);
        $owner = $this->resolveOwner($adminId);

        $dto = new CreateMessageDto(
            content: new MessageContentDto(
                subject: $subject,
                text: $body,
                footer: $this->configProvider->getValue(ConfigOption::MessageFooter) ?? '',
            ),
            format: new MessageFormatDto(sendFormat: 'HTML'),
            metadata: new MessageMetadataDto(status: MessageStatus::Draft),
            options: new MessageOptionsDto(fromField: $from ?? $this->defaultFrom()),
            schedule: new MessageScheduleDto(embargo: 'now'),
        );

        $message = $this->messageManager->createMessage($dto, $owner);
        $this->listMessageManager->associateMessageWithLists($message, $lists);
        $this->messageManager->updateStatus($message, MessageStatus::Submitted);

        return $message;
    }

    /**
     * @return SubscriberList[]
     * @throws InvalidArgumentException when a list or the owner cannot be resolved
     */
    private function resolveLists(array $references): array
    {
        $lists = [];
        foreach ($references as $reference) {
            $reference = trim($reference);
            $list = ctype_digit($reference)
                ? $this->listRepository->findById((int) $reference)
                : $this->listRepository->findOneBy(['name' => $reference]);
            if ($list === null) {
                throw new InvalidArgumentException(sprintf('List "%s" not found.', $reference));
            }
            $lists[$list->getId()] = $list;
        }
        if ($lists === []) {
            throw new InvalidArgumentException('At least one list is required.');
        }

        return array_values($lists);
    }

    private function resolveOwner(?int $adminId): Administrator
    {
        $admin = $adminId !== null
            ? $this->administratorRepository->find($adminId)
            : $this->administratorRepository->findOneBy([], ['id' => 'ASC']);
        if ($admin === null) {
            throw new InvalidArgumentException('No administrator found to own the campaign.');
        }

        return $admin;
    }

    private function defaultFrom(): string
    {
        return trim(
            ($this->configProvider->getValue(ConfigOption::MessageFromName) ?? '') . ' '
            . ($this->configProvider->getValue(ConfigOption::MessageFromAddress) ?? '')
        );
    }
}
