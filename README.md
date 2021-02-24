# DigitalOcean Dynamic DNS

A command-line tool to automatically update your DigitalOcean DNS records with your current IP address. Only `A` records for a single root domain (e.g., `example.com`) are supported at this time.

## Requirements

- [PHP](https://www.php.net/downloads.php) >= 8.0
- [Composer](https://getcomposer.org)
- A DigitalOcean [API token](https://cloud.digitalocean.com/account/api/tokens)
- A task scheduler (such as *cron* or *Windows Task Scheduler*)

## Setup

The following assumes a Linux host, you will need to modify as needed if you are running Windows or macOS; this script should work on any platform.

**Note:** The record(s) you want updated must already exist on DigitalOcean.

- Clone or download this repository to a new directory of your choice
- Install required dependencies: `composer install --no-dev`
- Setup the environment: `cp .env.example .env && nano .env`
- Test run: `php update.php`, if you see any errors your `.env` file is probably incorrectly configured
- Create a scheduled task, e.g., I use this in `crontab`: `0 * * * * php /home/benjivm/digitalocean-ddns/update.php >> /dev/null 2>&1` (update hourly)
