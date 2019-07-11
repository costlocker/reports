
# Costlocker reports

[![CircleCI](https://circleci.com/gh/costlocker/reports/tree/master.svg?style=svg&circle-token=6a72d2fe098452b9b7113b830c035045e58e65d7)](https://circleci.com/gh/costlocker/reports/tree/master)

Generate XLSX/HTML reports from [Costlocker API](http://docs.costlocker.apiary.io/).
UI version is available at https://reports.costlocker.com ([source code](https://gitlab.com/costlocker/integrations/tree/master/reports)).

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

## Reports

### Usage

Previous versions used [CLI options](https://github.com/costlocker/reports/tree/v2.0.0#options) for generating reports.
Since v3 report is completely configued in JSON file ([JSON schema](/src/Reports/Config/schema.json)).

```bash
# 0) Info about reports
bin/console report --help

# 1) Prepare config
## 1a) Create default config + manual edit
bin/console report Projects.Overview
nano config-Projects.Overview.json
## 1b) You can pass config in options
bin/console report Projects.Overviews --host "https://new.costlocker.com|<YOUR_API_KEY>" --email "john@example.com"

# 2) Generate report
bin/console report --config config-Projects.Overview.json
ls -lAh var/exports
```

### Available reports

| Title | Report type | Preview | 
| ----- | ----------- | ------- |
| [Projects Billing & Tags](https://assets.costlocker.com/reports/Projects.BillingAndTags.png) | [Projects.BillingAndTags](/src/CustomReports/Projects/BillingAndTagsExtractor.php) |
| [Projects Overview](https://assets.costlocker.com/reports/Projects.Overview.png) | [Projects.Overview](/src/CustomReports/Projects/ProjectsOverviewExtractor.php) |
| [Company Overview](https://assets.costlocker.com/reports/Company.Overview.png) | [Company.Overview](/src/CustomReports/Company/CompanyOverviewExtractor.php) |
| [Grouped timesheet for recurring project](https://assets.costlocker.com/reports/Timesheet.RecurringProject.png) | [Timesheet.RecurringProject](/src/CustomReports/Timesheet/GroupedRecurringTimesheetExtractor.php) |
| [Yearly tracked hours](https://assets.costlocker.com/reports/Timesheet.TrackedHours.png) | [Timesheet.TrackedHours](/src/CustomReports/Timesheet/TrackedHoursExtractor.php) |
| [Weekly timesheet for groups](https://assets.costlocker.com/reports/Timesheet.Week.png) | [Timesheet.Week](/src/CustomReports/Timesheet/WeeklyTimesheetExtractor.php) |

![Available reports from https://reports.costlocker.com/reports/available](https://trello-attachments.s3.amazonaws.com/5cfe127e9fdd6084611ce4f4/1150x572/7258ed0a02e8fdc17ca95f0e440f06bc/available-reports-2019-07-11.png)

### New report

Reports are using [ETL terminology](https://en.wikipedia.org/wiki/Extract,_transform,_load):

* **Extract** data from Costlocker API
* **Transform** data to XLS or HTML
* **Load** data to your system _(filesystem, Google Drive, e-mail)_

New report must implement [Extractor](src/Reports/Extract/Extractor.php) and [Transformer](src/Reports/Transform/Transformer.php).
Take a look at [available reports](#available-reports). For example check [ProjectsOverviewExtractor](src/CustomReports/Projects/ProjectsOverviewExtractor.php) and [ProjectsOverviewToXls](src/CustomReports/Projects/ProjectsOverviewToXls.php). Let us know if it's still unclear!

## Contributing

Contributions from others would be very much appreciated! Send 
[pull request](https://github.com/costlocker/reports/pulls)/[issue](https://github.com/costlocker/reports/issues). Thanks!

## License

Copyright (c) 2017, 2018 Costlocker SE. MIT Licensed,
see [LICENSE](/LICENSE) for details.
