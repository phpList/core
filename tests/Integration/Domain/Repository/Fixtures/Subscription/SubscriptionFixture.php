<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\Subscription;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\SubscriberList;
use PhpList\Core\Domain\Model\Subscription\Subscription;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

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

        $subscriberRepository = $manager->getRepository(Subscriber::class);
        $subscriberListRepository = $manager->getRepository(SubscriberList::class);

        $headers = fgetcsv($handle);

        do {
            $data = fgetcsv($handle);
            if ($data === false) {
                break;
            }
            $row = array_combine($headers, $data);

            $subscriber = $subscriberRepository->find((int)$row['userid']);
            $subscriberList = $subscriberListRepository->find((int)$row['listid']);

            $subscription = new Subscription();
            $subscriberList->addSubscription($subscription);
            $subscriber->addSubscription($subscription);

            $manager->persist($subscription);

            $this->setSubjectProperty($subscription, 'creationDate', new DateTime($row['entered']));
            $this->setSubjectProperty($subscription, 'modificationDate', new DateTime($row['modified']));
        } while (true);

        fclose($handle);
    }
}
