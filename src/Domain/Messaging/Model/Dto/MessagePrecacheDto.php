<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

/** @SuppressWarnings(TooManyFields) */
class MessagePrecacheDto
{
    public string $replyToEmail = '';
    public string $replyToName;
    public string $fromName;
    public string $fromEmail;
    public string $to;
    public string $subject;
    public string $content;
    public string $textContent = '';
    public string $footer;
    public string $textFooter;
    public string $htmlFooter = '';
    public bool $htmlFormatted;
    public string $sendFormat;
    public ?string $template = null;
    public ?string $templateText = null;
    public ?int $templateId = null;
//    public string $htmlCharset= 'UTF-8';
//    public string $textCharset= 'UTF-8';
    public bool $userSpecificUrl;
    public string $googleTrack;
    public array $adminAttributes = [];
}
