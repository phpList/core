<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Messaging\SubscriberList;
use PhpList\Core\Tests\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

/**
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
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

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);
            $admin = new Administrator();
            $this->setSubjectId($admin,(int)$row['owner']);

            $subscriberList = new SubscriberList();
            $this->setSubjectId($subscriberList,(int)$row['id']);
            $subscriberList->setName($row['name']);
            $subscriberList->setDescription($row['description']);
            $this->setSubjectProperty($subscriberList,'creationDate', new DateTime($row['entered']));
            $this->setSubjectProperty($subscriberList,'modificationDate', new DateTime($row['modified']));
            $subscriberList->setListPosition((int)$row['listorder']);
            $subscriberList->setSubjectPrefix($row['prefix']);
            $subscriberList->setPublic((bool) $row['active']);
            $subscriberList->setCategory($row['category']);
            $subscriberList->setOwner($admin);

            $manager->persist($admin);
            $manager->persist($subscriberList);
            $this->setSubjectProperty($subscriberList,'creationDate', new DateTime($row['entered']));
        }

        fclose($handle);
        $manager->flush();
    }
}
