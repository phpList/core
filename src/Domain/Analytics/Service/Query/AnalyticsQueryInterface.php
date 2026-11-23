<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service\Query;

/**
 * The query parameters an analytics tool (Google Analytics, Matomo) needs on the links of a campaign,
 * like phplist3's AnalyticsQuery.
 */
interface AnalyticsQueryInterface
{
    /**
     * @param string $emailFormat HTML or text
     * @param array<string, mixed> $messageData the campaign's message data
     * @return array<string, mixed> query parameters as name => value, empty values are not added to links
     */
    public function trackingParameters(string $emailFormat, array $messageData): array;

    /**
     * The parameters a campaign can set, with the values to use when it has not set them.
     *
     * @param array<string, mixed> $messageData
     * @return array<string, string>
     */
    public function editableParameters(array $messageData): array;

    /** Prefix shared by the parameter names, used to take off tracking parameters that are already on a link. */
    public function prefix(): string;
}
