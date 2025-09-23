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
            repeatUntil: new DateTime($dto->repeatUntil),
            requeueInterval: $dto->requeueInterval,
            requeueUntil: new DateTime($dto->requeueUntil),
            embargo: new DateTime($dto->embargo)
        );
    }
}
