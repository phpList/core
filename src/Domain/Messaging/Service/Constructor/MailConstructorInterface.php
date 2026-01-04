<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Constructor;

interface MailConstructorInterface
{
    /**
     * Build HTML and Text representations of a message body using a given subject.
     *
     * @param string $content The message body, can contain HTML or plain text
     * @param string $subject The message subject used for template replacements
     *
     * @return array{0:string,1:string} [htmlContent, textContent]
     */
    public function __invoke(string $content, string $subject = ''): array;
}
