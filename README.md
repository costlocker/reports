
# Costlocker reports

[![CircleCI](https://circleci.com/gh/costlocker/reports/tree/master.svg?style=svg&circle-token=6a72d2fe098452b9b7113b830c035045e58e65d7)](https://circleci.com/gh/costlocker/reports/tree/master)

Generate XLSX reports from [Costlocker API](http://docs.costlocker.apiary.io/).

## Installation

### Clone + composer

* requires `PHP >= 7.0`, `curl` and `gd` extension

```bash
git clone https://github.com/costlocker/reports.git
cd reports
composer install --no-dev
bin/console report --help
```

### Docker

```bash
docker build --file .circleci/Dockerfile --tag reports-costlocker ./
docker run --rm -it \
    --volume "$(realpath ./my-report.json):/app/my-report.json" \
    --volume "$(realpath ./var/logs):/app/var/logs" \
    --volume "$(realpath ./var/exports):/app/var/exports" \
    --volume "$(realpath ./var/googleDrive):/app/var/googleDrive" \
    reports-costlocker \
    bin/console report --help
```

## Export configuration

Report is created in [/var/exports](/var/exports). Optionally you can:

* [Send report to e-mail](#e-mail)
* [Upload report to Google Drive](#google-drive)

### E-mail

Configure [SMTP](https://swiftmailer.symfony.com/docs/sending.html#smtp-with-a-username-and-password) in [.env](/.env.example).

```bash
cp .env.example .env
nano .env
```

### Google Drive

You have to create [an OAuth Client](https://stackoverflow.com/a/19766913) and copy configuration to [/var/googleDrive](/var/googleDrive).

| File | Description |
| ---- | ------------|
| [`client.json`](https://github.com/costlocker/reports/blob/v2.0.0/var/drive/example/client.json) | Google client registered via [API console](https://stackoverflow.com/a/19766913) |
| [`token.json`](https://github.com/costlocker/reports/blob/v2.0.0/var/drive/example/token.json) | Access token, you can download first token from https://developers.google.com/oauthplayground |

```bash
mv ~/Downloads/client.json var/googleDrive/client.json
mv ~/Downloads/token.json var/googleDrive/token.json
```

## Available reports

All examples are using environment variable with url and 
[api key](http://docs.costlocker.apiary.io/#reference/0/authentication/personal-access-token).

```
COSTLOCKER_HOST="https://new.costlocker.com|<YOUR_API_KEY>"
```

##### Options

| CLI option | Value | Description |
| ---------- | ------------- | ----------- |
| `--host` | `https://new.costlocker.com\|<YOUR_API_KEY>` | Costlocker API url and API key of your organization |
| `--email` | `john@example.com` | Report is saved in `var/reports` and send report to the email provided |
| `--drive` | [`var/drive/example`](/var/drive/example) | Local directory with Google Drive configuration |
| `--drive-client` | [`var/drive/example`](/var/drive/example) | Optional (shared) client configuration (`client.json`, `token.json`), `--drive` contains `config.php` and `files.json` |
| `--monthStart` | `previous month` | First month use for generating report |
| `--monthEnd` | `current month` | Last month for generating report |
| `--currency` | `CZK` | Currency used in XLSX report, supported currencies: CZK, EUR |
| `--personsSettings` | `<PATH_TO_CSV_FILE>` | Person positions and hours used for calculation, take a look at [example](/tests/fixtures/persons.csv) |
| `--filter=Developer` | Position | Filter persons by their position |
| `--cache` | | Cache Costlocker responses (useful when you generate full Company report and reports filtered by position) |
| `--format` | `xls` | You could define different export types, by default only `xls` exporter is provided |

### Profitability

Are your employees profitable? 

![Detailed report](https://cloud.githubusercontent.com/assets/7994022/24850859/f8818d2a-1dd1-11e7-91fa-9af4006e22e7.png)

```bash
# monthly report for January and February 2017 saved in var/reports
bin/console report profitability --monthStart "2017-01" --monthEnd "2017-03" --host $COSTLOCKER_HOST --email "save"
```

![Summary report](https://cloud.githubusercontent.com/assets/7994022/23854171/807855a8-07f0-11e7-98b1-32ec70ca4d02.png)

```bash
# summary report for year 2016 sent to mail
bin/console report profitability:summary --monthStart "2016-01" --monthEnd "2016-12" --host $COSTLOCKER_HOST --personsSettings tests/fixtures/persons.csv --email "john@example.com"
```

## Contributing

Contributions from others would be very much appreciated! Send 
[pull request](https://github.com/costlocker/reports/pulls)/[issue](https://github.com/costlocker/reports/issues). Thanks!

## License

Copyright (c) 2017, 2018 Costlocker SE. MIT Licensed,
see [LICENSE](/LICENSE) for details.
