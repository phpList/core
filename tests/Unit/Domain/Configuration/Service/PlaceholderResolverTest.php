<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Service\PlaceholderResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \PhpList\Core\Domain\Configuration\Service\PlaceholderResolver
 */
final class PlaceholderResolverTest extends TestCase
{
    public function testNullAndEmptyAreReturnedAsIs(): void
    {
        $resolver = new PlaceholderResolver();

        $this->assertNull($resolver->resolve(null));
        $this->assertSame('', $resolver->resolve(''));
    }

    public function testUnregisteredTokensRemainUnchanged(): void
    {
        $resolver = new PlaceholderResolver();

        $input = 'Hello [NAME], click [UNSUBSCRIBEURL] to opt out.';
        $this->assertSame($input, $resolver->resolve($input));
    }

    public function testCaseInsensitiveTokenResolution(): void
    {
        $resolver = new PlaceholderResolver();
        $resolver->register('unsubscribeurl', fn () => 'https://u.example/u/123');

        $input  = 'Click [UnSubscribeUrl]';
        $expect = 'Click https://u.example/u/123';

        $this->assertSame($expect, $resolver->resolve($input));
    }

    public function testMultipleDifferentTokensAreResolved(): void
    {
        $resolver = new PlaceholderResolver();
        $resolver->register('NAME', fn () => 'Ada');
        $resolver->register('EMAIL', fn () => 'ada@example.com');

        $input  = 'Hi [NAME] <[email]>';
        $expect = 'Hi Ada <ada@example.com>';

        $this->assertSame($expect, $resolver->resolve($input));
    }

    public function testAdjacentAndRepeatedTokens(): void
    {
        $resolver = new PlaceholderResolver();

        $count = 0;
        $resolver->register('X', function () use (&$count) {
            $count++;
            return 'V';
        });

        $input = 'Start [x][X]-[x] End';
        $expect = 'Start VV-V End';

        $this->assertSame($expect, $resolver->resolve($input));
        $this->assertSame(3, $count);
    }

    public function testDigitsAndUnderscoresInToken(): void
    {
        $resolver = new PlaceholderResolver();
        $resolver->register('USER_2', fn () => 'Bob#2');

        $input  = 'Hello [user_2]!';
        $expect = 'Hello Bob#2!';

        $this->assertSame($expect, $resolver->resolve($input));
    }

    public function testUnknownTokensArePreservedVerbatim(): void
    {
        $resolver = new PlaceholderResolver();
        $resolver->register('KNOWN', fn () => 'K');

        $input  = 'A[UNKNOWN]B[KNOWN]C';
        $expect = 'A[UNKNOWN]BKC';

        $this->assertSame($expect, $resolver->resolve($input));
    }
}
