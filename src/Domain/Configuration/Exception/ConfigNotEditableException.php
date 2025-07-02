<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Exception;

use Exception;

class ConfigNotEditableException extends Exception
{
    public function __construct(string $configKey)
    {
        parent::__construct(sprintf('Configuration item "%s" is not editable.', $configKey));
    }
}
