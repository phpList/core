<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Message;

use PhpList\Core\Domain\Messaging\Message\SubscriberConfirmationMessage;
use PHPUnit\Framework\TestCase;

class SubscriberConfirmationMessageTest extends TestCase
{
    public function testGettersReturnCorrectValues(): void
    {
        $email = 'test@example.com';
        $uniqueId = 'abc123';
        $htmlEmail = true;

        $message = new SubscriberConfirmationMessage($email, $uniqueId, $htmlEmail);

        $this->assertSame($email, $message->getEmail());
        $this->assertSame($uniqueId, $message->getUniqueId());
        $this->assertTrue($message->hasHtmlEmail());
    }

    public function testDefaultHtmlEmailIsFalse(): void
    {
        $email = 'test@example.com';
        $uniqueId = 'abc123';

        $message = new SubscriberConfirmationMessage($email, $uniqueId);

        $this->assertFalse($message->hasHtmlEmail());
    }
}
