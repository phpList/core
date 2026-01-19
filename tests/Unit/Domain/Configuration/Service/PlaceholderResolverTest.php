<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Service\PlaceholderResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \PhpList\Core\Domain\Configuration\Service\PlaceholderResolver
 */
final class PlaceholderResolverTest extends TestCase
{
    public function testEmptyAreReturnedAsIs(): void
    {
        $resolver = new PlaceholderResolver();
        $placeholderContext = $this->createMock(PlaceholderContext::class);

        $this->assertSame('', $resolver->resolve('', $placeholderContext));
    }

    public function testUnregisteredTokensRemainUnchanged(): void
    {
        $resolver = new PlaceholderResolver();
        $placeholderContext = $this->createMock(PlaceholderContext::class);

        $input = 'Hello [NAME], click [UNSUBSCRIBEURL] to opt out.';
        $this->assertSame($input, $resolver->resolve($input, $placeholderContext));
    }

    public function testCaseInsensitiveTokenResolution(): void
    {
        $resolver = new PlaceholderResolver();
        $resolver->register('unsubscribeurl', fn () => 'https://u.example/u/123');
        $placeholderContext = $this->createMock(PlaceholderContext::class);

        $input  = 'Click [UnSubscribeUrl]';
        $expect = 'Click https://u.example/u/123';

        $this->assertSame($expect, $resolver->resolve($input, $placeholderContext));
    }

    public function testMultipleDifferentTokensAreResolved(): void
    {
        $resolver = new PlaceholderResolver();
        $resolver->register('NAME', fn () => 'Ada');
        $resolver->register('EMAIL', fn () => 'ada@example.com');
        $placeholderContext = $this->createMock(PlaceholderContext::class);

        $input  = 'Hi [NAME] <[email]>';
        $expect = 'Hi Ada <ada@example.com>';

        $this->assertSame($expect, $resolver->resolve($input, $placeholderContext));
    }

    public function testAdjacentAndRepeatedTokens(): void
    {
        $resolver = new PlaceholderResolver();
        $placeholderContext = $this->createMock(PlaceholderContext::class);

        $count = 0;
        $resolver->register('X', function () use (&$count) {
            $count++;
            return 'V';
        });

        $input = 'Start [x][X]-[x] End';
        $expect = 'Start VV-V End';

        $this->assertSame($expect, $resolver->resolve($input, $placeholderContext));
        $this->assertSame(3, $count);
    }

    public function testDigitsAndUnderscoresInToken(): void
    {
        $resolver = new PlaceholderResolver();
        $resolver->register('USER_2', fn () => 'Bob#2');
        $placeholderContext = $this->createMock(PlaceholderContext::class);

        $input  = 'Hello [user_2]!';
        $expect = 'Hello Bob#2!';

        $this->assertSame($expect, $resolver->resolve($input, $placeholderContext));
    }

    public function testUnknownTokensArePreservedVerbatim(): void
    {
        $resolver = new PlaceholderResolver();
        $resolver->register('KNOWN', fn () => 'K');
        $placeholderContext = $this->createMock(PlaceholderContext::class);

        $input  = 'A[UNKNOWN]B[KNOWN]C';
        $expect = 'A[UNKNOWN]BKC';

        $this->assertSame($expect, $resolver->resolve($input, $placeholderContext));
    }
}
