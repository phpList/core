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
        $this->manager = new BounceRuleManager($this->regexRepository, $this->relationRepository);
    }

    public function testLoadActiveRulesMapsRowsAndSkipsInvalid(): void
    {
        $valid = new BounceRegex(regex: 'user unknown', regexHash: md5('user unknown'), action: 'delete');
        // invalids: no regex, no action, no id
        $noRegex = new BounceRegex(regex: '', regexHash: md5(''), action: 'delete');
        $noAction = new BounceRegex(regex: 'pattern', regexHash: md5('pattern'), action: '');
        $noId = new BounceRegex(regex: 'has no id', regexHash: md5('has no id'), action: 'keep');

        // Simulate id assignment for only some of them
        $this->setId($valid, 1);
        $this->setId($noRegex, 2);
        $this->setId($noAction, 3);
        // $noId intentionally left without id

        $this->regexRepository->expects($this->once())
            ->method('fetchActiveOrdered')
            ->willReturn([$valid, $noRegex, $noAction, $noId]);

        $result = $this->manager->loadActiveRules();

        $this->assertSame(['user unknown' => $valid], $result);
    }

    public function testLoadAllRulesDelegatesToRepository(): void
    {
        $r1 = new BounceRegex(regex: 'a', regexHash: md5('a'), action: 'keep');
        $r2 = new BounceRegex(regex: 'b', regexHash: md5('b'), action: 'delete');
        $this->setId($r1, 10);
        $this->setId($r2, 11);

        $this->regexRepository->expects($this->once())
            ->method('fetchAllOrdered')
            ->willReturn([$r1, $r2]);

        $result = $this->manager->loadAllRules();
        $this->assertSame(['a' => $r1, 'b' => $r2], $result);
    }

    public function testMatchBounceRulesMatchesQuotedAndRawAndHandlesInvalidPatterns(): void
    {
        $valid = new BounceRegex(regex: 'user unknown', regexHash: md5('user unknown'), action: 'delete');
        $this->setId($valid, 1);
        // invalid regex pattern that would break preg_match if not handled (unbalanced bracket)
        $invalid = new BounceRegex(regex: '([a-z', regexHash: md5('([a-z'), action: 'keep');
        $this->setId($invalid, 2);

        $rules = ['user unknown' => $valid, '([a-z' => $invalid];

        $matched = $this->manager->matchBounceRules('Delivery failed: user    unknown at example', $rules);
        $this->assertSame($valid, $matched);

        // Ensure invalid pattern does not throw and simply not match
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
