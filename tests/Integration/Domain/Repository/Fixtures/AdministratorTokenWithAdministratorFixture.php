<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Identity\AdministratorToken;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;
use DateTime;

class AdministratorTokenWithAdministratorFixture extends Fixture
{
    use ModelTestTrait;
    public function load(ObjectManager $manager): void
    {
        $csvFile = __DIR__ . '/AdministratorTokenWithAdministrator.csv';

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
            $this->setSubjectId($admin,(int)$data['adminid']);
            $manager->persist($admin);

            $adminToken = new AdministratorToken();
            $this->setSubjectId($adminToken,(int)$data['id']);
            $adminToken->setKey($row['value']);
            $this->setSubjectProperty($adminToken,'expiry', new DateTime($row['expires']));
            $this->setSubjectProperty($adminToken, 'creationDate', (bool) $row['entered']);
            $adminToken->setAdministrator($admin);
            $manager->persist($adminToken);
        }

        fclose($handle);
        $manager->flush();
    }
}
