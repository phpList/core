<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\FileHelper;
use PhpList\Core\Domain\Common\OnceCacheGuard;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Exception\AttachmentCopyException;
use PhpList\Core\Domain\Messaging\Model\Attachment;
use PhpList\Core\Domain\Messaging\Repository\AttachmentRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class AttachmentAdder
{
    public function __construct(
        private readonly AttachmentRepository $attachmentRepository,
        private readonly TranslatorInterface $translator,
        private readonly EventLogManager $eventLogManager,
        private readonly OnceCacheGuard $onceCacheGuard,
        private readonly FileHelper $fileHelper,
        #[Autowire('attachment_download_url')] private readonly string $attachmentDownloadUrl,
        #[Autowire('attachment_repository_path')] private readonly string $attachmentRepositoryPath = '/tmp',
    ) {
    }

    public function add(Email $email, int $campaignId, OutputFormat $format): bool
    {
        $attachments = $this->attachmentRepository->findAttachmentsForMessage($campaignId);

        if (empty($attachments)) {
            return true;
        }

        if ($format === OutputFormat::Text) {
            $this->prependTextAttachmentNotice($email);
        }

        $totalSize = 0;
        $memoryLimit = $this->getMemoryLimit();

        foreach ($attachments as $att) {
            $totalSize += $att->getSize();
            if (!$this->hasMemoryForAttachment($totalSize, $memoryLimit, $campaignId)) {
                return false;
            }

            switch ($format) {
                case OutputFormat::Html:
                    if (!$this->handleHtmlAttachment($email, $att, $campaignId)) {
                        return false;
                    }
                    break;

                case OutputFormat::Text:
                    $userEmail = $email->getTo()[0]->getAddress();
                    // todo: add endpoint in rest-api project
                    $viewUrl = $this->attachmentDownloadUrl . '/?id=' . $att->getId() . '&uid=' . $userEmail;

                    $email->text(
                        $email->getTextBody()
                        . $att->getDescription() . "\n"
                        . $this->translator->trans('Location') . ': ' . $viewUrl . "\n\n"
                    );
                    break;
            }
        }

        return true;
    }

    private function getMemoryLimit(): int
    {
        $val = ini_get('memory_limit');
        sscanf($val, '%f%c', $number, $unit);

        return (int)($number * match (strtolower($unit ?? '')) {
            'g'     => 1024 ** 3,
            'm'     => 1024 ** 2,
            'k'     => 1024,
            default => 1,
        });
    }

    private function prependTextAttachmentNotice(Email $email): void
    {
        $pre = $this->translator->trans('This message contains attachments that can be viewed with a webbrowser');
        $email->text($email->getTextBody() . $pre . ":\n");
    }

    private function hasMemoryForAttachment(?int $totalSize, int $memoryLimit, int $campaignId): bool
    {
        // the 3 is roughly the size increase to encode the string
        if ($memoryLimit > 0 && (3 * $totalSize) > $memoryLimit) {
            $this->eventLogManager->log(
                '',
                $this->translator->trans(
                    'Insufficient memory to add attachment to campaign %campaignId% %totalSize% - %memLimit%',
                    [
                        '%campaignId%' => $campaignId,
                        '%totalSize%' => $totalSize,
                        '%memLimit%' => $memoryLimit
                    ]
                )
            );

            return false;
        }

        return true;
    }

    private function handleHtmlAttachment(Email $email, Attachment $att, int $campaignId): bool
    {
        $key = 'attaching_fail:' . sha1($campaignId . '|' . $att->getRemoteFile());
        if ($this->attachFromRepository($email, $att)) {
            return true;
        }

        if ($this->fileHelper->isValidFile($att->getRemoteFile())) {
            return $this->handleLocalAttachment($email, $att, $campaignId, $key);
        }

        $this->handleMissingAttachment($att, $campaignId, $key);

        return false;
    }

    private function attachFromRepository(Email $email, Attachment $att): bool
    {
        $attachmentPath = $this->attachmentRepositoryPath . '/' . $att->getFilename();

        if (!$this->fileHelper->isValidFile($attachmentPath)) {
            return false;
        }

        $contents = $this->fileHelper->readFileContents($attachmentPath);
        if ($contents === null) {
            return false;
        }

        $email->attachFromPath($contents, basename($att->getRemoteFile()), $att->getMimeType());

        return true;
    }

    private function handleLocalAttachment(Email $email, Attachment $att, int $campaignId, string $key): bool
    {
        $remoteFile = $att->getRemoteFile();
        $contents = $this->fileHelper->readFileContents($remoteFile);

        if ($contents === null) {
            $this->eventLogManager->log(
                page: '',
                entry: $this->translator->trans(
                    'failed to open attachment (%remoteFile%) to add to campaign %campaignId%',
                    [
                        '%remoteFile%' => $remoteFile,
                        '%campaignId%' => $campaignId,
                    ]
                )
            );

            return false;
        }

        $email->attachFromPath($contents, basename($remoteFile), $att->getMimeType());
        $this->copyAttachmentToRepository($att, $contents, $campaignId, $key);

        return true;
    }

    private function copyAttachmentToRepository(Attachment $att, string $contents, int $campaignId, string $key): void
    {
        $remoteFile = $att->getRemoteFile();
        [$name, $ext] = explode('.', basename($remoteFile));

        $newFile = tempnam($this->attachmentRepositoryPath, $name);
        $newFile .= '.' . $ext;
        $relativeName = basename($newFile);

        $fullPath = $this->attachmentRepositoryPath . '/' . $relativeName;

        $fileHandle = fopen($fullPath, 'w');
        if ($fileHandle === false) {
            $this->handleCopyFailure($remoteFile, $campaignId, $key);
            return;
        }

        fwrite($fileHandle, $contents);
        fclose($fileHandle);

        if (filesize($fullPath)) {
            $att->setFilename($relativeName);
            return;
        }

        $this->handleCopyFailure($remoteFile, $campaignId, $key);
    }

    private function handleCopyFailure(string $remoteFile, int $campaignId, string $key): void
    {
        if ($this->onceCacheGuard->firstTime($key, 3600)) {
            $this->eventLogManager->log(
                page: '',
                entry: 'Unable to make a copy of attachment ' . $remoteFile . ' in repository'
            );

            $errorMessage = $this->translator->trans(
                'Error, when trying to send campaign %campaignId% the attachment (%remoteFile%)'
                . ' could not be copied to the repository. Check for permissions.',
                [
                    '%campaignId%' => $campaignId,
                    '%remoteFile%' => $remoteFile,
                ]
            );

            throw new AttachmentCopyException($errorMessage);
        }

        // Not the first time => silently allow send to continue
    }

    private function handleMissingAttachment(Attachment $att, int $campaignId, string $key): void
    {
        $remoteFile = $att->getRemoteFile();

        if ($this->onceCacheGuard->firstTime($key, 3600)) {
            $this->eventLogManager->log(
                page: '',
                entry: $this->translator->trans(
                    'Attachment %remoteFile% does not exist',
                    [
                        '%remoteFile%' => $remoteFile,
                    ]
                )
            );

            $errorMessage = $this->translator->trans(
                'Error, when trying to send campaign %campaignId% the attachment (%remoteFile%)'
                . ' could not be found in the repository.',
                [
                    '%campaignId%' => $campaignId,
                    '%remoteFile%' => $remoteFile,
                ]
            );

            throw new AttachmentCopyException($errorMessage);
        }
    }
}
