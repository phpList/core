<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Model\Identity\Administrator;
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

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);

            $admin = new Administrator();
            $this->setSubjectId($admin, (int)$row['id']);
            $admin->setLoginName($row['loginname']);
            $admin->setEmailAddress($row['email']);
            $this->setSubjectProperty($admin,'creationDate', new DateTime($row['created']));
            $admin->setPasswordHash($row['password']);
            $this->setSubjectProperty($admin,'passwordChangeDate', new DateTime($row['passwordchanged']));
            $admin->setDisabled((bool) $row['disabled']);
            $admin->setSuperUser((bool) $row['superuser']);

            $manager->persist($admin);
        }

        fclose($handle);
    }
}
