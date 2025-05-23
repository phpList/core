<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberAttributeManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Service for importing and exporting subscribers from/to CSV files.
 */
class SubscriberCsvExporter
{
    private SubscriberAttributeManager $attributeManager;
    private SubscriberRepository $subscriberRepository;
    private SubscriberAttributeDefinitionRepository $definitionRepository;

    public function __construct(
        SubscriberAttributeManager $attributeManager,
        SubscriberRepository $subscriberRepository,
        SubscriberAttributeDefinitionRepository $definitionRepository
    ) {
        $this->attributeManager = $attributeManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->definitionRepository = $definitionRepository;
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
        if ($filter === null) {
            $filter = new SubscriberFilter();
        }

        $tempFilePath = tempnam(sys_get_temp_dir(), 'subscribers_export_');
        $this->generateCsvContent($filter, $batchSize, $tempFilePath, $filter->getColumns());

        $response = new BinaryFileResponse($tempFilePath);

        return $this->configureResponse($response);
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

        $headers = $this->getExportHeaders($attributeDefinitions, $columns);
        fputcsv($handle, $headers);

        $this->exportSubscribers($handle, $filter, $batchSize, $attributeDefinitions, $headers);

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
            'email',
            'confirmed',
            'blacklisted',
            'htmlEmail',
            'disabled',
            'extraData',
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

        do {
            $subscribers = $this->subscriberRepository->getFilteredAfterId(
                lastId: $lastId,
                limit: $batchSize,
                filter: $filter
            );

            foreach ($subscribers as $subscriber) {
                $row = $this->getSubscriberRow($subscriber, $attributeDefinitions, $headers);
                fputcsv($handle, $row);
                $lastId = $subscriber->getId();
            }

            $subscriberCount = count($subscribers);
        } while ($subscriberCount === $batchSize);
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
        $row = [
            'id' => $subscriber->getId(),
            'email' => $subscriber->getEmail(),
            'confirmed' => $subscriber->isConfirmed() ? '1' : '0',
            'blacklisted' => $subscriber->isBlacklisted() ? '1' : '0',
            'htmlEmail' => $subscriber->hasHtmlEmail() ? '1' : '0',
            'disabled' => $subscriber->isDisabled() ? '1' : '0',
            'extraData' => $subscriber->getExtraData(),
        ];

        foreach ($attributeDefinitions as $definition) {
            $attributeValue = $this->attributeManager->getSubscriberAttribute(
                subscriberId: $subscriber->getId(),
                attributeDefinitionId: $definition->getId()
            );
            $row[$definition->getName()] = $attributeValue ? $attributeValue->getValue() : '';
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
}
