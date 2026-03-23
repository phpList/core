<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Filter;

use DateTimeImmutable;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\Filter\PaginatedFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

class SubscriberHistoryFilter extends PaginatedFilter implements FilterRequestInterface
{
    private ?Subscriber $subscriber;
    private ?string $ip;
    private ?DateTimeImmutable $dateFrom;
    private ?string $summery;

    public function __construct(
        ?Subscriber $subscriber = null,
        ?string $ip = null,
        ?DateTimeImmutable $dateFrom = null,
        ?string $summery = null,
        int $lastId = 0,
        int $limit = 50,
    ) {
        $this->subscriber = $subscriber;
        $this->ip = $ip;
        $this->dateFrom = $dateFrom;
        $this->summery = $summery;
        $this->setLastId($lastId);
        $this->setLimit($limit);
    }

    public function getSubscriber(): ?Subscriber
    {
        return $this->subscriber;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getDateFrom(): ?DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function getSummery(): ?string
    {
        return $this->summery;
    }
}
