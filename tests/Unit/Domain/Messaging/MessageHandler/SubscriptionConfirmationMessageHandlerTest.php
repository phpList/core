<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\MessageHandler;

use PhpList\Core\Domain\Configuration\Service\UserPersonalizer;
use PhpList\Core\Domain\Messaging\Message\SubscriptionConfirmationMessage;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PHPUnit\Framework\TestCase;
use PhpList\Core\Domain\Messaging\MessageHandler\SubscriptionConfirmationMessageHandler;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

/**
 * @covers \PhpList\Core\Domain\Messaging\MessageHandler\SubscriptionConfirmationMessageHandler
 */
class SubscriptionConfirmationMessageHandlerTest extends TestCase
{
    public function testSendsEmailWithPersonalizedContentAndListNames(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $configProvider = $this->createMock(ConfigProvider::class);
        $logger = $this->createMock(LoggerInterface::class);
        $userPersonalizer = $this->createMock(UserPersonalizer::class);
        $listRepo = $this->createMock(SubscriberListRepository::class);

        $handler = new SubscriptionConfirmationMessageHandler(
            emailService: $emailService,
            configProvider: $configProvider,
            logger: $logger,
            userPersonalizer: $userPersonalizer,
            subscriberListRepository: $listRepo
        );
        $configProvider
            ->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                [ConfigOption::SubscribeEmailSubject, 'Please confirm your subscription'],
                [ConfigOption::SubscribeMessage, 'Hi {{name}}, you subscribed to: [LISTS]'],
            ]);

        $message = new SubscriptionConfirmationMessage(
            email: 'alice@example.com',
            uniqueId: 'user-123',
            listIds: [10, 11],
        );

        $userPersonalizer->expects($this->once())
            ->method('personalize')
            ->with('Hi {{name}}, you subscribed to: [LISTS]', 'alice@example.com')
            ->willReturn('Hi Alice, you subscribed to: [LISTS]');

        $listA = $this->createMock(SubscriberList::class);
        $listA->method('getName')->willReturn('Releases');
        $listB = $this->createMock(SubscriberList::class);
        $listB->method('getName')->willReturn('Security Advisories');

        $listRepo->method('find')
            ->willReturnCallback(function (int $id) use ($listA, $listB) {
                return match ($id) {
                    10 => $listA,
                    11 => $listB,
                    default => null
                };
            });

        // Capture the Email object passed to EmailService
        $emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email): bool {
                $addresses = $email->getTo();
                if (count($addresses) !== 1 || $addresses[0]->getAddress() !== 'alice@example.com') {
                    return false;
                }
                if ($email->getSubject() !== 'Please confirm your subscription') {
                    return false;
                }
                $body = $email->getTextBody();
                return $body === 'Hi Alice, you subscribed to: Releases, Security Advisories';
            }));

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Subscription confirmation email sent to {email}',
                ['email' => 'alice@example.com']
            );

        $handler($message);
    }

    public function testHandlesMissingListsGracefullyAndEmptyJoin(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $configProvider = $this->createMock(ConfigProvider::class);
        $logger = $this->createMock(LoggerInterface::class);
        $userPersonalizer = $this->createMock(UserPersonalizer::class);
        $listRepo = $this->createMock(SubscriberListRepository::class);

        $handler = new SubscriptionConfirmationMessageHandler(
            emailService: $emailService,
            configProvider: $configProvider,
            logger: $logger,
            userPersonalizer: $userPersonalizer,
            subscriberListRepository: $listRepo
        );

        $configProvider->method('getValue')
            ->willReturnMap([
                [ConfigOption::SubscribeEmailSubject, 'Please confirm your subscription'],
                [ConfigOption::SubscribeMessage, 'Lists: [LISTS]'],
            ]);

        $message = $this->createMock(SubscriptionConfirmationMessage::class);
        $message->method('getEmail')->willReturn('bob@example.com');
        $message->method('getUniqueId')->willReturn('user-456');
        $message->method('getListIds')->willReturn([42]);

        $userPersonalizer->method('personalize')
            ->with('Lists: [LISTS]', 'bob@example.com')
            ->willReturn('Lists: [LISTS]');

        $listRepo->method('getListNames')->with([42])->willReturn([]);

        $emailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->callback(function (Email $email): bool {
                // Intended empty replacement when no lists found -> empty string
                return $email->getTextBody() === 'Lists: ';
            }));

        $logger->expects($this->once())
            ->method('info')
            ->with('Subscription confirmation email sent to {email}', ['email' => 'bob@example.com']);

        $handler($message);
    }
}
