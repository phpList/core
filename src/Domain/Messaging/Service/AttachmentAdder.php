<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Common\OnceCacheGuard;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Messaging\Exception\AttachmentCopyException;
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
            $pre = $this->translator->trans('This message contains attachments that can be viewed with a webbrowser');
            $email->text($email->getTextBody() . $pre . ":\n");
        }

        $totalSize = 0;
        $memoryLimit = $this->getMemoryLimit();

        foreach ($attachments as $att) {
            $totalSize += $att->getSize();
            // the 3 is roughly the size increase to encode the string
            if ($memoryLimit > 0 && (3 * $totalSize) > $memoryLimit) {
                $this->eventLogManager->log(
                    '',
                    $this->translator->trans(
                        'Insufficient memory to add attachment to campaign %campaignId% %totalSize% - %memLimit%',
                        [
                            '%campaignId%' => $campaignId,
                            '%totalSize%' => $totalSize,
                            '%memLimit%' =>  $memoryLimit
                        ]
                    )
                );
                return false;
            }
            $key = 'attaching_fail:' . sha1($campaignId . '|' . $att->getRemoteFile());

            switch ($format) {
                case OutputFormat::Html:
                    $attachmentPath = $this->attachmentRepositoryPath . '/' . $att->getFilename();
                    if (is_file($attachmentPath) && filesize($attachmentPath)) {
                        $fp = fopen($attachmentPath, 'r');
                        if ($fp) {
                            $contents = fread($fp, filesize($attachmentPath));
                            fclose($fp);
                            $email->attachFromPath($contents, basename($att->getRemoteFile()), $att->getMimeType());
                        }
                    } elseif (is_file($att->getRemoteFile()) && filesize($att->getRemoteFile())) {
                        // handle local filesystem attachments
                        $fp = fopen($att->getRemoteFile(), 'r');
                        if ($fp) {
                            $contents = fread($fp, filesize($att->getRemoteFile()));
                            fclose($fp);
                            $email->attachFromPath($contents, basename($att->getRemoteFile()), $att->getMimeType());
                            [$name, $ext] = explode('.', basename($att->getRemoteFile()));
                            // create a temporary file to make sure to use a unique file name to store with
                            $newFile = tempnam($this->attachmentRepositoryPath, $name);
                            $newFile .= '.'.$ext;
                            $newFile = basename($newFile);
                            $fd = fopen($this->attachmentRepositoryPath . '/' . $newFile, 'w');
                            fwrite($fd, $contents);
                            fclose($fd);
                            // check that it was successful
                            if (filesize($this->attachmentRepositoryPath . '/' . $newFile)) {
                                $att->setFilename($newFile);
                            } else {
                                if ($this->onceCacheGuard->firstTime($key, 3600)) {
                                    $this->eventLogManager->log(
                                        page: '',
                                        entry: 'Unable to make a copy of attachment '.$att->getRemoteFile().' in repository'
                                    );
                                    $errorMessage = $this->translator->trans(
                                        'Error, when trying to send campaign %campaignId% the attachment (%remoteFile%)'
                                        . ' could not be copied to the repository. Check for permissions.',
                                        [
                                            '%campaignId%' => $campaignId,
                                            '%remoteFile%' => $att->getRemoteFile(),
                                        ]
                                    );

                                    throw new AttachmentCopyException($errorMessage);
                                } else {
                                    return false;
                                }
                            }
                        } else {
                            $this->eventLogManager->log(
                                page: '',
                                entry: $this->translator->trans(
                                    'failed to open attachment (%remoteFile%) to add to campaign %campaignId%',
                                    [
                                        '%remoteFile%' => $att->getRemoteFile(),
                                        '%campaignId%' => $campaignId,
                                    ]
                                )
                            );
                           return false;
                        }
                    } else {
                        //# as above, avoid sending it many times
                        if ($this->onceCacheGuard->firstTime($key, 3600)) {
                            $this->eventLogManager->log(
                                page: '',
                                entry: $this->translator->trans(
                                    'Attachment %remoteFile% does not exist',
                                    [
                                        '%remoteFile%' => $att->getRemoteFile(),
                                    ]
                                )
                            );
                            $errorMessage = $this->translator->trans(
                                'Error, when trying to send campaign %campaignId% the attachment (%remoteFile%)'
                                . ' could not be found in the repository.',
                                [
                                    '%campaignId%' => $campaignId,
                                    '%remoteFile%' => $att->getRemoteFile(),
                                ]
                            );
                            throw new AttachmentCopyException($errorMessage);
                        }
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
}
