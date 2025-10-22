<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service;

use PhpList\Core\Bounce\Service\MessageParser;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MessageParserTest extends TestCase
{
    private SubscriberRepository&MockObject $repo;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(SubscriberRepository::class);
    }

    public function testDecodeBodyQuotedPrintable(): void
    {
        $parser = new MessageParser($this->repo);
        $header = "Content-Transfer-Encoding: quoted-printable\r\n";
        $body = 'Hello=20World';
        $this->assertSame('Hello World', $parser->decodeBody($header, $body));
    }

    public function testDecodeBodyBase64(): void
    {
        $parser = new MessageParser($this->repo);
        $header = "Content-Transfer-Encoding: base64\r\n";
        $body = base64_encode('hi there');
        $this->assertSame('hi there', $parser->decodeBody($header, $body));
    }

    public function testFindMessageId(): void
    {
        $parser = new MessageParser($this->repo);
        $text = "X-MessageId: abc-123\r\nOther: x\r\n";
        $this->assertSame('abc-123', $parser->findMessageId($text));
    }

    public function testFindUserIdWithHeaderNumeric(): void
    {
        $parser = new MessageParser($this->repo);
        $text = "X-User: 77\r\n";
        $this->assertSame(77, $parser->findUserId($text));
    }

    public function testFindUserIdWithHeaderEmailAndLookup(): void
    {
        $parser = new MessageParser($this->repo);
        $subscriber = $this->createConfiguredMock(Subscriber::class, ['getId' => 55]);
        $this->repo->method('findOneByEmail')->with('john@example.com')->willReturn($subscriber);
        $text = "X-User: john@example.com\r\n";
        $this->assertSame(55, $parser->findUserId($text));
    }

    public function testFindUserIdByScanningEmails(): void
    {
        $parser = new MessageParser($this->repo);
        $subscriber = $this->createConfiguredMock(Subscriber::class, ['getId' => 88]);
        $this->repo->method('findOneByEmail')->with('user@acme.com')->willReturn($subscriber);
        $text = 'Hello bounce for user@acme.com, thanks';
        $this->assertSame(88, $parser->findUserId($text));
    }

    public function testFindUserReturnsNullWhenNoMatches(): void
    {
        $parser = new MessageParser($this->repo);
        $this->repo->method('findOneByEmail')->willReturn(null);
        $this->assertNull($parser->findUserId('no users here'));
    }
}
