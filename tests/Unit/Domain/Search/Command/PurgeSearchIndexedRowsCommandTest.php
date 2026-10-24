<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Search\Command;

use DateInterval;
use PhpList\Core\Domain\Search\Client\ElasticsearchClientInterface;
use PhpList\Core\Domain\Search\Command\PurgeSearchIndexedRowsCommand;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchIndexableInterface;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchPurgeProviderInterface;
use PhpList\Core\Domain\Search\Registry\SearchPurgeProviderRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeSearchIndexedRowsCommandTest extends TestCase
{
    private ElasticsearchClientInterface&MockObject $client;

    private function makeFakeRow(int $id): SearchIndexableInterface&MockObject
    {
        $row = $this->createMock(SearchIndexableInterface::class);
        $row->method('getSearchDocumentId')->willReturn((string) $id);

        return $row;
    }

    private function commandTesterWithProviders(SearchPurgeProviderInterface ...$providers): CommandTester
    {
        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $registry = new SearchPurgeProviderRegistry($providers);
        $command = new PurgeSearchIndexedRowsCommand($registry, $this->client);

        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }

    public function testDeletesOnlyRowsConfirmedInElasticsearch(): void
    {
        $confirmedRow = $this->makeFakeRow(1);
        $unconfirmedRow = $this->makeFakeRow(2);

        $provider = $this->createMock(SearchPurgeProviderInterface::class);
        $provider->method('getAlias')->willReturn('some_alias');
        $provider->method('getRetentionPeriod')->willReturn(new DateInterval('P1M'));
        $provider->method('getSearchIndexName')->willReturn('phplist_some_alias');
        $provider->method('fetchBatchOlderThan')->willReturnOnConsecutiveCalls(
            [$confirmedRow, $unconfirmedRow],
            [],
        );

        $tester = $this->commandTesterWithProviders($provider);
        $this->client->method('search')->willReturn([
            'hits' => ['hits' => [['_id' => '1']]],
        ]);

        $provider->expects($this->once())->method('deleteByIds')->with([1])->willReturn(1);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('not found in', $output);
        $this->assertStringContainsString('deleted 1 row(s), skipped 1 unconfirmed', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testDryRunDoesNotDeleteAnything(): void
    {
        $row = $this->makeFakeRow(1);

        $provider = $this->createMock(SearchPurgeProviderInterface::class);
        $provider->method('getAlias')->willReturn('some_alias');
        $provider->method('getRetentionPeriod')->willReturn(new DateInterval('P1M'));
        $provider->method('getSearchIndexName')->willReturn('phplist_some_alias');
        $provider->method('fetchBatchOlderThan')->willReturnOnConsecutiveCalls([$row], []);

        $tester = $this->commandTesterWithProviders($provider);
        $this->client->method('search')->willReturn(['hits' => ['hits' => [['_id' => '1']]]]);

        $provider->expects($this->never())->method('deleteByIds');

        $tester->execute(['--dry-run' => true]);

        $this->assertStringContainsString('would delete', $tester->getDisplay());
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testSkipsProvidersWithoutARetentionPeriod(): void
    {
        $provider = $this->createMock(SearchPurgeProviderInterface::class);
        $provider->method('getAlias')->willReturn('some_alias');
        $provider->method('getRetentionPeriod')->willReturn(null);

        $tester = $this->commandTesterWithProviders($provider);
        $provider->expects($this->never())->method('fetchBatchOlderThan');

        $tester->execute([]);

        $this->assertStringContainsString(
            'No purge providers with a configured retention period',
            $tester->getDisplay(),
        );
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testFailsWhenAliasHasNoRetentionPeriodConfigured(): void
    {
        $provider = $this->createMock(SearchPurgeProviderInterface::class);
        $provider->method('getAlias')->willReturn('some_alias');
        $provider->method('getRetentionPeriod')->willReturn(null);

        $tester = $this->commandTesterWithProviders($provider);

        $tester->execute(['alias' => 'some_alias']);

        $this->assertStringContainsString('No retention period configured', $tester->getDisplay());
        $this->assertSame(1, $tester->getStatusCode());
    }
}
