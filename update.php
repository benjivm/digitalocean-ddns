<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$http = HttpClient::create();

/*
 * $getIp()
 *
 * Fetch our IP from the
 * URL set in .env
 */
$getIp = function () use ($http) {
    $ipRequest = $http->request('GET', getenv('CHECKIP_URL'));

    return trim($ipRequest->getContent());
};

/*
 * $getHostnames()
 *
 * Parse the HOSTNAMES variable defined
 * in .env and return an array.
 */
$getHostnames = function () {
    $hostnames = getenv('HOSTNAMES');

    if (strpos($hostnames, ',') !== false) {
        return explode(',', $hostnames);
    }

    return [$hostnames];
};

/*
 * $getDomainRecords()
 *
 * Get domain's DNS records
 * from DigitalOcean.
 */
$getDomainRecords = function () use ($http) {
    $dnsRecordsRequest = $http->request(
        'GET',
        'https://api.digitalocean.com/v2/domains/' . getenv('DOMAIN') . '/records',
        ['auth_bearer' => getenv('DO_API_TOKEN')]
    );

    return $dnsRecordsRequest->toArray()['domain_records'];
};

/*
 * $updateDnsRecord($record, $data)
 *
 * Update a DNS record
 * with the given data.
 */
$updateDnsRecord = fn ($record, $data) => $http->request(
    'PUT',
    'https://api.digitalocean.com/v2/domains/' . getenv('DOMAIN') . '/records/' . $record['id'],
    [
        'auth_bearer' => getenv('DO_API_TOKEN'),
        'json'        => ['data' => $data, 'ttl' => getenv('TTL', 1800)],
    ]
);

/*
 * Now bring it all together and
 * execute the update.
 */
$records = $getDomainRecords();
$hostnames = $getHostnames();
$ip = $getIp();

if (! filter_var($ip, FILTER_VALIDATE_IP) || ! count($records)) {
    die('Invalid IP or missing domain records.');
}

foreach ($hostnames as $host) {
    $record = current(
        array_filter(
            $records,
            fn ($value) => $value['name'] === $host && $value['type'] === 'A'
        )
    );

    if ($record) {
        if ($record['data'] !== $ip) {
            $updateDnsRecord($record, $ip);

            echo "'{$host}' now points to {$ip}\n";
        } else {
            echo "'{$host}' already points to {$ip}\n";
        }
    }
}
