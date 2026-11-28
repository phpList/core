<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Service\EmailTldValidator;
use PhpList\Core\Domain\Subscription\Service\TldListService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmailTldValidatorTest extends TestCase
{
    private TldListService&MockObject $tlds;
    private EmailTldValidator $validator;

    protected function setUp(): void
    {
        $this->tlds = $this->createMock(TldListService::class);
        $this->tlds->method('isKnownTld')->willReturnCallback(
            static fn (string $tld): bool => in_array($tld, ['com', 'org', 'xn--p1ai'], true)
        );
        $this->validator = new EmailTldValidator($this->tlds);
    }

    /** @dataProvider provider */
    public function testHasKnownTld(string $email, bool $expected): void
    {
        $this->assertSame($expected, $this->validator->hasKnownTld($email));
    }

    public static function provider(): array
    {
        return [
            'known tld' => ['a@example.com', true],
            'subdomain' => ['a@mail.example.org', true],
            'trailing dot' => ['a@example.com.', true],
            'unknown tld' => ['a@example.invalidtld', false],
            'no dot in domain' => ['a@localhost', false],
            'no at sign' => ['example.com', false],
            'empty tld' => ['a@example.', false],
            'last at sign wins' => ['a@b@example.com', true],
            'idn tld converted to punycode' => ['a@example.рф', true],
        ];
    }
}
