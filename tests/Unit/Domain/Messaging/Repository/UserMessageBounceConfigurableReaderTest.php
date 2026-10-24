<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Repository;

use DateTime;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceConfigurableReader;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceElasticsearchReader;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserMessageBounceConfigurableReaderTest extends TestCase
{
    private UserMessageBounceRepository&MockObject $databaseReader;
    private UserMessageBounceElasticsearchReader&MockObject $elasticsearchReader;

    protected function setUp(): void
    {
        $this->databaseReader = $this->createMock(UserMessageBounceRepository::class);
        $this->elasticsearchReader = $this->createMock(UserMessageBounceElasticsearchReader::class);
    }

    public function testDelegatesToElasticsearchWhenEnabled(): void
    {
        $reader = new UserMessageBounceConfigurableReader($this->databaseReader, $this->elasticsearchReader, true);

        $this->elasticsearchReader->expects($this->once())
            ->method('getCountByMessageId')
            ->with(42)
            ->willReturn(7);
        $this->databaseReader->expects($this->never())->method('getCountByMessageId');

        $this->assertSame(7, $reader->getCountByMessageId(42));
    }

    public function testDelegatesToDatabaseWhenDisabled(): void
    {
        $reader = new UserMessageBounceConfigurableReader($this->databaseReader, $this->elasticsearchReader, false);
        $start = new DateTime('2026-01-01');
        $end = new DateTime('2026-01-31');

        $this->databaseReader->expects($this->once())
            ->method('countBetween')
            ->with($start, $end)
            ->willReturn(3);
        $this->elasticsearchReader->expects($this->never())->method('countBetween');

        $this->assertSame(3, $reader->countBetween($start, $end));
    }

    public function testExistsByMessageIdAndUserIdDelegatesToActiveReader(): void
    {
        $reader = new UserMessageBounceConfigurableReader($this->databaseReader, $this->elasticsearchReader, true);

        $this->elasticsearchReader->expects($this->once())
            ->method('existsByMessageIdAndUserId')
            ->with(5, 9)
            ->willReturn(true);

        $this->assertTrue($reader->existsByMessageIdAndUserId(5, 9));
    }

    public function testGetByUserIdDelegatesToActiveReader(): void
    {
        $reader = new UserMessageBounceConfigurableReader($this->databaseReader, $this->elasticsearchReader, false);

        $this->databaseReader->expects($this->once())
            ->method('getByUserId')
            ->with(3)
            ->willReturn([]);

        $this->assertSame([], $reader->getByUserId(3));
    }
}
