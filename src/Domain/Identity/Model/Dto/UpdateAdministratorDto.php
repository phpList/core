<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model\Dto;

final class UpdateAdministratorDto
{
    public function __construct(
        public readonly int $administratorId,
        public readonly ?string $loginName = null,
        public readonly ?string $password = null,
        public readonly ?string $email = null,
        public readonly ?bool $superAdmin = null,
        public readonly array $privileges = [],
    ) {
    }
}
