
# Costlocker reports

[![CircleCI](https://circleci.com/gh/costlocker/reports/tree/master.svg?style=svg&circle-token=6a72d2fe098452b9b7113b830c035045e58e65d7)](https://circleci.com/gh/costlocker/reports/tree/master)

Generate XLSX reports from [Costlocker API](http://docs.costlocker.apiary.io/).

## Requirements

- PHP >= 7.0
- composer
- `curl` extension

## Installation

```bash
git clone https://github.com/costlocker/reports.git
cd reports
composer install
bin/console report --help
```

##### Custom mailer (gmail, ...)

By default [`mail()`](http://swiftmailer.org/docs/sending.html#using-the-mail-transport)
is used for sending e-mails. But you can define custom 
[`Swift_MailTransport`](http://swiftmailer.org/docs/sending.html#) in `app/config.php`.

```bash
cp app/config.default.php app/config.php
nano app/config.php
```

## Available reports

All examples are using environment variable with url and 
[api key](http://docs.costlocker.apiary.io/#reference/0/authentication/personal-access-token).

```
COSTLOCKER_HOST="https://app.costlocker.com|<YOUR_API_KEY>"
```

##### Options

| CLI option | Value | Description |
| ---------- | ------------- | ----------- |
| `--host` | `https://app.costlocker.com\|<YOUR_API_KEY>` | Costlocker API url and API key of your organization |
| `--email` | | Show simplified console report |
| `--email` | `save` | Report is saved in `var/reports` if e-mail is _invalid_ |
| `--email` | `john@example.com` | Send report to the email provided |
| `--monthStart` | `previous month` | First month use for generating report |
| `--monthEnd` | `current month` | Last month for generating report |
| `--currency` | `CZK` | Currency used in XLSX report, supported currencies: CZK, EUR |
| `--personsSettings` | `<PATH_TO_CSV_FILE>` | Person positions and hours used for calculation, take a look at [example](/tests/fixtures/persons.csv) |

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

Copyright (c) 2017 Costlocker SE. MIT Licensed,
see [LICENSE](/LICENSE) for details.
