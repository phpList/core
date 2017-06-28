<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Identity;

use Doctrine\Common\Persistence\ObjectRepository;
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
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministratorToken.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $expectedModel = new AdministratorToken();
        $this->setId($expectedModel, $id);
        $expectedModel->setExpiry(new \DateTime('2017-06-22 16:43:29'));
        $expectedModel->setKey('cfdf64eecbbf336628b0f3071adba762');

        $actualModel = $this->subject->find($id);

        self::assertEquals($expectedModel, $actualModel);
    }
}
