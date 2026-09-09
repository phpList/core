<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Search;

use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Messaging\Service\Search\UserMessageBounceReindexProvider;
use PHPUnit\Framework\TestCase;

class UserMessageBounceReindexProviderTest extends TestCase
{
    public function testAliasMatchesEntitySearchIndexName(): void
    {
        $provider = new UserMessageBounceReindexProvider($this->createMock(UserMessageBounceRepository::class));

        $this->assertSame('user_message_bounce', $provider->getAlias());
    }
}
