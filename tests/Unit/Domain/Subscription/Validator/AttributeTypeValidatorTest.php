<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Validator;

use PhpList\Core\Domain\Subscription\Validator\AttributeTypeValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Exception\ValidatorException;

class AttributeTypeValidatorTest extends TestCase
{
    private AttributeTypeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AttributeTypeValidator(new Translator('en'));
    }

    public function testValidatesValidType(): void
    {
        $this->validator->validate('textline');
        $this->validator->validate('checkbox');
        $this->validator->validate('date');
        
        $this->assertTrue(true);
    }

    public function testThrowsExceptionForInvalidType(): void
    {
        $this->expectException(ValidatorException::class);

        $this->validator->validate('invalid_type');
        $this->assertTrue(true);
    }

    public function testThrowsExceptionForNonStringValue(): void
    {
        $this->expectException(ValidatorException::class);

        $this->validator->validate(123);
        $this->assertTrue(true);
    }
}
