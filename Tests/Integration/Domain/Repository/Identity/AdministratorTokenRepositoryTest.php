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

        $this->subject = $this->bootstrap->getEntityManager()->getRepository(AdministratorToken::class);
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
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorToken.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $expiry = new \DateTime('2017-06-22 16:43:29');
        $key = 'cfdf64eecbbf336628b0f3071adba762';

        /** @var AdministratorToken $actualModel */
        $actualModel = $this->subject->find($id);

        self::assertInstanceOf(AdministratorToken::class, $actualModel);
        self::assertSame($id, $actualModel->getId());
        self::assertEquals($expiry, $actualModel->getExpiry());
        self::assertSame($key, $actualModel->getKey());
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
}
