<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Model\Messaging\SubscriberList;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\Subscription;
use PhpList\Core\Tests\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

/**
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
class SubscriptionFixture extends Fixture
{
    use ModelTestTrait;
    public function load(ObjectManager $manager): void
    {
        $csvFile = __DIR__ . '/Subscription.csv';

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

            $subscriber = new Subscriber();
            $this->setSubjectId($subscriber,(int)$row['userid']);
            $manager->persist($subscriber);

            $subscriberList = new SubscriberList();
            $this->setSubjectId($subscriberList,(int)$row['listid']);
            $manager->persist($subscriberList);

            $subscription = new Subscription();
            $this->setSubjectProperty($subscription,'subscriber', $subscriber);
            $this->setSubjectProperty($subscription,'subscriberList', $subscriberList);
            $this->setSubjectProperty($subscription,'creationDate', new DateTime($row['entered']));
            $this->setSubjectProperty($subscription,'modificationDate', new DateTime($row['modified']));
            $manager->persist($subscription);
        }

        fclose($handle);
        $manager->flush();
    }
}
