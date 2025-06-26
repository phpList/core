<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model\Dto;

final class CreateAdministratorDto
{
    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function __construct(
        public readonly string $loginName,
        public readonly string $password,
        public readonly string $email,
        public readonly bool $isSuperUser = false,
        public readonly array $privileges = [],
    ) {
    }
}
