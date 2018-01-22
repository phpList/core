<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Identity;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorRepository;
use PhpList\PhpList4\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\PhpList4\Tests\Integration\AbstractDatabaseTest;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepositoryTest extends AbstractDatabaseTest
{
    use SimilarDatesAssertionTrait;

    /**
     * @var string
     */
    const TABLE_NAME = 'phplist_admin';

    /**
     * @var AdministratorRepository
     */
    private $subject = null;

    protected function setUp()
    {
        parent::setUp();

        $this->subject = $this->container->get(AdministratorRepository::class);
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
        $emailAddress = 'john@example.com';
        $creationDate = new \DateTime('2017-06-22 15:01:17');
        $modificationDate = new \DateTime('2017-06-23 19:50:43');
        $passwordHash = '1491a3c7e7b23b9a6393323babbb095dee0d7d81b2199617b487bd0fb5236f3c';
        $passwordChangeDate = new \DateTime('2017-06-28');

        /** @var Administrator $actualModel */
        $actualModel = $this->subject->find($id);

        self::assertSame($id, $actualModel->getId());
        self::assertSame($loginName, $actualModel->getLoginName());
        self::assertSame($emailAddress, $actualModel->getEmailAddress());
        self::assertEquals($creationDate, $actualModel->getCreationDate());
        self::assertEquals($modificationDate, $actualModel->getModificationDate());
        self::assertSame($passwordHash, $actualModel->getPasswordHash());
        self::assertEquals($passwordChangeDate, $actualModel->getPasswordChangeDate());
        self::assertTrue($actualModel->isDisabled());
        self::assertTrue($actualModel->isDisabled());
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

    /**
     * @test
     */
    public function findOneByLoginCredentialsForMatchingCredentialsReturnsModel()
    {
        $id = 1;
        $loginName = 'john.doe';
        $password = 'Bazinga!';

        $result = $this->subject->findOneByLoginCredentials($loginName, $password);

        self::assertInstanceOf(Administrator::class, $result);
        self::assertSame($id, $result->getId());
    }

    /**
     * @return string[][]
     */
    public function incorrectLoginCredentialsDataProvider(): array
    {
        $loginName = 'john.doe';
        $password = 'Bazinga!';

        return [
            'all empty' => ['', ''],
            'matching login name, empty password' => [$loginName, ''],
            'matching login name, incorrect password' => [$loginName, 'The cake is a lie.'],
            'empty login name, correct password' => ['', $password],
            'incorrect name, correct password' => ['jane.doe', $password],
        ];
    }

    /**
     * @test
     */
    public function findOneByLoginCredentialsIgnoresNonSuperUser()
    {
        $id = 1;
        $loginName = 'max.doe';
        $password = 'Bazinga!';

        $result = $this->subject->findOneByLoginCredentials($loginName, $password);

        self::assertNull($result);
    }

    /**
     * @test
     * @param string $loginName
     * @param string $password
     * @dataProvider incorrectLoginCredentialsDataProvider
     */
    public function findOneByLoginCredentialsForNonMatchingCredentialsReturnsNull(string $loginName, string $password)
    {
        $result = $this->subject->findOneByLoginCredentials($loginName, $password);

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function savePersistsAndFlushesModel()
    {
        $this->touchDatabaseTable(self::TABLE_NAME);

        $model = new Administrator();
        $this->subject->save($model);

        self::assertSame($model, $this->subject->find($model->getId()));
    }
}
