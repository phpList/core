<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Validator;

use PhpList\Core\Domain\Common\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Common\Model\ValidationContext;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

abstract class AbstractAttributeTypeValidator implements ValidatorInterface
{
    public function __construct(protected readonly TranslatorInterface $translator)
    {
    }

    /**
     * @return AttributeTypeEnum[]
     */
    abstract protected function getValidTypes(): array;

    public function validate(mixed $value, ValidationContext $context = null): void
    {
        $enum = $this->normalizeToEnum($value);

        if (!in_array($enum, $this->getValidTypes(), true)) {
            $validList = implode(', ', array_map(
                static fn (AttributeTypeEnum $enum) => $enum->value,
                $this->getValidTypes()
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
     * @throws ValidatorException if value cannot be converted to AttributeTypeEnum
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

        throw new ValidatorException(
            $this->translator->trans('Value must be an AttributeTypeEnum or string.')
        );
    }
}