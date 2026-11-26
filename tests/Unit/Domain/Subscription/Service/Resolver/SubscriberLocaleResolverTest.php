<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Resolver;

use PhpList\Core\Domain\Subscription\Model\SubscribePageData;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberPageDataRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\SubscriberLocaleResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriberLocaleResolverTest extends TestCase
{
    private SubscriberPageDataRepository&MockObject $repository;
    private SubscriberLocaleResolver $resolver;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SubscriberPageDataRepository::class);
        $this->resolver = new SubscriberLocaleResolver($this->repository, 'en');
    }

    private function subscriberWithPage(?int $pageId): Subscriber
    {
        $subscriber = new Subscriber('a@example.com');
        $subscriber->setSubscribePage($pageId);

        return $subscriber;
    }

    public function testMapsLanguageFileOfSubscribePage(): void
    {
        $data = (new SubscribePageData())->setId(5)->setName('language_file')->setData('german.inc');
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 5, 'name' => 'language_file'])
            ->willReturn($data);

        $this->assertSame('de', $this->resolver->resolve($this->subscriberWithPage(5)));
    }

    public function testDefaultsWhenSubscriberHasNoPage(): void
    {
        $this->repository->expects($this->never())->method('findOneBy');

        $this->assertSame('en', $this->resolver->resolve($this->subscriberWithPage(null)));
    }

    public function testDefaultsWhenPageHasNoLanguageFile(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);

        $this->assertSame('en', $this->resolver->resolve($this->subscriberWithPage(5)));
    }

    public function testDefaultsWhenLanguageFileIsEmptyOrUnknown(): void
    {
        $empty = (new SubscribePageData())->setData('');
        $unknown = (new SubscribePageData())->setData('klingon.inc');
        $this->repository->method('findOneBy')->willReturnOnConsecutiveCalls($empty, $unknown);

        $this->assertSame('en', $this->resolver->resolve($this->subscriberWithPage(5)));
    }

    /**
     * @dataProvider languageFileProvider
     */
    public function testLocaleFromLanguageFile(?string $file, ?string $expected): void
    {
        $this->assertSame($expected, $this->resolver->localeFromLanguageFile($file));
    }

    public static function languageFileProvider(): array
    {
        return [
            'with extension' => ['french.inc', 'fr'],
            'uppercase' => ['French.INC', 'fr'],
            'without extension' => ['spanish', 'es'],
            'hyphenated' => ['english-usa.inc', 'en_US'],
            'path is stripped' => ['../texts/dutch.inc', 'nl'],
            'unknown' => ['nope.inc', null],
            'empty' => ['  ', null],
            'null' => [null, null],
        ];
    }
}
