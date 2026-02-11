<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use GuzzleHttp\Psr7\Utils;
use PhpList\Core\Domain\Messaging\Exception\AttachmentFileNotFoundException;
use PhpList\Core\Domain\Messaging\Exception\SubscriberNotFoundException;
use PhpList\Core\Domain\Messaging\Model\Attachment;
use PhpList\Core\Domain\Messaging\Model\Dto\DownloadableAttachment;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\MimeTypes;

class AttachmentDownloadService
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        #[Autowire('%phplist.attachment_repository_path%')] private readonly string $attachmentRepositoryPath = '/tmp',
    ) {
    }

    public function getDownloadable(Attachment $attachment, string $uid): DownloadableAttachment
    {
        $this->validateUid($uid);

        $original = $attachment->getFilename();
        if ($original === null || $original === '') {
            throw new AttachmentFileNotFoundException('Attachment has no filename.');
        }
        $filename = basename($original);
        $filePath = $this->validateFilePath($filename, $original);

        $mimeType = $attachment->getMimeType()
            ?? MimeTypes::getDefault()->guessMimeType($filePath)
            ?? 'application/octet-stream';

        $size = filesize($filePath);
        $size = $size === false ? null : $size;

        /** @var StreamInterface $stream */
        $stream = Utils::streamFor(Utils::tryFopen($filePath, 'rb'));

        return new DownloadableAttachment(
            filename: $filename,
            mimeType: $mimeType,
            size: $size,
            content: $stream,
        );
    }

    private function validateUid(string $uid): void
    {
        if ($uid === Attachment::FORWARD) {
            return;
        }

        $subscriber = $this->subscriberRepository->findOneByEmail($uid);
        if ($subscriber === null) {
            throw new SubscriberNotFoundException();
        }
    }

    private function validateFilePath(string $filename, ?string $original): string
    {
        if ($filename === '' || $filename !== $original) {
            throw new AttachmentFileNotFoundException('Invalid attachment filename: ' . $original);
        }

        $baseDir = realpath($this->attachmentRepositoryPath);
        if ($baseDir === false) {
            throw new AttachmentFileNotFoundException('Attachment repository path does not exist.');
        }

        $filePath = $baseDir . DIRECTORY_SEPARATOR . $filename;
        $realPath = realpath($filePath);

        if ($realPath === false ||
            !str_starts_with($realPath, $baseDir . DIRECTORY_SEPARATOR) ||
            !is_file($realPath) ||
            !is_readable($realPath)
        ) {
            throw new AttachmentFileNotFoundException('Attachment file not available');
        }

        return $filePath;
    }
}
