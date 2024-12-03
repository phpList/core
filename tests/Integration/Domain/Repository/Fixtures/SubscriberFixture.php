<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

class SubscriberFixture extends Fixture
{
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
        if ($headers === false) {
            throw new RuntimeException('Could not read headers from CSV file.');
        }

        /** @var Connection $connection */
        $connection = $manager->getConnection();

        $insertQuery = "
            INSERT INTO phplist_user_user (
                id, entered, modified, email, confirmed, blacklisted, bouncecount,
                uniqid, htmlemail, disabled, extradata
            ) VALUES (
                :id, :creation_date, :modification_date, :email, :confirmed, :blacklisted, :bounce_count,
                :unique_id, :html_email, :disabled, :extra_data
            )
        ";

        $stmt = $connection->prepare($insertQuery);

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);

            $stmt->executeStatement([
                'id' => (int) $row['id'],
                'creation_date' => (new DateTime($row['entered']))->format('Y-m-d H:i:s'),
                'modification_date' => (new DateTime($row['modified']))->format('Y-m-d H:i:s'),
                'email' => $row['email'],
                'confirmed' => (bool) $row['confirmed'] ? 1 : 0,
                'blacklisted' => (bool) $row['blacklisted'] ? 1 : 0,
                'bounce_count' => (int) $row['bouncecount'],
                'unique_id' => $row['uniqueid'],
                'html_email' => (bool) $row['htmlemail'] ? 1 : 0,
                'disabled' => (bool) $row['disabled'] ? 1 : 0,
                'extra_data' => $row['extradata'],
            ]);
        }

        fclose($handle);
    }
}
