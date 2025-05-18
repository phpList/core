<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Exception;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service for importing and exporting subscribers from/to CSV files.
 */
class SubscriberCsvManager
{
    private SubscriberManager $subscriberManager;
    private SubscriberAttributeManager $attributeManager;
    private SubscriberRepository $subscriberRepository;
    private SubscriberAttributeDefinitionRepository $attrDefRepository;

    public function __construct(
        SubscriberManager $subscriberManager,
        SubscriberAttributeManager $attributeManager,
        SubscriberRepository $subscriberRepository,
        SubscriberAttributeDefinitionRepository $attributeDefinitionRepository
    ) {
        $this->subscriberManager = $subscriberManager;
        $this->attributeManager = $attributeManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->attrDefRepository = $attributeDefinitionRepository;
    }

    /**
     * Import subscribers from a CSV file.
     *
     * @param UploadedFile $file The uploaded CSV file
     * @param bool $updateExisting Whether to update existing subscribers
     * @return array Import statistics
     */
    public function importFromCsv(UploadedFile $file, bool $updateExisting = false): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        [$handle, $headers, $attributeDefinitions] = $this->prepareImport($file);

        $lineNumber = 2;
        $data = fgetcsv($handle);
        while ($data !== false) {
            try {
                $this->processRow($data, $headers, $attributeDefinitions, $updateExisting, $stats, $lineNumber);
            } catch (Exception $e) {
                $stats['errors'][] = 'Line ' . $lineNumber . ': ' . $e->getMessage();
                $stats['skipped']++;
            }

            $lineNumber++;
            $data = fgetcsv($handle);
        }

