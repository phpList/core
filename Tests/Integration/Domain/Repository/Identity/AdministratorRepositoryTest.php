<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Identity;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorRepository;
use PhpList\PhpList4\Tests\Integration\Domain\Repository\AbstractRepositoryTest;
use PhpList\PhpList4\Tests\Support\Traits\SimilarDatesAssertionTrait;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepositoryTest extends AbstractRepositoryTest
{
    use SimilarDatesAssertionTrait;

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

        $this->subject = $this->entityManager->getRepository(Administrator::class);
    }

    /**
     * @test
     */
    public function instanceFromEntityManagerIsAdministratorRepository()
    {
        self::assertInstanceOf(AdministratorRepository::class, $this->subject);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $loginName = 'john.doe';
        $normalizedLoginName = 'john-doe';
        $emailAddress = 'john@example.com';
        $creationDate = new \DateTime('2017-06-22 15:01:17');
        $modificationDate = new \DateTime('2017-06-23 19:50:43');
        $passwordHash = '8d0c8f9d1a9539021fda006427b993b9';
        $passwordChangeDate = new \DateTime('2017-06-28');
        $disabled = true;

        /** @var Administrator $actualModel */
        $actualModel = $this->subject->find($id);

        self::assertSame($id, $actualModel->getId());
        self::assertSame($loginName, $actualModel->getLoginName());
        self::assertSame($normalizedLoginName, $actualModel->getNormalizedLoginName());
        self::assertSame($emailAddress, $actualModel->getEmailAddress());
        self::assertEquals($creationDate, $actualModel->getCreationDate());
        self::assertEquals($modificationDate, $actualModel->getModificationDate());
        self::assertSame($passwordHash, $actualModel->getPasswordHash());
        self::assertEquals($passwordChangeDate, $actualModel->getPasswordChangeDate());
        self::assertSame($disabled, $actualModel->isDisabled());
    }

    /**
     * @test
     */
    public function creationDateOfExistingModelStaysUnchangedOnUpdate()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        /** @var Administrator $model */
        $model = $this->subject->find($id);
        $creationDate = $model->getCreationDate();

        $model->setLoginName('mel');
        $this->entityManager->flush();

        self::assertSame($creationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function modificationDateOfExistingModelGetsUpdatedOnUpdate()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        /** @var Administrator $model */
        $model = $this->subject->find($id);
        $expectedModificationDate = new \DateTime();

        $model->setLoginName('mel');
        $this->entityManager->flush();

        self::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }

    /**
     * @test
     */
    public function creationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $model = new Administrator();
        $expectedCreationDate = new \DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function modificationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $model = new Administrator();
        $expectedModificationDate = new \DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }
}
