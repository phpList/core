<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PHPUnit\Framework\TestCase;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Subscription\Model\SubscribePage;
use PhpList\Core\Domain\Subscription\Model\SubscribePageData;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Service\SubscribePagePlaceholderProcessor;

class SubscribePagePlaceholderProcessorTest extends TestCase
{
    public function testItProcessesPlaceholders(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $listRepository = $this->createMock(SubscriberListRepository::class);

        $configProvider
            ->method('getValue')
            ->willReturnMap([
                [ConfigOption::OrganisationName, 'My Organisation'],
                [ConfigOption::SubscribeUrl, 'https://example.com/subscribe'],
                [ConfigOption::UnsubscribeUrl, 'https://example.com/unsubscribe'],
                [ConfigOption::PreferencesUrl, 'https://example.com/preferences'],
            ]);

        $list1 = $this->createMock(SubscriberList::class);
        $list1->method('getName')->willReturn('News');

        $list2 = $this->createMock(SubscriberList::class);
        $list2->method('getName')->willReturn('Offers');

        $listRepository
            ->expects($this->once())
            ->method('getPublicByIds')
            ->with(['1', '2'])
            ->willReturn([$list1, $list2]);

        $processor = new SubscribePagePlaceholderProcessor(
            $configProvider,
            $listRepository
        );

        $listsData = new SubscribePageData();
        $listsData->setName('lists');
        $listsData->setData('1,2');

        $contentData = new SubscribePageData();
        $contentData->setName('confirmationmessage');
        $contentData->setData(
            'Welcome to [ORGANISATION_NAME]. ' .
            'Lists: [LISTS]. ' .
            'Subscribe: [SUBSCRIBEURL]'
        );

        $page = $this->createMock(SubscribePage::class);

        $page->method('getId')->willReturn(42);
        $page->method('getData')->willReturn([
            $listsData,
            $contentData,
        ]);

        $processor->process($page);

        $this->assertSame(
            'Welcome to My Organisation. Lists: News' . "\n" .
            'Offers. Subscribe: https://example.com/subscribe&id=42',
            $contentData->getData()
        );
    }
}
