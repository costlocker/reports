# Costlocker reports

## Usage

```bash
# crontab
0 8 1 * * bin/console report --date "previous month" --host "https://new.costlocker.com|apiKey" --email "kamil@costlocker.com" 2>&1 >> report.log
```

## Development

``` bash
git clone ...
vendor/bin/phpunit
bin/qa
```

### Mail sender

```
cp app/config.default.php app/config.php
nano app/config.php
```
