<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Model;

use PhpList\Core\Domain\Identity\Model\PrivilegeFlag;
use PHPUnit\Framework\TestCase;

/**
 * Testcase for the PrivilegeFlag enum.
 */
class PrivilegeFlagTest extends TestCase
{
    public function testEnumHasSubscribersCase(): void
    {
        self::assertSame('subscribers', PrivilegeFlag::Subscribers->value);
    }

    public function testEnumHasCampaignsCase(): void
    {
        self::assertSame('campaigns', PrivilegeFlag::Campaigns->value);
    }

    public function testEnumHasStatisticsCase(): void
    {
        self::assertSame('statistics', PrivilegeFlag::Statistics->value);
    }

    public function testEnumHasSettingsCase(): void
    {
        self::assertSame('settings', PrivilegeFlag::Settings->value);
    }

    public function testEnumHasFourCases(): void
    {
        $cases = PrivilegeFlag::cases();
        
        self::assertCount(4, $cases);
        self::assertContains(PrivilegeFlag::Subscribers, $cases);
        self::assertContains(PrivilegeFlag::Campaigns, $cases);
        self::assertContains(PrivilegeFlag::Statistics, $cases);
        self::assertContains(PrivilegeFlag::Settings, $cases);
    }
}
