<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use HTMLPurifier;
use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\MessageData;
use PhpList\Core\Domain\Messaging\Repository\MessageDataRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\MessageDataManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MessageDataManagerTest extends TestCase
{
    private MessageDataRepository&MockObject $repository;
    private MessageDataManager $manager;
    private Message&MockObject $campaign;
    /** @var array<string, MessageData> */
    private array $stored = [];

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MessageDataRepository::class);
        $this->repository->method('findByIdAndName')->willReturnCallback(
            fn (int $id, string $name): ?MessageData => $this->stored[$name] ?? null
        );
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (MessageData $data): void {
            $this->stored[$data->getName()] = $data;
        });

        $this->manager = new MessageDataManager(
            $this->repository,
            $entityManager,
            $this->createMock(HTMLPurifier::class)
        );
        $this->campaign = $this->createMock(Message::class);
        $this->campaign->method('getId')->willReturn(7);
    }

    /** @return array<string, string|null> */
    private function storedValues(): array
    {
        return array_map(static fn (MessageData $data): ?string => $data->getData(), $this->stored);
    }

    public function testEveryEntryIsStoredUnderItsOwnName(): void
    {
        $this->manager->storeMessageData($this->campaign, [
            'utm_source' => 'newsletter',
            'notify_start' => 'a@example.com',
            'excludelist' => [3, 'x', 5],
        ]);

        $values = $this->storedValues();
        self::assertSame('newsletter', $values['utm_source']);
        self::assertSame('a@example.com', $values['notify_start']);
        self::assertSame('SER:' . serialize([0 => 3, 2 => 5]), $values['excludelist']);
        self::assertSame(7, $this->stored['utm_source']->getId());
    }

    public function testBooleansAreStoredAsOneAndZero(): void
    {
        $this->manager->storeMessageData($this->campaign, ['google_track' => true, 'pk_source' => false]);

        self::assertSame(['google_track' => '1', 'pk_source' => '0'], $this->storedValues());
    }

    public function testCheckboxesAreResetBeforeTheSubmittedValuesAreStored(): void
    {
        $this->manager->storeMessageData(
            $this->campaign,
            ['google_track' => '1'],
            ['google_track', 'pk_source']
        );

        self::assertSame(['google_track' => '1', 'pk_source' => '0'], $this->storedValues());
    }

    public function testExistingValuesAreOverwritten(): void
    {
        $this->manager->storeMessageData($this->campaign, ['utm_source' => 'old']);
        $this->manager->storeMessageData($this->campaign, ['utm_source' => 'new']);

        self::assertSame(['utm_source' => 'new'], $this->storedValues());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string[]}> */
    public static function notWritableProvider(): array
    {
        return [
            'system setting' => [['status' => 'submitted'], []],
            'message column' => [['subject' => 'x'], []],
            'session name' => [['PHPSESSID' => 'abc'], []],
            'checkbox' => [[], ['resetstats']],
        ];
    }

    /**
     * @dataProvider notWritableProvider
     * @param array<string, mixed> $data
     * @param string[] $checkboxes
     */
    public function testNothingIsStoredWhenAnyNameIsNotWritable(array $data, array $checkboxes): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->manager->storeMessageData($this->campaign, ['utm_medium' => 'email'] + $data, $checkboxes);
        } finally {
            self::assertSame([], $this->storedValues());
        }
    }
}
