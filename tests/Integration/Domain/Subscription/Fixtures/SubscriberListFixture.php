<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

class SubscriberListFixture extends Fixture
{
    use ModelTestTrait;
    public function load(ObjectManager $manager): void
    {
        $csvFile = __DIR__ . '/SubscriberList.csv';

        if (!file_exists($csvFile)) {
            throw new RuntimeException(sprintf('Fixture file "%s" not found.', $csvFile));
        }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not open fixture file "%s".', $csvFile));
        }

        $headers = fgetcsv($handle);

        $adminRepository = $manager->getRepository(Administrator::class);

        do {
            $data = fgetcsv($handle);
            if ($data === false) {
                break;
            }
            $row = array_combine($headers, $data);

            $admin = $adminRepository->find($row['owner']);
            if ($admin === null) {
                $admin = new Administrator();
                $this->setSubjectId($admin, (int)$row['owner']);
                $manager->persist($admin);
            }

            $subscriberList = new SubscriberList();
            $this->setSubjectId($subscriberList, (int)$row['id']);
            $subscriberList->setName($row['name']);
            $subscriberList->setDescription($row['description']);
            $subscriberList->setListPosition((int)$row['listorder']);
            $subscriberList->setSubjectPrefix($row['prefix']);
            $subscriberList->setPublic((bool) $row['active']);
            $subscriberList->setCategory($row['category']);
            $subscriberList->setOwner($admin);

            $manager->persist($subscriberList);

            $this->setSubjectProperty($subscriberList, 'createdAt', new DateTime($row['entered']));
            $this->setSubjectProperty($subscriberList, 'updatedAt', new DateTime($row['modified']));
        } while (true);

        fclose($handle);
    }
}
