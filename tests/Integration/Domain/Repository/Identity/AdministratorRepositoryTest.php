<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Identity;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorRepository;
use PhpList\PhpList4\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\PhpList4\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepositoryTest extends TestCase
{
    use DatabaseTestTrait;
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
        $this->setUpDatabaseTest();

        $this->subject = $this->container->get(AdministratorRepository::class);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
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

        static::assertSame($id, $actualModel->getId());
        static::assertSame($loginName, $actualModel->getLoginName());
        static::assertSame($emailAddress, $actualModel->getEmailAddress());
        static::assertEquals($creationDate, $actualModel->getCreationDate());
        static::assertEquals($modificationDate, $actualModel->getModificationDate());
        static::assertSame($passwordHash, $actualModel->getPasswordHash());
        static::assertEquals($passwordChangeDate, $actualModel->getPasswordChangeDate());
        static::assertTrue($actualModel->isDisabled());
        static::assertTrue($actualModel->isDisabled());
    }

    /**
     * @test
     */
    public function creationDateOfExistingModelStaysUnchangedOnUpdate()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        /** @var Administrator $model */
        $model = $this->subject->find($id);
        $creationDate = $model->getCreationDate();

        $model->setLoginName('mel');
        $this->entityManager->flush();

        static::assertSame($creationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function modificationDateOfExistingModelGetsUpdatedOnUpdate()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        /** @var Administrator $model */
        $model = $this->subject->find($id);
        $expectedModificationDate = new \DateTime();

        $model->setLoginName('mel');
        $this->entityManager->flush();

        static::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }

    /**
     * @test
     */
    public function creationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->touchDatabaseTable(static::TABLE_NAME);

        $model = new Administrator();
        $expectedCreationDate = new \DateTime();

        $this->entityManager->persist($model);

        static::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function modificationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->touchDatabaseTable(static::TABLE_NAME);

        $model = new Administrator();
        $expectedModificationDate = new \DateTime();

        $this->entityManager->persist($model);

        static::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }

    /**
     * @test
     */
    public function findOneByLoginCredentialsForMatchingCredentialsReturnsModel()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $loginName = 'john.doe';
        $password = 'Bazinga!';

        $result = $this->subject->findOneByLoginCredentials($loginName, $password);

        static::assertInstanceOf(Administrator::class, $result);
        static::assertSame($id, $result->getId());
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
        $loginName = 'max.doe';
        $password = 'Bazinga!';

        $result = $this->subject->findOneByLoginCredentials($loginName, $password);

        static::assertNull($result);
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

        static::assertNull($result);
    }

    /**
     * @test
     */
    public function savePersistsAndFlushesModel()
    {
        $this->touchDatabaseTable(static::TABLE_NAME);

        $model = new Administrator();
        $this->subject->save($model);

        static::assertSame($model, $this->subject->find($model->getId()));
    }

    /**
     * @test
     */
    public function removeRemovesModel()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
        $this->applyDatabaseChanges();

        /** @var Administrator[] $allModels */
        $allModels = $this->subject->findAll();
        $numberOfModelsBeforeRemove = count($allModels);
        $firstModel = $allModels[0];

        $this->subject->remove($firstModel);

        $numberOfModelsAfterRemove = count($this->subject->findAll());
        static::assertSame(1, $numberOfModelsBeforeRemove - $numberOfModelsAfterRemove);
    }
}
