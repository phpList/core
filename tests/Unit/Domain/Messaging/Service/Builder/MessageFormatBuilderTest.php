<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Builder;

use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Model\Dto\Message\MessageFormatDto;
use PhpList\Core\Domain\Messaging\Service\Builder\MessageFormatBuilder;
use PHPUnit\Framework\TestCase;

class MessageFormatBuilderTest extends TestCase
{
    private MessageFormatBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MessageFormatBuilder();
    }

    public function testBuildsMessageFormatSuccessfully(): void
    {
        $dto = new MessageFormatDto(htmlFormated: true, sendFormat: 'html', formatOptions: ['html', 'text']);
        $messageFormat = $this->builder->build($dto);

        $this->assertSame(true, $messageFormat->isHtmlFormatted());
        $this->assertSame('html', $messageFormat->getSendFormat());
        $this->assertEqualsCanonicalizing(['html', 'text'], $messageFormat->getFormatOptions());
    }

    public function testThrowsExceptionOnInvalidDto(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $invalidDto = new \stdClass();
        $this->builder->build($invalidDto);
    }
}
