<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Exception;

use RuntimeException;

class AttributeNotAllowedException extends RuntimeException
{
    public function __construct(string $attributeType)
    {
        parent::__construct(sprintf(
            'Attribute type "%s" cannot be set via this endpoint. ' .
            'Use the subscriber update operation instead.',
            $attributeType
        ));
    }
}
