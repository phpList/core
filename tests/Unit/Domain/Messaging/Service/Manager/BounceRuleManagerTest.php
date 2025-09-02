<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\BounceRegex;
use PhpList\Core\Domain\Messaging\Model\BounceRegexBounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRegexBounceRepository;
use PhpList\Core\Domain\Messaging\Repository\BounceRegexRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceRuleManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BounceRuleManagerTest extends TestCase
{
    private BounceRegexRepository&MockObject $regexRepository;
    private BounceRegexBounceRepository&MockObject $relationRepository;
    private BounceRuleManager $manager;

    protected function setUp(): void
    {
        $this->regexRepository = $this->createMock(BounceRegexRepository::class);
        $this->relationRepository = $this->createMock(BounceRegexBounceRepository::class);
        $this->manager = new BounceRuleManager(
            repository: $this->regexRepository,
            bounceRelationRepository: $this->relationRepository,
        );
    }

    public function testLoadActiveRulesMapsRowsAndSkipsInvalid(): void
    {
        $valid = $this->createMock(BounceRegex::class);
        $valid->method('getId')->willReturn(1);
        $valid->method('getAction')->willReturn('delete');
        $valid->method('getRegex')->willReturn('user unknown');
        $valid->method('getRegexHash')->willReturn(md5('user unknown'));

        $noRegex = $this->createMock(BounceRegex::class);
        $noRegex->method('getId')->willReturn(2);

        $noAction = $this->createMock(BounceRegex::class);
        $noAction->method('getId')->willReturn(3);
        $noAction->method('getRegex')->willReturn('pattern');
        $noAction->method('getRegexHash')->willReturn(md5('pattern'));

        $noId = $this->createMock(BounceRegex::class);
        $noId->method('getRegex')->willReturn('has no id');
        $noId->method('getRegexHash')->willReturn(md5('has no id'));
        $noId->method('getAction')->willReturn('keep');

        $this->regexRepository->expects($this->once())
            ->method('fetchActiveOrdered')
            ->willReturn([$valid, $noRegex, $noAction, $noId]);

        $result = $this->manager->loadActiveRules();

        $this->assertSame(['user unknown' => $valid], $result);
    }

    public function testLoadAllRulesDelegatesToRepository(): void
    {
        $rule1 = $this->createMock(BounceRegex::class);
        $rule1->method('getId')->willReturn(10);
        $rule1->method('getAction')->willReturn('keep');
        $rule1->method('getRegex')->willReturn('a');
        $rule1->method('getRegexHash')->willReturn(md5('a'));

        $rule2 = $this->createMock(BounceRegex::class);
        $rule2->method('getId')->willReturn(11);
        $rule2->method('getAction')->willReturn('delete');
        $rule2->method('getRegex')->willReturn('b');
        $rule2->method('getRegexHash')->willReturn(md5('b'));

        $this->regexRepository->expects($this->once())
            ->method('fetchAllOrdered')
            ->willReturn([$rule1, $rule2]);

        $result = $this->manager->loadAllRules();
        $this->assertSame(['a' => $rule1, 'b' => $rule2], $result);
    }

    public function testMatchBounceRulesMatchesQuotedAndRawAndHandlesInvalidPatterns(): void
    {
        $valid = $this->createMock(BounceRegex::class);
        $valid->method('getId')->willReturn(1);
        $valid->method('getAction')->willReturn('delete');
        $valid->method('getRegex')->willReturn('user unknown');
        $valid->method('getRegexHash')->willReturn(md5('user unknown'));

        $invalid = $this->createMock(BounceRegex::class);
        $invalid->method('getId')->willReturn(2);
        $invalid->method('getAction')->willReturn('keep');
        $invalid->method('getRegex')->willReturn('([a-z');
        $invalid->method('getRegexHash')->willReturn(md5('([a-z'));

        $rules = ['user unknown' => $valid, '([a-z' => $invalid];

        $matched = $this->manager->matchBounceRules('Delivery failed: user    unknown at example', $rules);
        $this->assertSame($valid, $matched);

        // Ensure an invalid pattern does not throw and simply not match
        $matchedInvalid = $this->manager->matchBounceRules('something else', ['([a-z' => $invalid]);
        $this->assertNull($matchedInvalid);
    }

    public function testIncrementCountPersists(): void
    {
        $rule = new BounceRegex(regex: 'x', regexHash: md5('x'), action: 'keep', count: 0);
        $this->setId($rule, 5);

        $this->regexRepository->expects($this->once())
            ->method('save')
            ->with($rule);

        $this->manager->incrementCount($rule);
        $this->assertSame(1, $rule->getCount());
    }

    public function testLinkRuleToBounceCreatesRelationAndSaves(): void
    {
        $rule = new BounceRegex(regex: 'y', regexHash: md5('y'), action: 'delete');
        $bounce = new Bounce();
        $this->setId($rule, 9);
        $this->setId($bounce, 20);

        $this->relationRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(BounceRegexBounce::class));

        $relation = $this->manager->linkRuleToBounce($rule, $bounce);

        $this->assertInstanceOf(BounceRegexBounce::class, $relation);
        $this->assertSame(9, $relation->getRegexId());
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
