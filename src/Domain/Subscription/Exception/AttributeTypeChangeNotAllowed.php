<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Exception;

use RuntimeException;

class AttributeTypeChangeNotAllowed extends RuntimeException
{
    public function __construct(string $oldType, string $newType)
    {
        parent::__construct(sprintf(
            'attribute_definition.type_change_not_allowed:%s->%s',
            $oldType,
            $newType
        ));
    }
}
