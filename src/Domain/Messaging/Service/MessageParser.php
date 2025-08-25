<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class MessageParser
{
    public function __construct(
        private readonly SubscriberManager $subscriberManager,
    ) {
    }
    public function decodeBody(string $header, string $body): string
    {
        $transferEncoding = '';
        if (preg_match('/Content-Transfer-Encoding: ([\w-]+)/i', $header, $regs)) {
            $transferEncoding = strtolower($regs[1]);
        }

        return match ($transferEncoding) {
            'quoted-printable' => quoted_printable_decode($body),
            'base64' => base64_decode($body) ?: '',
            default => $body,
        };
    }

    public function findMessageId(string $text): ?string
    {
        if (preg_match('/(?:X-MessageId|X-Message): (.*)\r\n/iU', $text, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    public function findUserId(string $text): ?int
    {
        // Try X-ListMember / X-User first
        if (preg_match('/(?:X-ListMember|X-User): (.*)\r\n/iU', $text, $match)) {
            $user = trim($match[1]);
            if (str_contains($user, '@')) {
                return $this->subscriberManager->getSubscriberByEmail($user)?->getId();
            } elseif (preg_match('/^\d+$/', $user)) {
                return (int)$user;
            } elseif ($user !== '') {
                return $this->subscriberManager->getSubscriberByEmail($user)?->getId();
            }
        }
        // Fallback: parse any email in the body and see if it is a subscriber
        if (preg_match_all('/[._a-zA-Z0-9-]+@[.a-zA-Z0-9-]+/', $text, $regs)) {
            foreach ($regs[0] as $email) {
                $id = $this->subscriberManager->getSubscriberByEmail($email)?->getId();
                if ($id) {
                    return $id;
                }
            }
        }

        return null;
    }
}
