<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Model\Messaging;

use DateTime;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Messaging\Message;
use PhpList\Core\Domain\Model\Messaging\Message\MessageContent;
use PhpList\Core\Domain\Model\Messaging\Message\MessageFormat;
use PhpList\Core\Domain\Model\Messaging\Message\MessageMetadata;
use PhpList\Core\Domain\Model\Messaging\Message\MessageOptions;
use PhpList\Core\Domain\Model\Messaging\Message\MessageSchedule;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Interfaces\ModificationDate;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    private Message $message;
    private MessageFormat $format;
    private MessageSchedule $schedule;
    private MessageMetadata $metadata;
    private MessageContent $content;
    private MessageOptions $options;
    private Administrator $owner;

    protected function setUp(): void
    {
        $this->format = new MessageFormat(true, MessageFormat::FORMAT_TEXT);
        $this->schedule = new MessageSchedule(1, new DateTime(), 2, new DateTime(), null);
        $this->metadata = new MessageMetadata();
        $this->content = new MessageContent('This is the body');
        $this->options = new MessageOptions();
        $this->owner = new Administrator();

        $this->message = new Message(
            $this->format,
            $this->schedule,
            $this->metadata,
            $this->content,
            $this->options,
            $this->owner
        );
    }

    public function testIsDomainModel(): void
    {
        self::assertInstanceOf(DomainModel::class, $this->message);
        self::assertInstanceOf(Identity::class, $this->message);
        self::assertInstanceOf(ModificationDate::class, $this->message);
    }

    public function testUuidIsGenerated(): void
    {
        $uuid = $this->message->getUuid();
        self::assertNotEmpty($uuid);
        self::assertMatchesRegularExpression('/^[a-f0-9]{36}$/', $uuid);
    }

    public function testGetFormat(): void
    {
        self::assertSame($this->format, $this->message->getFormat());
    }

    public function testGetSchedule(): void
    {
        self::assertSame($this->schedule, $this->message->getSchedule());
    }

    public function testGetMetadata(): void
    {
        self::assertSame($this->metadata, $this->message->getMetadata());
    }

    public function testGetContent(): void
    {
        self::assertSame($this->content, $this->message->getContent());
    }

    public function testGetOptions(): void
    {
        self::assertSame($this->options, $this->message->getOptions());
    }

    public function testGetOwner(): void
    {
        self::assertSame($this->owner, $this->message->getOwner());
    }

    public function testGetOwnerInitiallyNull(): void
    {
        $message = new Message(
            $this->format,
            $this->schedule,
            $this->metadata,
            $this->content,
            $this->options,
            null
        );

        self::assertNull($message->getOwner());
    }
}
