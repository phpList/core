<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use League\Csv\Reader;
use PhpList\Core\Domain\Subscription\Model\Dto\ImportSubscriberDto;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use League\Csv\Exception as CsvException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class CsvImporter
{
    public function __construct(
        private readonly CsvRowToDtoMapper $rowMapper,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $csvFilePath
     * @return array{valid: ImportSubscriberDto[], errors: array<int, array<string>>}
     * @throws CsvException
     */
    public function import(string $csvFilePath): array
    {
        $reader = Reader::createFromPath($csvFilePath, 'r');
        $reader->setHeaderOffset(0);
        $records = $reader->getRecords();
        $validDtos = [];
        $errors = [];

        foreach ($records as $index => $row) {
            try {
                $dto = $this->rowMapper->map($row);
                $violations = $this->validator->validate($dto);

                if (count($violations) > 0) {
                    $errors[$index + 1] = [];
                    foreach ($violations as $violation) {
                        $errors[$index + 1][] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                    }
                    continue;
                }

                $validDtos[] = $dto;
            } catch (Throwable $e) {
                $errors[$index + 1][] = $this->translator->trans('Unexpected error: %error%', [
                    '%error%' => $e->getMessage()
                ]);
            }
        }

        return [
            'valid' => $validDtos,
            'errors' => $errors,
        ];
    }
}
