<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Service for importing and exporting subscribers from/to CSV files.
 */
class SubscriberCsvExporter
{
    public function __construct(
        private readonly SubscriberAttributeManager $attributeManager,
        private readonly AttributeValueResolver $attributeValueResolver,
        private readonly SubscriberRepository $subscriberRepository,
        private readonly SubscriberAttributeDefinitionRepository $definitionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Export subscribers to a CSV file.
     *
     * @param SubscriberFilter|null $filter Optional filter to apply
     * @param int $batchSize Number of subscribers to process in each batch
     * @return Response A response with the CSV file for download
     */
    public function exportToCsv(?SubscriberFilter $filter = null, int $batchSize = 1000): Response
    {
        $this->logger->info('Starting subscriber CSV export', [
            'batch_size' => $batchSize,
            'filter' => $filter ? get_class($filter) : 'null'
        ]);

        if ($filter === null) {
            $filter = new SubscriberFilter();
            $this->logger->debug('No filter provided, using default filter');
        }

        $tempFilePath = tempnam(sys_get_temp_dir(), 'subscribers_export_');
        $this->logger->debug('Created temporary file for export', ['path' => $tempFilePath]);

        $this->generateCsvContent(
            filter: $filter,
            batchSize: $batchSize,
            filePath: $tempFilePath,
            columns: $filter->getColumns(),
        );

        $response = new BinaryFileResponse($tempFilePath);
        $response = $this->configureResponse($response);

        $this->logger->info('Subscriber CSV export completed', [
            'file_size' => filesize($tempFilePath),
            'temp_file' => $tempFilePath
        ]);

        return $response;
    }

    /**
     * Generate CSV content for the export.
     *
     * @param SubscriberFilter $filter Filter to apply
     * @param int $batchSize Batch size for processing
     * @param string $filePath Path to the file where CSV content will be written
     */
    private function generateCsvContent(
        SubscriberFilter $filter,
        int $batchSize,
        string $filePath,
        array $columns
    ): void {
        $handle = fopen($filePath, 'w');
        /** @var SubscriberAttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $this->definitionRepository->findAll();

        $headers = $this->getExportHeaders(attributeDefinitions: $attributeDefinitions, columns: $columns);
        fputcsv($handle, $headers);

        $this->exportSubscribers(
            handle: $handle,
            filter: $filter,
            batchSize: $batchSize,
            attributeDefinitions: $attributeDefinitions,
            headers: $headers,
        );

        fclose($handle);
    }

    /**
     * Get headers for the export CSV.
     *
     * @param SubscriberAttributeDefinition[] $attributeDefinitions Attribute definitions
     * @return array Headers
     */
    private function getExportHeaders(array $attributeDefinitions, array $columns): array
    {
        $headers = [
            'id',
            'email',
            'confirmed',
            'blacklisted',
//        'manualConfirm',
            'bounceCount',
            'createdAt',
            'updatedAt',
            'uniqueId',
            'htmlEmail',
            'rssFrequency',
            'disabled',
            'extraData',
            'foreignKey',
        ];

        foreach ($attributeDefinitions as $definition) {
            $headers[] = $definition->getName();
        }

        $headers = array_filter($headers, fn($header) => in_array($header, $columns, true));

        return array_values($headers);
    }

    /**
     * Export subscribers in batches.
     *
     * @param resource $handle File handle
     * @param SubscriberFilter $filter Filter to apply
     * @param int $batchSize Batch size
     * @param SubscriberAttributeDefinition[] $attributeDefinitions Attribute definitions
     */
    private function exportSubscribers(
        $handle,
        SubscriberFilter $filter,
        int $batchSize,
        array $attributeDefinitions,
        array $headers
    ): void {
        $lastId = 0;
        $totalExported = 0;
        $batchNumber = 0;

        $this->logger->debug('Starting batch export of subscribers', [
            'batch_size' => $batchSize,
            'attribute_definitions_count' => count($attributeDefinitions),
            'headers_count' => count($headers)
        ]);

        do {
            $batchNumber++;
            $this->logger->debug('Processing subscriber batch', [
                'batch_number' => $batchNumber,
                'last_id' => $lastId,
                'batch_size' => $batchSize
            ]);

            $subscribers = $this->subscriberRepository
                ->getFilteredAfterId($filter->setLastId($lastId)->setLimit($batchSize))
                ->getItems();

            $subscriberCount = count($subscribers);
            $this->logger->debug('Retrieved subscribers for batch', [
                'batch_number' => $batchNumber,
                'count' => $subscriberCount
            ]);

            foreach ($subscribers as $subscriber) {
                $row = $this->getSubscriberRow(
                    subscriber: $subscriber,
                    attributeDefinitions: $attributeDefinitions,
                    headers: $headers,
                );
                fputcsv($handle, $row);
                $lastId = $subscriber->getId();
            }

            $totalExported += $subscriberCount;

            $this->logger->debug('Completed batch processing', [
                'batch_number' => $batchNumber,
                'processed_in_batch' => $subscriberCount,
                'total_exported' => $totalExported,
                'last_id' => $lastId
            ]);
        } while ($subscriberCount === $batchSize);

        $this->logger->info('Completed exporting all subscribers', [
            'total_batches' => $batchNumber,
            'total_subscribers' => $totalExported
        ]);
    }

    /**
     * Get a row of data for a subscriber.
     *
     * @param Subscriber $subscriber The subscriber
     * @param SubscriberAttributeDefinition[] $attributeDefinitions Attribute definitions
     * @return array Row data
     */
    private function getSubscriberRow(Subscriber $subscriber, array $attributeDefinitions, array $headers): array
    {
        $row = $this->normalizerSubscriberData($subscriber);

        foreach ($attributeDefinitions as $definition) {
            $attrValue = $this->attributeManager->getSubscriberAttribute(
                subscriberId: $subscriber->getId(),
                attributeDefinitionId: $definition->getId()
            );

            $row[$definition->getName()] = $attrValue ? $this->attributeValueResolver->resolve($attrValue) : '';
        }

        $row = array_intersect_key($row, array_flip($headers));

        $filteredRow = [];
        foreach ($headers as $key) {
            $filteredRow[] = $row[$key] ?? '';
        }

        return $filteredRow;
    }

    /**
     * Configure the response for CSV download.
     *
     * @param BinaryFileResponse $response The response
     * @return Response The configured response
     */
    private function configureResponse(BinaryFileResponse $response): Response
    {
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'subscribers_export_' . date('Y-m-d') . '.csv'
        );
        $response->headers->set('Content-Disposition', $disposition);
        $response->deleteFileAfterSend();

        return $response;
    }

    /** @SuppressWarnings("CyclomaticComplexity") */
    private function normalizerSubscriberData(Subscriber $subscriber): array
    {
        return [
            'id' => $subscriber->getId(),
            'email' => $subscriber->getEmail(),
            'confirmed' => $subscriber->isConfirmed() ? '1' : '0',
            'blacklisted' => $subscriber->isBlacklisted() ? '1' : '0',
            'bounceCount' => $subscriber->getBounceCount(),
            'createdAt' => $subscriber->getCreatedAt()?->format('Y-m-d H:i:s') ?? '',
            'updatedAt' => $subscriber->getUpdatedAt()->format('Y-m-d H:i:s'),
            'uniqueId' => $subscriber->getUniqueId(),
            'htmlEmail' => $subscriber->hasHtmlEmail() ? '1' : '0',
            'rssFrequency' => $subscriber->getRssFrequency(),
            'disabled' => $subscriber->isDisabled() ? '1' : '0',
            'extraData' => $this->normalizeNullable($subscriber->getExtraData()),
            'foreignKey' => $this->normalizeNullable($subscriber->getForeignKey()),
        ];
    }

    private function normalizeNullable(mixed $value): string
    {
        return $value ?? '';
    }
}
