<?php

require __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Load environment variables from
 * our .env file.
 */
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required([
    'DO_API_TOKEN',
    'CHECKIP_URL',
    'DOMAIN',
    'HOSTNAMES',
    'TTL',
]);

/**
 * Setup our HTTP client
 */
$http = HttpClient::create();

/**
 * $getIp()
 *
 * Fetch our IP from the
 * URL set in .env
 *
 * @throws Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
 * @throws Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
 * @throws Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
 * @throws Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
 */
$getIp = fn (): string => trim(
    $http->request('GET', $_ENV['CHECKIP_URL'])->getContent()
);

/**
 * $getHostnames()
 *
 * Parse the HOSTNAMES variable defined
 * in .env and return an array.
 */
$getHostnames = function (): array {
    $hostnames = $_ENV['HOSTNAMES'];

    if (str_contains($hostnames, ',')) {
        return array_map(
            callback: 'trim',
            array: explode(',', $hostnames)
        );
    }

    return [$hostnames];
};

/**
 * $getDomainRecords()
 *
 * Get domain's DNS records
 * from DigitalOcean.
 *
 * @throws Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
 * @throws Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
 * @throws Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
 * @throws Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
 * @throws Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
 */
$getDomainRecords = fn (): array => $http->request(
    'GET',
    'https://api.digitalocean.com/v2/domains/'.$_ENV['DOMAIN'].'/records',
    ['auth_bearer' => $_ENV['DO_API_TOKEN']]
)->toArray()['domain_records'];

/**
 * $updateDnsRecord($record, $data)
 *
 * Update a DNS record
 * with the given data.
 *
 * @throws Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
 */
$updateDnsRecord = fn ($record, $data) => $http->request(
    'PUT',
    sprintf('https://api.digitalocean.com/v2/domains/%s/records/%s', $_ENV['DOMAIN'], $record['id']),
    [
        'auth_bearer' => $_ENV['DO_API_TOKEN'],
        'json' => [
            'data' => $data,
            'ttl' => $_ENV['TTL'],
        ],
    ]
);

/**
 * Now bring it all together and
 * execute the update.
 */
$records = $getDomainRecords();
$hostnames = $getHostnames();
$ip = $getIp();

if (! filter_var($ip, FILTER_VALIDATE_IP) || ! count($records)) {
    exit('Invalid IP or missing domain records.');
}

foreach ($hostnames as $host) {
    $record = current(
        array_filter(
            array: $records,
            callback: fn ($value) => $value['name'] === $host && $value['type'] === 'A'
        )
    );

    if ($record['data'] !== $ip) {
        $updateDnsRecord($record, $ip);
    }
}
