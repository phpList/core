<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Builder;

use DateTime;
use InvalidArgumentException;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageScheduleDto;
use PhpList\Core\Domain\Model\Messaging\Message\MessageSchedule;

class MessageScheduleBuilder
{
    public function build(object $dto): MessageSchedule
    {
        if (!$dto instanceof MessageScheduleDto) {
            throw new InvalidArgumentException('Invalid request dto type: ' . get_class($dto));
        }

        return new MessageSchedule(
            $dto->repeatInterval,
            new DateTime($dto->repeatUntil),
            $dto->requeueInterval,
            new DateTime($dto->requeueUntil),
            new DateTime($dto->embargo)
        );
    }
}
