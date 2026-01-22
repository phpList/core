<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

/** @SuppressWarnings(TooManyFields) */
class MessagePrecacheDto
{
    public string $replyToEmail = '';
    public ?string $replyToName = null;
    public ?string $fromName = null;
    public ?string $fromEmail = null;
    public ?string $to = null;
    public string $subject = '';
    public string $content = '';
    public string $textContent = '';
    public string $footer = '';
    public ?string $textFooter = null;
    public string $htmlFooter = '';
    public bool $htmlFormatted = false;
    public ?string $sendFormat = null;
    public ?string $template = null;
    public ?string $templateText = null;
    public ?int $templateId = null;
//    public string $htmlCharset= 'UTF-8';
//    public string $textCharset= 'UTF-8';
    public bool $userSpecificUrl = false;
    public ?string $googleTrack = null;
    public array $adminAttributes = [];
}
