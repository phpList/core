<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;

class ConfigRepository extends AbstractRepository
{
    public function findValueByItem(string $name): ?string
    {
        return $this->findOneBy(['item' => $name])?->getValue();
    }
}
