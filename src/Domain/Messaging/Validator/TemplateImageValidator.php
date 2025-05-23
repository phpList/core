<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Validator;

use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Common\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Throwable;

class TemplateImageValidator implements ValidatorInterface
{
    public function __construct(private readonly ClientInterface $httpClient)
    {
    }

    public function validate(mixed $value, ValidationContext $context = null): void
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Value must be an array of image URLs.');
        }

        $checkFull = $context?->get('checkImages', false);
        $checkExist = $context?->get('checkExternalImages', false);

        $errors = array_merge(
            $checkFull ? $this->validateFullUrls($value) : [],
            $checkExist ? $this->validateExistence($value) : []
        );

        if (!empty($errors)) {
            throw new ValidatorException(implode("\n", $errors));
        }
    }

    private function validateFullUrls(array $urls): array
    {
        $errors = [];

        foreach ($urls as $url) {
            if (!preg_match('#^https?://#i', $url)) {
                $errors[] = sprintf('Image "%s" is not a full URL.', $url);
            }
        }

        return $errors;
    }

    private function validateExistence(array $urls): array
    {
        $errors = [];

        foreach ($urls as $url) {
            if (!preg_match('#^https?://#i', $url)) {
                continue;
            }

            try {
                $response = $this->httpClient->request('HEAD', $url);
                if ($response->getStatusCode() !== 200) {
                    $errors[] = sprintf('Image "%s" does not exist (HTTP %s)', $url, $response->getStatusCode());
                }
            } catch (Throwable $e) {
                $errors[] = sprintf('Image "%s" could not be validated: %s', $url, $e->getMessage());
            }
        }

        return $errors;
    }
}
