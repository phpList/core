<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Message;

class PasswordResetMessage
{
    private string $email;
    private string $token;

    public function __construct(string $email, string $token)
    {
        $this->email = $email;
        $this->token = $token;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
