<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Service\Builder\MessageOptionsBuilder;
use PHPUnit\Framework\TestCase;

class MessageOptionsBuilderTest extends TestCase
{
    private MessageOptionsBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MessageOptionsBuilder();
    }

    public function testBuildsMessageOptionsSuccessfully(): void
    {
        $dto = new MessageOptionsDto(
            fromField: 'info@example.com',
            toField: 'user@example.com',
            replyTo: 'reply@example.com',
            userSelection: 'all-users'
        );

        $messageOptions = $this->builder->build($dto);

        $this->assertSame('info@example.com', $messageOptions->getFromField());
        $this->assertSame('user@example.com', $messageOptions->getToField());
        $this->assertSame('reply@example.com', $messageOptions->getReplyTo());
        $this->assertSame('all-users', $messageOptions->getUserSelection());
    }

    public function testThrowsExceptionOnInvalidDto(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $invalidDto = new \stdClass();
        $this->builder->build($invalidDto);
    }
}
