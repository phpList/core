<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Mail;

use DateTimeImmutable;
use IMAP\Connection;

interface MailReaderInterface
{
    public function open(string $mailbox, ?string $user = null, ?string $password = null, int $options = 0): Connection;
    public function numMessages(Connection $link): int;
    public function fetchHeader(Connection $link, int $msgNo): string;
    public function headerDate(Connection $link, int $msgNo): DateTimeImmutable;
    public function body(Connection $link, int $msgNo): string;
    public function delete(Connection $link, int $msgNo): void;
    public function close(Connection $link, bool $expunge): void;
}
