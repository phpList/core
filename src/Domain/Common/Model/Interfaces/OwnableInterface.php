<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model\Interfaces;


use PhpList\Core\Domain\Identity\Model\Administrator;

interface OwnableInterface
{
    public function getOwner(): ?Administrator;
}
