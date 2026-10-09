<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class MessageParser
{
    private SubscriberRepository $subscriberRepository;

    public function __construct(SubscriberRepository $subscriberRepository)
    {
        $this->subscriberRepository = $subscriberRepository;
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
        $candidate = $this->extractUserHeader($text);
        if ($candidate) {
            $id = $this->resolveUserIdentifier($candidate);
            if ($id) {
                return $id;
            }
        }

        $emails = $this->extractEmails($text);

        return $this->findFirstSubscriberId($emails);
    }

    private function extractUserHeader(string $text): ?string
    {
        if (preg_match('/^(?:X-ListMember|X-User):\s*(?P<user>[^\r\n]+)/mi', $text, $matches)) {
            $user = trim($matches['user']);

            return $user !== '' ? $user : null;
        }

        return null;
    }

    private function resolveUserIdentifier(string $user): ?int
    {
        if (filter_var($user, FILTER_VALIDATE_EMAIL)) {
            return $this->subscriberRepository->findOneByEmail($user)?->getId();
        }

        if (ctype_digit($user)) {
            return (int) $user;
        }

        return $this->subscriberRepository->findOneByEmail($user)?->getId();
    }

    private function extractEmails(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+/i', $text, $matches);
        if (empty($matches[0])) {
            return [];
        }
        $norm = array_map('strtolower', $matches[0]);

        return array_values(array_unique($norm));
    }

    private function findFirstSubscriberId(array $emails): ?int
    {
        foreach ($emails as $email) {
            $id = $this->subscriberRepository->findOneByEmail($email)?->getId();
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }
}
