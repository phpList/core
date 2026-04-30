<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Messaging\Model\Dto\CreateMessageDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageMetadataDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageOptionsDto;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageScheduleDto;
use PHPUnit\Framework\TestCase;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageFormatBuilder;

final class MessageFormatBuilderTest extends TestCase
{
    public function testBuildSetsHtmlFormattedToFalseWhenContentIsPlainText(): void
    {
        $dto = $this->createDto('Plain text content', 'text');

        $builder = new MessageFormatBuilder();
        $result = $builder->build($dto);

        self::assertInstanceOf(MessageFormat::class, $result);
        self::assertFalse($result->isHtmlFormatted());
        self::assertSame('text', $result->getSendFormat());
    }

    public function testBuildSetsHtmlFormattedToTrueWhenContentContainsHtml(): void
    {
        $dto = $this->createDto('<p>Hello <strong>world</strong></p>', 'html');

        $builder = new MessageFormatBuilder();
        $result = $builder->build($dto);

        self::assertInstanceOf(MessageFormat::class, $result);
        self::assertTrue($result->isHtmlFormatted());
        self::assertSame('html', $result->getSendFormat());
    }

    private function createDto(string $text, string $sendFormat): CreateMessageDto
    {
        return new CreateMessageDto(
            content: new MessageContentDto(subject: '', text: $text, footer: ''),
            format: new MessageFormatDto(sendFormat: $sendFormat),
            metadata: $this->createMock(MessageMetadataDto::class),
            options: $this->createMock(MessageOptionsDto::class),
            schedule: $this->createMock(MessageScheduleDto::class),
            templateId: null,
        );
    }
}
