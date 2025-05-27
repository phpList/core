<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Identity\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

class AdministratorFixture extends Fixture
{
    use ModelTestTrait;
    public function load(ObjectManager $manager): void
    {
        $csvFile = __DIR__ . '/Administrator.csv';

        if (!file_exists($csvFile)) {
            throw new RuntimeException(sprintf('Fixture file "%s" not found.', $csvFile));
        }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not open fixture file "%s".', $csvFile));
        }

        $headers = fgetcsv($handle);

        do {
            $data = fgetcsv($handle);
            if ($data === false) {
                break;
            }
            $row = array_combine($headers, $data);

            $admin = new Administrator();
            $this->setSubjectId($admin, (int)$row['id']);
            $admin->setLoginName($row['loginname']);
            $admin->setEmail($row['email']);
            $admin->setPasswordHash($row['password']);
            $admin->setDisabled((bool) $row['disabled']);
            $admin->setSuperUser((bool) $row['superuser']);

            $manager->persist($admin);
            $this->setSubjectProperty($admin, 'createdAt', new DateTime($row['created']));
            $this->setSubjectProperty($admin, 'passwordChangeDate', new DateTime($row['passwordchanged']));
        } while (true);

        fclose($handle);
    }
}
