<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Validator;

use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Common\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class TemplateImageValidator implements ValidatorInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function validate(mixed $value, ValidationContext $context = null): void
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException($this->translator->trans('Value must be an array of image URLs.'));
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
                $errors[] = $this->translator->trans('Image "%url%" is not a full URL.', ['%url%' => $url]);
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
                    $errors[] = $this->translator->trans('Image "%url%" does not exist (HTTP %code%)', [
                        '%url%' => $url,
                        '%code%' => $response->getStatusCode()
                    ]);
                }
            } catch (Throwable $e) {
                $errors[] = $this->translator->trans('Image "%url%" could not be validated: %message%', [
                    '%url%' => $url,
                    '%message%' => $e->getMessage()
                ]);
            }
        }

        return $errors;
    }
}
