<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Message\SubscriptionConfirmationMessage;
use PhpList\Core\Domain\Subscription\Exception\CouldNotReadUploadedFileException;
use PhpList\Core\Domain\Subscription\Model\Dto\ChangeSetDto;
use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\SubscriberImportOptions;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriptionManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * phpcs:ignore Generic.Commenting.Todo
 * @todo: check if dryRun will work (some function flush)
 * Service for importing subscribers from a CSV file.
 * @SuppressWarnings("CouplingBetweenObjects")
 * @SuppressWarnings("ExcessiveParameterList")
 */
class SubscriberCsvImporter
{
    private SubscriberManager $subscriberManager;
    private SubscriberAttributeManager $attributeManager;
    private SubscriptionManager $subscriptionManager;
    private SubscriberRepository $subscriberRepository;
    private CsvImporter $csvImporter;
    private EntityManagerInterface $entityManager;
    private TranslatorInterface $translator;
    private MessageBusInterface $messageBus;
    private SubscriberHistoryManager $subscriberHistoryManager;

    public function __construct(
        SubscriberManager $subscriberManager,
        SubscriberAttributeManager $attributeManager,
        SubscriptionManager $subscriptionManager,
        SubscriberRepository $subscriberRepository,
        CsvImporter $csvImporter,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        MessageBusInterface $messageBus,
        SubscriberHistoryManager $subscriberHistoryManager,
    ) {
        $this->subscriberManager = $subscriberManager;
        $this->attributeManager = $attributeManager;
        $this->subscriptionManager = $subscriptionManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->csvImporter = $csvImporter;
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->messageBus = $messageBus;
        $this->subscriberHistoryManager = $subscriberHistoryManager;
    }

    /**
     * Import subscribers from a CSV file.
     * @return array Import statistics
     * @throws CouldNotReadUploadedFileException
     */
    public function importFromCsv(
        UploadedFile $file,
        SubscriberImportOptions $options,
        ?Administrator $admin = null
    ): array {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'blacklisted' => 0,
            'errors' => [],
        ];

        try {
            $path = $file->getRealPath();
            if ($path === false) {
                throw new CouldNotReadUploadedFileException(
                    $this->translator->trans('Could not read the uploaded file.')
                );
            }

            $result = $this->csvImporter->import($path);

            foreach ($result['valid'] as $dto) {
                try {
                    $this->entityManager->beginTransaction();

                    $message = $this->processRow($dto, $options, $stats, $admin);

                    if ($options->dryRun) {
                        $this->entityManager->rollback();
                    } else {
                        $this->entityManager->flush();
                        $this->entityManager->commit();
                        if ($message !== null) {
                            $this->messageBus->dispatch($message);
                        }
                    }
                } catch (Throwable $e) {
                    $this->entityManager->rollback();

                    $stats['errors'][] = $this->translator->trans(
                        'Error processing %email%: %error%',
                        ['%email%' => $dto->email, '%error%' => $e->getMessage()]
                    );
                    $stats['skipped']++;
                }
            }

            foreach ($result['errors'] as $line => $messages) {
                $stats['errors'][] = 'Line ' . $line . ': ' . implode('; ', $messages);
                $stats['skipped']++;
            }
        } catch (Throwable $e) {
            $stats['errors'][] = $this->translator->trans(
                'General import error: %error%',
                ['%error%' => $e->getMessage()]
            );
        }

        return $stats;
    }

    /**
     * Import subscribers with an update strategy.
     * @SuppressWarnings("BooleanArgumentFlag")
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importAndUpdateFromCsv(
        UploadedFile $file,
        Administrator $admin,
        ?array $listIds = [],
        bool $dryRun = false
    ): array {
        return $this->importFromCsv(
            file: $file,
            options: new SubscriberImportOptions(updateExisting: true, listIds: $listIds, dryRun: $dryRun),
            admin: $admin,
        );
    }

    /**
     * Import subscribers without updating existing ones.
     * @SuppressWarnings("BooleanArgumentFlag")
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importNewFromCsv(
        UploadedFile $file,
        Administrator $admin,
        ?array $listIds = [],
        bool $dryRun = false
    ): array {
        return $this->importFromCsv(
            file: $file,
            options: new SubscriberImportOptions(listIds: $listIds, dryRun: $dryRun),
            admin: $admin,
        );
    }

    /**
     * Process a single row from the CSV file.
     */
    private function processRow(
        ImportSubscriberDto $dto,
        SubscriberImportOptions $options,
        array &$stats,
        ?Administrator $admin = null
    ): ?SubscriptionConfirmationMessage {
        if ($this->handleInvalidEmail($dto, $options, $stats)) {
            return null;
        }

        $subscriber = $this->subscriberRepository->findOneByEmail($dto->email);
        if ($this->handleSkipCase($subscriber, $options, $stats)) {
            return null;
        }

        if ($subscriber) {
            $changeSet = $this->subscriberManager->updateFromImport($subscriber, $dto);
            $stats['updated']++;
        } else {
            $subscriber = $this->subscriberManager->createFromImport($dto);
            $stats['created']++;
        }

        $this->attributeManager->processAttributes($subscriber, $dto->extraAttributes);

        $addedNewSubscriberToList = false;
        $listLines = [];
        if (!$subscriber->isBlacklisted() && count($options->listIds) > 0) {
            foreach ($options->listIds as $listId) {
                $created = $this->subscriptionManager->addSubscriberToAList($subscriber, $listId);
                if ($created) {
                    $addedNewSubscriberToList = true;
                    $listLines[] = $this->translator->trans(
                        'Subscribed to %list%',
                        ['%list%' => $created->getSubscriberList()->getName()]
                    );
                }
            }
        }

        if ($subscriber->isBlacklisted()) {
            $stats['blacklisted']++;
        }

        $this->subscriberHistoryManager->addHistoryFromImport(
            subscriber: $subscriber,
            listLines: $listLines,
            changeSetDto: $changeSet ?? new ChangeSetDto(),
            admin: $admin
        );

        return $this->prepareConfirmationMessage($subscriber, $options, $dto, $addedNewSubscriberToList);
    }

    private function handleInvalidEmail(
        ImportSubscriberDto $dto,
        SubscriberImportOptions $options,
        array &$stats
    ): bool {
        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            if ($options->skipInvalidEmail) {
                $stats['skipped']++;

                return true;
            }
            // phpcs:ignore Generic.Commenting.Todo
            // @todo: check
            $dto->email = 'invalid_' . $dto->email;
            $dto->sendConfirmation = false;
        }

        return false;
    }

    private function handleSkipCase(
        ?Subscriber $existingSubscriber,
        SubscriberImportOptions $options,
        array &$stats
    ): bool {
        if ($existingSubscriber && !$options->updateExisting) {
            $stats['skipped']++;

            return true;
        }

        return false;
    }

    private function prepareConfirmationMessage(
        Subscriber $subscriber,
        SubscriberImportOptions $options,
        ImportSubscriberDto $dto,
        bool $addedNewSubscriberToList
    ): ?SubscriptionConfirmationMessage {
        if ($dto->sendConfirmation && $addedNewSubscriberToList) {
            return new SubscriptionConfirmationMessage(
                email: $subscriber->getEmail(),
                uniqueId: $subscriber->getUniqueId(),
                listIds: $options->listIds,
                htmlEmail: $subscriber->hasHtmlEmail(),
            );
        }

        return null;
    }
}