        fclose($handle);
        return $stats;
    }

    /**
     * Import subscribers with update strategy.
     *
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importAndUpdateFromCsv(UploadedFile $file): array
    {
        return $this->importFromCsv($file, true);
    }

    /**
     * Import subscribers without updating existing ones.
     *
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importNewFromCsv(UploadedFile $file): array
    {
        return $this->importFromCsv($file, false);
    }

    /**
     * Prepare for import by opening file and validating headers.
     *
     * @param UploadedFile $file The uploaded CSV file
     * @return array [file handle, headers, attribute definitions]
     * @throws RuntimeException If file cannot be opened or is invalid
     */
    private function prepareImport(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            throw new RuntimeException('Could not open file for reading');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('CSV file is empty or invalid');
        }

        if (!in_array('email', $headers, true)) {
            fclose($handle);
            throw new RuntimeException('CSV file must contain an "email" column');
        }

        $attributeDefinitions = $this->getAttributeDefinitions($headers);

        return [$handle, $headers, $attributeDefinitions];
    }

    /**
     * Get attribute definitions from headers.
     *
     * @param array $headers CSV headers
     * @return array Attribute definitions indexed by column position
     */
    private function getAttributeDefinitions(array $headers): array
    {
        $attributeDefinitions = [];
        $systemFields = ['email', 'confirmed', 'blacklisted', 'html_email', 'disabled', 'extra_data'];

        foreach ($headers as $index => $header) {
            if (in_array($header, $systemFields, true)) {
                continue;
            }

            $attributeDefinition = $this->attrDefRepository->findOneBy(['name' => $header]);
            if ($attributeDefinition) {
                $attributeDefinitions[$index] = $attributeDefinition;
            }
        }

        return $attributeDefinitions;
    }

    /**
     * Process a single row from the CSV file.
     *
     * @param array $data Row data
     * @param array $headers CSV headers
     * @param array $attributeDefinitions Attribute definitions
     * @param bool $updateExisting Whether to update existing subscribers
     * @param array $stats Statistics to update
     * @param int $lineNumber Current line number for error reporting
     */
    private function processRow(
        array $data,
        array $headers,
        array $attributeDefinitions,
        bool $updateExisting,
        array &$stats,
        int $lineNumber
    ): void {
        $email = trim($data[array_search('email', $headers, true)]);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stats['errors'][] = 'Line ' . $lineNumber . ': Invalid email address';
            $stats['skipped']++;
            return;
        }

        $existingSubscriber = $this->subscriberRepository->findOneByEmail($email);

        if ($existingSubscriber && !$updateExisting) {
            $stats['skipped']++;
            return;
        }

        $subscriber = $this->createOrUpdateSubscriber(
            $email,
            $data,
            $headers,
            $existingSubscriber,
            $stats
        );

        $this->processAttributes($subscriber, $data, $attributeDefinitions);
    }

    /**
     * Create a new subscriber or update an existing one.
     *
     * @param string $email Subscriber email
     * @param array $data Row data
     * @param array $headers CSV headers
     * @param Subscriber|null $existingSubscriber Existing subscriber if found
     * @param array $stats Statistics to update
     * @return Subscriber The created or updated subscriber
     */
    private function createOrUpdateSubscriber(
        string $email,
        array $data,
        array $headers,
        ?Subscriber $existingSubscriber,
        array &$stats
    ): Subscriber {
        $confirmedIndex = array_search('confirmed', $headers, true);

        if ($existingSubscriber) {
            $subscriber = $this->updateExistingSubscriber(
                $existingSubscriber,
                $email,
                $data,
                $headers,
                $confirmedIndex
            );
            $stats['updated']++;
        } else {
            $subscriber = $this->createNewSubscriber(
                $email,
                $data,
                $headers,
                $confirmedIndex
            );
            $stats['created']++;
        }

        return $subscriber;
    }

    /**
     * Update an existing subscriber.
     *
     * @param Subscriber $existingSubscriber The subscriber to update
     * @param string $email Subscriber email
     * @param array $data Row data
     * @param array $headers CSV headers
     * @param int|false $confirmedIndex Index of confirmed column
     * @return Subscriber The updated subscriber
     */
    private function updateExistingSubscriber(
        Subscriber $existingSubscriber,
        string $email,
        array $data,
        array $headers,
        $confirmedIndex
    ): Subscriber {
        $confirmed = $this->isBooleanTrue($data, $confirmedIndex, $existingSubscriber->isConfirmed());

        $blacklistedIndex = array_search('blacklisted', $headers, true);
        $blacklisted = $this->isBooleanTrue($data, $blacklistedIndex, $existingSubscriber->isBlacklisted());

        $htmlEmailIndex = array_search('html_email', $headers, true);
        $htmlEmail = $this->isBooleanTrue($data, $htmlEmailIndex, $existingSubscriber->hasHtmlEmail());

        $disabledIndex = array_search('disabled', $headers, true);
        $disabled = $this->isBooleanTrue($data, $disabledIndex, $existingSubscriber->isDisabled());

        $extraDataIndex = array_search('extra_data', $headers, true);
        $additionalData = $extraDataIndex !== false && isset($data[$extraDataIndex]) ? $data[$extraDataIndex] : $existingSubscriber->getExtraData();

        $dto = new UpdateSubscriberDto(
            $existingSubscriber->getId(),
            $email,
            $confirmed,
            $blacklisted,
            $htmlEmail,
            $disabled,
            $additionalData
        );

        return $this->subscriberManager->updateSubscriber($dto);
    }

    /**
     * Create a new subscriber.
     *
     * @param string $email Subscriber email
     * @param array $data Row data
     * @param array $headers CSV headers
     * @param int|false $confirmedIndex Index of confirmed column
     * @return Subscriber The created subscriber
     */
    private function createNewSubscriber(
        string $email,
        array $data,
        array $headers,
        $confirmedIndex
    ): Subscriber {
        $requestConfirmation = !($confirmedIndex !== false && isset($data[$confirmedIndex]) &&
            filter_var($data[$confirmedIndex], FILTER_VALIDATE_BOOLEAN));

        $htmlEmailIndex = array_search('html_email', $headers, true);
        $htmlEmail = $htmlEmailIndex !== false && isset($data[$htmlEmailIndex]) &&
            filter_var($data[$htmlEmailIndex], FILTER_VALIDATE_BOOLEAN);

        $dto = new CreateSubscriberDto(
            $email,
            $requestConfirmation,
            $htmlEmail
        );

        $subscriber = $this->subscriberManager->createSubscriber($dto);

        $this->setOptionalBooleanField($subscriber, 'setBlacklisted', $data, $headers, 'blacklisted');
        $this->setOptionalBooleanField($subscriber, 'setDisabled', $data, $headers, 'disabled');

        $extraDataIndex = array_search('extra_data', $headers, true);
        if ($extraDataIndex !== false && isset($data[$extraDataIndex])) {
            $subscriber->setExtraData($data[$extraDataIndex]);
        }

        $this->subscriberRepository->save($subscriber);
        return $subscriber;
    }

    /**
     * Set an optional boolean field on a subscriber if it exists in the data.
     *
     * @param Subscriber $subscriber The subscriber to update
     * @param string $method The method to call on the subscriber
     * @param array $data Row data
     * @param array $headers CSV headers
     * @param string $fieldName The field name to look for in headers
     */
    private function setOptionalBooleanField(
        Subscriber $subscriber,
        string $method,
        array $data,
        array $headers,
        string $fieldName
    ): void {
        $index = array_search($fieldName, $headers, true);
        if ($index !== false && isset($data[$index])) {
            $subscriber->$method(filter_var($data[$index], FILTER_VALIDATE_BOOLEAN));
        }
    }

    /**
     * Check if a boolean value is true in data, with fallback.
     *
     * @param array $data Row data
     * @param int|false $index Index of the column
     * @param bool $default Default value if not found
     * @return bool The boolean value
     */
    private function isBooleanTrue(array $data, $index, bool $default): bool
    {
        return $index !== false && isset($data[$index]) ? filter_var($data[$index], FILTER_VALIDATE_BOOLEAN) : $default;
    }

    /**
     * Process subscriber attributes.
     *
     * @param Subscriber $subscriber The subscriber
     * @param array $data Row data
     * @param array $attributeDefinitions Attribute definitions
     */
    private function processAttributes(Subscriber $subscriber, array $data, array $attributeDefinitions): void
    {
        foreach ($attributeDefinitions as $index => $attributeDefinition) {
            if (isset($data[$index]) && $data[$index] !== '') {
                $this->attributeManager->createOrUpdate(
                    $subscriber,
                    $attributeDefinition,
                    $data[$index]
                );
            }
        }
    }

    /**
     * Export subscribers to a CSV file.
     *
     * @param SubscriberFilter|null $filter Optional filter to apply
     * @param int $batchSize Number of subscribers to process in each batch
     * @return Response A streamed response with the CSV file
     */
    public function exportToCsv(?SubscriberFilter $filter = null, int $batchSize = 1000): Response
    {
        if ($filter === null) {
            $filter = new SubscriberFilter();
        }

        $response = new StreamedResponse(function () use ($filter, $batchSize) {
            $this->generateCsvContent($filter, $batchSize);
        });

        return $this->configureResponse($response);
    }

    /**
     * Generate CSV content for the export.
     *
     * @param SubscriberFilter $filter Filter to apply
     * @param int $batchSize Batch size for processing
     */
    private function generateCsvContent(SubscriberFilter $filter, int $batchSize): void
    {
        $handle = fopen('php://output', 'w');
        $attributeDefinitions = $this->attrDefRepository->findAll();

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
    private function exportSubscribers($handle, SubscriberFilter $filter, int $batchSize, array $attributeDefinitions): void
    {
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
     * @param StreamedResponse $response The response
     * @return Response The configured response
     */
    private function configureResponse(StreamedResponse $response): Response
    {
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'subscribers_export_' . date('Y-m-d') . '.csv'
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
