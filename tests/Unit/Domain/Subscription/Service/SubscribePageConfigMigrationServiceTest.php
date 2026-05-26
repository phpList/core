<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Configuration\Model\Config;
use PhpList\Core\Domain\Configuration\Repository\ConfigRepository;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageDataRepository;
use PhpList\Core\Domain\Subscription\Service\SubscribePageConfigMigrationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscribePageConfigMigrationServiceTest extends TestCase
{
    private ConfigRepository|MockObject $configRepository;
    private SubscriberPageDataRepository|MockObject $pageDataRepository;
    private EntityManagerInterface|MockObject $entityManager;
    private SubscribePageConfigMigrationService $service;

    protected function setUp(): void
    {
        $this->configRepository = $this->createMock(ConfigRepository::class);
        $this->pageDataRepository = $this->createMock(SubscriberPageDataRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new SubscribePageConfigMigrationService(
            configRepository: $this->configRepository,
            pageDataRepository: $this->pageDataRepository,
            entityManager: $this->entityManager,
        );
    }

    public function testCopyToPageDataSkipsPagesWithoutId(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(null);

        $this->configRepository
            ->expects($this->never())
            ->method('findValueByItem');

        $this->pageDataRepository
            ->expects($this->never())
            ->method('getByPage');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->assertFalse($this->service->copyToPageData($page));
    }

    public function testCopyToPageDataSkipsWhenNoConfigValuesFound(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(1);

        $this->configRepository
            ->expects($this->exactly(6))
            ->method('findValueByItem')
            ->willReturn(null);

        $this->pageDataRepository
            ->expects($this->never())
            ->method('getByPage');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->assertFalse($this->service->copyToPageData($page));
    }

    public function testCopyToPageDataSyncsExistingAndAddsMissingEntriesThenFlushes(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId', 'setData'])
            ->getMock();
        $page->method('getId')->willReturn(1);
        $page->method('setData')->willReturnSelf();

        $existing = (new SubscribePageData())
            ->setId(1)
            ->setName('confirmationsubject')
            ->setData('existing intro');

        $this->configRepository
            ->expects($this->exactly(6))
            ->method('findValueByItem')
            ->willReturnMap([
                ['subscribemessage:1', 'message from config'],
                ['subscribesubject:1', null],
                ['confirmationsubject:1', 'updated subject'],
                ['confirmationmessage:1', null],
                ['unsubscribesubject:1', null],
                ['unsubscribemessage:1', null],
            ]);

        $this->pageDataRepository
            ->expects($this->once())
            ->method('getByPage')
            ->with($page)
            ->willReturn([$existing]);

        $this->pageDataRepository
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(static function (SubscribePageData $pageData): bool {
                return $pageData->getId() === 1
                    && $pageData->getName() === 'subscribemessage'
                    && $pageData->getData() === 'message from config';
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $page
            ->expects($this->once())
            ->method('setData')
            ->with($this->callback(static function (array $data): bool {
                if (count($data) !== 2) {
                    return false;
                }

                $dataByName = [];
                foreach ($data as $item) {
                    if (!$item instanceof SubscribePageData) {
                        return false;
                    }
                    $dataByName[$item->getName()] = $item->getData();
                }

                return isset($dataByName['confirmationsubject'], $dataByName['subscribemessage'])
                    && $dataByName['confirmationsubject'] === 'updated subject'
                    && $dataByName['subscribemessage'] === 'message from config';
            }))
            ->willReturnSelf();

        $this->assertTrue($this->service->copyToPageData($page));
    }

    public function testCopyToConfigSkipsPagesWithoutId(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(null);

        $this->configRepository
            ->expects($this->never())
            ->method('findOneBy');

        $this->configRepository
            ->expects($this->never())
            ->method('persist');

        $this->service->copyToConfig($page, ['subscribemessage' => 'message']);
    }

    public function testCopyToConfigSkipsUnsupportedDataKeys(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(10);

        $this->configRepository
            ->expects($this->never())
            ->method('findOneBy');

        $this->configRepository
            ->expects($this->never())
            ->method('persist');

        $this->service->copyToConfig($page, ['other_key' => 'value']);
    }

    public function testCopyToConfigCreatesMissingConfigRows(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(7);

        $this->configRepository
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->withConsecutive(
                [['key' => 'subscribemessage:7']],
                [['key' => 'confirmationsubject:7']],
            )
            ->willReturnOnConsecutiveCalls(null, null);

        $this->configRepository
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(static function (Config $config): bool {
                return in_array($config->getKey(), ['subscribemessage:7', 'confirmationsubject:7'], true)
                    && in_array($config->getValue(), ['msg', 'subject'], true);
            }));

        $this->service->copyToConfig(
            $page,
            [
                'subscribemessage' => 'msg',
                'confirmationsubject' => 'subject',
            ],
        );
    }

    public function testCopyToConfigUpdatesExistingRowsWithoutPersist(): void
    {
        $page = $this->getMockBuilder(SubscribePage::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $page->method('getId')->willReturn(11);

        $existing = (new Config())
            ->setKey('confirmationmessage:11')
            ->setValue('old');

        $this->configRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['key' => 'confirmationmessage:11'])
            ->willReturn($existing);

        $this->configRepository
            ->expects($this->never())
            ->method('persist');

        $this->service->copyToConfig($page, ['confirmationmessage' => 'new']);

        $this->assertSame('new', $existing->getValue());
    }
}
