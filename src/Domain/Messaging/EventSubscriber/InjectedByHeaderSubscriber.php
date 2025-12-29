<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

class InjectedByHeaderSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [MessageEvent::class => 'onMessage'];
    }

    public function onMessage(MessageEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // todo: add custom header only to messages sent individually not from campaigns
        // when the email is generated from a webpage (quite possible :-) add a "received line" to identify the origin
        $msg = $event->getMessage();
        if (!$msg instanceof Email) {
            return;
        }

        // Only when triggered by HTTP request context (not CLI workers)
        if (PHP_SAPI === 'cli') {
            return;
        }

        $ip = $request->getClientIp() ?? 'unknown';
        $host = gethostname() ?: 'unknown-host';

        $timestamp = $request->server->get('REQUEST_TIME') ?? time();

        $msg->getHeaders()->addTextHeader(
            'X-phpList-Injected-By',
            sprintf('from [%s] by %s with HTTP; %s', $ip, $host, $timestamp)
        );
    }
}
