<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use DateTime;
use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageScheduleBuilder;
use PHPUnit\Framework\TestCase;

class MessageScheduleBuilderTest extends TestCase
{
    private MessageScheduleBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MessageScheduleBuilder();
    }

    public function testBuildsMessageScheduleSuccessfully(): void
    {
        $dto = new MessageScheduleDto(
            embargo: '2025-04-17T09:00:00+00:00',
            repeatInterval: 1440,
            repeatUntil: '2025-04-30T00:00:00+00:00',
            requeueInterval: 720,
            requeueUntil: '2025-04-20T00:00:00+00:00'
        );

        $messageSchedule = $this->builder->build($dto);

        $this->assertSame(1440, $messageSchedule->getRepeatInterval());
        $this->assertEquals(new DateTime('2025-04-30T00:00:00+00:00'), $messageSchedule->getRepeatUntil());
        $this->assertSame(720, $messageSchedule->getRequeueInterval());
        $this->assertEquals(new DateTime('2025-04-20T00:00:00+00:00'), $messageSchedule->getRequeueUntil());
        $this->assertEquals(new DateTime('2025-04-17T09:00:00+00:00'), $messageSchedule->getEmbargo());
    }

    public function testThrowsExceptionOnInvalidDto(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $invalidDto = new \stdClass();
        $this->builder->build($invalidDto);
    }
}
