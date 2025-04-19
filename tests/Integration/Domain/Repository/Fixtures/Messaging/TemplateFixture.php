<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\Messaging;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use PhpList\Core\Domain\Model\Messaging\Template;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use RuntimeException;

class TemplateFixture extends Fixture
{
    use ModelTestTrait;

    public function load(ObjectManager $manager): void
    {
        $csvFile = __DIR__ . '/Template.csv';

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

            $template = new Template($row['title']);
            $template->setTemplate($row['template']);
            $template->setTemplateText($row['template_text']);
            $template->setListOrder((int)$row['listorder']);

            $this->setSubjectId($template, (int)$row['id']);
            $manager->persist($template);
        } while (true);

        fclose($handle);
    }
}
