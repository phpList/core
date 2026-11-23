<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\EventSubscriber;

use PhpList\Core\Domain\Messaging\Service\Builder\HttpReceivedStampBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * When the email is generated from a web request, add a "Received" line to identify the origin
 * (same as phplist3's PHPlistMailer::add_timestamp()). Mails sent from the CLI or by async
 * workers have no request, so they are not stamped.
 */
class InjectedByHeaderSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly HttpReceivedStampBuilder $stampBuilder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [MessageEvent::class => 'onMessage'];
    }

    public function onMessage(MessageEvent $event): void
    {
        $msg = $event->getMessage();
        if (!$msg instanceof Email) {
            return;
        }

        $stamp = $this->stampBuilder->buildStamp();
        if ($stamp === null) {
            return;
        }

        $headers = $msg->getHeaders();
        $headers->remove('Received');
        $headers->addTextHeader('Received', preg_replace('/[\r\n]+/', ' ', $stamp));
    }
}
