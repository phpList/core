<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Model\Messaging\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Service\Builder\MessageContentBuilder;
use PHPUnit\Framework\TestCase;

class MessageContentBuilderTest extends TestCase
{
    private MessageContentBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MessageContentBuilder();
    }

    public function testBuildsMessageContentSuccessfully(): void
    {
        $dto = new MessageContentDto(
            subject: 'Test Subject',
            text: 'Full text content',
            textMessage: 'Short text version',
            footer: 'Footer text'
        );

        $messageContent = $this->builder->build($dto);

        $this->assertSame('Test Subject', $messageContent->getSubject());
        $this->assertSame('Full text content', $messageContent->getText());
        $this->assertSame('Short text version', $messageContent->getTextMessage());
        $this->assertSame('Footer text', $messageContent->getFooter());
    }

    public function testThrowsExceptionOnInvalidDto(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $invalidDto = new \stdClass();
        $this->builder->build($invalidDto);
    }
}
