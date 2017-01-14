# Costlocker reports

## Usage

```bash
# crontab
0 8 1 * * bin/console report --monthStart "previous month" --monthEnd "previous month" --host "https://new.costlocker.com|apiKey" --email "kamil@costlocker.com" --currency EUR 2>&1 >> report.log
0 8 1 1 * bin/console report --monthStart "now - 12 months" --monthEnd "now - 1 month" --host "https://new.costlocker.com|apiKey" --email "kamil@costlocker.com" --hardcodedHours var/2fresh/hours.csv 2>&1 >> report.log
```

## Development

``` bash
git clone ...
composer install
vendor/bin/phpunit
bin/qa
```

### Mail sender

```
cp app/config.default.php app/config.php
nano app/config.php
```
