<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\UserTrackValueResolver;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\TestCase;

final class UserTrackValueResolverTest extends TestCase
{
    private function makeUser(string $uid = 'U-42'): Subscriber
    {
        $u = new Subscriber('user@example.com');
        $u->setUniqueId($uid);
        return $u;
    }

    public function testName(): void
    {
        $resolver = new UserTrackValueResolver('https://api.example');
        $this->assertSame('USERTRACK', $resolver->name());
    }

    public function testReturnsEmptyForTextFormat(): void
    {
        $resolver = new UserTrackValueResolver('https://api.example');
        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);
        $this->assertSame('', $resolver($ctx));
    }

    public function testHtmlUsesAbsoluteRestApiBaseUrl(): void
    {
        $resolver = new UserTrackValueResolver('https://api.example/api/v2/');

        $ctx = new PlaceholderContext(
            user: $this->makeUser('UID-XYZ'),
            format: OutputFormat::Html,
            messagePrecacheDto: null,
            locale: 'en',
            forwardedBy: null,
            messageId: 99,
        );

        $expected = '<img src="https://api.example/api/v2/t/open.gif?u=UID-XYZ&amp;m=99" width="1" height="1" border="0" alt="" />';
        $this->assertSame($expected, $resolver($ctx));
    }
}
