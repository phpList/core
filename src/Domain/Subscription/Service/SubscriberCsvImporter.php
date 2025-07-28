<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\SubscriberImportOptions;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriptionManager;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

/**
 * Service for importing subscribers from a CSV file.
 * @SuppressWarnings("CouplingBetweenObjects")
 */
class SubscriberCsvImporter
{
    private SubscriberManager $subscriberManager;
    private SubscriberAttributeManager $attributeManager;
    private SubscriptionManager $subscriptionManager;
    private SubscriberRepository $subscriberRepository;
    private CsvImporter $csvImporter;
    private SubscriberAttributeDefinitionRepository $attrDefinitionRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        SubscriberManager $subscriberManager,
        SubscriberAttributeManager $attributeManager,
        SubscriptionManager $subscriptionManager,
        SubscriberRepository $subscriberRepository,
        CsvImporter $csvImporter,
        SubscriberAttributeDefinitionRepository $attrDefinitionRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->subscriberManager = $subscriberManager;
        $this->attributeManager = $attributeManager;
        $this->subscriptionManager = $subscriptionManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->csvImporter = $csvImporter;
        $this->attrDefinitionRepository = $attrDefinitionRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Import subscribers from a CSV file.
     *
     * @param UploadedFile $file The uploaded CSV file
     * @param SubscriberImportOptions $options
     * @return array Import statistics
     * @throws RuntimeException When the uploaded file cannot be read or for any other errors during import
     */
    public function importFromCsv(UploadedFile $file, SubscriberImportOptions $options): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $path = $file->getRealPath();
            if ($path === false) {
                throw new RuntimeException('Could not read the uploaded file.');
            }

            $result = $this->csvImporter->import($path);

            foreach ($result['valid'] as $dto) {
                try {
                    $this->processRow($dto, $options, $stats);
                    if (!$options->dryRun) {
                        $this->entityManager->flush();
                    }
                } catch (Throwable $e) {
                    $stats['errors'][] = 'Error processing ' . $dto->email . ': ' . $e->getMessage();
                    $stats['skipped']++;
                }
            }

            foreach ($result['errors'] as $line => $messages) {
                $stats['errors'][] = 'Line ' . $line . ': ' . implode('; ', $messages);
                $stats['skipped']++;
            }
        } catch (Throwable $e) {
            $stats['errors'][] = 'General import error: ' . $e->getMessage();
        }

        return $stats;
    }

    /**
     * Import subscribers with an update strategy.
     * @SuppressWarnings("BooleanArgumentFlag")
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importAndUpdateFromCsv(UploadedFile $file, ?array $listIds = [], bool $dryRun = false): array
    {
        return $this->importFromCsv(
            file: $file,
            options: new SubscriberImportOptions(updateExisting: true, listIds: $listIds, dryRun: $dryRun)
        );
    }

    /**
     * Import subscribers without updating existing ones.
     * @SuppressWarnings("BooleanArgumentFlag")
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importNewFromCsv(UploadedFile $file, ?array $listIds = [], bool $dryRun = false): array
    {
        return $this->importFromCsv(
            file: $file,
            options: new SubscriberImportOptions(listIds: $listIds, dryRun: $dryRun)
        );
    }

    /**
     * Process a single row from the CSV file.
     *
     * @param ImportSubscriberDto $dto
     * @param SubscriberImportOptions $options
     * @param array $stats Statistics to update
     */
    private function processRow(
        ImportSubscriberDto $dto,
        SubscriberImportOptions $options,
        array &$stats,
    ): void {
        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            if ($options->skipInvalidEmail) {
                $stats['skipped']++;
                return;
            } else {
                $dto->email = 'invalid_' . $dto->email;
            }
        }
        $subscriber = $this->subscriberRepository->findOneByEmail($dto->email);

        if ($subscriber && !$options->updateExisting) {
            $stats['skipped']++;
            return;
        }
        if ($subscriber) {
            $this->subscriberManager->updateFromImport($subscriber, $dto);
            $stats['updated']++;
        } else {
            $subscriber = $this->subscriberManager->createFromImport($dto);
            $stats['created']++;
        }

        $this->processAttributes($subscriber, $dto);

        if (count($options->listIds) > 0) {
            foreach ($options->listIds as $listId) {
                $this->subscriptionManager->addSubscriberToAList($subscriber, $listId);
            }
        }
    }

    /**
     * Process subscriber attributes.
     *
     * @param Subscriber $subscriber The subscriber
     * @param ImportSubscriberDto $dto
     */
    private function processAttributes(Subscriber $subscriber, ImportSubscriberDto $dto): void
    {
        foreach ($dto->extraAttributes as $key => $value) {
            $attributeDefinition = $this->attrDefinitionRepository->findOneByName($key);
            if ($attributeDefinition !== null) {
                $this->attributeManager->createOrUpdate(
                    subscriber: $subscriber,
                    definition: $attributeDefinition,
                    value: $value
                );
            }
        }
    }
}
