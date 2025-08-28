<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Service\Handler\DeleteBounceHandler;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeleteBounceHandlerTest extends TestCase
{
    private BounceManager&MockObject $bounceManager;
    private DeleteBounceHandler $handler;

    protected function setUp(): void
    {
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->handler = new DeleteBounceHandler($this->bounceManager);
    }

    public function testSupportsOnlyDeleteBounce(): void
    {
        $this->assertTrue($this->handler->supports('deletebounce'));
        $this->assertFalse($this->handler->supports('deleteuser'));
        $this->assertFalse($this->handler->supports(''));
    }

    public function testHandleDeletesBounce(): void
    {
        $bounce = $this->createMock(Bounce::class);
        $this->bounceManager->expects($this->once())->method('delete')->with($bounce);

        $this->handler->handle([
            'bounce' => $bounce,
        ]);
    }
}
