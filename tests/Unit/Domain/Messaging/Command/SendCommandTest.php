<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Command;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PhpList\Core\Domain\Messaging\Command\SendCommand;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Service\CliCampaignCreator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendCommandTest extends TestCase
{
    private CliCampaignCreator&MockObject $creator;
    private EntityManagerInterface&MockObject $entityManager;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->creator = $this->createMock(CliCampaignCreator::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => strtr($id, $params)
        );
        $this->tester = new CommandTester(new SendCommand($this->creator, $translator, $this->entityManager));
    }

    public function testRequiresSubjectAndList(): void
    {
        $this->creator->expects($this->never())->method('create');

        $this->tester->execute(['--subject' => 'Hi']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testRejectsEmptyBody(): void
    {
        $this->creator->expects($this->never())->method('create');

        $this->tester->setInputs([]);
        $this->tester->execute(['--subject' => 'Hi', '--list' => ['1']]);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testCreatesAndQueuesCampaignFromStdin(): void
    {
        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(7);

        $this->creator->expects($this->once())->method('create')
            ->with('Hi', "Hello world\n", ['1', 'News'], 'Me <me@example.com>', null)
            ->willReturn($campaign);

        $this->tester->setInputs(['Hello world']);
        $this->tester->execute([
            '--subject' => 'Hi',
            '--list' => ['1', 'News'],
            '--from' => 'Me <me@example.com>',
        ]);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('id 7', $this->tester->getDisplay());
    }

    public function testUnknownListFails(): void
    {
        $this->creator->method('create')->willThrowException(new InvalidArgumentException('List "x" not found.'));

        $this->tester->setInputs(['body']);
        $this->tester->execute(['--subject' => 'Hi', '--list' => ['x']]);

        $this->assertSame(1, $this->tester->getStatusCode());
        $this->assertStringContainsString('not found', $this->tester->getDisplay());
    }
}
