<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Identity;

use Doctrine\Common\Persistence\ObjectRepository;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorRepository;
use PhpList\PhpList4\Tests\Integration\Domain\Repository\AbstractRepositoryTest;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepositoryTest extends AbstractRepositoryTest
{
    /**
     * @var string
     */
    const TABLE_NAME = 'phplist_admin';

    /**
     * @var AdministratorRepository|ObjectRepository
     */
    private $subject = null;

    protected function setUp()
    {
        parent::setUp();

        $this->subject = $this->bootstrap->getEntityManager()->getRepository(Administrator::class);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/DetachedAdministrator.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $expectedModel = new Administrator();
        $this->setId($expectedModel, $id);
        $expectedModel->setLoginName('john.doe');
        $expectedModel->setNormalizedLoginName('john-doe');
        $expectedModel->setEmailAddress('john@example.com');
        $expectedModel->setCreationDate(new \DateTime('2017-06-22 15:01:17'));
        $expectedModel->setModificationDate(new \DateTime('2017-06-23 19:50:43'));
        $expectedModel->setPasswordHash('8d0c8f9d1a9539021fda006427b993b9');
        $expectedModel->setDisabled(true);

        $actualModel = $this->subject->find($id);

        self::assertEquals($expectedModel, $actualModel);
    }
}
