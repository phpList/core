<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\ListMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\ListMessageRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\ListMessageManager;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ListMessageManagerTest extends TestCase
{
    private ListMessageRepository&MockObject $listMessageRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private ListMessageManager $manager;

    protected function setUp(): void
    {
        $this->listMessageRepository = $this->createMock(ListMessageRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->manager = new ListMessageManager(
            $this->listMessageRepository,
            $this->entityManager
        );
    }

    public function testAssociateMessageWithList(): void
    {
        $message = $this->createMock(Message::class);
        $subscriberList = $this->createMock(SubscriberList::class);
        
        $message->method('getId')->willReturn(1);
        $subscriberList->method('getId')->willReturn(2);
        
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (ListMessage $listMessage) {
                return $listMessage->getMessage()->getId() === 1
                    && $listMessage->getList()->getId() === 2
                    && $listMessage->getEntered() instanceof DateTime;
            }));
            
        $this->entityManager->expects($this->once())
            ->method('flush');
            
        $result = $this->manager->associateMessageWithList($message, $subscriberList);
        
        $this->assertInstanceOf(ListMessage::class, $result);
        $this->assertEquals(1, $result->getMessage()->getId());
        $this->assertEquals(2, $result->getList()->getId());
        $this->assertInstanceOf(DateTime::class, $result->getEntered());
    }
    
    public function testRemoveAssociation(): void
    {
        $message = $this->createMock(Message::class);
        $subscriberList = $this->createMock(SubscriberList::class);
        $listMessage = $this->createMock(ListMessage::class);
        
        $message->method('getId')->willReturn(1);
        $subscriberList->method('getId')->willReturn(2);
        
        $this->listMessageRepository->expects($this->once())
            ->method('getByMessageAndList')
            ->with($message, $subscriberList)
            ->willReturn($listMessage);
            
        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($listMessage);
            
        $this->manager->removeAssociation($message, $subscriberList);
    }
    
    public function testIsMessageAssociatedWithList(): void
    {
        $message = $this->createMock(Message::class);
        $subscriberList = $this->createMock(SubscriberList::class);
        
        $message->method('getId')->willReturn(1);
        $subscriberList->method('getId')->willReturn(2);
        
        $this->listMessageRepository->expects($this->once())
            ->method('isMessageAssociatedWithList')
            ->with($message, $subscriberList)
            ->willReturn(true);
            
        $result = $this->manager->isMessageAssociatedWithList($message, $subscriberList);
        
        $this->assertTrue($result);
    }
    
    public function testAssociateMessageWithLists(): void
    {
        $message = $this->createMock(Message::class);
        $subscriberList1 = $this->createMock(SubscriberList::class);
        $subscriberList2 = $this->createMock(SubscriberList::class);
        
        $message->method('getId')->willReturn(1);
        $subscriberList1->method('getId')->willReturn(2);
        $subscriberList2->method('getId')->willReturn(3);
        
        // We expect associateMessageWithList to be called twice, once for each list
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function (ListMessage $listMessage) {
                return $listMessage->getMessage()->getId() === 1
                    && ($listMessage->getList()->getId() === 2 || $listMessage->getList()->getId() === 3)
                    && $listMessage->getEntered() instanceof DateTime;
            }));
            
        $this->entityManager->expects($this->exactly(2))
            ->method('flush');
            
        $this->manager->associateMessageWithLists($message, [$subscriberList1, $subscriberList2]);
    }
    
    public function testRemoveAllListAssociationsForMessage(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(1);
        
        $this->listMessageRepository->expects($this->once())
            ->method('removeAllListAssociationsForMessage')
            ->with($message);
            
        $this->manager->removeAllListAssociationsForMessage($message);
    }
}
