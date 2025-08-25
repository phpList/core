<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Mail;

use DateTimeImmutable;
use IMAP\Connection;
use RuntimeException;

class NativeImapMailReader implements MailReaderInterface
{
    public function open(string $mailbox, ?string $user = null, ?string $password = null, int $options = 0): Connection
    {
        $link = @imap_open($mailbox, (string)$user, (string)$password, $options);
        if ($link === false) {
            throw new RuntimeException('Cannot open mailbox: '.(imap_last_error() ?: 'unknown error'));
        }
        return $link;
    }

    public function numMessages(Connection $link): int
    {
        return imap_num_msg($link);
    }

    public function fetchHeader(Connection $link, int $msgNo): string
    {
        return imap_fetchheader($link, $msgNo) ?: '';
    }

    public function headerDate(Connection $link, int $msgNo): DateTimeImmutable
    {
        $info = imap_headerinfo($link, $msgNo);
        $date = $info->date ?? null;

        return $date ? new DateTimeImmutable($date) : new DateTimeImmutable();
    }

    public function body(Connection $link, int $msgNo): string
    {
        return imap_body($link, $msgNo) ?: '';
    }

    public function delete(Connection $link, int $msgNo): void
    {
        imap_delete($link, (string)$msgNo);
    }

    public function close(Connection $link, bool $expunge): void
    {
        $expunge ? imap_close($link, CL_EXPUNGE) : imap_close($link);
    }
}
