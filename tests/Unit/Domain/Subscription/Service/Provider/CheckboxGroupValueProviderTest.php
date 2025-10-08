<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use PhpList\Core\Domain\Subscription\Service\Provider\CheckboxGroupValueProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckboxGroupValueProviderTest extends TestCase
{
    /** @var DynamicListAttrRepository&MockObject */
    private DynamicListAttrRepository $repo;

    private CheckboxGroupValueProvider $subject;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(DynamicListAttrRepository::class);
        $this->subject = new CheckboxGroupValueProvider($this->repo);
    }

    private function createAttribute(
        string $type = 'checkboxgroup',
        ?string $tableName = 'colors'
    ): SubscriberAttributeDefinition {
        $attr = new SubscriberAttributeDefinition();
        $attr->setName('prefs')->setType($type)->setTableName($tableName);

        return $attr;
    }

    private function createUserAttr(SubscriberAttributeDefinition $def, ?string $value): SubscriberAttributeValue
    {
        $subscriber = new Subscriber();
        $userAttr = new SubscriberAttributeValue($def, $subscriber);
        $userAttr->setValue($value);

        return $userAttr;
    }

    public function testSupportsReturnsTrueForCheckboxgroup(): void
    {
        $attr = $this->createAttribute('checkboxgroup');
        self::assertTrue($this->subject->supports($attr));
    }

    public function testSupportsReturnsFalseForOtherTypes(): void
    {
        $attr = $this->createAttribute('textline');
        self::assertFalse($this->subject->supports($attr));
    }

    public function testGetValueReturnsEmptyStringForNullOrEmptyValue(): void
    {
        $attr = $this->createAttribute();

        $uaNull = $this->createUserAttr($attr, null);
        self::assertSame('', $this->subject->getValue($attr, $uaNull));

        $uaEmpty = $this->createUserAttr($attr, '');
        self::assertSame('', $this->subject->getValue($attr, $uaEmpty));
    }

    public function testGetValueReturnsEmptyStringWhenNoParsedIds(): void
    {
        $attr = $this->createAttribute();
        $ua = $this->createUserAttr($attr, '0, -1, foo, bar');

        // Repository should not be called in this case
        $this->repo->expects(self::never())->method('fetchOptionNames');

        self::assertSame('', $this->subject->getValue($attr, $ua));
    }

    public function testGetValueReturnsEmptyStringWhenNoTableName(): void
    {
        $attr = $this->createAttribute('checkboxgroup', null);
        $ua = $this->createUserAttr($attr, '1,2');

        $this->repo->expects(self::never())->method('fetchOptionNames');

        self::assertSame('', $this->subject->getValue($attr, $ua));
    }

    public function testGetValueFetchesNamesAndJoinsWithSemicolon(): void
    {
        $attr = $this->createAttribute('checkboxgroup', 'colors');
        $ua = $this->createUserAttr($attr, '1, 2,3');

        $this->repo
            ->expects(self::once())
            ->method('fetchOptionNames')
            ->with('colors', [1, 2, 3])
            ->willReturn(['Red', 'Green', 'Blue']);

        self::assertSame('Red; Green; Blue', $this->subject->getValue($attr, $ua));
    }

    public function testGetValueParsesAndPreservesOrderAndFiltersInvalids(): void
    {
        $attr = $this->createAttribute('checkboxgroup', 'sizes');
        $ua = $this->createUserAttr($attr, '3, 0, -2, two, 1, 2 , 2');
        // After parsing: [3,1,2,2] -> duplicates are allowed and passed through to repository
        $this->repo
            ->expects(self::once())
            ->method('fetchOptionNames')
            ->with('sizes', [3, 1, 2, 2])
            ->willReturn(['Large', 'Small', 'Medium', 'Medium']);

        self::assertSame('Large; Small; Medium; Medium', $this->subject->getValue($attr, $ua));
    }
}
