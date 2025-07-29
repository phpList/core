<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ImportSubscriberDto
{
    #[Assert\NotBlank]
    public string $email;

    #[Assert\Type('bool')]
    public bool $confirmed;

    #[Assert\Type('bool')]
    public bool $blacklisted;

    #[Assert\Type('bool')]
    public bool $htmlEmail;

    #[Assert\Type('bool')]
    public bool $disabled;

    public ?string $extraData = null;

    /** @var array<string, string|int|bool|null> */
    public array $extraAttributes = [];

    public bool $sendConfirmation;

    public function __construct(
        string $email,
        bool $confirmed,
        bool $blacklisted,
        bool $htmlEmail,
        bool $disabled,
        ?string $extraData = null,
        array $extraAttributes = []
    ) {
        $this->email = $email;
        $this->confirmed = $confirmed;
        $this->sendConfirmation = !$confirmed;
        $this->blacklisted = $blacklisted;
        $this->htmlEmail = $htmlEmail;
        $this->disabled = $disabled;
        $this->extraData = $extraData;
        $this->extraAttributes = $extraAttributes;
    }
}
