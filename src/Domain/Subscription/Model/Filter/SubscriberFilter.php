<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Filter;

use DateTimeImmutable;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;

class SubscriberFilter implements FilterRequestInterface
{
    private ?int $listId;
    private ?DateTimeImmutable $subscribedDateFrom;
    private ?DateTimeImmutable $subscribedDateTo;
    private ?DateTimeImmutable $createdDateFrom;
    private ?DateTimeImmutable $createdDateTo;
    private ?DateTimeImmutable $updatedDateFrom;
    private ?DateTimeImmutable $updatedDateTo;
    private array $columns;

    public function __construct(
        ?int $listId = null,
        ?DateTimeImmutable $subscribedDateFrom = null,
        ?DateTimeImmutable $subscribedDateTo = null,
        ?DateTimeImmutable $createdDateFrom = null,
        ?DateTimeImmutable $createdDateTo = null,
        ?DateTimeImmutable $updatedDateFrom = null,
        ?DateTimeImmutable $updatedDateTo = null,
        array $columns = [],
    ) {
        $this->listId = $listId;
        $this->subscribedDateFrom = $subscribedDateFrom;
        $this->subscribedDateTo = $subscribedDateTo;
        $this->createdDateFrom = $createdDateFrom;
        $this->createdDateTo = $createdDateTo;
        $this->updatedDateFrom = $updatedDateFrom;
        $this->updatedDateTo = $updatedDateTo;
        $this->columns = $columns;
    }

    public function getListId(): ?int
    {
        return $this->listId;
    }

    public function getSubscribedDateFrom(): ?DateTimeImmutable
    {
        return $this->subscribedDateFrom;
    }

    public function getSubscribedDateTo(): ?DateTimeImmutable
    {
        return $this->subscribedDateTo;
    }

    public function getCreatedDateFrom(): ?DateTimeImmutable
    {
        return $this->createdDateFrom;
    }

    public function getCreatedDateTo(): ?DateTimeImmutable
    {
        return $this->createdDateTo;
    }

    public function getUpdatedDateFrom(): ?DateTimeImmutable
    {
        return $this->updatedDateFrom;
    }

    public function getUpdatedDateTo(): ?DateTimeImmutable
    {
        return $this->updatedDateTo;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }
}
