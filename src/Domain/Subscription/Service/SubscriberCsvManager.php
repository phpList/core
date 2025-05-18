<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use Exception;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Dto\UpdateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
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
    private SubscriberAttributeDefinitionRepository $attributeDefinitionRepository;

    public function __construct(
        SubscriberManager $subscriberManager,
        SubscriberAttributeManager $attributeManager,
        SubscriberRepository $subscriberRepository,
        SubscriberAttributeDefinitionRepository $attributeDefinitionRepository
    ) {
        $this->subscriberManager = $subscriberManager;
        $this->attributeManager = $attributeManager;
        $this->subscriberRepository = $subscriberRepository;
        $this->attributeDefinitionRepository = $attributeDefinitionRepository;
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

        $attributeDefinitions = [];
        foreach ($headers as $index => $header) {
            if (in_array($header, ['email', 'confirmed', 'blacklisted', 'html_email', 'disabled', 'extra_data'], true)) {
                continue;
            }

            $attributeDefinition = $this->attributeDefinitionRepository->findOneBy(['name' => $header]);
            if ($attributeDefinition) {
                $attributeDefinitions[$index] = $attributeDefinition;
            }
        }

        $lineNumber = 2;
        while (($data = fgetcsv($handle)) !== false) {
            try {
                $email = trim($data[array_search('email', $headers, true)]);
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stats['errors'][] = "Line $lineNumber: Invalid email address";
                    $stats['skipped']++;
                    $lineNumber++;
                    continue;
                }

                $existingSubscriber = $this->subscriberRepository->findOneByEmail($email);

                if ($existingSubscriber && !$updateExisting) {
                    $stats['skipped']++;
                    $lineNumber++;
                    continue;
                }

                $confirmedIndex = array_search('confirmed', $headers, true);
                if ($existingSubscriber) {
                    $confirmed = $confirmedIndex !== false && isset($data[$confirmedIndex])
                        ? filter_var($data[$confirmedIndex], FILTER_VALIDATE_BOOLEAN) 
                        : $existingSubscriber->isConfirmed();

                    $blacklistedIndex = array_search('blacklisted', $headers, true);
                    $blacklisted = $blacklistedIndex !== false && isset($data[$blacklistedIndex]) 
                        ? filter_var($data[$blacklistedIndex], FILTER_VALIDATE_BOOLEAN) 
                        : $existingSubscriber->isBlacklisted();

                    $htmlEmailIndex = array_search('html_email', $headers, true);
                    $htmlEmail = $htmlEmailIndex !== false && isset($data[$htmlEmailIndex]) 
                        ? filter_var($data[$htmlEmailIndex], FILTER_VALIDATE_BOOLEAN) 
                        : $existingSubscriber->hasHtmlEmail();

                    $disabledIndex = array_search('disabled', $headers, true);
                    $disabled = $disabledIndex !== false && isset($data[$disabledIndex]) 
                        ? filter_var($data[$disabledIndex], FILTER_VALIDATE_BOOLEAN) 
                        : $existingSubscriber->isDisabled();

                    $extraDataIndex = array_search('extra_data', $headers, true);
                    $additionalData = $extraDataIndex !== false && isset($data[$extraDataIndex]) 
                        ? $data[$extraDataIndex] 
                        : $existingSubscriber->getExtraData();

                    $dto = new UpdateSubscriberDto(
                        $existingSubscriber->getId(),
                        $email,
                        $confirmed,
                        $blacklisted,
                        $htmlEmail,
                        $disabled,
                        $additionalData
                    );

                    $subscriber = $this->subscriberManager->updateSubscriber($dto);
                    $stats['updated']++;
                } else {
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

                    $blacklistedIndex = array_search('blacklisted', $headers, true);
                    if ($blacklistedIndex !== false && isset($data[$blacklistedIndex])) {
                        $subscriber->setBlacklisted(filter_var($data[$blacklistedIndex], FILTER_VALIDATE_BOOLEAN));
                    }

                    $disabledIndex = array_search('disabled', $headers, true);
                    if ($disabledIndex !== false && isset($data[$disabledIndex])) {
                        $subscriber->setDisabled(filter_var($data[$disabledIndex], FILTER_VALIDATE_BOOLEAN));
                    }

                    $extraDataIndex = array_search('extra_data', $headers, true);
                    if ($extraDataIndex !== false && isset($data[$extraDataIndex])) {
                        $subscriber->setExtraData($data[$extraDataIndex]);
                    }

                    $this->subscriberRepository->save($subscriber);
                    $stats['created']++;
                }

                foreach ($attributeDefinitions as $index => $attributeDefinition) {
                    if (isset($data[$index]) && $data[$index] !== '') {
                        $this->attributeManager->createOrUpdate(
                            $subscriber,
                            $attributeDefinition,
                            $data[$index]
                        );
                    }
                }
            } catch (Exception $e) {
                $stats['errors'][] = "Line $lineNumber: " . $e->getMessage();
                $stats['skipped']++;
            }

            $lineNumber++;
        }

        fclose($handle);
        return $stats;
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
            $handle = fopen('php://output', 'w');

            $attributeDefinitions = $this->attributeDefinitionRepository->findAll();

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

            fputcsv($handle, $headers);

            $lastId = 0;

            do {
                $subscribers = $this->subscriberRepository->getFilteredAfterId(
                    lastId: $lastId,
                    limit: $batchSize,
                    filter: $filter
                );

                foreach ($subscribers as $subscriber) {
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
                            subscriberId:$subscriber->getId(),
                            attributeDefinitionId: $definition->getId()
                        );
                        $row[] = $attributeValue ? $attributeValue->getValue() : '';
                    }

                    fputcsv($handle, $row);

                    $lastId = $subscriber->getId();
                }

            } while (count($subscribers) === $batchSize);

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'subscribers_export_' . date('Y-m-d') . '.csv'
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
