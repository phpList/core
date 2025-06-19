<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Service\EmailService;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\Email;

class SubscriberManager
{
    private SubscriberRepository $subscriberRepository;
    private EntityManagerInterface $entityManager;
    private EmailService $emailService;
    private string $confirmationUrl;

    public function __construct(
        SubscriberRepository $subscriberRepository, 
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        string $confirmationUrl
    ) {
        $this->subscriberRepository = $subscriberRepository;
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->confirmationUrl = $confirmationUrl;
    }

    public function createSubscriber(CreateSubscriberDto $subscriberDto): Subscriber
    {
        $subscriber = new Subscriber();
        $subscriber->setEmail($subscriberDto->email);
        $confirmed = (bool)$subscriberDto->requestConfirmation;
        $subscriber->setConfirmed(!$confirmed);
        $subscriber->setBlacklisted(false);
        $subscriber->setHtmlEmail((bool)$subscriberDto->htmlEmail);
        $subscriber->setDisabled(false);

        $this->subscriberRepository->save($subscriber);

        if ($subscriberDto->requestConfirmation) {
            $this->sendConfirmationEmail($subscriber);
        }

        return $subscriber;
    }

    /**
     * Send a confirmation email to the subscriber
     */
    private function sendConfirmationEmail(Subscriber $subscriber): void
    {
        $confirmationLink = $this->generateConfirmationLink($subscriber);

        $subject = 'Please confirm your subscription';
        $textContent = "Thank you for subscribing!\n\n"
            . "Please confirm your subscription by clicking the link below:\n"
            . $confirmationLink . "\n\n"
            . "If you did not request this subscription, please ignore this email.";

        $htmlContent = '';
        if ($subscriber->hasHtmlEmail()) {
            $htmlContent = "<p>Thank you for subscribing!</p>"
                . "<p>Please confirm your subscription by clicking the link below:</p>"
                . "<p><a href=\"" . $confirmationLink . "\">Confirm Subscription</a></p>"
                . "<p>If you did not request this subscription, please ignore this email.</p>";
        }

        $email = (new Email())
            ->to($subscriber->getEmail())
            ->subject($subject)
            ->text($textContent);

        if (!empty($htmlContent)) {
            $email->html($htmlContent);
        }

        $this->emailService->sendEmail($email);
    }

    /**
     * Generate a confirmation link for the subscriber
     */
    private function generateConfirmationLink(Subscriber $subscriber): string
    {
        return $this->confirmationUrl . $subscriber->getUniqueId();
    }

    public function getSubscriber(int $subscriberId): Subscriber
    {
        $subscriber = $this->subscriberRepository->findSubscriberWithSubscriptions($subscriberId);

        if (!$subscriber) {
            throw new NotFoundHttpException('Subscriber not found');
        }

        return $subscriber;
    }

    public function updateSubscriber(UpdateSubscriberDto $subscriberDto): Subscriber
    {
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find($subscriberDto->subscriberId);

        $subscriber->setEmail($subscriberDto->email);
        $subscriber->setConfirmed($subscriberDto->confirmed);
        $subscriber->setBlacklisted($subscriberDto->blacklisted);
        $subscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $subscriber->setDisabled($subscriberDto->disabled);
        $subscriber->setExtraData($subscriberDto->additionalData);

        $this->entityManager->flush();

        return $subscriber;
    }

    public function deleteSubscriber(Subscriber $subscriber): void
    {
        $this->subscriberRepository->remove($subscriber);
    }

    public function createFromImport(ImportSubscriberDto $subscriberDto): Subscriber
    {
        $subscriber = new Subscriber();
        $subscriber->setEmail($subscriberDto->email);
        $subscriber->setConfirmed($subscriberDto->confirmed);
        $subscriber->setBlacklisted($subscriberDto->blacklisted);
        $subscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $subscriber->setDisabled($subscriberDto->disabled);
        $subscriber->setExtraData($subscriberDto->extraData);

        $this->subscriberRepository->save($subscriber);

        return $subscriber;
    }

    public function updateFromImport(Subscriber $existingSubscriber, ImportSubscriberDto $subscriberDto): Subscriber
    {
        $existingSubscriber->setEmail($subscriberDto->email);
        $existingSubscriber->setConfirmed($subscriberDto->confirmed);
        $existingSubscriber->setBlacklisted($subscriberDto->blacklisted);
        $existingSubscriber->setHtmlEmail($subscriberDto->htmlEmail);
        $existingSubscriber->setDisabled($subscriberDto->disabled);
        $existingSubscriber->setExtraData($subscriberDto->extraData);

        return $existingSubscriber;
    }
}
