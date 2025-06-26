<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use InvalidArgumentException;
use PhpList\Core\Core\ConfigProvider;
use PhpList\Core\Domain\Analytics\Model\LinkTrack;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackRepository;
use PhpList\Core\Domain\Analytics\Service\LinkTrackService;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LinkTrackServiceTest extends TestCase
{
    private LinkTrackService $subject;
    private LinkTrackRepository|MockObject $linkTrackRepository;

    protected function setUp(): void
    {
        $this->linkTrackRepository = $this->createMock(LinkTrackRepository::class);
        $configProvider = $this->createMock(ConfigProvider::class);

        $configProvider->method('get')
            ->with('click_track', false)
            ->willReturn(true);

        $this->subject = new LinkTrackService($this->linkTrackRepository, $configProvider);
    }

    public function testExtractAndSaveLinksWithNoLinks(): void
    {
        $messageId = 123;
        $userId = 456;

        $messageContent = new MessageContent('Test Subject', 'No links here');

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getContent')->willReturn($messageContent);

        $this->linkTrackRepository->expects(self::never())->method('save');

        $result = $this->subject->extractAndSaveLinks($message, $userId);

        self::assertEmpty($result);
    }

    public function testExtractAndSaveLinksWithLinks(): void
    {
        $messageId = 123;
        $userId = 456;
        $htmlContent = '<p>Check out <a href="https://example.com">this link</a> and '
            . '<a href="https://test.com">this one</a>.</p>';

        $messageContent = new MessageContent('Test Subject', $htmlContent);

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getContent')->willReturn($messageContent);

        $this->linkTrackRepository->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (LinkTrack $linkTrack) use ($messageId, $userId) {
                self::assertSame($messageId, $linkTrack->getMessageId());
                self::assertSame($userId, $linkTrack->getUserId());
                self::assertContains($linkTrack->getUrl(), ['https://example.com', 'https://test.com']);
                return null;
            });

        $result = $this->subject->extractAndSaveLinks($message, $userId);

        self::assertCount(2, $result);
        self::assertSame('https://example.com', $result[0]->getUrl());
        self::assertSame('https://test.com', $result[1]->getUrl());
    }

    public function testExtractAndSaveLinksWithFooter(): void
    {
        $messageId = 123;
        $userId = 456;
        $htmlContent = '<p>Main content with <a href="https://example.com">a link</a>.</p>';
        $footerContent = '<p>Footer with <a href="https://footer.com">another link</a>.</p>';

        $messageContent = new MessageContent('Test Subject', $htmlContent, null, $footerContent);

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getContent')->willReturn($messageContent);

        $this->linkTrackRepository->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (LinkTrack $linkTrack) use ($messageId, $userId) {
                self::assertSame($messageId, $linkTrack->getMessageId());
                self::assertSame($userId, $linkTrack->getUserId());
                self::assertContains($linkTrack->getUrl(), ['https://example.com', 'https://footer.com']);
                return null;
            });

        $result = $this->subject->extractAndSaveLinks($message, $userId);

        self::assertCount(2, $result);
        self::assertSame('https://example.com', $result[0]->getUrl());
        self::assertSame('https://footer.com', $result[1]->getUrl());
    }

    public function testExtractAndSaveLinksWithDuplicateLinks(): void
    {
        $messageId = 123;
        $userId = 456;
        $htmlContent = '<p><a href="https://example.com">Link 1</a> and <a href="https://example.com">Link 2</a>.</p>';

        $messageContent = new MessageContent('Test Subject', $htmlContent);

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getContent')->willReturn($messageContent);

        $this->linkTrackRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (LinkTrack $linkTrack) use ($messageId, $userId) {
                self::assertSame($messageId, $linkTrack->getMessageId());
                self::assertSame($userId, $linkTrack->getUserId());
                self::assertSame('https://example.com', $linkTrack->getUrl());
                return null;
            });

        $result = $this->subject->extractAndSaveLinks($message, $userId);

        self::assertCount(1, $result);
        self::assertSame('https://example.com', $result[0]->getUrl());
    }

    public function testExtractAndSaveLinksWithNullText(): void
    {
        $messageId = 123;
        $userId = 456;
        $footerContent = '<p>Footer with <a href="https://footer.com">a link</a>.</p>';

        $messageContent = new MessageContent('Test Subject', null, null, $footerContent);

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getContent')->willReturn($messageContent);

        $this->linkTrackRepository->expects(self::once())
            ->method('save')
            ->willReturnCallback(function (LinkTrack $linkTrack) use ($messageId, $userId) {
                self::assertSame($messageId, $linkTrack->getMessageId());
                self::assertSame($userId, $linkTrack->getUserId());
                self::assertSame('https://footer.com', $linkTrack->getUrl());
                return null;
            });

        $result = $this->subject->extractAndSaveLinks($message, $userId);

        self::assertCount(1, $result);
        self::assertSame('https://footer.com', $result[0]->getUrl());
    }

    public function testExtractAndSaveLinksWithMessageWithoutId(): void
    {
        $userId = 456;
        $htmlContent = '<p><a href="https://example.com">Link</a></p>';

        $messageContent = new MessageContent('Test Subject', $htmlContent);

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(null);
        $message->method('getContent')->willReturn($messageContent);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message must have an ID');

        $this->subject->extractAndSaveLinks($message, $userId);
    }

    public function testIsExtractAndSaveLinksApplicableWhenClickTrackIsTrue(): void
    {
        self::assertTrue($this->subject->isExtractAndSaveLinksApplicable());
    }

    public function testIsExtractAndSaveLinksApplicableWhenClickTrackIsFalse(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('get')
            ->with('click_track', false)
            ->willReturn(false);

        $subject = new LinkTrackService($this->linkTrackRepository, $configProvider);

        self::assertFalse($subject->isExtractAndSaveLinksApplicable());
    }

    public function testExtractAndSaveLinksWithExistingLink(): void
    {
        $messageId = 123;
        $userId = 456;
        $url = 'https://example.com';
        $htmlContent = '<p>Check out <a href="' . $url . '">this link</a>.</p>';

        $messageContent = new MessageContent('Test Subject', $htmlContent);

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getContent')->willReturn($messageContent);

        $existingLinkTrack = new LinkTrack();
        $existingLinkTrack->setMessageId($messageId);
        $existingLinkTrack->setUserId($userId);
        $existingLinkTrack->setUrl($url);

        $this->linkTrackRepository->expects(self::once())
            ->method('findByUrlUserIdAndMessageId')
            ->with($url, $userId, $messageId)
            ->willReturn($existingLinkTrack);

        $this->linkTrackRepository->expects(self::never())
            ->method('save');

        $result = $this->subject->extractAndSaveLinks($message, $userId);

        self::assertCount(1, $result);
        self::assertSame($existingLinkTrack, $result[0]);
    }
}
