<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use DateTimeInterface;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

class MessageForwardService
{
    public function __construct(private readonly UserMessageForwardRepository $repository)
    {
    }

    public function forward(array $emails, Subscriber $subscriber, DateTimeInterface $cutoff): void
    {
        $forwardPeriodCount = $this->repository->getCountByUserSince($subscriber, $cutoff);
    }
}
