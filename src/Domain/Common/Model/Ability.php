<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model;

enum Ability: string
{
    case VIEW   = 'view';
    case CREATE = 'create';
    case EDIT   = 'edit';
}
