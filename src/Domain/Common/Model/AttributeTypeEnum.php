<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model;

use PhpList\Core\Domain\Subscription\Exception\AttributeTypeChangeNotAllowed;

enum AttributeTypeEnum: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case MultiSelect = 'multiselect';
    case CheckboxGroup = 'checkboxgroup';
    case Radio = 'radio';
    case Hidden = 'hidden';
    case TextLine = 'textline';

    public function equals(self $other): bool
    {
        return $this === $other;
    }

    public function isMultiValued(): bool
    {
        return match ($this) {
            self::Select,
            self::Checkbox,
            self::MultiSelect,
            self::Radio,
            self::CheckboxGroup => true,
            default => false,
        };
    }

    public function canTransitionTo(self $toType): bool
    {
        if ($this === $toType) {
            return true;
        }

        if ($this->isMultiValued() !== $toType->isMultiValued()) {
            return false;
        }

        return true;
    }

    public function assertTransitionAllowed(self $toType): void
    {
        if (!$this->canTransitionTo($toType)) {
            throw new AttributeTypeChangeNotAllowed($this->value, $toType->value);
        }
    }
}
