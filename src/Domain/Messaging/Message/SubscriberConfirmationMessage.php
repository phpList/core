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
    private ?string $locale;

    public function __construct(
        string $email,
        string $uniqueId,
        bool $htmlEmail = false,
        ?string $locale = null
    ) {
        $this->email = $email;
        $this->uniqueId = $uniqueId;
        $this->htmlEmail = $htmlEmail;
        $this->locale = $locale;
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

    /**
     * Explicit email locale; when null the handler resolves it from the subscriber's subscribe page.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
