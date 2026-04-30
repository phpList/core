<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use PhpList\Core\Domain\Common\Html2Text;
use PhpList\Core\Domain\Messaging\Exception\InvalidDtoTypeException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageContentDto;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageContentBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MessageContentBuilderTest extends TestCase
{
    private MessageContentBuilder $builder;
    private Html2Text&MockObject $html2Text;

    protected function setUp(): void
    {
        $this->html2Text = $this->createMock(Html2Text::class);
        $this->builder = new MessageContentBuilder($this->html2Text);
    }

    public function testBuildsMessageContentSuccessfully(): void
    {
        $this->html2Text
            ->expects($this->never())
            ->method('__invoke');

        $dto = new MessageContentDto(
            subject: 'Test Subject',
            text: 'Full text content',
            footer: 'Footer text'
        );

        $messageContent = $this->builder->build($dto);

        $this->assertSame('Test Subject', $messageContent->getSubject());
        $this->assertSame('Full text content', $messageContent->getText());
        $this->assertSame('Full text content', $messageContent->getTextMessage());
        $this->assertSame('Footer text', $messageContent->getFooter());
    }

    public function testBuildsPlainTextMessageFromHtmlText(): void
    {
        $dto = new MessageContentDto(
            subject: 'Test Subject',
            text: '<p>Full <b>text</b> content</p>',
            footer: 'Footer text'
        );

        $this->html2Text
            ->expects($this->once())
            ->method('__invoke')
            ->with('<p>Full <b>text</b> content</p>')
            ->willReturn('Full text content');

        $messageContent = $this->builder->build($dto);

        $this->assertSame('Full text content', $messageContent->getTextMessage());
    }

    public function testThrowsExceptionOnInvalidDto(): void
    {
        $this->expectException(InvalidDtoTypeException::class);

        $this->html2Text
            ->expects($this->never())
            ->method('__invoke');

        $invalidDto = new \stdClass();
        $this->builder->build($invalidDto);
    }
}
