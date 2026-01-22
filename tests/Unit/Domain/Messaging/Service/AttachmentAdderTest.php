<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\FileHelper;
use PhpList\Core\Domain\Common\OnceCacheGuard;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Exception\AttachmentCopyException;
use PhpList\Core\Domain\Messaging\Model\Attachment;
use PhpList\Core\Domain\Messaging\Repository\AttachmentRepository;
use PhpList\Core\Domain\Messaging\Service\AttachmentAdder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AttachmentAdderTest extends TestCase
{
    private AttachmentRepository&MockObject $attachmentRepository;
    private TranslatorInterface&MockObject $translator;
    private EventLogManager&MockObject $eventLogManager;
    private FileHelper&MockObject $fileHelper;

    private function makeAdder(string $downloadUrl = 'https://attachments.test') : AttachmentAdder
    {
        $cache = $this->createMock(CacheInterface::class);
        // default: firstTime returns true once per unique key
        $cache->method('has')->willReturn(false);
        $cache->method('set')->willReturn(true);
        $onceCacheGuard = new OnceCacheGuard($cache);

        return new AttachmentAdder(
            attachmentRepository: $this->attachmentRepository,
            translator: $this->translator,
            eventLogManager: $this->eventLogManager,
            onceCacheGuard: $onceCacheGuard,
            fileHelper: $this->fileHelper,
            attachmentDownloadUrl: $downloadUrl,
            attachmentRepositoryPath: '/repo',
        );
    }

    protected function setUp(): void
    {
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->eventLogManager = $this->createMock(EventLogManager::class);
        $this->fileHelper = $this->createMock(FileHelper::class);

        // default translator: return the message id itself for easier asserts
        $this->translator
            ->method('trans')
            ->willReturnCallback(static fn(string $id, array $params = []) => $id);
    }

    public function testAddReturnsTrueWhenNoAttachments(): void
    {
        $this->attachmentRepository
            ->method('findAttachmentsForMessage')
            ->willReturn([]);

        $adder = $this->makeAdder();
        $email = (new Email())->to(new Address('user@example.com'));

        $this->assertTrue($adder->add($email, 123, OutputFormat::Text));
        $this->assertSame('', (string)$email->getTextBody());
    }

    public function testTextModePrependsNoticeAndLinks(): void
    {
        $att = $this->createMock(Attachment::class);
        $att->method('getId')->willReturn(42);
        $att->method('getDescription')->willReturn('Doc description');
        $this->attachmentRepository->method('findAttachmentsForMessage')->willReturn([$att]);

        $email = (new Email())->to(new Address('user@example.com'));
        $adder = $this->makeAdder(downloadUrl: 'https://dl.example');

        $ok = $adder->add($email, 10, OutputFormat::Text);
        $this->assertTrue($ok);

        $body = (string)$email->getTextBody();
        $this->assertStringContainsString(
            'This message contains attachments that can be viewed with a webbrowser',
            $body
        );
        $this->assertStringContainsString('Doc description', $body);
        $this->assertStringContainsString('Location', $body);
        $this->assertStringContainsString('https://dl.example/?id=42&uid=user@example.com', $body);
    }

    public function testHtmlUsesRepositoryFileIfExists(): void
    {
        $att = $this->createMock(Attachment::class);
        $att->method('getFilename')->willReturn('stored/file.pdf');
        $att->method('getRemoteFile')->willReturn('/originals/file.pdf');
        $att->method('getMimeType')->willReturn('application/pdf');
        $att->method('getSize')->willReturn(10);

        $this->attachmentRepository
            ->method('findAttachmentsForMessage')
            ->willReturn([$att]);

        // repository path file exists and can be read
        $this->fileHelper
            ->method('isValidFile')
            ->willReturnCallback(
                function (string $path): bool {
                    return $path === '/repo/stored/file.pdf';
                }
            );
        $this->fileHelper
            ->method('readFileContents')
            ->willReturnCallback(
                function (string $path): ?string {
                    return $path === '/repo/stored/file.pdf' ? 'PDF-DATA' : null;
                }
            );

        $email = (new Email())->to(new Address('user@example.com'));
        $adder = $this->makeAdder();

        $ok = $adder->add($email, 77, OutputFormat::Html);
        $this->assertTrue($ok);

        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('file.pdf', $attachments[0]->getFilename());
    }

    public function testHtmlLocalFileUnreadableLogsAndReturnsFalse(): void
    {
        $att = $this->createMock(Attachment::class);
        $att->method('getFilename')->willReturn(null);
        $att->method('getRemoteFile')->willReturn('/local/missing.txt');
        $att->method('getMimeType')->willReturn('text/plain');
        $att->method('getSize')->willReturn(10);

        $this->attachmentRepository->method('findAttachmentsForMessage')->willReturn([$att]);

        // Not in repository; local path considered valid file, but cannot be read
        $this->fileHelper->method('isValidFile')->willReturn(true);
        $this->fileHelper->method('readFileContents')->willReturn(null);

        $this->eventLogManager->expects($this->once())->method('log');

        $email = (new Email())->to(new Address('user@example.com'));
        $adder = $this->makeAdder();

        $ok = $adder->add($email, 501, OutputFormat::Html);
        $this->assertFalse($ok);
        $this->assertCount(0, $email->getAttachments());
    }

    public function testCopyFailureThrowsOnFirstTime(): void
    {
        $att = $this->createMock(Attachment::class);
        $att->method('getFilename')->willReturn(null);
        $att->method('getRemoteFile')->willReturn('/local/ok.pdf');
        $att->method('getMimeType')->willReturn('application/pdf');
        $att->method('getSize')->willReturn(10);

        $this->attachmentRepository
            ->method('findAttachmentsForMessage')
            ->willReturn([$att]);

        // Repository path should not exist, local file should be readable
        $this->fileHelper
            ->method('isValidFile')
            ->willReturnCallback(
                function (string $path): bool {
                    if ($path === '/repo/') {
                        // repository lookup should fail
                        return false;
                    }
                    return $path === '/local/ok.pdf';
                }
            );
        $this->fileHelper
            ->method('readFileContents')
            ->willReturnCallback(
                function (string $path): ?string {
                    return $path === '/local/ok.pdf' ? 'PDF' : null;
                }
            );
        // copy to repository fails
        $this->fileHelper
            ->method('writeFileToDirectory')
            ->willReturn(null);

        $this->eventLogManager
            ->expects($this->once())
            ->method('log');

        $email = (new Email())->to(new Address('user@example.com'));
        $adder = $this->makeAdder();

        $this->expectException(AttachmentCopyException::class);
        $adder->add($email, 321, OutputFormat::Html);
    }

    public function testMissingAttachmentThrowsOnFirstTime(): void
    {
        $att = $this->createMock(Attachment::class);
        $att->method('getFilename')->willReturn(null);
        $att->method('getRemoteFile')->willReturn('/local/not-exist.bin');
        $att->method('getMimeType')->willReturn('application/octet-stream');
        $att->method('getSize')->willReturn(5);

        $this->attachmentRepository
            ->method('findAttachmentsForMessage')
            ->willReturn([$att]);

        // Not in repository; local path invalid -> missing
        $this->fileHelper
            ->method('isValidFile')
            ->willReturn(false);

        $this->eventLogManager
            ->expects($this->once())
            ->method('log');

        $email = (new Email())->to(new Address('user@example.com'));
        $adder = $this->makeAdder();

        $this->expectException(AttachmentCopyException::class);
        $adder->add($email, 999, OutputFormat::Html);
    }
}
