<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Validator;

use PhpList\Core\Domain\Common\Model\ValidationContext;
use PhpList\Core\Domain\Subscription\Validator\TemplateLinkValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Exception\ValidatorException;

class TemplateLinkValidatorTest extends TestCase
{
    private TemplateLinkValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TemplateLinkValidator();
    }

    public function testSkipsValidationIfNotString(): void
    {
        $context = (new ValidationContext())->set('checkLinks', true);

        $this->validator->validate(['not', 'a', 'string'], $context);

        $this->assertTrue(true);
    }

    public function testSkipsValidationIfCheckLinksIsFalse(): void
    {
        $context = (new ValidationContext())->set('checkLinks', false);

        $this->validator->validate('<a href="invalid">Broken link</a>', $context);

        $this->assertTrue(true);
    }

    public function testValidatesInvalidLinks(): void
    {
        $context = (new ValidationContext())->set('checkLinks', true);

        $html = '<html><body><a href="invalid-link">Broken</a></body></html>';

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessageMatches('/invalid-link/');

        $this->validator->validate($html, $context);
    }

    public function testAllowsValidLinksAndPlaceholders(): void
    {
        $context = (new ValidationContext())->set('checkLinks', true);

        $html = '<html><body>' .
            '<a href="http://example.com">Valid Link</a>' .
            '<a href="https://example.com">Valid Link</a>' .
            '<a href="mailto:test@example.com">Email Link</a>' .
            '<a href="[UNSUBSCRIBEURL]">Placeholder</a>' .
            '</body></html>';

        $this->validator->validate($html, $context);

        $this->assertTrue(true);
    }
}
