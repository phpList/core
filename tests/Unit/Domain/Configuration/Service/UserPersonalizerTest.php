<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\LegacyUrlBuilder;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Configuration\Service\MessagePlaceholderProcessor;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserPersonalizerTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private LegacyUrlBuilder&MockObject $urlBuilder;
    private SubscriberRepository&MockObject $subRepo;
    private SubscriberAttributeValueRepository&MockObject $attrRepo;
    private AttributeValueResolver&MockObject $attrResolver;
    private UserPersonalizer $personalizer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->urlBuilder = $this->createMock(LegacyUrlBuilder::class);
        $this->subRepo = $this->createMock(SubscriberRepository::class);
        $this->attrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $this->attrResolver = $this->createMock(AttributeValueResolver::class);

        $this->personalizer = new UserPersonalizer(
            $this->config,
            $this->urlBuilder,
            $this->subRepo,
            $this->attrRepo,
            $this->attrResolver
        );
    }

    public function testReturnsOriginalWhenSubscriberNotFound(): void
    {
        $this->subRepo
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('nobody@example.com')
            ->willReturn(null);

        $result = $this->personalizer->personalize('Hello [EMAIL]', 'nobody@example.com');

        $this->assertSame('Hello [EMAIL]', $result);
    }

    public function testBuiltInPlaceholdersAreResolved(): void
    {
        $email = 'ada@example.com';
        $uid = 'U123';

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn($email);
        $subscriber->method('getUniqueId')->willReturn($uid);

        $this->subRepo
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with($email)
            ->willReturn($subscriber);

        // Config values for URLs + domain/website + subscribe url
        $this->config->method('getValue')->willReturnCallback(function ($opt) {
            return match ($opt) {
                ConfigOption::UnsubscribeUrl => 'https://u.example/unsub',
                ConfigOption::ConfirmationUrl => 'https://u.example/confirm',
                ConfigOption::PreferencesUrl => 'https://u.example/prefs',
                ConfigOption::SubscribeUrl => 'https://u.example/subscribe',
                ConfigOption::Domain => 'example.org',
                ConfigOption::Website => 'site.example.org',
                default => null,
            };
        });

        // LegacyUrlBuilder glue behavior
        $this->urlBuilder
            ->method('withUid')
            ->willReturnCallback(fn(string $base, string $u) => $base . '?uid=' . $u);

        $this->attrRepo
            ->expects($this->once())
            ->method('getForSubscriber')
            ->with($subscriber)
            ->willReturn([]);

        $input = 'Email: [EMAIL]
            Unsub: [UNSUBSCRIBEURL]
            Conf: [confirmationurl]
            Prefs: [PREFERENCESURL]
            Sub: [SUBSCRIBEURL]
            Domain: [DOMAIN]
            Website: [WEBSITE]';


        $result = $this->personalizer->personalize($input, $email);

        $this->assertStringContainsString('Email: ada@example.com', $result);
        // trailing space is expected after URL placeholders
        $this->assertStringContainsString('Unsub: https://u.example/unsub?uid=U123 ', $result);
        $this->assertStringContainsString('Conf: https://u.example/confirm?uid=U123 ', $result);
        $this->assertStringContainsString('Prefs: https://u.example/prefs?uid=U123 ', $result);
        $this->assertStringContainsString('Sub: https://u.example/subscribe ', $result);
        $this->assertStringContainsString('Domain: example.org', $result);
        $this->assertStringContainsString('Website: site.example.org', $result);
    }

    public function testDynamicUserAttributesAreResolvedCaseInsensitive(): void
    {
        $email = 'bob@example.com';
        $uid = 'U999';

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn($email);
        $subscriber->method('getUniqueId')->willReturn($uid);

        $this->subRepo
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with($email)
            ->willReturn($subscriber);

        // Only needed so registration for URL placeholders doesn't blow up; values don't matter in this test
        $this->config->method('getValue')->willReturnMap([
            [ConfigOption::UnsubscribeUrl, ''],
            [ConfigOption::ConfirmationUrl, ''],
            [ConfigOption::PreferencesUrl, ''],
            [ConfigOption::SubscribeUrl, ''],
            [ConfigOption::Domain, 'example.org'],
            [ConfigOption::Website, 'site.example.org'],
        ]);

        $this->urlBuilder->method('withUid')->willReturnCallback(fn(string $b, string $u) => $b . '?uid=' . $u);

        // Build a fake attribute value entity with definition NAME => "Full Name"
        $attrDefinition = $this->createMock(SubscriberAttributeDefinition::class);
        $attrDefinition->method('getName')->willReturn('Full_Name2');
        $attrValue = $this->createMock(SubscriberAttributeValue::class);
        $attrValue->method('getAttributeDefinition')->willReturn($attrDefinition);

        $this->attrRepo
            ->expects($this->once())
            ->method('getForSubscriber')
            ->with($subscriber)
            ->willReturn([$attrValue]);

        // When resolver is called with our attr value, return computed string
        $this->attrResolver
            ->expects($this->once())
            ->method('resolve')
            ->with($attrValue)
            ->willReturn('Bob #2');

        $input = 'Hello [full_name2], your email is [email].';
        $result = $this->personalizer->personalize($input, $email);

        $this->assertSame('Hello Bob #2, your email is bob@example.com.', $result);
    }

    public function testMultipleOccurrencesAndAdjacency(): void
    {
        $email = 'eve@example.com';
        $uid = 'UID42';

        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getEmail')->willReturn($email);
        $subscriber->method('getUniqueId')->willReturn($uid);

        $this->subRepo->method('findOneByEmail')->willReturn($subscriber);

        $this->config->method('getValue')->willReturnMap([
            [ConfigOption::UnsubscribeUrl, 'https://x/unsub'],
            [ConfigOption::ConfirmationUrl, 'https://x/conf'],
            [ConfigOption::PreferencesUrl, 'https://x/prefs'],
            [ConfigOption::SubscribeUrl, 'https://x/sub'],
            [ConfigOption::Domain, 'x.tld'],
            [ConfigOption::Website, 'w.x.tld'],
        ]);

        $this->urlBuilder->method('withUid')->willReturnCallback(fn(string $b, string $u) => $b . '?uid=' . $u);

        // Two attributes: FOO & BAR
        $defFoo = $this->createMock(SubscriberAttributeDefinition::class);
        $defFoo->method('getName')->willReturn('FOO');
        $valFoo = $this->createMock(SubscriberAttributeValue::class);
        $valFoo->method('getAttributeDefinition')->willReturn($defFoo);

        $defBar = $this->createMock(SubscriberAttributeDefinition::class);
        $defBar->method('getName')->willReturn('bar');
        $valBar = $this->createMock(SubscriberAttributeValue::class);
        $valBar->method('getAttributeDefinition')->willReturn($defBar);

        $this->attrRepo->method('getForSubscriber')->willReturn([$valFoo, $valBar]);

        $this->attrResolver
            ->method('resolve')
            ->willReturnMap([
                [$valFoo, 'FVAL'],
                [$valBar, 'BVAL'],
            ]);

        $input = '[foo][BAR]-[email]-[UNSUBSCRIBEURL]';
        $out = $this->personalizer->personalize($input, $email);

        $this->assertSame('FVALBVAL-eve@example.com-https://x/unsub?uid=UID42 ', $out);
    }
}
