<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Messaging\Fixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageFormat;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Model\Message\MessageOptions;
use PhpList\Core\Domain\Messaging\Model\Message\MessageSchedule;
use PhpList\Core\Domain\Messaging\Model\Template;
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
        $templateRepository = $manager->getRepository(Template::class);

        do {
            $data = fgetcsv($handle);
            if ($data === false) {
                break;
            }
            $row = array_combine($headers, $data);
            $admin = $adminRepository->find($row['owner']);
            $template = $templateRepository->find($row['template']);

            $format = new MessageFormat(
                (bool)$row['htmlformatted'],
                $row['sendformat'],
                array_keys(array_filter([
                    MessageFormat::FORMAT_TEXT => $row['astext'],
                    MessageFormat::FORMAT_HTML => $row['ashtml'],
                    MessageFormat::FORMAT_PDF => $row['aspdf'],
                ]))
            );

            $schedule = new MessageSchedule(
                (int)$row['repeatinterval'],
                new DateTime($row['repeatuntil']),
                (int)$row['requeueinterval'],
                new DateTime($row['requeueuntil']),
                new DateTime($row['embargo']),
            );
            $metadata = new MessageMetadata(
                $row['status'],
                (int)$row['bouncecount'],
                new DateTime($row['entered']),
                new DateTime($row['sent']),
                new DateTime($row['sendstart']),
            );
            $metadata->setProcessed((bool) $row['processed']);
            $metadata->setViews($row['viewed']);
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
                $row['userselection'],
                $row['rsstemplate'],
            );

            $message = new Message(
                $format,
                $schedule,
                $metadata,
                $content,
                $options,
                $admin,
                $template,
            );
            $this->setSubjectId($message, (int)$row['id']);
            $this->setSubjectProperty($message, 'uuid', $row['uuid']);

            $manager->persist($message);
            $this->setSubjectProperty($message, 'updatedAt', new DateTime($row['modified']));
        } while (true);

        fclose($handle);
    }
}
