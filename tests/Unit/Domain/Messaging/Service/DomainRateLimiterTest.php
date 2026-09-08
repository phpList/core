<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Service\DomainRateLimiter;
use PHPUnit\Framework\TestCase;

class DomainRateLimiterTest extends TestCase
{
    public function testAllowsSendsWhenDisabled(): void
    {
        $limiter = new DomainRateLimiter(enabled: false, domainBatchSize: 1, domainBatchPeriod: 120);

        $this->assertTrue($limiter->canSendTo('a@example.com'));
        $limiter->recordSend('a@example.com');
        $this->assertTrue($limiter->canSendTo('a@example.com'));
    }

    public function testAllowsSendsWhenBatchSizeOrPeriodIsNotPositive(): void
    {
        $limiter = new DomainRateLimiter(enabled: true, domainBatchSize: 0, domainBatchPeriod: 120);
        $this->assertTrue($limiter->canSendTo('a@example.com'));

        $limiter = new DomainRateLimiter(enabled: true, domainBatchSize: 1, domainBatchPeriod: 0);
        $this->assertTrue($limiter->canSendTo('a@example.com'));
    }

    public function testBlocksSendsToSameDomainOnceQuotaReached(): void
    {
        $limiter = new DomainRateLimiter(enabled: true, domainBatchSize: 2, domainBatchPeriod: 120);

        $this->assertTrue($limiter->canSendTo('first@example.com'));
        $limiter->recordSend('first@example.com');

        $this->assertTrue($limiter->canSendTo('second@example.com'));
        $limiter->recordSend('second@example.com');

        $this->assertFalse($limiter->canSendTo('third@example.com'));
    }

    public function testTracksEachDomainIndependently(): void
    {
        $limiter = new DomainRateLimiter(enabled: true, domainBatchSize: 1, domainBatchPeriod: 120);

        $limiter->recordSend('a@example.com');

        $this->assertFalse($limiter->canSendTo('b@example.com'));
        $this->assertTrue($limiter->canSendTo('c@example.org'));
    }

    public function testResetsQuotaAfterPeriodElapses(): void
    {
        $limiter = new DomainRateLimiter(enabled: true, domainBatchSize: 1, domainBatchPeriod: 0);

        $limiter->recordSend('a@example.com');

        // domainBatchPeriod = 0 means the window is already elapsed on the very next check
        $this->assertTrue($limiter->canSendTo('a@example.com'));
    }

    public function testTreatsAddressWithoutAtSignAsUnthrottleable(): void
    {
        $limiter = new DomainRateLimiter(enabled: true, domainBatchSize: 1, domainBatchPeriod: 120);

        $limiter->recordSend('not-an-email');

        $this->assertTrue($limiter->canSendTo('not-an-email'));
    }

    public function testDomainMatchingIsCaseInsensitive(): void
    {
        $limiter = new DomainRateLimiter(enabled: true, domainBatchSize: 1, domainBatchPeriod: 120);

        $limiter->recordSend('first@Example.com');

        $this->assertFalse($limiter->canSendTo('second@example.COM'));
    }
}