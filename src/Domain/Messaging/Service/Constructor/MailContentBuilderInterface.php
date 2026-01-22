<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Constructor;

use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;

interface MailContentBuilderInterface
{
    /**
     * Build HTML and Text representations of a message body using a given subject.
     *
     * @param MessagePrecacheDto $messagePrecacheDto The message precache data containing content and subject
     *
     * @return array{0:string,1:string} [htmlContent, textContent]
     */
    public function __invoke(MessagePrecacheDto $messagePrecacheDto, ?int $campaignId = null): array;
}
