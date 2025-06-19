<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Exception;
use PhpList\Core\Domain\Analytics\Model\LinkTrackUmlClick;
use PhpList\Core\Domain\Analytics\Model\UserMessageView;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Model\UserMessageForward;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\Domain\Subscription\Service\SubscriberDeletionService;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SubscriberDeletionServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ?SubscriberDeletionService $subscriberDeletionService = null;
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->subscriberDeletionService = self::getContainer()->get(SubscriberDeletionService::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testDeleteSubscriberWithRelatedDataDoesNotThrowDoctrineError(): void
    {
        $admin = new Administrator();
        $this->entityManager->persist($admin);

        $msg = new Message(
            new MessageFormat(true, MessageFormat::FORMAT_TEXT),
            new MessageSchedule(1, null, 3, null, null),
            new MessageMetadata('done'),
            new MessageContent('Owned by Admin 1!'),
            new MessageOptions(),
            $admin
        );
        $this->entityManager->persist($msg);

        $subscriber = new Subscriber();
        $subscriber->setEmail('test-delete@example.com');
        $subscriber->setConfirmed(true);
        $subscriber->setHtmlEmail(true);
        $subscriber->setBlacklisted(false);
        $subscriber->setDisabled(false);
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $subscriberId = $subscriber->getId();
        $this->assertNotNull($subscriberId, 'Subscriber ID should not be null');

        $subscriberList = new SubscriberList();
        $subscriberList->setDescription('Test List Description');
        $this->entityManager->persist($subscriberList);

        $subscription = new Subscription();
        $subscription->setSubscriber($subscriber);
        $subscription->setSubscriberList($subscriberList);
        $this->entityManager->persist($subscription);

        $linkTrackUmlClick = new LinkTrackUmlClick();
        $linkTrackUmlClick->setMessageId(1);
        $linkTrackUmlClick->setUserId($subscriberId);
        $this->entityManager->persist($linkTrackUmlClick);

        $userMessage = new UserMessage($subscriber, $msg);
        $userMessage->setStatus('sent');
        $this->entityManager->persist($userMessage);

        $userMessageBounce = new UserMessageBounce(1);
        $userMessageBounce->setUserId($subscriberId);
        $userMessageBounce->setMessageId(1);
        $this->entityManager->persist($userMessageBounce);

        $userMessageForward = new UserMessageForward();
        $userMessageForward->setUserId($subscriberId);
        $userMessageForward->setMessageId(1);
        $this->entityManager->persist($userMessageForward);

        $userMessageView = new UserMessageView();
        $userMessageView->setMessageId(1);
        $userMessageView->setUserid($subscriberId);
        $this->entityManager->persist($userMessageView);

        $this->entityManager->flush();

        try {
            $this->subscriberDeletionService->deleteLeavingBlacklist($subscriber);
            $this->entityManager->flush();
            $this->assertTrue(true, 'No exception was thrown');
        } catch (Exception $e) {
            $this->fail('Exception was thrown: ' . $e->getMessage());
        }

        // Verify the subscriber was deleted
        $deletedSubscriber = $this->entityManager->getRepository(Subscriber::class)->find($subscriberId);
        $this->assertNull($deletedSubscriber, 'Subscriber should be deleted');

        // Verify related entities were deleted
        $subscriptions = $this->entityManager->getRepository(Subscription::class)->findBy(['subscriber' => $subscriber]);
        $this->assertEmpty($subscriptions, 'Subscriptions should be deleted');

        $linkTrackUmlClicks = $this->entityManager->getRepository(LinkTrackUmlClick::class)->findBy(['userId' => $subscriberId]);
        $this->assertEmpty($linkTrackUmlClicks, 'LinkTrackUmlClicks should be deleted');

        $userMessages = $this->entityManager->getRepository(UserMessage::class)->findBy(['user' => $subscriber]);
        $this->assertEmpty($userMessages, 'UserMessages should be deleted');

        $userMessageBounces = $this->entityManager->getRepository(UserMessageBounce::class)->findBy(['userId' => $subscriberId]);
        $this->assertEmpty($userMessageBounces, 'UserMessageBounces should be deleted');

        $userMessageForwards = $this->entityManager->getRepository(UserMessageForward::class)->findBy(['userId' => $subscriberId]);
        $this->assertEmpty($userMessageForwards, 'UserMessageForwards should be deleted');

        $userMessageViews = $this->entityManager->getRepository(UserMessageView::class)->findBy(['userId' => $subscriberId]);
        $this->assertEmpty($userMessageViews, 'UserMessageViews should be deleted');
    }
}
