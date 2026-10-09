<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Client;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticsearchClientFactory
{
    public static function create(
        array $hosts,
        ?string $username,
        ?string $password,
        int $connectTimeout,
        int $requestTimeout,
    ): Client {
        $builder = ClientBuilder::create()->setHosts($hosts);

        if (!empty($username)) {
            $builder->setBasicAuthentication($username, $password ?? '');
        }

        $builder->setHttpClientOptions([
            'max_connect_duration' => $connectTimeout,
            'timeout' => $requestTimeout,
        ]);

        return $builder->build();
    }
}
