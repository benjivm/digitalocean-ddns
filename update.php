<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

/*
 * $httpClient
 *
 * This is our HTTP Client.
 */
$httpClient = HttpClient::create();

/*
 * $getIp()
 *
 * This function fetches our IP from
 * the endpoint defined in .env.
 */
$getIp = function () use ($httpClient) {
    $ipRequest = $httpClient->request('GET', getenv('CHECKIP_URL'));

    return trim($ipRequest->getContent());
};

/*
 * $getHostnames()
 *
 * This function parses the HOSTNAMES
 * variable defined in .env, it
 * returns an array.
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
 * This function fetches our domain's
 * DNS records from DigitalOcean.
 */
$getDomainRecords = function () use ($httpClient) {
    $dnsRecordsRequest = $httpClient->request(
        'GET',
        'https://api.digitalocean.com/v2/domains/' . getenv('DOMAIN') . '/records',
        ['auth_bearer' => getenv('DO_API_TOKEN')]
    );

    return $dnsRecordsRequest->toArray()['domain_records'];
};

/*
 * $updateDnsRecord($record, $data)
 *
 * This function updates a DNS record's
 * value with the given data.
 */
$updateDnsRecord = function ($record, $data) use ($httpClient) {
    $httpClient->request(
        'PUT',
        'https://api.digitalocean.com/v2/domains/' . getenv('DOMAIN') . '/records/' . $record['id'],
        [
            'auth_bearer' => getenv('DO_API_TOKEN'),
            'json'        => ['data' => $data, 'ttl' => 30],
        ]
    );
};

/*
 * Execute the update.
 * Checks for a valid IP and
 * DNS records before updating.
 */
$records = $getDomainRecords();
$hostnames = $getHostnames();
$ip = $getIp();

if (! filter_var($ip, FILTER_VALIDATE_IP) || ! count($records)) {
    die('Invalid IP or missing domain records.');
}

foreach ($hostnames as $host) {
    $record = current(array_filter($records, function ($value) use ($host) {
        return $value['name'] === $host && $value['type'] === 'A';
    }));

    $updateDnsRecord($record, $ip);
    
    echo "'{$host}' pointed to {$ip}\n";
}
