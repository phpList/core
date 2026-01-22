<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Placeholder;

use PhpList\Core\Domain\Configuration\Model\Dto\PlaceholderContext;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Configuration\Service\Placeholder\ListsValueResolver;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ListsValueResolverTest extends TestCase
{
    private SubscriberListRepository&MockObject $repo;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(SubscriberListRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    private function makeUser(): Subscriber
    {
        $u = new Subscriber();
        $u->setEmail('user@example.com');
        $u->setUniqueId('UID-L');
        return $u;
    }

    public function testName(): void
    {
        $resolver = new ListsValueResolver($this->repo, $this->translator, false);
        $this->assertSame('LISTS', $resolver->name());
    }

    public function testReturnsTranslatedMessageWhenNoLists(): void
    {
        $this->repo->expects($this->once())
            ->method('getActiveListNamesForSubscriber')
            ->with($this->isInstanceOf(Subscriber::class), false)
            ->willReturn([]);

        $this->translator->method('trans')
            ->with('Sorry, you are not subscribed to any of our newsletters with this email address.')
            ->willReturn('No subscriptions');

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);
        $resolver = new ListsValueResolver($this->repo, $this->translator, false);

        $this->assertSame('No subscriptions', $resolver($ctx));
    }

    public function testHtmlEscapesNamesAndJoinsWithBr(): void
    {
        $names = ['News & Updates', 'Special <Offers>', "Quotes ' \" "];

        $this->repo->expects($this->once())
            ->method('getActiveListNamesForSubscriber')
            ->with($this->isInstanceOf(Subscriber::class), false)
            ->willReturn($names);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Html);
        $resolver = new ListsValueResolver($this->repo, $this->translator, false);

        $out = $resolver($ctx);

        $expected = implode(
            '<br/>',
            array_map(
                static fn(string $n) => htmlspecialchars($n, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $names
            )
        );

        $this->assertSame($expected, $out);
    }

    public function testTextJoinsWithNewlinesWithoutEscaping(): void
    {
        $names = ['General', 'Dev & QA', 'Sales <EU>'];

        $this->repo->expects($this->once())
            ->method('getActiveListNamesForSubscriber')
            ->with($this->isInstanceOf(Subscriber::class), false)
            ->willReturn($names);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);
        $resolver = new ListsValueResolver($this->repo, $this->translator, false);

        $out = $resolver($ctx);

        $this->assertSame(implode("\n", $names), $out);
    }

    public function testRespectsShowPrivateFlagTrue(): void
    {
        $names = ['Private List'];

        $this->repo->expects($this->once())
            ->method('getActiveListNamesForSubscriber')
            ->with($this->isInstanceOf(Subscriber::class), true)
            ->willReturn($names);

        $ctx = new PlaceholderContext(user: $this->makeUser(), format: OutputFormat::Text);
        $resolver = new ListsValueResolver($this->repo, $this->translator, true);

        $this->assertSame('Private List', $resolver($ctx));
    }
}
