<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

class InjectedByHeaderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [MessageEvent::class => 'onMessage'];
    }

    public function onMessage(MessageEvent $event): void
    {
        // todo: add custom header only to messages sent individually not from campaigns
        // when the email is generated from a webpage (quite possible :-) add a "received line" to identify the origin
        $msg = $event->getMessage();
        if (!$msg instanceof Email)
        {
            return;
        }

        // Only when triggered by HTTP request context (not CLI workers)
        if (PHP_SAPI === 'cli')
        {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $host = gethostname() ?: 'unknown-host';
        $time = date('r', $_SERVER['REQUEST_TIME'] ?? time());

        $msg->getHeaders()->addTextHeader(
            'X-phpList-Injected-By',
            "from [$ip] by $host with HTTP; $time"
        );
    }
}
