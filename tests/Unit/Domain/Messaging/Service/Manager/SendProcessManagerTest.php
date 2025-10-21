<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\SendProcess;
use PhpList\Core\Domain\Messaging\Repository\SendProcessRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\SendProcessManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SendProcessManagerTest extends TestCase
{
    private SendProcessRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private SendProcessManager $manager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SendProcessRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->manager = new SendProcessManager($this->repository, $this->em);
    }

    public function testCreatePersistsEntityAndSetsFields(): void
    {
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(SendProcess::class));

        $sp = $this->manager->create('pageA', 'proc-1');
        $this->assertInstanceOf(SendProcess::class, $sp);
        $this->assertSame('pageA', $sp->getPage());
        $this->assertSame('proc-1', $sp->getIpaddress());
        $this->assertSame(1, $sp->getAlive());
        $this->assertInstanceOf(DateTime::class, $sp->getStartedDate());
    }

    public function testFindNewestAliveWithAgeReturnsNullWhenNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findNewestAlive')
            ->with('pageX')
            ->willReturn(null);

        $this->assertNull($this->manager->findNewestAliveWithAge('pageX'));
    }

    public function testFindNewestAliveWithAgeReturnsIdAndAge(): void
    {
        $model = new SendProcess();
        // set id
        $this->setId($model, 42);
        // set updatedAt to now - 5 seconds
        $updated = new \DateTime('now');
        $updated->sub(new DateInterval('PT5S'));
        $this->setUpdatedAt($model, $updated);

        $this->repository->expects($this->once())
            ->method('findNewestAlive')
            ->with('pageY')
            ->willReturn($model);

        $result = $this->manager->findNewestAliveWithAge('pageY');

        $this->assertIsArray($result);
        $this->assertSame(42, $result['id']);
        $this->assertGreaterThanOrEqual(0, $result['age']);
        $this->assertLessThan(60, $result['age']);
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }

    private function setUpdatedAt(SendProcess $entity, \DateTime $dt): void
    {
        $ref = new \ReflectionProperty($entity, 'updatedAt');
        $ref->setValue($entity, $dt);
    }
}
