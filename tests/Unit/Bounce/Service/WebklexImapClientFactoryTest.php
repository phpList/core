<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service;

use PhpList\Core\Bounce\Service\WebklexImapClientFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class WebklexImapClientFactoryTest extends TestCase
{
    private ClientManager&MockObject $manager;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(ClientManager::class);
    }

    public function testMakeForMailboxBuildsClientWithConfiguredParams(): void
    {
        $factory = new WebklexImapClientFactory(
            clientManager: $this->manager,
            mailbox: 'imap.example.com#BOUNCES',
            host: 'imap.example.com',
            username: 'user',
            password: 'pass',
            protocol: 'imap',
            port: 993,
            encryption: 'ssl'
        );

        $client = $this->createMock(Client::class);

        $this->manager
            ->expects($this->once())
            ->method('make')
            ->with($this->callback(function (array $cfg) {
                $this->assertSame('imap.example.com', $cfg['host']);
                $this->assertSame(993, $cfg['port']);
                $this->assertSame('ssl', $cfg['encryption']);
                $this->assertTrue($cfg['validate_cert']);
                $this->assertSame('user', $cfg['username']);
                $this->assertSame('pass', $cfg['password']);
                $this->assertSame('imap', $cfg['protocol']);
                return true;
            }))
            ->willReturn($client);

        $out = $factory->makeForMailbox();
        $this->assertSame($client, $out);
        $this->assertSame('BOUNCES', $factory->getFolderName());
    }

    public function testGetFolderNameDefaultsToInbox(): void
    {
        $factory = new WebklexImapClientFactory(
            clientManager: $this->manager,
            mailbox: 'imap.example.com',
            host: 'imap.example.com',
            username: 'u',
            password: 'p',
            protocol: 'imap',
            port: 993
        );
        $this->assertSame('INBOX', $factory->getFolderName());
    }
}
