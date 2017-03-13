
# Costlocker reports

[![CircleCI](https://circleci.com/gh/costlocker/reports/tree/master.svg?style=svg&circle-token=6a72d2fe098452b9b7113b830c035045e58e65d7)](https://circleci.com/gh/costlocker/reports/tree/master)

Generate XLSX reports from [Costlocker API](http://docs.costlocker.apiary.io/).

## Requirements

- PHP >= 7.0

## Install

```bash
git clone https://github.com/costlocker/reports.git
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

All examples are using environment variable with url and api key.

```
COSTLOCKER_HOST="https://app.costlocker.com|<YOUR_API_KEY>"
```

##### Options

| CLI option | Value | Description |
| ---------- | ------------- | ----------- |
| `--host` | `https://app.costlocker.com|<YOUR_API_KEY>` | Costlocker API url and API key of your organization |
| `--email` | | Show simplified console report |
| `--email` | `save` | Report is saved in `var/reports` if e-mail is _invalid_ |
| `--email` | `john@example.com` | Send report to the email |
| `--monthStart` | `previous month` | First month use for generating report |
| `--monthEnd` | `current month` | Last month for generating report |

### Profitability

Are your employees profitable? 

![screen shot 2017-03-13 at 13 22 50](https://cloud.githubusercontent.com/assets/7994022/23854122/36654c14-07f0-11e7-9f1e-be320344f5e0.png)

```bash
# monthly report for January and February 2017 saved in var/reports
bin/console report profitability --monthStart "2017-01" --monthEnd "2017-03" --host $COSTLOCKER_HOST --email "save"
```

![screen shot 2017-03-13 at 13 25 08](https://cloud.githubusercontent.com/assets/7994022/23854171/807855a8-07f0-11e7-98b1-32ec70ca4d02.png)

```bash
# summary report for year 2016 sent to mail
bin/console report profitability:summary --monthStart "2016-01" --monthEnd "2016-12" --host $COSTLOCKER_HOST --personsSettings tests/fixtures/persons.csv --email "john@example.com"
```

##### Options

| CLI option | Value | Description |
| ---------- | ------- | ----------- |
| `--currency` | `CZK` | Currency used in XLSX report, supported currencies: CZK, EUR |
| `--personsSettings` | `<PATH_TO_CSV_FILE>` | Person positions and hours used for calculaction, take a look at [example](/tests/fixtures/persons.csv) |

### Clients revenues

Find clients revenues for finished and running projects

![screen shot 2017-03-13 at 13 21 58](https://cloud.githubusercontent.com/assets/7994022/23854087/0ebe01e2-07f0-11e7-9bd2-be12c9ee9ec8.png)

```bash
# yearly report grouped by clients printed to console
bin/console report clients --monthStart "2016-12" --monthEnd "2017-01" --host $COSTLOCKER_HOST
```
##### Why is column _Profit_ empty?

Profit isn't available in [API](http://docs.costlocker.apiary.io/#reference/0/projects).
Write me at `development@costlocker.com` if you need profit in clients report.

## Contributing

Contributions from others would be very much appreciated! Send 
[pull request](https://github.com/costlocker/reports/pulls)/[issue](https://github.com/costlocker/reports/issues). Thanks!

## License

Copyright (c) 2017 Costlocker SE. MIT Licensed,
see [LICENSE](/LICENSE) for details.
