<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Validator;

use PhpList\Core\Domain\Model\Dto\ValidationContext;

interface ValidatorInterface
{
    public function validate(mixed $value, ValidationContext $context = null): void;
}
