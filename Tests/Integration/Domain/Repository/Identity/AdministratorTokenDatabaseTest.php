<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Identity;

use Doctrine\ORM\Proxy\Proxy;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Model\Identity\AdministratorToken;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorRepository;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorTokenRepository;
use PhpList\PhpList4\Tests\Integration\AbstractDatabaseTest;
use PhpList\PhpList4\Tests\Support\Traits\SimilarDatesAssertionTrait;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTokenDatabaseTest extends AbstractDatabaseTest
{
    use SimilarDatesAssertionTrait;

    /**
     * @var string
     */
    const TABLE_NAME = 'phplist_admintoken';

    /**
     * @var string
     */
    const ADMINISTRATOR_TABLE_NAME = 'phplist_admin';

    /**
     * @var AdministratorTokenRepository
     */
    private $subject = null;

    protected function setUp()
    {
        parent::setUp();

        $this->subject = $this->container->get(AdministratorTokenRepository::class);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $creationDate = new \DateTime('2017-12-06 17:41:40');
        $expiry = new \DateTime('2017-06-22 16:43:29');
        $key = 'cfdf64eecbbf336628b0f3071adba762';

        /** @var AdministratorToken $model */
        $model = $this->subject->find($id);

        self::assertInstanceOf(AdministratorToken::class, $model);
        self::assertSame($id, $model->getId());
        self::assertEquals($creationDate, $model->getCreationDate());
        self::assertEquals($expiry, $model->getExpiry());
        self::assertSame($key, $model->getKey());
    }

    /**
     * @test
     */
    public function createsAdministratorAssociationAsProxy()
    {
        $this->getDataSet()->addTable(self::ADMINISTRATOR_TABLE_NAME, __DIR__ . '/Fixtures/Administrator.csv');
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/AdministratorTokenWithAdministrator.csv');
        $this->applyDatabaseChanges();

        $tokenId = 1;
        $administratorId = 1;
        /** @var AdministratorToken $model */
        $model = $this->subject->find($tokenId);
        $administrator = $model->getAdministrator();

        self::assertInstanceOf(Administrator::class, $administrator);
        self::assertInstanceOf(Proxy::class, $administrator);
        self::assertSame($administratorId, $administrator->getId());
    }

    /**
     * @test
     */
    public function creationDateOfExistingModelStaysUnchangedOnUpdate()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        /** @var AdministratorToken $model */
        $model = $this->subject->find($id);
        $creationDate = $model->getCreationDate();

        $model->setKey('asdfasd');
        $this->entityManager->flush();

        self::assertEquals($creationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function creationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $model = new Administrator();
        $expectedCreationDate = new \DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function findOneUnexpiredByKeyFindsUnexpiredTokenWithMatchingKey()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $id = 2;
        $key = '8321b19193d80ce5e1b7cd8742266a5f';

        /** @var AdministratorToken $model */
        $model = $this->subject->findOneUnexpiredByKey($key);

        self::assertInstanceOf(AdministratorToken::class, $model);
        self::assertSame($id, $model->getId());
    }

    /**
     * @test
     */
    public function findOneUnexpiredByKeyNotFindsExpiredTokenWithMatchingKey()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $key = 'cfdf64eecbbf336628b0f3071adba762';

        $model = $this->subject->findOneUnexpiredByKey($key);

        self::assertNull($model);
    }

    /**
     * @test
     */
    public function findOneUnexpiredByKeyNotFindsUnexpiredTokenWithNonMatchingKey()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $key = '03e7a64fb29115ba7581092c342299df';

        $model = $this->subject->findOneUnexpiredByKey($key);

        self::assertNull($model);
    }

    /**
     * @test
     */
    public function removeExpiredRemovesExpiredToken()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $idOfExpiredToken = 1;
        $this->subject->removeExpired();

        $token = $this->subject->find($idOfExpiredToken);
        self::assertNull($token);
    }

    /**
     * @test
     */
    public function removeExpiredKeepsUnexpiredToken()
    {
        $this->assertNotYear2037Yet();

        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $idOfUnexpiredToken = 2;
        $this->subject->removeExpired();

        $token = $this->subject->find($idOfUnexpiredToken);
        self::assertNotNull($token);
    }

    /**
     * Asserts that it's not year 2037 yet (which is the year the "not expired" token in the fixture
     * data set expires).
     *
     * @return void
     */
    private function assertNotYear2037Yet()
    {
        $currentYear = (int)date('Y');
        if ($currentYear >= 2037) {
            self::markTestIncomplete('The tests token has an expiry in the year 2037. Please update this test.');
        }
    }

    /**
     * @test
     */
    public function removeExpiredForNoExpiredTokensReturnsZero()
    {
        self::assertSame(0, $this->subject->removeExpired());
    }

    /**
     * @test
     */
    public function removeExpiredForOneExpiredTokenReturnsOne()
    {
        $this->assertNotYear2037Yet();

        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        self::assertSame(1, $this->subject->removeExpired());
    }

    /**
     * @test
     */
    public function savePersistsAndFlushesModel()
    {
        $this->touchDatabaseTable(self::TABLE_NAME);
        $this->getDataSet()->addTable(self::ADMINISTRATOR_TABLE_NAME, __DIR__ . '/Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $administratorRepository = $this->container->get(AdministratorRepository::class);
        /** @var Administrator $administrator */
        $administrator = $administratorRepository->find(1);

        $model = new AdministratorToken();
        $model->setAdministrator($administrator);
        $this->subject->save($model);

        self::assertSame($model, $this->subject->find($model->getId()));
    }
}
