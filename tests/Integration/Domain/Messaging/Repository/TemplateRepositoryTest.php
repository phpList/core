<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Messaging\Repository;

use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Messaging\Model\Template;
use PhpList\Core\Domain\Messaging\Repository\TemplateRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\Tests\Integration\Domain\Messaging\Fixtures\TemplateFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TemplateRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private TemplateRepository $templateRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->templateRepository = self::getContainer()->get(TemplateRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testGetAllTemplatesReturnsCorrectResults(): void
    {
        $template1 = new Template('Template 1');
        $template2 = new Template('Template 2');

        $this->entityManager->persist($template1);
        $this->entityManager->persist($template2);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $allTemplates = $this->templateRepository->findAll();

        self::assertCount(2, $allTemplates);
        self::assertContainsOnlyInstancesOf(Template::class, $allTemplates);
    }

    public function testTemplateIsPersistedAndFetchedCorrectly(): void
    {
        $this->loadFixtures([TemplateFixture::class]);

        $fetched = $this->templateRepository->findOneBy(['title' => 'Newsletter Template']);

        self::assertInstanceOf(Template::class, $fetched);
        self::assertSame('Newsletter Template', $fetched->getTitle());
        self::assertSame('<html><body><h1>Welcome</h1></body></html>', $fetched->getContent());
        self::assertSame('', $fetched->getText());
        self::assertSame(1, $fetched->getListOrder());
    }

    public function testGetAllTemplatesReturnsCorrectType(): void
    {
        $this->loadFixtures([TemplateFixture::class]);

        $allTemplates = $this->templateRepository->findAll();

        self::assertNotEmpty($allTemplates);
        self::assertContainsOnlyInstancesOf(Template::class, $allTemplates);
    }
}
