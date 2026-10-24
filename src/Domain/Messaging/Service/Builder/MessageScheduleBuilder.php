<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use DateTime;
use PhpList\Core\Domain\Messaging\Exception\InvalidDtoTypeException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;

class MessageScheduleBuilder
{
    public function build(object $dto): MessageSchedule
    {
        if (!$dto instanceof MessageScheduleDto) {
            throw new InvalidDtoTypeException(get_debug_type($dto));
        }

        return new MessageSchedule(
            repeatInterval: $dto->repeatInterval,
            repeatUntil: $dto->repeatUntil ? new DateTime($dto->repeatUntil) : null,
            requeueInterval: $dto->requeueInterval,
            requeueUntil: $dto->requeueUntil ? new DateTime($dto->requeueUntil) : null,
            embargo: $dto->embargo ? new DateTime($dto->embargo) : null
        );
    }
}
