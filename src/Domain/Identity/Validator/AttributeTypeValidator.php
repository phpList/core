<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Validator;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Common\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class AttributeTypeValidator implements ValidatorInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    private const VALID_TYPES = [
        AttributeTypeEnum::TextLine,
        AttributeTypeEnum::Hidden,
    ];

    public function validate(mixed $value, ValidationContext $context = null): void
    {
        $enum = $this->normalizeToEnum($value);

        if (!in_array($enum, self::VALID_TYPES, true)) {
            $validList = implode(', ', array_map(
                static fn(AttributeTypeEnum $enum) => $enum->value,
                self::VALID_TYPES
            ));

            $message = $this->translator->trans(
                'Invalid attribute type: "%type%". Valid types are: %valid_types%',
                [
                    '%type%' => $enum->value,
                    '%valid_types%' => $validList,
                ]
            );

            throw new ValidatorException($message);
        }
    }

    /**
     * @throws InvalidArgumentException if value cannot be converted to AttributeTypeEnum
     */
    private function normalizeToEnum(mixed $value): AttributeTypeEnum
    {
        if ($value instanceof AttributeTypeEnum) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return AttributeTypeEnum::from($value);
            } catch (Throwable) {
                $lower = strtolower($value);
                foreach (AttributeTypeEnum::cases() as $case) {
                    if ($case->value === $lower) {
                        return $case;
                    }
                }
            }
        }

        throw new InvalidArgumentException(
            $this->translator->trans('Value must be an AttributeTypeEnum or string.')
        );
    }
}
