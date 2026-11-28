<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Command;

use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Subscription\Command\ImportSubscribersCommand;
use PhpList\Core\Domain\Subscription\Model\Dto\SubscriberImportOptions;
use PhpList\Core\Domain\Subscription\Service\SubscriberCsvImporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportSubscribersCommandTest extends TestCase
{
    private SubscriberCsvImporter&MockObject $importer;
    private AdministratorRepository&MockObject $adminRepository;
    private CommandTester $tester;
    private string $csv;

    protected function setUp(): void
    {
        $this->importer = $this->createMock(SubscriberCsvImporter::class);
        $this->adminRepository = $this->createMock(AdministratorRepository::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $p = []) => strtr($id, $p));
        $this->tester = new CommandTester(
            new ImportSubscribersCommand($this->importer, $this->adminRepository, $translator)
        );
        $this->csv = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($this->csv, "email\na@example.com\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->csv)) {
            unlink($this->csv);
        }
    }

    private function stats(array $override = []): array
    {
        return $override + ['created' => 1, 'updated' => 0, 'skipped' => 0, 'blacklisted' => 0,
            'invalid_email' => 0, 'errors' => []];
    }

    public function testMissingFileIsInvalid(): void
    {
        $this->importer->expects($this->never())->method('importFromCsv');

        $this->tester->execute(['--file' => '/no/such/file.csv', '--list' => '1']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testMissingListsIsInvalid(): void
    {
        $this->importer->expects($this->never())->method('importFromCsv');

        $this->tester->execute(['--file' => $this->csv, '--list' => 'abc,0']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testUnknownAdminIsInvalid(): void
    {
        $this->adminRepository->method('find')->with(9)->willReturn(null);
        $this->importer->expects($this->never())->method('importFromCsv');

        $this->tester->execute(['--file' => $this->csv, '--list' => '1', '--admin' => '9']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testImportsWithLegacyCliDefaults(): void
    {
        $this->importer->expects($this->once())->method('importFromCsv')
            ->with(
                $this->callback(fn (UploadedFile $f) => $f->getRealPath() === realpath($this->csv)),
                $this->callback(function (SubscriberImportOptions $o): bool {
                    return $o->listIds === [1, 2] && $o->updateExisting && $o->skipInvalidEmail
                        && !$o->notify && !$o->dryRun;
                }),
                null
            )
            ->willReturn($this->stats());

        $this->tester->execute(['--file' => $this->csv, '--list' => '1, 2,2']);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('Created: 1', $this->tester->getDisplay());
    }

    public function testFlagsAndAdminArePassedAndErrorsPrinted(): void
    {
        $admin = $this->createMock(Administrator::class);
        $this->adminRepository->method('find')->with(3)->willReturn($admin);
        $this->importer->expects($this->once())->method('importFromCsv')
            ->with(
                $this->anything(),
                $this->callback(fn (SubscriberImportOptions $o) => !$o->updateExisting && $o->notify && $o->dryRun),
                $admin
            )
            ->willReturn($this->stats(['errors' => ['Line 2: bad']]));

        $this->tester->execute([
            '--file' => $this->csv, '--list' => '1', '--admin' => '3',
            '--no-update' => true, '--notify' => true, '--dry-run' => true,
        ]);

        $this->assertStringContainsString('Line 2: bad', $this->tester->getDisplay());
    }
}
