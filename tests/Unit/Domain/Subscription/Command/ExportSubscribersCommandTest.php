<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Command;

use PhpList\Core\Domain\Subscription\Command\ExportSubscribersCommand;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvExporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExportSubscribersCommandTest extends TestCase
{
    private SubscriberCsvExporter&MockObject $exporter;
    private SubscriberAttributeDefinitionRepository&MockObject $definitions;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->exporter = $this->createMock(SubscriberCsvExporter::class);
        $this->definitions = $this->createMock(SubscriberAttributeDefinitionRepository::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $p = []) => strtr($id, $p));
        $this->tester = new CommandTester(
            new ExportSubscribersCommand($this->exporter, $this->definitions, $translator)
        );
    }

    private function csvResponse(string $contents): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'exp');
        file_put_contents($path, $contents);

        return new BinaryFileResponse($path);
    }

    public function testInvalidListIsRejected(): void
    {
        $this->exporter->expects($this->never())->method('exportToCsv');

        $this->tester->execute(['--list' => 'x']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testUnwritableOutputIsRejected(): void
    {
        $this->exporter->expects($this->never())->method('exportToCsv');

        $this->tester->execute(['--output' => '/no/such/dir/out.csv']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testExportsAllColumnsIncludingAttributesToStdout(): void
    {
        $definition = $this->createMock(SubscriberAttributeDefinition::class);
        $definition->method('getName')->willReturn('Country');
        $this->definitions->method('findAll')->willReturn([$definition]);

        $this->exporter->expects($this->once())->method('exportToCsv')
            ->with($this->callback(function (SubscriberFilter $f): bool {
                return $f->getListId() === 4
                    && in_array('email', $f->getColumns(), true)
                    && in_array('Country', $f->getColumns(), true);
            }))
            ->willReturn($this->csvResponse("id,email\n1,a@example.com\n"));

        $this->tester->execute(['--list' => '4']);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertSame("id,email\n1,a@example.com\n", $this->tester->getDisplay());
    }

    public function testWritesToOutputFile(): void
    {
        $this->definitions->method('findAll')->willReturn([]);
        $this->exporter->method('exportToCsv')->willReturn($this->csvResponse("id\n1\n"));
        $target = tempnam(sys_get_temp_dir(), 'out');

        $this->tester->execute(['--output' => $target]);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertSame("id\n1\n", file_get_contents($target));
        if (is_file($target)) {
            unlink($target);
        }
    }

    public function testNonFileResponseFails(): void
    {
        $this->definitions->method('findAll')->willReturn([]);
        $this->exporter->method('exportToCsv')->willReturn(new Response());

        $this->tester->execute([]);

        $this->assertSame(1, $this->tester->getStatusCode());
    }
}
