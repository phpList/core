<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\BounceRegex;
use PhpList\Core\Domain\Messaging\Model\BounceRegexBounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRegexRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceRegexManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class BounceRegexManagerTest extends TestCase
{
    private BounceRegexRepository&MockObject $regexRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private BounceRegexManager $manager;

    protected function setUp(): void
    {
        $this->regexRepository = $this->createMock(BounceRegexRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->manager = new BounceRegexManager(
            bounceRegexRepository: $this->regexRepository,
            entityManager: $this->entityManager
        );
    }

    public function testCreateNewRegex(): void
    {
        $pattern = 'user unknown';
        $expectedHash = md5($pattern);

        $this->regexRepository->expects($this->once())
            ->method('findOneByRegexHash')
            ->with($expectedHash)
            ->willReturn(null);

        $this->regexRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(BounceRegex::class));

        $regex = $this->manager->createOrUpdateFromPattern(
            regex: $pattern,
            action: 'delete',
            listOrder: 5,
            adminId: 1,
            comment: 'test',
            status: 'active'
        );

        $this->assertInstanceOf(BounceRegex::class, $regex);
        $this->assertSame($pattern, $regex->getRegex());
        $this->assertSame($expectedHash, $regex->getRegexHash());
        $this->assertSame('delete', $regex->getAction());
        $this->assertSame(5, $regex->getListOrder());
        $this->assertSame(1, $regex->getAdminId());
        $this->assertSame('test', $regex->getComment());
        $this->assertSame('active', $regex->getStatus());
    }

    public function testUpdateExistingRegex(): void
    {
        $pattern = 'mailbox full';
        $hash = md5($pattern);

        $existing = new BounceRegex(
            regex: $pattern,
            regexHash: $hash,
            action: 'keep',
            listOrder: 0,
            adminId: null,
            comment: null,
            status: 'inactive',
            count: 3
        );

        $this->regexRepository->expects($this->once())
            ->method('findOneByRegexHash')
            ->with($hash)
            ->willReturn($existing);

        $this->regexRepository->expects($this->once())
            ->method('save')
            ->with($existing);

        $updated = $this->manager->createOrUpdateFromPattern(
            regex: $pattern,
            action: 'delete',
            listOrder: 10,
            adminId: 2,
            comment: 'upd',
            status: 'active'
        );

        $this->assertSame('delete', $updated->getAction());
        $this->assertSame(10, $updated->getListOrder());
        $this->assertSame(2, $updated->getAdminId());
        $this->assertSame('upd', $updated->getComment());
        $this->assertSame('active', $updated->getStatus());
        $this->assertSame($hash, $updated->getRegexHash());
    }

    public function testDeleteRegex(): void
    {
        $model = $this->createMock(BounceRegex::class);

        $this->regexRepository->expects($this->once())
            ->method('remove')
            ->with($model);

        $this->manager->delete($model);
    }

    public function testAssociateBounceIncrementsCountAndPersistsRelation(): void
    {
        $regex = new BounceRegex(regex: 'x', regexHash: md5('x'));

        $refRegex = new ReflectionProperty(BounceRegex::class, 'id');
        $refRegex->setValue($regex, 7);

        $bounce = $this->createMock(Bounce::class);
        $bounce->method('getId')->willReturn(11);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) use ($regex) {
                return $entity instanceof BounceRegexBounce
                    && $entity->getRegexId() === $regex->getId();
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->assertSame(0, $regex->getCount());
        $this->manager->associateBounce($regex, $bounce);
        $this->assertSame(1, $regex->getCount());
    }
}
