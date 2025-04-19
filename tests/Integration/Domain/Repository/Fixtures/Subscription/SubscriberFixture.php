<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\Subscription;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

class SubscriberFixture extends Fixture
{
    use ModelTestTrait;

    public function load(ObjectManager $manager): void
    {
        $csvFile = __DIR__ . '/Subscriber.csv';

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

            $subscriber = new Subscriber();
            $this->setSubjectId($subscriber, (int)$row['id']);

            $subscriber->setEmail($row['email']);
            $subscriber->setConfirmed((bool) $row['confirmed']);
            $subscriber->setBlacklisted((bool) $row['blacklisted']);
            $subscriber->setBounceCount((int) $row['bouncecount']);
            $subscriber->setHtmlEmail((bool) $row['htmlemail']);
            $subscriber->setDisabled((bool) $row['disabled']);
            $subscriber->setExtraData($row['extradata']);

            $manager->persist($subscriber);
            // avoid pre-persist
            $subscriber->setUniqueId($row['uniqueid']);
            $this->setSubjectProperty($subscriber, 'creationDate', new DateTime($row['entered']));
            $this->setSubjectProperty($subscriber, 'modificationDate', new DateTime($row['modified']));
        } while (true);

        fclose($handle);
    }
}
