<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use GuzzleHttp\Psr7\Utils;
use PhpList\Core\Domain\Messaging\Exception\AttachmentFileNotFoundException;
use PhpList\Core\Domain\Messaging\Model\Attachment;
use PhpList\Core\Domain\Messaging\Model\Dto\DownloadableAttachment;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\MimeTypes;

class AttachmentDownloadService
{
    public function __construct(
        #[Autowire('%phplist.attachment_repository_path%')] private readonly string $attachmentRepositoryPath = '/tmp',
    ) {
    }

    public function getDownloadable(Attachment $attachment): DownloadableAttachment
    {
        $filename = $attachment->getFilename();
        if ($filename === null || $filename === '') {
            throw new AttachmentFileNotFoundException();
        }

        $path = rtrim($this->attachmentRepositoryPath, '/');
        $filePath = $path . '/' . $filename;

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new AttachmentFileNotFoundException();
        }

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
}
