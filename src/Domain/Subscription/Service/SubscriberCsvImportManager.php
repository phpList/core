<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Exception;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\SubscriberImportOptions;
use PhpList\Core\Domain\Subscription\Model\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service for importing and exporting subscribers from/to CSV files.
 */
class SubscriberCsvImportManager
{
    private SubscriberManager $subscriberManager;
    private SubscriberAttributeManager $attributeManager;
    private SubscriberRepository $subscriberRepository;
    private SubscriberAttributeDefinitionRepository $definitionRepository;

    public function __construct(
        SubscriberManager $subscriberManager,
        SubscriberAttributeManager $attributeManager,
        SubscriberRepository $subscriberRepository,
        SubscriberAttributeDefinitionRepository $definitionRepository
    ) {
        $this->subscriberManager = $subscriberManager;
        $this->attributeManager = $attributeManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->definitionRepository = $definitionRepository;
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

        [$handle, $headers, $attributeDefinitions] = $this->prepareImport($file);

        $lineNumber = 2;
        $data = fgetcsv($handle);
        while ($data !== false) {
            try {
                $this->processRow(
                    data: $data,
                    headers: $headers,
                    attributeDefinitions: $attributeDefinitions,
                    options: $options,
                    stats: $stats,
                    lineNumber: $lineNumber
                );
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
        return $this->importFromCsv($file, new SubscriberImportOptions(updateExisting: true));
    }

    /**
     * Import subscribers without updating existing ones.
     *
     * @param UploadedFile $file The uploaded CSV file
     * @return array Import statistics
     */
    public function importNewFromCsv(UploadedFile $file): array
    {
        return $this->importFromCsv($file, new SubscriberImportOptions());
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

            $attributeDefinition = $this->definitionRepository->findOneBy(['name' => $header]);
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
     * @param SubscriberImportOptions $options
     * @param array $stats Statistics to update
     * @param int $lineNumber Current line number for error reporting
     */
    private function processRow(
        array $data,
        array $headers,
        array $attributeDefinitions,
        SubscriberImportOptions $options,
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

        if ($existingSubscriber && !$options->updateExisting) {
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
        if ($extraDataIndex !== false && isset($data[$extraDataIndex])) {
            $additionalData = $data[$extraDataIndex];
        } else {
            $additionalData = $existingSubscriber->getExtraData();
        }

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
}
