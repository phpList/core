<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\EventSubscriber;

use PhpList\Core\Domain\Messaging\EventSubscriber\InjectedByHeaderSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class InjectedByHeaderSubscriberTest extends TestCase
{
    public function testNoHeaderWhenNoCurrentRequest(): void
    {
        $requestStack = new RequestStack();
        $subscriber = new InjectedByHeaderSubscriber($requestStack);

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Subject')
            ->text('Body');

        $event = new MessageEvent($email, Envelope::create($email), 'test');

        $subscriber->onMessage($event);

        $this->assertFalse(
            $email->getHeaders()->has('X-phpList-Injected-By'),
            'Header must not be added when there is no current Request.'
        );
    }

    public function testNoHeaderWhenMessageIsNotEmail(): void
    {
        $requestStack = new RequestStack();
        // Push a Request to ensure the early return is due to non-Email message, not missing request
        $requestStack->push(new Request(server: ['REQUEST_TIME' => time()]));

        $subscriber = new InjectedByHeaderSubscriber($requestStack);

        $raw = new RawMessage('raw');
        // Create an arbitrary envelope; it does not need to match the message class
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);
        $event = new MessageEvent($raw, $envelope, 'test');

        // RawMessage has no headers; the subscriber should return early
        $subscriber->onMessage($event);
        // sanity check to use the variable
        $this->assertSame('raw', $raw->toString());
        // Nothing to assert on headers (RawMessage has none), but the lack of exceptions is a success
        $this->addToAssertionCount(1);
    }

    public function testNoHeaderWhenRunningInCliEvenWithRequestAndEmail(): void
    {
        // In PHPUnit, PHP_SAPI is typically "cli"; ensure we have a Request to pass other guards
        $request = new Request(server: [
            'REQUEST_TIME' => time(),
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $subscriber = new InjectedByHeaderSubscriber($requestStack);

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Subject')
            ->text('Body');

        $event = new MessageEvent($email, Envelope::create($email), 'test');

        $subscriber->onMessage($event);

        // Because tests run under CLI SAPI, the header must not be added
        $this->assertFalse(
            $email->getHeaders()->has('X-phpList-Injected-By'),
            'Header must not be added when running under CLI.'
        );
    }
}
