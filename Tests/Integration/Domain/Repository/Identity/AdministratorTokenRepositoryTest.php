<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Identity;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\Proxy\Proxy;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Model\Identity\AdministratorToken;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorTokenRepository;
use PhpList\PhpList4\Tests\Integration\Domain\Repository\AbstractRepositoryTest;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTokenRepositoryTest extends AbstractRepositoryTest
{
    /**
     * @var string
     */
    const TABLE_NAME = 'phplist_admintoken';

    /**
     * @var string
     */
    const ADMINISTRATOR_TABLE_NAME = 'phplist_admin';

    /**
     * @var AdministratorTokenRepository|ObjectRepository
     */
    private $subject = null;

    protected function setUp()
    {
        parent::setUp();

        $this->subject = $this->entityManager->getRepository(AdministratorToken::class);
    }

    /**
     * @test
     */
    public function instanceFromEntityManagerIsAdministratorTokenRepository()
    {
        self::assertInstanceOf(AdministratorTokenRepository::class, $this->subject);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorTokens.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $expiry = new \DateTime('2017-06-22 16:43:29');
        $key = 'cfdf64eecbbf336628b0f3071adba762';

        /** @var AdministratorToken $model */
        $model = $this->subject->find($id);

        self::assertInstanceOf(AdministratorToken::class, $model);
        self::assertSame($id, $model->getId());
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
}
