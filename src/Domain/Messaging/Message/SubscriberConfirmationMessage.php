<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message;

/**
 * Message class for asynchronous subscriber confirmation email processing
 */
class SubscriberConfirmationMessage
{
    private string $email;
    private string $uniqueId;
    private bool $htmlEmail;

    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function __construct(
        string $email,
        string $uniqueId,
        bool $htmlEmail = false
    ) {
        $this->email = $email;
        $this->uniqueId = $uniqueId;
        $this->htmlEmail = $htmlEmail;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function hasHtmlEmail(): bool
    {
        return $this->htmlEmail;
    }
}
