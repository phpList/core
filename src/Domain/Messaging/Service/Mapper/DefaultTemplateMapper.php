<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Mapper;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

class DefaultTemplateMapper
{
    private const DEFAULT_TEMPLATES_DIR = __DIR__ . '/../../Resources/Templates';
    private const DEFAULT_TEMPLATES_MAP_FILE = self::DEFAULT_TEMPLATES_DIR . '/templates.json';

    public function list(): array
    {
        return $this->loadDefaultTemplatesMap();
    }

    public function findByKey(string $key): array
    {
        foreach ($this->loadDefaultTemplatesMap() as $template) {
            if ($template['key'] === $key) {
                return $template;
            }
        }

        throw new InvalidArgumentException(sprintf('Default template with key "%s" does not exist.', $key));
    }

    public function loadContent(string $file): string
    {
        $templatePath = self::DEFAULT_TEMPLATES_DIR . '/' . $file;
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $content = @file_get_contents($templatePath);
        if ($content === false) {
            throw new RuntimeException(
                sprintf('Could not read default template file "%s".', $templatePath)
            );
        }

        return $content;
    }

    private function loadDefaultTemplatesMap(): array
    {
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $raw = @file_get_contents(self::DEFAULT_TEMPLATES_MAP_FILE);
        if ($raw === false) {
            throw new RuntimeException(
                sprintf('Could not read default templates map file "%s".', self::DEFAULT_TEMPLATES_MAP_FILE)
            );
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Default templates map file "%s" is not valid JSON.', self::DEFAULT_TEMPLATES_MAP_FILE),
                previous: $exception
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                sprintf('Default templates map file "%s" must contain an array.', self::DEFAULT_TEMPLATES_MAP_FILE)
            );
        }

        $templates = [];
        foreach ($decoded as $index => $template) {
            if (!is_array($template)) {
                throw new RuntimeException(
                    sprintf('Default template entry at index %d must be an object.', $index)
                );
            }

            $this->checkRequiredFields($template, $index);

            $templates[] = [
                'key' => $template['key'],
                'name' => $template['name'],
                'description' => $template['description'],
                'file' => $template['file'],
            ];
        }

        return $templates;
    }

    private function checkRequiredFields(array $template, int|string $index): void
    {
        foreach (['key', 'name', 'description', 'file'] as $requiredField) {
            if (!isset($template[$requiredField])
                || !is_string($template[$requiredField]) || $template[$requiredField] === ''
            ) {
                throw new RuntimeException(
                    sprintf(
                        'Default template entry at index %d is missing required string field "%s".',
                        $index,
                        $requiredField
                    )
                );
            }
        }
    }
}
