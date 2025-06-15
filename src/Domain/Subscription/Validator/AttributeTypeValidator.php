<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Validator;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Common\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidatorException;

class AttributeTypeValidator implements ValidatorInterface
{
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
            throw new InvalidArgumentException('Value must be a string.');
        }

        $errors = [];
        if (!in_array($value, self::VALID_TYPES, true)) {
            $errors[] = sprintf(
                'Invalid attribute type: "%s". Valid types are: %s',
                $value,
                implode(', ', self::VALID_TYPES)
            );
        }

        if (!empty($errors)) {
            throw new ValidatorException(implode("\n", $errors));
        }
    }
}
