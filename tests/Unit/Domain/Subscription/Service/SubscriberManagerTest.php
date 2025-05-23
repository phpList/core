<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Dto\CreateSubscriberDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use PHPUnit\Framework\TestCase;

class SubscriberManagerTest extends TestCase
{
    public function testCreateSubscriberPersistsAndReturnsProperlyInitializedEntity(): void
    {
        $repoMock = $this->createMock(SubscriberRepository::class);
        $emMock = $this->createMock(EntityManagerInterface::class);
        $repoMock
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Subscriber $sub): bool {
                return $sub->getEmail() === 'foo@bar.com'
                    && $sub->isConfirmed() === false
                    && $sub->isBlacklisted() === false
                    && $sub->hasHtmlEmail() === true
                    && $sub->isDisabled() === false;
            }));

        $manager = new SubscriberManager($repoMock, $emMock);

        $dto = new CreateSubscriberDto(email: 'foo@bar.com', requestConfirmation: true, htmlEmail: true);

        $result = $manager->createSubscriber($dto);

        $this->assertInstanceOf(Subscriber::class, $result);
        $this->assertSame('foo@bar.com', $result->getEmail());
        $this->assertFalse($result->isConfirmed());
        $this->assertFalse($result->isBlacklisted());
        $this->assertTrue($result->hasHtmlEmail());
        $this->assertFalse($result->isDisabled());
    }
}
