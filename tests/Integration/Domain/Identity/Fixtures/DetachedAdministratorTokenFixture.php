<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Identity\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Identity\Model\AdministratorToken;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

class DetachedAdministratorTokenFixture extends Fixture
{
    use ModelTestTrait;
    public function load(ObjectManager $manager): void
    {
        $csvFile = __DIR__ . '/DetachedAdministratorTokens.csv';

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

            $adminToken = new AdministratorToken();
            $this->setSubjectId($adminToken, (int)$row['id']);
            $adminToken->setKey($row['value']);

            $manager->persist($adminToken);

            $this->setSubjectProperty($adminToken, 'expiry', new DateTime($row['expires']));
            $this->setSubjectProperty($adminToken, 'createdAt', $row['entered']);
        } while (true);

        fclose($handle);
    }
}
