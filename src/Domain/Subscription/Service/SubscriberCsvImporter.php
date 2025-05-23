<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

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
 */
class SubscriberCsvImporter
{
    private SubscriberManager $subscriberManager;
    private SubscriberAttributeManager $attributeManager;
    private SubscriptionManager $subscriptionManager;
    private SubscriberRepository $subscriberRepository;
    private CsvImporter $csvImporter;
    private SubscriberAttributeDefinitionRepository $attrDefinitionRepository;

    public function __construct(
        SubscriberManager $subscriberManager,
        SubscriberAttributeManager $attributeManager,
        SubscriptionManager $subscriptionManager,
        SubscriberRepository $subscriberRepository,
        CsvImporter $csvImporter,
        SubscriberAttributeDefinitionRepository $attrDefinitionRepository
    ) {
        $this->subscriberManager = $subscriberManager;
        $this->attributeManager = $attributeManager;
        $this->subscriptionManager = $subscriptionManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->csvImporter = $csvImporter;
        $this->attrDefinitionRepository = $attrDefinitionRepository;
    }

    /**
     * Import subscribers from a CSV file.
     *
     * @param UploadedFile $file The uploaded CSV file
     * @param SubscriberImportOptions $options
     * @return array Import statistics
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
     *
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importAndUpdateFromCsv(UploadedFile $file, ?array $listIds = []): array
    {
        return $this->importFromCsv($file, new SubscriberImportOptions(updateExisting: true, listIds: $listIds));
    }

    /**
     * Import subscribers without updating existing ones.
     *
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importNewFromCsv(UploadedFile $file, ?array $listIds = []): array
    {
        return $this->importFromCsv($file, new SubscriberImportOptions(listIds: $listIds));
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
            if ($attributeDefinition = $this->attrDefinitionRepository->findOneByName($key)) {
                $this->attributeManager->createOrUpdate(
                    $subscriber,
                    $attributeDefinition,
                    $value
                );
            }
        }
    }
}
