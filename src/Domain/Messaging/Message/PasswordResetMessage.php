<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message;

class PasswordResetMessage
{
    private string $email;
    private string $token;
    private ?string $locale;

    public function __construct(string $email, string $token, ?string $locale = null)
    {
        $this->email = $email;
        $this->token = $token;
        $this->locale = $locale;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Locale of the request that asked for the reset; null means the default locale.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
