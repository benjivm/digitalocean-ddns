<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

/*
 * $dotenv
 *
 * Load our .env file.
 */
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
    $endpoint = sprintf(
        'https://api.digitalocean.com/v2/domains/%s/records/%s',
        getenv('DOMAIN'),
        $record['id']
    );

    $httpClient->request('PUT', $endpoint, [
        'auth_bearer' => getenv('DO_API_TOKEN'),
        'json'        => ['data' => $data, 'ttl' => 30],
    ]);
};

/*
 * Execute the update.
 */
$records = $getDomainRecords();
$hostnames = $getHostnames();
$ip = $getIp();

foreach ($hostnames as $host) {
    $record = current(array_filter($records, function ($value) use ($host) {
        return $value['name'] === $host;
    }));

    $updateDnsRecord($record, $ip);
}
