<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\Dto\ChangeSetDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberAttributeChangeSetProvider;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubscriberAttributeChangeSetProviderTest extends TestCase
{
    private AttributeValueResolver&MockObject $resolver;
    private SubscriberAttributeValueRepository&MockObject $repository;
    private SubscriberAttributeChangeSetProvider $provider;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(AttributeValueResolver::class);
        $this->resolver
            ->method('resolve')
            ->willReturnCallback(function (SubscriberAttributeValue $attr) {
                return $attr->getValue();
            });

        $this->repository = $this->createMock(SubscriberAttributeValueRepository::class);

        $this->provider = new SubscriberAttributeChangeSetProvider(
            resolver: $this->resolver,
            attributesRepository: $this->repository,
        );
    }

    public function testNoChangesWhenNewAndExistingAreIdenticalCaseInsensitive(): void
    {
        $subscriber = new Subscriber();
        $existing = [
            $this->attr('Name', 'John', $subscriber),
            $this->attr('Age', '30', $subscriber),
        ];

        $this->repository->expects(self::once())
            ->method('getForSubscriber')
            ->with($subscriber)
            ->willReturn($existing);

        $newData = [
            'name' => 'John',
            'AGE' => '30',
        ];

        $changeSet = $this->provider->getAttributeChangeSet($subscriber, $newData);

        self::assertInstanceOf(ChangeSetDto::class, $changeSet);
        self::assertFalse($changeSet->hasChanges());
        self::assertSame([], $changeSet->getChanges());
    }

    public function testAddedAttributeAppearsWithNullOldValue(): void
    {
        $subscriber = new Subscriber();
        $existing = [
            $this->attr('Name', 'John', $subscriber),
        ];

        $this->repository->method('getForSubscriber')->willReturn($existing);

        $newData = [
            'name' => 'John',
            'city' => 'NY',
        ];

        $changeSet = $this->provider->getAttributeChangeSet($subscriber, $newData);

        self::assertTrue($changeSet->hasField('city'));
        self::assertSame([null, 'NY'], $changeSet->getFieldChange('city'));

        self::assertSame(['city' => [null, 'NY']], $changeSet->getChanges());
    }

    public function testRemovedAttributeAppearsWithNullNewValue(): void
    {
        $subscriber = new Subscriber();
        $existing = [
            $this->attr('Country', 'US', $subscriber),
        ];

        $this->repository->method('getForSubscriber')->willReturn($existing);

        $changeSet = $this->provider->getAttributeChangeSet($subscriber, []);

        self::assertTrue($changeSet->hasField('country'));
        self::assertSame(['US', null], $changeSet->getFieldChange('country'));
        self::assertSame(['country' => ['US', null]], $changeSet->getChanges());
    }

    public function testChangedAttributeShowsOldAndNewValues(): void
    {
        $subscriber = new Subscriber();
        $existing = [
            $this->attr('Phone', '123', $subscriber),
        ];

        $this->repository->method('getForSubscriber')->willReturn($existing);

        $newData = [
            'phone' => '456',
        ];

        $changeSet = $this->provider->getAttributeChangeSet($subscriber, $newData);

        self::assertSame(['123', '456'], $changeSet->getFieldChange('phone'));
        self::assertSame(['phone' => ['123', '456']], $changeSet->getChanges());
    }

    public function testIgnoredAttributesAreExcluded(): void
    {
        $subscriber = new Subscriber();
        $existing = [
            $this->attr('Password', 'old', $subscriber),
            $this->attr('Modified', 'yesterday', $subscriber),
            $this->attr('Nickname', 'Bob', $subscriber),
        ];

        $this->repository->method('getForSubscriber')->willReturn($existing);

        $newData = [
            'password' => 'new',
            'MODIFIED' => null,
            'nickname' => 'Bobby',
        ];

        $changeSet = $this->provider->getAttributeChangeSet($subscriber, $newData);

        self::assertFalse($changeSet->hasField('password'));
        self::assertFalse($changeSet->hasField('modified'));
        self::assertTrue($changeSet->hasField('nickname'));
        self::assertSame(['Bob', 'Bobby'], $changeSet->getFieldChange('nickname'));
        self::assertSame(['nickname' => ['Bob', 'Bobby']], $changeSet->getChanges());
    }

    public function testCaseInsensitiveKeyComparisonAndResultLowercasing(): void
    {
        $subscriber = new Subscriber();
        $existing = [
            $this->attr('FirstName', 'Ann', $subscriber),
        ];

        $this->repository->method('getForSubscriber')->willReturn($existing);

        $newData = [
            'firstname' => 'Anna',
        ];

        $changeSet = $this->provider->getAttributeChangeSet($subscriber, $newData);

        self::assertTrue($changeSet->hasField('firstname'));
        self::assertSame(['Ann', 'Anna'], $changeSet->getFieldChange('firstname'));
        self::assertSame(['firstname' => ['Ann', 'Anna']], $changeSet->getChanges());
    }

    private function attr(string $name, ?string $value, Subscriber $subscriber): SubscriberAttributeValue
    {
        $def = (new SubscriberAttributeDefinition())->setName($name);
        $attr = new SubscriberAttributeValue($def, $subscriber);
        $attr->setValue($value);
        return $attr;
    }
}
