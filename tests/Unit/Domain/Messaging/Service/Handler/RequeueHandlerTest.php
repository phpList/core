<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Handler;

use DateInterval;
use DateTime;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Model\Message\MessageStatus;
use PhpList\Core\Domain\Messaging\Service\Handler\RequeueHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\Translator;

class RequeueHandlerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private OutputInterface&MockObject $output;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
    }

    private function createMessage(
        ?int $requeueInterval,
        ?DateTime $requeueUntil,
        ?DateTime $embargo
    ): Message {
        $format = new MessageFormat(htmlFormatted: false, sendFormat: null);
        $schedule = new MessageSchedule(
            repeatInterval: null,
            repeatUntil: null,
            requeueInterval: $requeueInterval,
            requeueUntil: $requeueUntil,
            embargo: $embargo
        );
        $metadata = new MessageMetadata(MessageStatus::Draft);
        $content = new MessageContent('(no subject)');
        $options = new MessageOptions();

        return new Message($format, $schedule, $metadata, $content, $options, owner: null, template: null);
    }

    public function testReturnsFalseWhenIntervalIsZeroOrNegative(): void
    {
        $handler = new RequeueHandler($this->logger, new Translator('en'));
        $message = $this->createMessage(0, null, null);

        $this->output->expects($this->never())->method('writeln');
        $this->logger->expects($this->never())->method('info');

        $result = $handler->handle($message, $this->output);

        $this->assertFalse($result);
        $this->assertSame(MessageStatus::Draft, $message->getMetadata()->getStatus());
    }

    public function testReturnsFalseWhenNowIsAfterRequeueUntil(): void
    {
        $handler = new RequeueHandler($this->logger, new Translator('en'));
        $past = (new DateTime())->sub(new DateInterval('PT5M'));
        $message = $this->createMessage(5, $past, null);

        $this->logger->expects($this->never())->method('info');

        $result = $handler->handle($message, $this->output);

        $this->assertFalse($result);
        $this->assertSame(MessageStatus::Draft, $message->getMetadata()->getStatus());
    }

    public function testRequeuesFromFutureEmbargoAndSetsSubmittedStatus(): void
    {
        $handler = new RequeueHandler($this->logger, new Translator('en'));
        $embargo = (new DateTime())->add(new DateInterval('PT5M'));
        $interval = 10;
        $message = $this->createMessage($interval, null, $embargo);

        $this->output->expects($this->once())->method('writeln');
        $this->logger->expects($this->once())->method('info');

        $result = $handler->handle($message, $this->output);

        $this->assertTrue($result);
        $this->assertSame(MessageStatus::Submitted, $message->getMetadata()->getStatus());

        $expectedNext = (clone $embargo)->add(new DateInterval('PT' . $interval . 'M'));
        $actualNext = $message->getSchedule()->getEmbargo();
        $this->assertInstanceOf(DateTime::class, $actualNext);
        $this->assertEquals($expectedNext->format(DateTime::ATOM), $actualNext->format(DateTime::ATOM));
    }

    public function testRequeuesFromNowWhenEmbargoIsNullOrPast(): void
    {
        $handler = new RequeueHandler($this->logger, new Translator('en'));
        $interval = 3;
        $message = $this->createMessage($interval, null, null);

        $this->logger->expects($this->once())->method('info');

        $before = new DateTime();
        $result = $handler->handle($message, $this->output);
        $after = new DateTime();

        $this->assertTrue($result);
        $this->assertSame(MessageStatus::Submitted, $message->getMetadata()->getStatus());

        $embargo = $message->getSchedule()->getEmbargo();
        $this->assertInstanceOf(DateTime::class, $embargo);

        $minExpected = (clone $before)->add(new DateInterval('PT' . $interval . 'M'));
        $maxExpected = (clone $after)->add(new DateInterval('PT' . $interval . 'M'));

        $this->assertGreaterThanOrEqual($minExpected->getTimestamp(), $embargo->getTimestamp());
        $this->assertLessThanOrEqual($maxExpected->getTimestamp(), $embargo->getTimestamp());
    }

    public function testReturnsFalseWhenNextEmbargoExceedsUntil(): void
    {
        $handler = new RequeueHandler($this->logger, new Translator('en'));
        $embargo = (new DateTime())->add(new DateInterval('PT1M'));
        $interval = 10;
        // next would be +10, which exceeds until
        $until = (clone $embargo)->add(new DateInterval('PT5M'));
        $message = $this->createMessage($interval, $until, $embargo);

        $this->logger->expects($this->never())->method('info');

        $result = $handler->handle($message, $this->output);

        $this->assertFalse($result);
        $this->assertSame(MessageStatus::Draft, $message->getMetadata()->getStatus());
        $this->assertEquals(
            $embargo->format(DateTime::ATOM),
            $message->getSchedule()->getEmbargo()?->format(DateTime::ATOM)
        );
    }
}
