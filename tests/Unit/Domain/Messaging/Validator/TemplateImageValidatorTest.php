<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Validator;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Subscription\Validator\TemplateImageValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Exception\ValidatorException;

class TemplateImageValidatorTest extends TestCase
{
    private TemplateImageValidator $validator;
    private ClientInterface&MockObject $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->validator = new TemplateImageValidator($this->httpClient);
    }

    public function testThrowsExceptionIfValueIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->validate('not-an-array');
    }

    public function testValidatesFullUrls(): void
    {
        $context = (new ValidationContext())->set('checkImages', true);

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessageMatches('/not-a-url/');

        $this->validator->validate(['not-a-url', 'https://valid.url/image.jpg'], $context);
    }

    public function testValidatesExistenceWithHttp200(): void
    {
        $context = (new ValidationContext())->set('checkExternalImages', true);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('HEAD', 'https://example.com/image.jpg')
            ->willReturn(new Response(200));

        $this->validator->validate(['https://example.com/image.jpg'], $context);

        $this->assertTrue(true);
    }

    public function testValidatesExistenceWithHttp404(): void
    {
        $context = (new ValidationContext())->set('checkExternalImages', true);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('HEAD', 'https://example.com/missing.jpg')
            ->willReturn(new Response(404));

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $this->validator->validate(['https://example.com/missing.jpg'], $context);
    }

    public function testValidatesExistenceThrowsHttpException(): void
    {
        $context = (new ValidationContext())->set('checkExternalImages', true);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new Exception('Connection failed'));

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessageMatches('/could not be validated/');

        $this->validator->validate(['https://example.com/broken.jpg'], $context);
    }
}
