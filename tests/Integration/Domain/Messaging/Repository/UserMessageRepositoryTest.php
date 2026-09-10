<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Messaging\Repository;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserMessageRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use ModelTestTrait;

    private UserMessageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->repository = self::getContainer()->get(UserMessageRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testCountSentSinceCountsOnlySentMessagesAfterGivenTime(): void
    {
        $admin = (new Administrator())->setLoginName('t');
        $this->entityManager->persist($admin);

        $campaign = new Message(
            new MessageFormat(true, 'text'),
            new MessageSchedule(1, null, 3, null, null),
            new MessageMetadata(MessageStatus::Sent),
            new MessageContent('Hello world!'),
            new MessageOptions(),
            $admin
        );
        $this->entityManager->persist($campaign);

        $sentBeforeThreshold = new Subscriber('before@example.com');
        $sentAfterThreshold = new Subscriber('after@example.com');
        $notSentAfterThreshold = new Subscriber('not-sent@example.com');
        $this->entityManager->persist($sentBeforeThreshold);
        $this->entityManager->persist($sentAfterThreshold);
        $this->entityManager->persist($notSentAfterThreshold);
        $this->entityManager->flush();

        $since = new DateTime('2026-01-01 00:00:00');

        $before = new UserMessage($sentBeforeThreshold, $campaign);
        $before->setStatus(UserMessageStatus::Sent);
        $this->setSubjectProperty($before, 'createdAt', new DateTime('2025-12-31 23:00:00'));
        $this->entityManager->persist($before);

        $after = new UserMessage($sentAfterThreshold, $campaign);
        $after->setStatus(UserMessageStatus::Sent);
        $this->setSubjectProperty($after, 'createdAt', new DateTime('2026-01-01 01:00:00'));
        $this->entityManager->persist($after);

        $notSent = new UserMessage($notSentAfterThreshold, $campaign);
        $notSent->setStatus(UserMessageStatus::Todo);
        $this->setSubjectProperty($notSent, 'createdAt', new DateTime('2026-01-01 02:00:00'));
        $this->entityManager->persist($notSent);

        $this->entityManager->flush();

        self::assertSame(1, $this->repository->countSentSince($since));
    }
}