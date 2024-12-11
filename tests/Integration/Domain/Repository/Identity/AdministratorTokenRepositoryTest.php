<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Identity;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Identity\AdministratorToken;
use PhpList\Core\Domain\Repository\Identity\AdministratorRepository;
use PhpList\Core\Domain\Repository\Identity\AdministratorTokenRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\AdministratorFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\AdministratorTokenWithAdministratorFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\DetachedAdministratorTokenFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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
        self::assertEqualsWithDelta($creationDate, $model->getCreationDate(), 1);
        self::assertEquals($expiry, $model->getExpiry());
        self::assertSame($key, $model->getKey());
    }

    public function testCreationDateOfExistingModelStaysUnchangedOnUpdate()
    {
        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        $id = 1;
        /** @var AdministratorToken $model */
        $model = $this->repository->find($id);
        $creationDate = $model->getCreationDate();

        $model->setKey('asdfasd');
        $this->entityManager->flush();

        self::assertEquals($creationDate, $model->getCreationDate());
    }

    public function testCreationDateOfNewModelIsSetToNowOnPersist()
    {
        $model = new Administrator();
        $expectedCreationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
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

    public function testRemoveExpiredRemovesExpiredToken()
    {
        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        $idOfExpiredToken = 1;
        $this->repository->removeExpired();

        $token = $this->repository->find($idOfExpiredToken);
        self::assertNull($token);
    }

    public function testRemoveExpiredKeepsUnexpiredToken()
    {
        $this->assertNotYear2037Yet();

        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        $idOfUnexpiredToken = 2;
        $this->repository->removeExpired();

        $token = $this->repository->find($idOfUnexpiredToken);
        self::assertNotNull($token);
    }

    /**
     * Asserts that it's not year 2037 yet (which is the year the "not expired" token in the fixture
     * data set expires).
     */
    private function assertNotYear2037Yet(): void
    {
        $currentYear = (int)date('Y');
        if ($currentYear >= 2037) {
            self::markTestIncomplete('The tests token has an expiry in the year 2037. Please update this test.');
        }
    }

    public function testRemoveExpiredForNoExpiredTokensReturnsZero()
    {
        self::assertSame(0, $this->repository->removeExpired());
    }

    public function testRemoveExpiredForOneExpiredTokenReturnsOne()
    {
        $this->assertNotYear2037Yet();

        $this->loadFixtures([DetachedAdministratorTokenFixture::class]);

        self::assertSame(1, $this->repository->removeExpired());
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
