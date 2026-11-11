<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\EventSubscriber;

use PhpList\Core\Domain\Messaging\EventSubscriber\InjectedByHeaderSubscriber;
use PhpList\Core\Domain\Messaging\Service\Builder\HttpReceivedStampBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class InjectedByHeaderSubscriberTest extends TestCase
{
    private HttpReceivedStampBuilder&MockObject $stampBuilder;

    protected function setUp(): void
    {
        $this->stampBuilder = $this->createMock(HttpReceivedStampBuilder::class);
    }

    private function email(): Email
    {
        return (new Email())->from('from@example.com')->to('to@example.com')->subject('S')->text('B');
    }

    public function testNoHeaderWhenNoStamp(): void
    {
        $this->stampBuilder->method('buildStamp')->willReturn(null);
        $email = $this->email();

        (new InjectedByHeaderSubscriber($this->stampBuilder))
            ->onMessage(new MessageEvent($email, Envelope::create($email), 'test'));

        self::assertFalse($email->getHeaders()->has('Received'));
    }

    public function testIgnoresNonEmailMessages(): void
    {
        $this->stampBuilder->expects(self::never())->method('buildStamp');
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        (new InjectedByHeaderSubscriber($this->stampBuilder))
            ->onMessage(new MessageEvent(new RawMessage('raw'), $envelope, 'test'));

        $this->addToAssertionCount(1);
    }

    public function testAddsReceivedHeader(): void
    {
        $this->stampBuilder->method('buildStamp')->willReturn('from [1.2.3.4] by host with HTTP; date');
        $email = $this->email();

        (new InjectedByHeaderSubscriber($this->stampBuilder))
            ->onMessage(new MessageEvent($email, Envelope::create($email), 'test'));

        self::assertSame(
            'from [1.2.3.4] by host with HTTP; date',
            $email->getHeaders()->get('Received')->getBodyAsString()
        );
    }

    public function testReplacesExistingReceivedHeader(): void
    {
        $this->stampBuilder->method('buildStamp')->willReturn('new');
        $email = $this->email();
        $email->getHeaders()->addTextHeader('Received', 'old');

        (new InjectedByHeaderSubscriber($this->stampBuilder))
            ->onMessage(new MessageEvent($email, Envelope::create($email), 'test'));

        self::assertCount(1, iterator_to_array($email->getHeaders()->all('Received')));
        self::assertSame('new', $email->getHeaders()->get('Received')->getBodyAsString());
    }
}
