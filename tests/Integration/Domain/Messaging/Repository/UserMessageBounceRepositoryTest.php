<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Messaging\Repository;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserMessageBounceRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private UserMessageBounceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->repository = self::getContainer()->get(UserMessageBounceRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testGetListBounceTotalsReturnsAggregatedBouncesPerSubscriberForList(): void
    {
        $admin = (new Administrator())
            ->setLoginName('admin')
            ->setEmail('admin@example.com');
        $this->entityManager->persist($admin);

        $targetList = (new SubscriberList())
            ->setName('Target list')
            ->setOwner($admin);
        $otherList = (new SubscriberList())
            ->setName('Other list')
            ->setOwner($admin);
        $this->entityManager->persist($targetList);
        $this->entityManager->persist($otherList);

        $subscriber1 = (new Subscriber('one@example.com'))
            ->setConfirmed(true)
            ->setBlacklisted(false);
        $subscriber2 = (new Subscriber('two@example.com'))
            ->setConfirmed(false)
            ->setBlacklisted(true);
        $subscriber3 = (new Subscriber('three@example.com'))
            ->setConfirmed(true)
            ->setBlacklisted(false);

        $this->entityManager->persist($subscriber1);
        $this->entityManager->persist($subscriber2);
        $this->entityManager->persist($subscriber3);
        $this->entityManager->flush();

        $subscription1 = (new Subscription())
            ->setSubscriber($subscriber1)
            ->setSubscriberList($targetList);
        $subscription2 = (new Subscription())
            ->setSubscriber($subscriber2)
            ->setSubscriberList($targetList);
        $subscription3 = (new Subscription())
            ->setSubscriber($subscriber3)
            ->setSubscriberList($otherList);

        $this->entityManager->persist($subscription1);
        $this->entityManager->persist($subscription2);
        $this->entityManager->persist($subscription3);

        $bounce1 = new Bounce();
        $bounce2 = new Bounce();
        $bounce3 = new Bounce();
        $bounce4 = new Bounce();

        $this->entityManager->persist($bounce1);
        $this->entityManager->persist($bounce2);
        $this->entityManager->persist($bounce3);
        $this->entityManager->persist($bounce4);
        $this->entityManager->flush();

        $date = new DateTime();

        $umb1 = (new UserMessageBounce($bounce1->getId(), $date))
            ->setUserId($subscriber1->getId())
            ->setMessageId(10);
        $umb2 = (new UserMessageBounce($bounce2->getId(), $date))
            ->setUserId($subscriber1->getId())
            ->setMessageId(11);
        $umb3 = (new UserMessageBounce($bounce3->getId(), $date))
            ->setUserId($subscriber2->getId())
            ->setMessageId(12);
        $umb4 = (new UserMessageBounce($bounce4->getId(), $date))
            ->setUserId($subscriber3->getId())
            ->setMessageId(13);

        $this->entityManager->persist($umb1);
        $this->entityManager->persist($umb2);
        $this->entityManager->persist($umb3);
        $this->entityManager->persist($umb4);
        $this->entityManager->flush();

        $rows = $this->repository->getListBounceTotals($targetList->getId());

        self::assertSame(
            [
                [
                    'subscriber_id' => $subscriber1->getId(),
                    'email' => 'one@example.com',
                    'confirmed' => true,
                    'blacklisted' => false,
                    'total_bounces' => 2,
                ],
                [
                    'subscriber_id' => $subscriber2->getId(),
                    'email' => 'two@example.com',
                    'confirmed' => false,
                    'blacklisted' => true,
                    'total_bounces' => 1,
                ],
            ],
            $rows
        );
    }
}
