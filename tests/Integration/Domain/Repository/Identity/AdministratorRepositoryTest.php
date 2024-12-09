<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Identity;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Repository\Identity\AdministratorRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\AdministratorFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use ModelTestTrait;

    private ?AdministratorRepository $repository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();
        $this->repository = self::getContainer()->get(AdministratorRepository::class);
        $this->loadFixtures([AdministratorFixture::class]);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testFindReadsModelFromDatabase(): void
    {
        /** @var Administrator $actual */
        $actual = $this->repository->find(1);

        $this->assertNotNull($actual);
        $this->assertFalse($actual->isDisabled());
        $this->assertTrue($actual->isSuperUser());
        $this->assertSame($actual->getLoginName(), $actual->getLoginName());
        $this->assertEqualsWithDelta(
            (new DateTime())->getTimestamp(),
            $actual->getModificationDate()->getTimestamp(),
            1
        );
        $this->assertSame('john@example.com', $actual->getEmailAddress());
        $this->assertSame('1491a3c7e7b23b9a6393323babbb095dee0d7d81b2199617b487bd0fb5236f3c', $actual->getPasswordHash());
        $this->assertEquals(new DateTime('2017-06-22 15:01:17'), $actual->getCreationDate());
        $this->assertEquals(new DateTime('2017-06-28'), $actual->getPasswordChangeDate());
    }

    public function testCreationDateOfExistingModelStaysUnchangedOnUpdate(): void
    {
        $id = 1;
        $model = $this->repository->find($id);
        $this->assertNotNull($model);
        $originalCreationDate = $model->getCreationDate();
        $model->setLoginName('mel');

        $this->entityManager->flush();

        $this->assertSame($originalCreationDate, $model->getCreationDate());
    }

    public function testModificationDateOfExistingModelGetsUpdatedOnUpdate(): void
    {
        $id = 1;
        $model = $this->repository->find($id);
        $this->assertNotNull($model);

        $model->setLoginName('mel');
        $this->entityManager->flush();

        $expectedModificationDate = new DateTime();
        $this->assertEqualsWithDelta($expectedModificationDate, $model->getModificationDate(), 5);
    }

    public function testCreationDateOfNewModelIsSetToNowOnPersist()
    {
        $model = new Administrator();

        $this->entityManager->persist($model);
        $this->entityManager->flush();

        $expectedCreationDate = new DateTime();
        $this->assertEqualsWithDelta($expectedCreationDate, $model->getCreationDate(), 1);
    }

    public function testModificationDateOfNewModelIsSetToNowOnPersist()
    {
        $model = new Administrator();

        $this->entityManager->persist($model);
        $this->entityManager->flush();

        $expectedCreationDate = new DateTime();
        $this->assertEqualsWithDelta($expectedCreationDate, $model->getModificationDate(), 1);
    }

    /**
     * Tests that findOneByLoginCredentials returns null for incorrect credentials.
     *
     * @dataProvider incorrectLoginCredentialsDataProvider
     */
    public function testFindOneByLoginCredentialsForNonMatchingCredentialsReturnsNull(string $loginName, string $password): void
    {
        $result = $this->repository->findOneByLoginCredentials($loginName, $password);

        $this->assertNull($result);
    }

    public function testFindOneByLoginCredentialsForMatchingCredentialsReturnsModel()
    {
        $id = 1;
        $loginName = 'john.doe';
        $password = 'Bazinga!';

        $result = $this->repository->findOneByLoginCredentials($loginName, $password);

        self::assertInstanceOf(Administrator::class, $result);
        self::assertSame($id, $result->getId());
    }

    public static function incorrectLoginCredentialsDataProvider(): array
    {
        $loginName = 'john.doe';
        $password = 'Bazinga!';

        return [
            'all empty' => ['', ''],
            'matching login name, empty password' => [$loginName, ''],
            'matching login name, incorrect password' => [$loginName, 'wrong-password'],
            'empty login name, correct password' => ['', $password],
            'incorrect login name, correct password' => ['jane.doe', $password],
        ];
    }

    public function testFindOneByLoginCredentialsIgnoresNonSuperUser()
    {
        $loginName = 'max.doe';
        $password = 'Bazinga!';

        $result = $this->repository->findOneByLoginCredentials($loginName, $password);

        self::assertNull($result);
    }

    public function testSavePersistsAndFlushesModel(): void
    {
        $model = new Administrator();
        $this->repository->save($model);

        $this->assertSame($model, $this->repository->find($model->getId()));
    }

    public function testRemoveRemovesModel(): void
    {
        $allModels = $this->repository->findAll();
        $this->assertNotEmpty($allModels);

        $model = $allModels[0];
        $this->repository->remove($model);

        $remainingModels = $this->repository->findAll();
        $this->assertCount(count($allModels) - 1, $remainingModels);
    }
}
