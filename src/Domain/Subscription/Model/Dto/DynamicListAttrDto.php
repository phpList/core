<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

use InvalidArgumentException;
use Symfony\Component\Serializer\Annotation\SerializedName;

class DynamicListAttrDto
{
    public readonly ?int $id;

    public readonly string $name;

    #[SerializedName('listorder')]
    public readonly ?int $listOrder;

    public function __construct(
        ?int $id,
        string $name,
        ?int $listOrder = null
    ) {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Option name cannot be empty');
        }

        $this->id = $id;
        $this->name = $trimmed;
        $this->listOrder = $listOrder;
    }
}
