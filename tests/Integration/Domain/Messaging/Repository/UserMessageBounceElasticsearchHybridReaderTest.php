<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Messaging\Repository;

use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceElasticsearchHybridReader;
use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Covers the hybrid reader methods that combine Elasticsearch (mocked here) with real DB joins
 * against Subscriber/Subscription, Message, Bounce and UserMessage - the ElasticsearchClientInterface
 * mock stands in for the "big table" side, while everything else is a real Doctrine entity persisted
 * against SQLite via DatabaseTestTrait, mirroring UserMessageBounceRepositoryTest's fixtures.
 */
class UserMessageBounceElasticsearchHybridReaderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ElasticsearchClientInterface&MockObject $client;
    private UserMessageBounceElasticsearchHybridReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $this->reader = new UserMessageBounceElasticsearchHybridReader($this->client, 'phplist_', $this->entityManager);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testGetListBounceTotalsMergesElasticsearchCountsWithSubscriberData(): void
    {
        $admin = (new Administrator())
            ->setLoginName('admin')
            ->setEmail('admin@example.com');
        $this->entityManager->persist($admin);

        $targetList = (new SubscriberList())->setName('Target list')->setOwner($admin);
        $otherList = (new SubscriberList())->setName('Other list')->setOwner($admin);
        $this->entityManager->persist($targetList);
        $this->entityManager->persist($otherList);

        $subscriber1 = (new Subscriber('one@example.com'))->setConfirmed(true)->setBlacklisted(false);
        $subscriber2 = (new Subscriber('two@example.com'))->setConfirmed(false)->setBlacklisted(true);
        $subscriber3 = (new Subscriber('three@example.com'))->setConfirmed(true)->setBlacklisted(false);
        $this->entityManager->persist($subscriber1);
        $this->entityManager->persist($subscriber2);
        $this->entityManager->persist($subscriber3);
        $this->entityManager->flush();

        $subscription1 = (new Subscription())->setSubscriber($subscriber1)->setSubscriberList($targetList);
        $subscription2 = (new Subscription())->setSubscriber($subscriber2)->setSubscriberList($targetList);
        $subscription3 = (new Subscription())->setSubscriber($subscriber3)->setSubscriberList($otherList);
        $this->entityManager->persist($subscription1);
        $this->entityManager->persist($subscription2);
        $this->entityManager->persist($subscription3);
        $this->entityManager->flush();

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with(
                'phplist_user_message_bounce',
                $this->callback(function (array $query) use ($subscriber1, $subscriber2): bool {
                    return $query['query']['bool']['filter'][0]['terms']['userId'] === [
                        $subscriber1->getId(),
                        $subscriber2->getId(),
                    ];
                }),
            )
            ->willReturn([
                'aggregations' => [
                    'by_field' => [
                        'buckets' => [
                            ['key' => $subscriber1->getId(), 'doc_count' => 2],
                            ['key' => $subscriber2->getId(), 'doc_count' => 1],
                        ],
                    ],
                ],
            ]);

        $rows = $this->reader->getListBounceTotals($targetList->getId());

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

    public function testGetCampaignBounceTotalsMergesElasticsearchCountsWithMessageData(): void
    {
        $admin = (new Administrator())
            ->setLoginName('admin')
            ->setEmail('admin@example.com');
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $message1 = $this->createMessage('Campaign one', $admin);
        $message2 = $this->createMessage('Campaign two', $admin);
        $this->entityManager->persist($message1);
        $this->entityManager->persist($message2);
        $this->entityManager->flush();

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with(
                'phplist_user_message_bounce',
                $this->callback(function (array $query) use ($message1, $message2): bool {
                    return $query['query']['bool']['filter'][0]['terms']['messageId'] === [
                        $message1->getId(),
                        $message2->getId(),
                    ];
                }),
            )
            ->willReturn([
                'aggregations' => [
                    'by_field' => [
                        'buckets' => [
                            ['key' => $message1->getId(), 'doc_count' => 4],
                        ],
                    ],
                ],
            ]);

        $rows = $this->reader->getCampaignBounceTotals();

        self::assertSame(
            [
                [
                    'message_id' => $message1->getId(),
                    'subject' => 'Campaign one',
                    'total_bounces' => 4,
                ],
            ],
            $rows
        );
    }

    public function testGetPaginatedWithJoinNoRelationHydratesMatchingBounceEntitiesAndSkipsMissingOnes(): void
    {
        $bounce1 = new Bounce(status: 'new');
        $bounce2 = new Bounce(status: 'processed');
        $this->entityManager->persist($bounce1);
        $this->entityManager->persist($bounce2);
        $this->entityManager->flush();

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with(
                'phplist_user_message_bounce',
                $this->callback(function (array $query): bool {
                    return $query['query'] === ['range' => ['idSort' => ['gt' => 0]]]
                        && $query['size'] === 10;
                }),
            )
            ->willReturn([
                'hits' => [
                    'hits' => [
                        ['_source' => [
                            'id' => 1,
                            'idSort' => 1,
                            'userId' => 5,
                            'messageId' => 10,
                            'bounceId' => $bounce1->getId(),
                            'time' => '2026-01-01T00:00:00+00:00',
                        ]],
                        ['_source' => [
                            'id' => 2,
                            'idSort' => 2,
                            'userId' => 6,
                            'messageId' => 11,
                            // No matching Bounce row - must be skipped, mirroring the SQL inner join.
                            'bounceId' => 999999,
                            'time' => '2026-01-02T00:00:00+00:00',
                        ]],
                        ['_source' => [
                            'id' => 3,
                            'idSort' => 3,
                            'userId' => 7,
                            'messageId' => 12,
                            'bounceId' => $bounce2->getId(),
                            'time' => '2026-01-03T00:00:00+00:00',
                        ]],
                    ],
                ],
            ]);

        $rows = $this->reader->getPaginatedWithJoinNoRelation(0, 10);

        self::assertCount(2, $rows);
        self::assertSame(1, $rows[0]['umb']->getId());
        self::assertSame($bounce1, $rows[0]['bounce']);
        self::assertSame(3, $rows[1]['umb']->getId());
        self::assertSame($bounce2, $rows[1]['bounce']);
    }

    public function testGetUserMessageHistoryWithBouncesMergesSentMessagesWithBounceDocs(): void
    {
        $admin = (new Administrator())
            ->setLoginName('admin')
            ->setEmail('admin@example.com');
        $this->entityManager->persist($admin);

        $subscriber = new Subscriber('history@example.com');
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $message1 = $this->createMessage('First', $admin);
        $message2 = $this->createMessage('Second', $admin);
        $this->entityManager->persist($message1);
        $this->entityManager->persist($message2);
        $this->entityManager->flush();

        $bounce = new Bounce(status: 'new');
        $this->entityManager->persist($bounce);
        $this->entityManager->flush();

        $userMessage1 = new UserMessage($subscriber, $message1);
        $userMessage1->setStatus(UserMessageStatus::Sent);
        $userMessage2 = new UserMessage($subscriber, $message2);
        $userMessage2->setStatus(UserMessageStatus::Sent);
        $this->entityManager->persist($userMessage1);
        $this->entityManager->persist($userMessage2);
        $this->entityManager->flush();

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with(
                'phplist_user_message_bounce',
                $this->callback(function (array $query) use ($subscriber): bool {
                    return $query['query'] === ['term' => ['userId' => $subscriber->getId()]];
                }),
            )
            ->willReturn([
                'hits' => [
                    'hits' => [
                        ['_source' => [
                            'id' => 1,
                            'idSort' => 1,
                            'userId' => $subscriber->getId(),
                            'messageId' => $message1->getId(),
                            'bounceId' => $bounce->getId(),
                            'time' => '2026-01-01T00:00:00+00:00',
                        ]],
                    ],
                ],
            ]);

        $rows = $this->reader->getUserMessageHistoryWithBounces($subscriber);

        self::assertCount(2, $rows);

        $rowsByMessageId = [];
        foreach ($rows as $row) {
            $rowsByMessageId[$row['um']->getMessage()->getId()] = $row;
        }

        self::assertSame($bounce, $rowsByMessageId[$message1->getId()]['b']);
        self::assertSame($bounce->getId(), $rowsByMessageId[$message1->getId()]['umb']->getBounceId());
        self::assertNull($rowsByMessageId[$message2->getId()]['b']);
        self::assertNull($rowsByMessageId[$message2->getId()]['umb']);
    }

    private function createMessage(string $subject, Administrator $owner): Message
    {
        return new Message(
            new MessageFormat(true, 'text'),
            new MessageSchedule(null, null, null, null, null),
            new MessageMetadata(),
            new MessageContent($subject),
            new MessageOptions(),
            $owner
        );
    }
}
