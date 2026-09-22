<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Service\AdminCopyEmailSender;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\Builder\SystemEmailBuilder;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AdminCopyEmailSenderTest extends TestCase
{
    public function testDoesNothingWhenSendAdminCopiesDisabled(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->expects(self::once())
            ->method('isEnabled')
            ->with(ConfigOption::SendAdminCopies)
            ->willReturn(false);

        $systemEmailBuilder = $this->createMock(SystemEmailBuilder::class);
        $systemEmailBuilder->expects(self::never())->method('buildSystemEmail');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $sender = new AdminCopyEmailSender(
            configProvider: $configProvider,
            systemEmailBuilder: $systemEmailBuilder,
            mailer: $mailer,
            logger: $this->createMock(LoggerInterface::class),
            sendListAdminCopy: true,
            bounceEmail: 'bounce@example.com',
        );

        $sender->__invoke('Subject', 'Message body');
    }

    public function testSendsToListOwnersWhenFlagEnabled(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabled')
            ->with(ConfigOption::SendAdminCopies)
            ->willReturn(true);

        $emails = ['owner1@example.com', 'owner2@example.com'];

        $systemEmailBuilder = $this->createMock(SystemEmailBuilder::class);
        // Expect called exactly for unique owner emails
        $systemEmailBuilder->expects(self::exactly(count($emails)))
            ->method('buildSystemEmail')
            ->with(self::callback(function (MessagePrecacheDto $data): bool {
                return str_starts_with($data->subject, 'phpList ')
                    && $data->content === 'Hello Admin';
            }))
            ->willReturn(new Email());

        $mailer = $this->createMock(MailerInterface::class);

        $bounce = 'bounces@phplist.test';
        $invocationIndex = 0;
        $mailer->expects(self::exactly(count($emails)))
            ->method('send')
            ->with(
                self::isInstanceOf(Email::class),
                self::callback(function (Envelope $envelope) use ($emails, &$invocationIndex, $bounce): bool {
                    // Verify bounce/sender address
                    $sender = $envelope->getSender();
                    $recipient = $envelope->getRecipients()[0] ?? null;
                    $expectedRecipient = $emails[$invocationIndex++] ?? null;

                    return $sender->getAddress() === $bounce
                        && $recipient !== null
                        && $recipient->getAddress() === $expectedRecipient;
                })
            );

        // Build lists with owners, including duplicates and a null owner
        $list1 = $this->createListWithOwner('owner1@example.com');
        $list2 = $this->createListWithOwner('owner2@example.com');
        // no owner
        $list3 = new SubscriberList();
        // duplicate owner to test de-dup
        $list4 = $this->createListWithOwner('owner1@example.com');

        $sender = new AdminCopyEmailSender(
            configProvider: $configProvider,
            systemEmailBuilder: $systemEmailBuilder,
            mailer: $mailer,
            logger: $this->createMock(LoggerInterface::class),
            sendListAdminCopy: true,
            bounceEmail: $bounce,
        );

        $sender->__invoke('Test Subject', 'Hello Admin', [$list1, $list2, $list3, $list4]);
    }

    public function testFallsBackToAdminAddressesWhenNoOwnersOrFlagFalse(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);

        $configProvider->method('isEnabled')
            ->with(ConfigOption::SendAdminCopies)
            ->willReturn(true);

        $getValueCalls = [];

        $configProvider->expects(self::exactly(2))
            ->method('getValue')
            ->willReturnCallback(
                function (ConfigOption $key) use (&$getValueCalls): string {
                    $getValueCalls[] = $key;

                    return match ($key) {
                        ConfigOption::AdminAddress => 'single@example.com',
                        ConfigOption::AdminAddresses =>
                        ' admin1@example.com, , admin2@example.com ,admin1@example.com ',
                        default => '',
                    };
                }
            );

        $expectedRecipients = [
            'admin1@example.com',
            'admin2@example.com',
            'single@example.com',
        ];

        $systemEmailBuilder = $this->createMock(SystemEmailBuilder::class);

        $buildCalls = [];

        $systemEmailBuilder
            ->expects(self::exactly(count($expectedRecipients)))
            ->method('buildSystemEmail')
            ->willReturnCallback(
                function (MessagePrecacheDto $data) use (&$buildCalls): Email {
                    $buildCalls[] = $data;

                    return new Email();
                }
            );

        $mailer = $this->createMock(MailerInterface::class);

        $sendCalls = [];

        $bounce = 'bounce@domain.test';

        $mailer->expects(self::exactly(count($expectedRecipients)))
            ->method('send')
            ->willReturnCallback(
                function (Email $email, Envelope $envelope) use (&$sendCalls): void {
                    $sendCalls[] = [$email, $envelope];
                }
            );

        $sender = new AdminCopyEmailSender(
            configProvider: $configProvider,
            systemEmailBuilder: $systemEmailBuilder,
            mailer: $mailer,
            logger: $this->createMock(LoggerInterface::class),
            sendListAdminCopy: false,
            bounceEmail: $bounce,
        );

        $listWithOwner = $this->createListWithOwner('ignored@example.com');

        $sender->__invoke('System Update', 'Body', [$listWithOwner]);

        $this->assertSame(
            [
                ConfigOption::AdminAddress,
                ConfigOption::AdminAddresses,
            ],
            $getValueCalls,
        );

        $this->assertCount(count($expectedRecipients), $buildCalls);

        foreach ($buildCalls as $call) {
            $this->assertInstanceOf(MessagePrecacheDto::class, $call);
            $this->assertStringStartsWith('phpList ', $call->subject);
        }

        $this->assertCount(count($expectedRecipients), $sendCalls);

        foreach ($sendCalls as $index => [$email, $envelope]) {
            $this->assertInstanceOf(Email::class, $email);

            $senderAddress = $envelope->getSender();
            $recipient = $envelope->getRecipients()[0] ?? null;

            $this->assertSame($bounce, $senderAddress->getAddress());

            $this->assertNotNull($recipient);
            $this->assertSame(
                $expectedRecipients[$index],
                $recipient->getAddress()
            );
        }
    }

    private function createListWithOwner(string $email): SubscriberList
    {
        $admin = new Administrator();
        $admin->setEmail($email);

        $list = new SubscriberList();
        $list->setOwner($admin);

        return $list;
    }
}
