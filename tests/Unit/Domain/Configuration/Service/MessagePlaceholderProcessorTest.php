<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service;

use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\MessagePlaceholderProcessor;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PatternValueResolverInterface;
use PhpList\Core\Domain\Configuration\Service\Placeholder\PlaceholderValueResolverInterface;
use PhpList\Core\Domain\Configuration\Service\Placeholder\SupportingPlaceholderResolverInterface;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MessagePlaceholderProcessorTest extends TestCase
{
    private ConfigProvider&MockObject $config;
    private SubscriberAttributeValueRepository&MockObject $attrRepo;
    private AttributeValueResolver&MockObject $attrResolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigProvider::class);
        $this->attrRepo = $this->createMock(SubscriberAttributeValueRepository::class);
        $this->attrResolver = $this->createMock(AttributeValueResolver::class);
        $this->attrRepo->method('getForSubscriber')->willReturn([]);
    }

    private function makeUser(string $email = 'user@example.com', string $uid = 'UID123'): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail($email);
        $u->setUniqueId($uid);
        return $u;
    }

    public function testEnsuresStandardPlaceholdersAndUsertrackInHtmlOnly(): void
    {
        $user = $this->makeUser();
        $dto = new MessagePrecacheDto();

        // alwaysAddUserTrack = true
        $processor = new MessagePlaceholderProcessor(
            config: $this->config,
            attributesRepository: $this->attrRepo,
            attributeValueResolver: $this->attrResolver,
            placeholderResolvers: [],
            patternResolvers: [],
            supportingResolvers: [],
            alwaysAddUserTrack: true,
        );

        $html = '<html><body>Hello</body></html>';
        $processedHtml = $processor->process(
            value: $html,
            user: $user,
            format: OutputFormat::Html,
            messagePrecacheDto: $dto,
            campaignId: 42,
            forwardedBy: null,
        );

        // FOOTER and SIGNATURE must be inserted before </body>, USERTRACK appended for Html when flag enabled
        $this->assertStringContainsString('<br />[FOOTER] [SIGNATURE] [USERTRACK]</body>', $processedHtml);

        // In Text, FOOTER and SIGNATURE are appended with newlines, no USERTRACK even if flag enabled
        $text = 'Hi';
        $processedText = $processor->process(
            value: $text,
            user: $user,
            format: OutputFormat::Text,
            messagePrecacheDto: $dto,
        );

        $this->assertStringEndsWith("\n\n[FOOTER]\n[SIGNATURE]", $processedText);
        $this->assertStringNotContainsString('[USERTRACK]', $processedText);
    }

    public function testBuiltInResolversReplaceEmailUserIdAndConfigValues(): void
    {
        $user = $this->makeUser('alice@example.com', 'U-999');
        $dto = new MessagePrecacheDto();

        $this->config->method('getValue')->willReturnCallback(
            function (ConfigOption $opt): ?string {
                return match ($opt) {
                    ConfigOption::Website => 'https://site.example',
                    ConfigOption::Domain => 'example.com',
                    ConfigOption::OrganisationName => 'ACME Inc',
                    default => null,
                };
            }
        );

        $processor = new MessagePlaceholderProcessor(
            config: $this->config,
            attributesRepository: $this->attrRepo,
            attributeValueResolver: $this->attrResolver,
            placeholderResolvers: [],
            patternResolvers: [],
            supportingResolvers: [],
            alwaysAddUserTrack: false,
        );

        $content = 'Hi [EMAIL], id=[USERID], web=[WEBSITE], dom=[DOMAIN], org=[ORGANIZATION_NAME].';
        $out = $processor->process(
            value: $content,
            user: $user,
            format: OutputFormat::Text,
            messagePrecacheDto: $dto,
            campaignId: 101,
            forwardedBy: 'bob@example.com',
        );

        $this->assertStringContainsString('Hi alice@example.com,', $out);
        $this->assertStringContainsString('id=U-999,', $out);
        $this->assertStringContainsString('web=https://site.example,', $out);
        $this->assertStringContainsString('dom=example.com,', $out);
        $this->assertStringContainsString('org=ACME Inc.', $out);
    }

    public function testCustomResolversFromIterablesAreApplied(): void
    {
        $user = $this->makeUser();
        $dto = new MessagePrecacheDto();

        // Placeholder by name: [CUSTOM]
        $customPlaceholder = new class implements PlaceholderValueResolverInterface {
            public function name(): string
            {
                return 'CUSTOM';
            }
            public function __invoke(PlaceholderContext $ctx): string
            {
                return 'XVAL';
            }
        };

        // Pattern resolver: [UPPER:text]
        $pattern = new class implements PatternValueResolverInterface {
            public function pattern(): string
            {
                return '/\[UPPER:([^\]]+)]/i';
            }
            public function __invoke(PlaceholderContext $ctx, array $matches): string
            {
                return strtoupper($matches[1]);
            }
        };

        // Supporting resolver: for key SUPPORT
        $supporting = new class implements SupportingPlaceholderResolverInterface {
            public function supports(string $key, PlaceholderContext $ctx): bool
            {
                return strtoupper($key) === 'SUPPORT';
            }
            public function resolve(string $key, PlaceholderContext $ctx): ?string
            {
                return 'SVAL';
            }
        };

        $processor = new MessagePlaceholderProcessor(
            config: $this->config,
            attributesRepository: $this->attrRepo,
            attributeValueResolver: $this->attrResolver,
            placeholderResolvers: [$customPlaceholder],
            patternResolvers: [$pattern],
            supportingResolvers: [$supporting],
            alwaysAddUserTrack: false,
        );

        $content = 'A [CUSTOM] B [UPPER:abc] C [SUPPORT]';
        $out = $processor->process(
            value: $content,
            user: $user,
            format: OutputFormat::Text,
            messagePrecacheDto: $dto,
        );

        $this->assertStringContainsString('A XVAL B ABC C SVAL', $out);
    }
}
