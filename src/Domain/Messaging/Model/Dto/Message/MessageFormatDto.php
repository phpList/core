<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto\Message;

class MessageFormatDto
{
    public function __construct(
        public readonly bool $htmlFormated,
        public readonly string $sendFormat,
        public readonly array $formatOptions,
    ) {
    }
}
