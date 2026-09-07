<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Repository;

use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceElasticsearchHybridReader;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceReportConfigurableReader;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserMessageBounceReportConfigurableReaderTest extends TestCase
{
    private UserMessageBounceRepository&MockObject $databaseReader;
    private UserMessageBounceElasticsearchHybridReader&MockObject $elasticsearchReader;

    protected function setUp(): void
    {
        $this->databaseReader = $this->createMock(UserMessageBounceRepository::class);
        $this->elasticsearchReader = $this->createMock(UserMessageBounceElasticsearchHybridReader::class);
    }

    public function testGetListBounceTotalsDelegatesToElasticsearchWhenEnabled(): void
    {
        $reader = new UserMessageBounceReportConfigurableReader(
            $this->databaseReader,
            $this->elasticsearchReader,
            true,
        );
        $expected = [['subscriber_id' => 1, 'email' => 'a@example.com', 'confirmed' => true,
            'blacklisted' => false, 'total_bounces' => 2]];

        $this->elasticsearchReader->expects($this->once())
            ->method('getListBounceTotals')
            ->with(10)
            ->willReturn($expected);
        $this->databaseReader->expects($this->never())->method('getListBounceTotals');

        $this->assertSame($expected, $reader->getListBounceTotals(10));
    }

    public function testGetCampaignBounceTotalsDelegatesToDatabaseWhenDisabled(): void
    {
        $reader = new UserMessageBounceReportConfigurableReader(
            $this->databaseReader,
            $this->elasticsearchReader,
            false,
        );
        $expected = [['message_id' => 1, 'subject' => 'Hello', 'total_bounces' => 4]];

        $this->databaseReader->expects($this->once())
            ->method('getCampaignBounceTotals')
            ->with(7)
            ->willReturn($expected);
        $this->elasticsearchReader->expects($this->never())->method('getCampaignBounceTotals');

        $this->assertSame($expected, $reader->getCampaignBounceTotals(7));
    }
}
