<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Builder;

use DateTime;
use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;

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
