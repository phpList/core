<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Service for importing and exporting subscribers from/to CSV files.
 */
class SubscriberCsvExportManager
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
        $this->generateCsvContent($filter, $batchSize, $tempFilePath);

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
    private function generateCsvContent(SubscriberFilter $filter, int $batchSize, string $filePath): void
    {
        $handle = fopen($filePath, 'w');
        $attributeDefinitions = $this->definitionRepository->findAll();

        $headers = $this->getExportHeaders($attributeDefinitions);
        fputcsv($handle, $headers);

        $this->exportSubscribers($handle, $filter, $batchSize, $attributeDefinitions);

        fclose($handle);
    }

    /**
     * Get headers for the export CSV.
     *
     * @param array $attributeDefinitions Attribute definitions
     * @return array Headers
     */
    private function getExportHeaders(array $attributeDefinitions): array
    {
        $headers = [
            'email',
            'confirmed',
            'blacklisted',
            'html_email',
            'disabled',
            'extra_data',
        ];

        foreach ($attributeDefinitions as $definition) {
            $headers[] = $definition->getName();
        }

        return $headers;
    }

    /**
     * Export subscribers in batches.
     *
     * @param resource $handle File handle
     * @param SubscriberFilter $filter Filter to apply
     * @param int $batchSize Batch size
     * @param array $attributeDefinitions Attribute definitions
     */
    private function exportSubscribers(
        $handle,
        SubscriberFilter $filter,
        int $batchSize,
        array $attributeDefinitions
    ): void {
        $lastId = 0;

        do {
            $subscribers = $this->subscriberRepository->getFilteredAfterId(
                lastId: $lastId,
                limit: $batchSize,
                filter: $filter
            );

            foreach ($subscribers as $subscriber) {
                $row = $this->getSubscriberRow($subscriber, $attributeDefinitions);
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
     * @param array $attributeDefinitions Attribute definitions
     * @return array Row data
     */
    private function getSubscriberRow(Subscriber $subscriber, array $attributeDefinitions): array
    {
        $row = [
            $subscriber->getEmail(),
            $subscriber->isConfirmed() ? '1' : '0',
            $subscriber->isBlacklisted() ? '1' : '0',
            $subscriber->hasHtmlEmail() ? '1' : '0',
            $subscriber->isDisabled() ? '1' : '0',
            $subscriber->getExtraData(),
        ];

        foreach ($attributeDefinitions as $definition) {
            $attributeValue = $this->attributeManager->getSubscriberAttribute(
                subscriberId: $subscriber->getId(),
                attributeDefinitionId: $definition->getId()
            );
            $row[] = $attributeValue ? $attributeValue->getValue() : '';
        }

        return $row;
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
