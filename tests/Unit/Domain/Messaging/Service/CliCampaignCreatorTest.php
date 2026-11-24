<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use InvalidArgumentException;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Messaging\Model\Dto\CreateMessageDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Service\CliCampaignCreator;
use PhpList\Core\Domain\Messaging\Service\Manager\ListMessageManager;
use PhpList\Core\Domain\Messaging\Service\Manager\MessageManager;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CliCampaignCreatorTest extends TestCase
{
    private MessageManager&MockObject $messageManager;
    private ListMessageManager&MockObject $listMessageManager;
    private SubscriberListRepository&MockObject $listRepository;
    private AdministratorRepository&MockObject $administratorRepository;
    private ConfigProvider&MockObject $configProvider;
    private CliCampaignCreator $creator;

    protected function setUp(): void
    {
        $this->messageManager = $this->createMock(MessageManager::class);
        $this->listMessageManager = $this->createMock(ListMessageManager::class);
        $this->listRepository = $this->createMock(SubscriberListRepository::class);
        $this->administratorRepository = $this->createMock(AdministratorRepository::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);

        $this->creator = new CliCampaignCreator(
            $this->messageManager,
            $this->listMessageManager,
            $this->listRepository,
            $this->administratorRepository,
            $this->configProvider,
        );
    }

    private function list(int $id): SubscriberList&MockObject
    {
        $list = $this->createMock(SubscriberList::class);
        $list->method('getId')->willReturn($id);

        return $list;
    }

    public function testCreatesAndSubmitsCampaignWithListsFromIdAndName(): void
    {
        $byId = $this->list(1);
        $byName = $this->list(2);
        $admin = $this->createMock(Administrator::class);
        $message = $this->createMock(Message::class);

        $this->listRepository->method('findById')->with(1)->willReturn($byId);
        $this->listRepository->method('findOneBy')->with(['name' => 'Weekly'])->willReturn($byName);
        $this->administratorRepository->method('findOneBy')->with([], ['id' => 'ASC'])->willReturn($admin);
        $this->configProvider->method('getValue')->willReturnMap([
            [ConfigOption::MessageFooter, 'the footer'],
            [ConfigOption::MessageFromName, 'News'],
            [ConfigOption::MessageFromAddress, 'news@example.com'],
        ]);

        $this->messageManager->expects($this->once())->method('createMessage')
            ->with(
                $this->callback(function (CreateMessageDto $dto): bool {
                    $this->assertSame('Subject', $dto->getContent()->subject);
                    $this->assertSame('<p>Body</p>', $dto->getContent()->text);
                    $this->assertSame('the footer', $dto->getContent()->footer);
                    $this->assertSame('News news@example.com', $dto->getOptions()->fromField);
                    $this->assertSame('now', $dto->getSchedule()->embargo);

                    return true;
                }),
                $admin
            )
            ->willReturn($message);
        $this->listMessageManager->expects($this->once())->method('associateMessageWithLists')
            ->with($message, [$byId, $byName]);
        $this->messageManager->expects($this->once())->method('updateStatus')
            ->with($message, MessageStatus::Submitted);

        $result = $this->creator->create('Subject', '<p>Body</p>', ['1', 'Weekly']);

        $this->assertSame($message, $result);
    }

    public function testUsesGivenFromAndAdmin(): void
    {
        $admin = $this->createMock(Administrator::class);
        $this->listRepository->method('findById')->willReturn($this->list(1));
        $this->administratorRepository->expects($this->once())->method('find')->with(5)->willReturn($admin);
        $this->administratorRepository->expects($this->never())->method('findOneBy');
        $this->messageManager->method('createMessage')
            ->with(
                $this->callback(fn (CreateMessageDto $dto) => $dto->getOptions()->fromField === 'Me <me@x.org>'),
                $admin
            )
            ->willReturn($this->createMock(Message::class));

        $this->creator->create('S', 'B', ['1'], 'Me <me@x.org>', 5);
    }

    public function testDuplicateListReferencesAreAssociatedOnce(): void
    {
        $list = $this->list(1);
        $this->listRepository->method('findById')->willReturn($list);
        $this->listRepository->method('findOneBy')->willReturn($list);
        $this->administratorRepository->method('findOneBy')->willReturn($this->createMock(Administrator::class));
        $this->messageManager->method('createMessage')->willReturn($this->createMock(Message::class));

        $this->listMessageManager->expects($this->once())->method('associateMessageWithLists')
            ->with($this->anything(), [$list]);

        $this->creator->create('S', 'B', ['1', 'Same']);
    }

    public function testUnknownListThrowsAndCreatesNothing(): void
    {
        $this->listRepository->method('findOneBy')->willReturn(null);
        $this->messageManager->expects($this->never())->method('createMessage');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List "Nope" not found.');

        $this->creator->create('S', 'B', ['Nope']);
    }

    public function testNoListsThrows(): void
    {
        $this->messageManager->expects($this->never())->method('createMessage');

        $this->expectException(InvalidArgumentException::class);

        $this->creator->create('S', 'B', []);
    }

    public function testMissingAdministratorThrowsAndCreatesNothing(): void
    {
        $this->listRepository->method('findById')->willReturn($this->list(1));
        $this->administratorRepository->method('findOneBy')->willReturn(null);
        $this->messageManager->expects($this->never())->method('createMessage');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No administrator found');

        $this->creator->create('S', 'B', ['1']);
    }
}
