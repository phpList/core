<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

class SubscriptionFixture extends Fixture
{
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
        if ($headers === false) {
            throw new RuntimeException('Could not read headers from CSV file.');
        }

        /** @var Connection $connection */
        $connection = $manager->getConnection();

        $insertSubscriptionQuery = "
            INSERT INTO phplist_listuser (
                userid, listid, entered, modified
            ) VALUES (
                :subscriber_id, :subscriber_list_id, :creation_date, :modification_date
            )
        ";

        $subscriptionStmt = $connection->prepare($insertSubscriptionQuery);

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);

            $subscriptionStmt->executeStatement([
                'subscriber_id' => (int) $row['userid'],
                'subscriber_list_id' => (int) $row['listid'],
                'creation_date' => (new DateTime($row['entered']))->format('Y-m-d H:i:s'),
                'modification_date' => (new DateTime($row['modified']))->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($handle);
    }
}
