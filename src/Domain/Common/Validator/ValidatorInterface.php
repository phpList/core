<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Validator;

use PhpList\Core\Domain\Common\Model\ValidationContext;

interface ValidatorInterface
{
    public function validate(mixed $value, ValidationContext $context = null): void;
}
