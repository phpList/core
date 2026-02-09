<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\UserDataSupportingResolver;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserDataSupportingResolverTest extends TestCase
{
    private SubscriberRepository&MockObject $repo;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(SubscriberRepository::class);
    }

    private function makeCtx(Subscriber $user = null): PlaceholderContext
    {
        $u = $user ?? (function () {
            $s = new Subscriber();
            $s->setEmail('user@example.com');
            $s->setUniqueId('UID-X');
            // Ensure the entity has a non-null ID for repository lookup
            $rp = new \ReflectionProperty(Subscriber::class, 'id');
            $rp->setAccessible(true);
            $rp->setValue($s, 42);
            return $s;
        })();

        return new PlaceholderContext($u, OutputFormat::Text);
    }

    public function testSupportsIsCaseInsensitiveForKnownKeys(): void
    {
        $resolver = new UserDataSupportingResolver($this->repo);

        $ctx = $this->makeCtx();
        $this->assertTrue($resolver->supports('confirmed', $ctx));
        $this->assertTrue($resolver->supports('CONFIRMED', $ctx));
        $this->assertTrue($resolver->supports('UniqId', $ctx));
        $this->assertFalse($resolver->supports('UNKNOWN_KEY', $ctx));
    }

    public function testResolveReturnsScalarStringForMatchingKey(): void
    {
        $resolver = new UserDataSupportingResolver($this->repo);
        $ctx = $this->makeCtx();

        $this->repo->expects($this->once())
            ->method('getDataById')
            ->with($ctx->getUser()->getId())
            ->willReturn(
                [
                'confirmed' => true,
                'uniqid' => 'ABC123',
                ]
            );

        $this->assertSame('ABC123', $resolver->resolve('uniqid', $ctx));
    }

    public function testResolveReturnsNullWhenValueNullOrEmpty(): void
    {
        $resolver = new UserDataSupportingResolver($this->repo);
        $ctx = $this->makeCtx();

        $this->repo->method('getDataById')
            ->with($ctx->getUser()->getId())
            ->willReturn(
                [
                'uuid' => null,
                'foreignkey' => '',
                ]
            );

        $this->assertNull($resolver->resolve('uuid', $ctx));
        $this->assertNull($resolver->resolve('foreignkey', $ctx));
    }

    public function testResolveReturnsNullWhenKeyAbsent(): void
    {
        $resolver = new UserDataSupportingResolver($this->repo);
        $ctx = $this->makeCtx();

        $this->repo->method('getDataById')
            ->with($ctx->getUser()->getId())
            ->willReturn(
                [
                'confirmed' => 1,
                'uniqid' => 'Z',
                ]
            );

        $this->assertNull($resolver->resolve('rssfrequency', $ctx));
    }
}
