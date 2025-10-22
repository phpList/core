<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Dto\CreateAdministratorDto;
use PhpList\Core\Domain\Identity\Model\Dto\UpdateAdministratorDto;
use PhpList\Core\Domain\Identity\Service\AdministratorManager;
use PhpList\Core\Security\HashGenerator;
use PHPUnit\Framework\TestCase;

class AdministratorManagerTest extends TestCase
{
    public function testCreateAdministrator(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $hashGenerator = $this->createMock(HashGenerator::class);

        $dto = new CreateAdministratorDto(
            loginName: 'admin',
            password: 'securepass',
            email: 'admin@example.com',
            isSuperUser: true
        );

        $hashGenerator->expects($this->once())
            ->method('createPasswordHash')
            ->with('securepass')
            ->willReturn('hashed_pass');

        $entityManager->expects($this->once())->method('persist');

        $manager = new AdministratorManager($entityManager, $hashGenerator);
        $admin = $manager->createAdministrator($dto);

        $this->assertEquals('admin', $admin->getLoginName());
        $this->assertEquals('admin@example.com', $admin->getEmail());
        $this->assertTrue($admin->isSuperUser());
        $this->assertEquals('hashed_pass', $admin->getPasswordHash());
    }

    public function testUpdateAdministrator(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $hashGenerator = $this->createMock(HashGenerator::class);

        $admin = new Administrator();
        $admin->setLoginName('old');
        $admin->setEmail('old@example.com');
        $admin->setSuperUser(false);
        $admin->setPasswordHash('old_hash');

        $dto = new UpdateAdministratorDto(
            administratorId: 1,
            loginName: 'new',
            password: 'newpass',
            email: 'new@example.com',
            superAdmin: true
        );

        $hashGenerator->expects($this->once())
            ->method('createPasswordHash')
            ->with('newpass')
            ->willReturn('new_hash');

        $manager = new AdministratorManager($entityManager, $hashGenerator);
        $manager->updateAdministrator($admin, $dto);

        $this->assertEquals('new', $admin->getLoginName());
        $this->assertEquals('new@example.com', $admin->getEmail());
        $this->assertTrue($admin->isSuperUser());
        $this->assertEquals('new_hash', $admin->getPasswordHash());
    }

    public function testDeleteAdministrator(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $hashGenerator = $this->createMock(HashGenerator::class);

        $admin = $this->createMock(Administrator::class);

        $entityManager->expects($this->once())->method('remove')->with($admin);

        $manager = new AdministratorManager($entityManager, $hashGenerator);
        $manager->deleteAdministrator($admin);

        $this->assertTrue(true);
    }
}
