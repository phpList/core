<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\MaskNotFoundException;

class WebklexImapClientFactory
{
    private ClientManager $clientManager;
    private string $mailbox;
    private string $host;
    private string $username;
    private string $password;
    private string $protocol;
    private int $port;
    private string $encryption;

    public function __construct(
        ClientManager $clientManager,
        string $mailbox,
        string $host,
        string $username,
        string $password,
        string $protocol,
        int $port,
        string $encryption = 'ssl'
    ) {
        $this->clientManager = $clientManager;
        $this->mailbox = $mailbox;
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->protocol = $protocol;
        $this->port = $port;
        $this->encryption = $encryption;
    }

    /**
     * @param array<string,mixed> $config
     * @throws MaskNotFoundException
     */
    public function make(array $config): Client
    {
        return $this->clientManager->make($config);
    }

    public function makeForMailbox(): Client
    {
        return $this->make([
            'host'          => $this->host,
            'port'          => $this->port,
            'encryption'    => $this->encryption,
            'validate_cert' => true,
            'username'      => $this->username,
            'password'      => $this->password,
            'protocol'      => $this->protocol,
        ]);
    }

    public function getFolderName(): string
    {
        return $this->parseMailbox($this->mailbox)[1];
    }

    private function parseMailbox(string $mailbox): array
    {
        if (str_contains($mailbox, '#')) {
            [$host, $folder] = explode('#', $mailbox, 2);
            $host = trim($host);
            $folder = trim($folder) ?: 'INBOX';
            return [$host, $folder];
        }
        return [trim($mailbox), 'INBOX'];
    }
}
