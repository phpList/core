<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use Symfony\Component\Mime\Email;

class EmailBuilder
{
    public function __construct(
        private readonly string $googleSenderId,
        private readonly bool $useAmazonSes,
        private readonly bool $usePrecedenceHeader,
    ) {
    }

    public function buildPhplistEmail(
        string $messageId,
        string $destinationEmail,
        bool $inBlast = true,
    ): Email {
        $email = (new Email());

        $email->getHeaders()->addTextHeader('X-MessageID', $messageId);
        $email->getHeaders()->addTextHeader('X-ListMember', $destinationEmail);
        if ($this->googleSenderId !== '') {
            $email->getHeaders()->addTextHeader('Feedback-ID', sprintf('%s:%s', $messageId, $this->googleSenderId));
        }

        if (!$this->useAmazonSes && $this->usePrecedenceHeader) {
            $email->getHeaders()->addTextHeader('Precedence', 'bulk');
        }

        if ($inBlast) {
            $email->getHeaders()->addTextHeader('X-Blast', '1');
        }

        return $email;
    }
}
