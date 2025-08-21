<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\BounceRegex;
use PhpList\Core\Domain\Messaging\Model\BounceRegexBounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRegexBounceRepository;
use PhpList\Core\Domain\Messaging\Repository\BounceRegexRepository;

class BounceRuleManager
{
    public function __construct(
        private readonly BounceRegexRepository $repository,
        private readonly BounceRegexBounceRepository $bounceRegexBounceRepository
    ) {
    }

    /**
     * @return array<string,BounceRegex>
     */
    public function loadActiveRules(): array
    {
        return $this->mapRows($this->repository->fetchActiveOrdered());
    }

    /**
     * @return array<string,BounceRegex>
     */
    public function loadAllRules(): array
    {
        return $this->mapRows($this->repository->fetchAllOrdered());
    }

    /**
     * Internal helper to normalize repository rows into the legacy shape.
     *
     * @param BounceRegex[] $rows
     * @return array<string,BounceRegex>
     */
    private function mapRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $regex = $row->getRegex();
            $action = $row->getAction();
            $id = $row->getId();

            if (
                !is_string($regex)
                || $regex === ''
                || !is_string($action)
                || $action === ''
                || !is_int($id)
            ) {
                continue;
            }

            $result[$regex] = $row;
        }

        return $result;
    }


    /**
     * @param array<string,BounceRegex> $rules
     */
    public function matchBounceRules(string $text, array $rules): ?BounceRegex
    {
        foreach ($rules as $pattern => $rule) {
            $pattern = str_replace(' ', '\s+', $pattern);
            if (@preg_match('/'.preg_quote($pattern).'/iUm', $text)) {
                return $rule;
            } elseif (@preg_match('/'.$pattern.'/iUm', $text)) {
                return $rule;
            }
        }

        return null;
    }

    public function incrementCount(BounceRegex $rule): void
    {
        $rule->setCount($rule->getCount() + 1);

        $this->repository->save($rule);
    }

    public function linkRuleToBounce(BounceRegex $rule, Bounce $bounce): BounceregexBounce
    {
        $relation = new BounceRegexBounce($rule->getId(), $bounce->getId());
        $this->bounceRegexBounceRepository->save($relation);

        return $relation;
    }
}
