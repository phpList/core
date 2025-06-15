<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Messaging\Message\AsyncEmailMessage;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for processing asynchronous email messages
 */
#[AsMessageHandler]
class AsyncEmailMessageHandler
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Process an asynchronous email message by sending the email
     */
    public function __invoke(AsyncEmailMessage $message): void
    {
        $this->emailService->sendEmailSync(
            $message->getEmail(),
            $message->getCc(),
            $message->getBcc(),
            $message->getReplyTo(),
            $message->getAttachments()
        );
    }
}
