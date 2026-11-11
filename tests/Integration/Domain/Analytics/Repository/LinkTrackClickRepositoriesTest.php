<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Analytics\Repository;

use DateTimeImmutable;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Analytics\Model\LinkTrackMl;
use PhpList\Core\Domain\Analytics\Model\LinkTrackUmlClick;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackForwardRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackMlRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackUmlClickRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LinkTrackClickRepositoriesTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private LinkTrackForwardRepository $forwardRepository;
    private LinkTrackMlRepository $mlRepository;
    private LinkTrackUmlClickRepository $umlClickRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->forwardRepository = self::getContainer()->get(LinkTrackForwardRepository::class);
        $this->mlRepository = self::getContainer()->get(LinkTrackMlRepository::class);
        $this->umlClickRepository = self::getContainer()->get(LinkTrackUmlClickRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testFindOrCreateByUrlCreatesOneForwardPerUrl(): void
    {
        $first = $this->forwardRepository->findOrCreateByUrl('https://example.com/a', false);
        $again = $this->forwardRepository->findOrCreateByUrl('https://example.com/a', false);
        $other = $this->forwardRepository->findOrCreateByUrl('https://example.com/b', true);

        self::assertSame($first->getId(), $again->getId());
        self::assertNotSame($first->getId(), $other->getId());
        self::assertSame('https://example.com/a', $first->getUrl());
        self::assertSame(md5('https://example.com/a'), $first->getUrlHash());
        self::assertFalse($first->isPersonalise());
        self::assertTrue($other->isPersonalise());
        self::assertSame(36, strlen((string) $first->getUuid()));
        self::assertNotSame($first->getUuid(), $other->getUuid());
        self::assertSame($first->getId(), $this->forwardRepository->findOneByUuid($first->getUuid())->getId());
        self::assertNull($this->forwardRepository->findOneByUuid('unknown'));
    }

    public function testIncrementTotalCreatesAndIncrementsTheRow(): void
    {
        $this->mlRepository->incrementTotal(7, 11);
        $this->mlRepository->incrementTotal(7, 11);
        $this->mlRepository->incrementTotal(7, 12);

        self::assertSame(2, $this->findMl(7, 11)->getTotal());
        self::assertSame(1, $this->findMl(7, 12)->getTotal());
        self::assertSame(0, $this->findMl(7, 11)->getClicked());
    }

    public function testRecordClickOnMlKeepsTheFirstClickAndSplitsHtmlAndText(): void
    {
        $this->mlRepository->incrementTotal(7, 11);
        $this->mlRepository->recordClick(7, 11, true, new DateTimeImmutable('2026-01-01 10:00:00'));
        $this->mlRepository->recordClick(7, 11, false, new DateTimeImmutable('2026-01-02 11:00:00'));
        $this->mlRepository->recordClick(7, 11, true, new DateTimeImmutable('2026-01-03 12:00:00'));

        $ml = $this->findMl(7, 11);
        self::assertSame(1, $ml->getTotal(), 'sending total is not touched by clicks');
        self::assertSame(3, $ml->getClicked());
        self::assertSame(2, $ml->getHtmlClicked());
        self::assertSame(1, $ml->getTextClicked());
        self::assertSame('2026-01-01 10:00:00', $ml->getFirstClick()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-03 12:00:00', $ml->getLatestClick()->format('Y-m-d H:i:s'));
    }

    public function testRecordClickOnMlCreatesTheRowWhenNothingWasSentYet(): void
    {
        $this->mlRepository->recordClick(7, 11, false, new DateTimeImmutable('2026-01-01 10:00:00'));

        $ml = $this->findMl(7, 11);
        self::assertSame(1, $ml->getClicked());
        self::assertSame(1, $ml->getTextClicked());
        self::assertSame(0, $ml->getHtmlClicked());
    }

    public function testRecordClickPerSubscriberIsUniquePerCampaignSubscriberAndLink(): void
    {
        $this->umlClickRepository->recordClick(7, 3, 11, true, new DateTimeImmutable('2026-01-01 10:00:00'));
        $this->umlClickRepository->recordClick(7, 3, 11, false, new DateTimeImmutable('2026-01-02 11:00:00'));
        $this->umlClickRepository->recordClick(7, 4, 11, true, new DateTimeImmutable('2026-01-02 11:00:00'));
        $this->umlClickRepository->recordClick(7, 3, 12, true, new DateTimeImmutable('2026-01-02 11:00:00'));

        $click = $this->findUml(7, 3, 11);
        self::assertSame(2, $click->getClicked());
        self::assertSame(1, $click->getHtmlClicked());
        self::assertSame(1, $click->getTextClicked());
        self::assertSame('2026-01-01 10:00:00', $click->getFirstClick()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-02 11:00:00', $click->getLatestClick()->format('Y-m-d H:i:s'));
        self::assertSame(1, $this->findUml(7, 4, 11)->getClicked());
        self::assertSame(1, $this->findUml(7, 3, 12)->getClicked());
        self::assertCount(3, $this->umlClickRepository->findAll());
    }

    private function findMl(int $messageId, int $forwardId): LinkTrackMl
    {
        $this->entityManager->clear();

        return $this->mlRepository->findOneBy(['messageId' => $messageId, 'forwardId' => $forwardId]);
    }

    private function findUml(int $messageId, int $userId, int $forwardId): LinkTrackUmlClick
    {
        $this->entityManager->clear();

        return $this->umlClickRepository->findOneBy(
            ['messageId' => $messageId, 'userId' => $userId, 'forwardId' => $forwardId]
        );
    }
}
