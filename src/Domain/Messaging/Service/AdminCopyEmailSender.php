<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Service\Builder\SystemEmailBuilder;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class AdminCopyEmailSender
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly SystemEmailBuilder $systemEmailBuilder,
        private readonly MailerInterface $mailer,
        #[Autowire('%messaging.send_list_admin_copy%')] private readonly bool $sendListAdminCopy,
        #[Autowire('%imap_bounce.email%')] private readonly string $bounceEmail,
        private readonly string $installationName = 'phpList',
    ) {
    }

    /** @param SubscriberList[] $lists */
    public function __invoke(string $subject, string $message, array $lists = []): void
    {
        $sendCopy = (bool) $this->configProvider->getValue(ConfigOption::SendAdminCopies);
        if ($sendCopy === false) {
            return;
        }

        $mails = [];
        if (count($lists) && $this->sendListAdminCopy) {
            foreach ($lists as $list) {
                $mails[] = $list->getOwner()->getEmail();
            }
        }

        if (count($mails) === 0) {
            $adminMail = $this->configProvider->getValue(ConfigOption::AdminAddress);
            $adminMailsString = $this->configProvider->getValue(ConfigOption::AdminAddresses);

            $mails  = $adminMailsString ? explode(',', $adminMailsString) : [];
            $mails[] = $adminMail;
        }

        $sent = [];
        foreach ($mails as $adminMail) {
            $adminMail = trim($adminMail);
            if (!isset($sent[$adminMail]) && !empty($adminMail)) {
                $data = new MessagePrecacheDto();
                $data->to = $adminMail;
                $data->subject = $this->installationName . ' ' . $subject;
                $data->content = $message;

                $email = $this->systemEmailBuilder->buildSystemEmail(data: $data);

                $envelope = new Envelope(
                    sender: new Address($this->bounceEmail, 'PHPList'),
                    recipients: [new Address($adminMail)],
                );
                $this->mailer->send(message: $email, envelope: $envelope);

                $sent[$adminMail] = 1;
            }
        }
    }
}
