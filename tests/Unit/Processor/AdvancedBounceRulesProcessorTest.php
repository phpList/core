<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Processor;

use PhpList\Core\Bounce\Service\BounceActionResolver;
use PhpList\Core\Bounce\Service\Manager\BounceManager;
use PhpList\Core\Bounce\Service\Processor\AdvancedBounceRulesProcessor;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\BounceRegex;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceRuleManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Translator;

class AdvancedBounceRulesProcessorTest extends TestCase
{
    private BounceManager&MockObject $bounceManager;
    private BounceRuleManager&MockObject $ruleManager;
    private BounceActionResolver&MockObject $actionResolver;
    private SubscriberManager&MockObject $subscriberManager;
    private SymfonyStyle&MockObject $io;

    protected function setUp(): void
    {
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->ruleManager = $this->createMock(BounceRuleManager::class);
        $this->actionResolver = $this->createMock(BounceActionResolver::class);
        $this->subscriberManager = $this->createMock(SubscriberManager::class);
        $this->io = $this->createMock(SymfonyStyle::class);
    }

    public function testNoActiveRules(): void
    {
        $translator = new Translator('en');
        $this->io
            ->expects($this->once())
            ->method('section')
            ->with($translator->trans('Processing bounces based on active bounce rules'));
        $this->ruleManager->method('loadActiveRules')->willReturn([]);
        $this->io
            ->expects($this->once())
            ->method('writeln')
            ->with($translator->trans('No active rules'));

        $processor = new AdvancedBounceRulesProcessor(
            bounceManager: $this->bounceManager,
            ruleManager: $this->ruleManager,
            actionResolver: $this->actionResolver,
            subscriberManager: $this->subscriberManager,
            translator: $translator,
        );

        $processor->process($this->io, 100);
    }

    public function testProcessingWithMatchesAndNonMatches(): void
    {
        $rule1 = $this->createMock(BounceRegex::class);
        $rule1->method('getId')->willReturn(10);
        $rule1->method('getAction')->willReturn('blacklist');
        $rule1->method('getCount')->willReturn(0);

        $rule2 = $this->createMock(BounceRegex::class);
        $rule2->method('getId')->willReturn(20);
        $rule2->method('getAction')->willReturn('notify');
        $rule2->method('getCount')->willReturn(0);

        $rules = [$rule1, $rule2];
        $this->ruleManager->method('loadActiveRules')->willReturn($rules);

        $this->bounceManager->method('getUserMessageBounceCount')->willReturn(3);

        $bounce1 = $this->createMock(Bounce::class);
        $bounce1->method('getHeader')->willReturn('H1');
        $bounce1->method('getData')->willReturn('D1');

        $bounce2 = $this->createMock(Bounce::class);
        $bounce2->method('getHeader')->willReturn('H2');
        $bounce2->method('getData')->willReturn('D2');

        $bounce3 = $this->createMock(Bounce::class);
        $bounce3->method('getHeader')->willReturn('H3');
        $bounce3->method('getData')->willReturn('D3');

        $umb1 = $this->createMock(UserMessageBounce::class);
        $umb1->method('getId')->willReturn(1);
        $umb1->method('getUserId')->willReturn(111);

        $umb2 = $this->createMock(UserMessageBounce::class);
        $umb2->method('getId')->willReturn(2);
        $umb2->method('getUserId')->willReturn(0);

        $umb3 = $this->createMock(UserMessageBounce::class);
        $umb3->method('getId')->willReturn(3);
        $umb3->method('getUserId')->willReturn(222);

        $this->bounceManager->method('fetchUserMessageBounceBatch')->willReturnOnConsecutiveCalls(
            [ ['umb' => $umb1, 'bounce' => $bounce1], ['umb' => $umb2, 'bounce' => $bounce2] ],
            [ ['umb' => $umb3, 'bounce' => $bounce3] ]
        );

        // Rule matches for first and third, not for second
        $this->ruleManager->expects($this->exactly(3))
            ->method('matchBounceRules')
            ->willReturnCallback(function (string $text, array $r) use ($rules) {
                $this->assertSame($rules, $r);
                if ($text === 'H1' . "\n\n" . 'D1') {
                    return $rules[0];
                }
                if ($text === 'H2' . "\n\n" . 'D2') {
                    return null;
                }
                if ($text === 'H3' . "\n\n" . 'D3') {
                    return $rules[1];
                }
                $this->fail('Unexpected arguments to matchBounceRules: ' . $text);
            });

        $this->ruleManager->expects($this->exactly(2))->method('incrementCount');
        $this->ruleManager->expects($this->exactly(2))->method('linkRuleToBounce');

        // subscriber lookups for umb1 and umb3 (111 and 222). umb2 has 0 user id so skip.
        $subscriber111 = $this->createMock(Subscriber::class);
        $subscriber111->method('getId')->willReturn(111);
        $subscriber111->method('isConfirmed')->willReturn(true);
        $subscriber111->method('isBlacklisted')->willReturn(false);

        $subscriber222 = $this->createMock(Subscriber::class);
        $subscriber222->method('getId')->willReturn(222);
        $subscriber222->method('isConfirmed')->willReturn(false);
        $subscriber222->method('isBlacklisted')->willReturn(true);

        $this->subscriberManager->expects($this->exactly(2))
            ->method('getSubscriberById')
            ->willReturnCallback(function (int $id) use ($subscriber111, $subscriber222) {
                if ($id === 111) {
                    return $subscriber111;
                }
                if ($id === 222) {
                    return $subscriber222;
                }
                $this->fail('Unexpected subscriber id: ' . $id);
            });

        $this->actionResolver->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(function (string $action, array $ctx) {
                if ($action === 'blacklist') {
                    $this->assertSame(111, $ctx['userId']);
                    $this->assertTrue($ctx['confirmed']);
                    $this->assertFalse($ctx['blacklisted']);
                    $this->assertSame(10, $ctx['ruleId']);
                    $this->assertInstanceOf(Bounce::class, $ctx['bounce']);
                } elseif ($action === 'notify') {
                    $this->assertSame(222, $ctx['userId']);
                    $this->assertFalse($ctx['confirmed']);
                    $this->assertTrue($ctx['blacklisted']);
                    $this->assertSame(20, $ctx['ruleId']);
                } else {
                    $this->fail('Unexpected action: ' . $action);
                }
                return null;
            });

        $translator = new Translator('en');
        $this->io
            ->expects($this->once())
            ->method('section')
            ->with($translator->trans('Processing bounces based on active bounce rules'));
        $this->io->expects($this->exactly(4))->method('writeln');

        $processor = new AdvancedBounceRulesProcessor(
            bounceManager: $this->bounceManager,
            ruleManager: $this->ruleManager,
            actionResolver: $this->actionResolver,
            subscriberManager: $this->subscriberManager,
            translator: $translator,
        );

        $processor->process($this->io, 2);
    }
}
