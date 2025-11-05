<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message;

/**
 * Message class for asynchronous subscriber confirmation email processing
 */
class SubscriptionConfirmationMessage
{
    private string $email;
    private string $uniqueId;
    private array $listIds;
    private bool $htmlEmail;

    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function __construct(
        string $email,
        string $uniqueId,
        array $listIds,
        bool $htmlEmail = false
    ) {
        $this->email = $email;
        $this->uniqueId = $uniqueId;
        $this->listIds = $listIds;
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

    public function getListIds(): array
    {
        return $this->listIds;
    }

    public function hasHtmlEmail(): bool
    {
        return $this->htmlEmail;
    }
}
