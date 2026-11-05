<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Dto;

/** @SuppressWarnings("TooManyFields") */
class MessagePrecacheDto
{
    public string $replyToEmail = '';
    public ?string $replyToName = null;
    public ?string $fromName = null;
    public ?string $fromEmail = null;
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
    public bool $userSpecificUrl = false;
    public bool $googleTrack = false;
    /** @var array<string, mixed> the campaign's analytics settings (utm_*, pk_*) */
    public array $analyticsParameters = [];
    public array $adminAttributes = [];
}
