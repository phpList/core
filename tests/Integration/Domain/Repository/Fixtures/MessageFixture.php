<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Messaging\Message;
use PhpList\Core\Domain\Model\Messaging\Message\MessageContent;
use PhpList\Core\Domain\Model\Messaging\Message\MessageFormat;
use PhpList\Core\Domain\Model\Messaging\Message\MessageMetadata;
use PhpList\Core\Domain\Model\Messaging\Message\MessageOptions;
use PhpList\Core\Domain\Model\Messaging\Message\MessageSchedule;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

class MessageFixture extends Fixture
{
    use ModelTestTrait;

    public function load(ObjectManager $manager): void
    {
        $csvFile = __DIR__ . '/Message.csv';

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

            $format = new MessageFormat(
                $row['htmlformatted'],
                $row['sendformat'],
                (bool)$row['astext'],
                (bool)$row['ashtml'],
                (bool)$row['aspdf'],
                (bool)$row['astextandhtml'],
                (bool)$row['astextandpdf']
            );

            $schedule = new MessageSchedule(
                $row['repeatinterval'],
                $row['repeatuntil'],
                $row['requeueinterval'],
                $row['requeueuntil']
            );
            $metadata = new MessageMetadata(
                $row['status'],
                $row['processed'],
                $row['viewed'],
                $row['bouncecount'],
                $row['entered'],
                $row['sent']
            );
            $content = new MessageContent(
                $row['subject'],
                $row['message'],
                $row['textmessage'],
                $row['footer']
            );
            $options = new MessageOptions(
                $row['fromfield'],
                $row['tofield'],
                $row['replyto'],
                $row['embargo'],
                $row['userselection'],
                $row['template'],
                $row['sendstart'],
                $row['rsstemplate'],
            );

            $message = new Message(
                $format,
                $schedule,
                $metadata,
                $content,
                $options,
                $admin,
            );
            $this->setSubjectId($message, (int)$row['id']);
            $this->setSubjectProperty($message, 'uuid', $row['uuid']);

            $manager->persist($message);
            $this->setSubjectProperty($message, 'modificationDate', new DateTime($row['modified']));
        } while (true);

        fclose($handle);
    }
}
