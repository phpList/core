<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Identity\Repository;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\AdministratorToken;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\Core\Tests\Integration\Domain\Identity\Fixtures\DetachedAdministratorTokenFixture;
use PhpList\Core\Tests\Integration\Domain\Identity\Fixtures\AdministratorFixture;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTokenRepositoryTest extends WebTestCase
{
    use DatabaseTestTrait;
    use SimilarDatesAssertionTrait;

    private ?AdministratorTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();
        $this->repository = self::getContainer()->get(AdministratorTokenRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testFindReadsModelFromDatabase()
    {
        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        $id = 1;
        $creationDate = new DateTime('2017-12-06 17:41:40');
        $expiry = new DateTime('2017-06-22 16:43:29');
        $key = 'cfdf64eecbbf336628b0f3071adba762';

        /** @var AdministratorToken $model */
        $model = $this->repository->find($id);

        self::assertInstanceOf(AdministratorToken::class, $model);
        self::assertSame($id, $model->getId());
        self::assertEqualsWithDelta($creationDate, $model->getCreatedAt(), 1);
        self::assertEquals($expiry, $model->getExpiry());
        self::assertSame($key, $model->getKey());
    }

    public function testCreationDateOfExistingModelStaysUnchangedOnUpdate()
    {
        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        $id = 1;
        /** @var AdministratorToken $model */
        $model = $this->repository->find($id);
        $creationDate = $model->getCreatedAt();

        $model->setKey('asdfasd');
        $this->entityManager->flush();

        self::assertEquals($creationDate, $model->getCreatedAt());
    }

    public function testCreationDateOfNewModelIsSetToNowOnPersist()
    {
        $model = new Administrator();
        $expectedCreationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedCreationDate, $model->getCreatedAt());
    }

    public function testFindOneUnexpiredByKeyFindsUnexpiredTokenWithMatchingKey()
    {
        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        $id = 2;
        $key = '8321b19193d80ce5e1b7cd8742266a5f';

        /** @var AdministratorToken $model */
        $model = $this->repository->findOneUnexpiredByKey($key);

        self::assertInstanceOf(AdministratorToken::class, $model);
        self::assertSame($id, $model->getId());
    }

    public function testFindOneUnexpiredByKeyNotFindsExpiredTokenWithMatchingKey()
    {
        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        $key = 'cfdf64eecbbf336628b0f3071adba762';

        $model = $this->repository->findOneUnexpiredByKey($key);

        self::assertNull($model);
    }

    public function testFindOneUnexpiredByKeyNotFindsUnexpiredTokenWithNonMatchingKey()
    {
        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        $key = '03e7a64fb29115ba7581092c342299df';

        $model = $this->repository->findOneUnexpiredByKey($key);

        self::assertNull($model);
    }

    public function testSavePersistsAndFlushesModel()
    {
        $this->loadFixtures([AdministratorFixture::class]);

        $administratorRepository = $this->getContainer()->get(AdministratorRepository::class);
        /** @var Administrator $administrator */
        $administrator = $administratorRepository->find(1);

        $model = new AdministratorToken();
        $model->setAdministrator($administrator);
        $this->repository->save($model);

        self::assertSame($model, $this->repository->find($model->getId()));
    }

    public function testRemoveRemovesModel()
    {
        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        /** @var AdministratorToken[] $allModels */
        $allModels = $this->repository->findAll();
        $numberOfModelsBeforeRemove = count($allModels);
        $firstModel = $allModels[0];

        $this->repository->remove($firstModel);

        $numberOfModelsAfterRemove = count($this->repository->findAll());
        self::assertSame(1, $numberOfModelsBeforeRemove - $numberOfModelsAfterRemove);
    }
}
