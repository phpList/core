<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Validator;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UploadDirectoryValidator
{
    public function __construct(
        #[Autowire('%kernel.application_dir%/public')]
        private readonly string $publicPath,
    ) {
    }

    public function validate(string $directory): string
    {
        $directory = trim($directory);

        if ($directory === '') {
            $directory = '/';
        }

        if (!preg_match('#^[A-Za-z0-9/_-]+$#', $directory)) {
            throw new BadRequestHttpException('Invalid directory name.');
        }

        $requestedPath = $this->publicPath . '/' . ltrim($directory, '/');

        $realBasePath = realpath($this->publicPath);
        $realRequestedPath = realpath($requestedPath);

        if ($realBasePath === false || $realRequestedPath === false) {
            throw new NotFoundHttpException(sprintf('Directory "%s" not found.', $directory));
        }

        if (!str_starts_with($realRequestedPath, $realBasePath . DIRECTORY_SEPARATOR)
            && $realRequestedPath !== $realBasePath) {
            throw new BadRequestHttpException('Invalid directory.');
        }

        if (!is_dir($realRequestedPath)) {
            throw new NotFoundHttpException(sprintf('Directory "%s" not found.', $directory));
        }

        return $realRequestedPath;
    }
}
