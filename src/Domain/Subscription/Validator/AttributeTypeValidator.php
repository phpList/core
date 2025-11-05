<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Validator;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Common\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Contracts\Translation\TranslatorInterface;

class AttributeTypeValidator implements ValidatorInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    private const VALID_TYPES = [
        'textline',
        'checkbox',
        'checkboxgroup',
        'radio',
        'select',
        'hidden',
        'textarea',
        'date',
    ];

    public function validate(mixed $value, ValidationContext $context = null): void
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($this->translator->trans('Value must be a string.'));
        }

        $errors = [];
        if (!in_array($value, self::VALID_TYPES, true)) {
            $errors[] = $this->translator->trans(
                'Invalid attribute type: "%type%". Valid types are: %valid_types%',
                [
                    '%type%' => $value,
                    '%valid_types%' => implode(', ', self::VALID_TYPES),
                ]
            );
        }

        if (!empty($errors)) {
            throw new ValidatorException(implode("\n", $errors));
        }
    }
}
