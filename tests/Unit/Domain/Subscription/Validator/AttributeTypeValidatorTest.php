<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Validator;

use InvalidArgumentException;
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
        $this->expectExceptionMessage('Invalid attribute type: "invalid_type"');
        
        $this->validator->validate('invalid_type');
    }

    public function testThrowsExceptionForNonStringValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be a string.');
        
        $this->validator->validate(123);
    }
}
