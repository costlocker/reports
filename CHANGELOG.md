
# Changelog

## v3.0.0 (_2019-08-19_)

Big rewrite because of https://reports.costlocker.com ([source code](https://gitlab.com/costlocker/integrations/tree/master/reports)).
There are a lot of BC. We might not mention them all. **Let us know if have troubles with migration from v2!**

### Available reports

* Profitability reports is removed in favor of [Business Reports](https://new.costlocker.com/dashboard/workload?peopleInactive=false)
* We've added [6 projects/company/timesheet reports](/README.md#available-reports) that we've generated for our customers

### Config

| Breaking change | Before | After |
| --------------- | ------ | ----- |
| Generating report | [Passing CLI options](https://github.com/costlocker/reports/tree/v2.0.0#available-reports) | [bin/console report --config report.json](/README.md#usage) |
| E-mail configuration | [/app/config.php](https://github.com/costlocker/reports/blob/v2.0.0/app/config.default.php) | [.env](/README.md#e-mail) |
| Google Drive configuration | [/var/drive](https://github.com/costlocker/reports/tree/v2.0.0/var/drive) | Client configuration in [/var/googleDrive](/README.md#google-drive), files.json and config.php are now part of [JSON config](/README.md#usage) |

### Code

New code uses [ETL terminology](/README.md#new-report). Previously you could do whatever you want in `Provider`/`ToXls`. Now it's restricted by [Extractor](src/Reports/Extract/Extractor.php), [Transformer](src/Reports/Transform/Transformer.php) and [JSON schema](/src/Reports/Config/schema.json).

| Breaking change | Before | After |
| --------------- | ------ | ----- |
| Generate url | `ReportSettings->generateProjectUrl->__invoke($id)` | `ReportSettings->costlocker->projectUrl($id)` |
| Advanced CLI options | `ReportSettings->filter`<br />`ReportSettings->personsSettings`<br />`ReportSettings->exportSettings`  | Any custom config from [json file](/src/Reports/Config/schema.json#L64) is available in `ReportSettings->customConfig['...']` |
| Custom title | `ReportSettings->yearStart` | [Title is configured in json `config.title`](https://github.com/costlocker/reports/blob/v3/tests/Reports/ParseConfigTest.php#L76) |

_Check how we migrated Timesheet reports: [Timesheet.RecurringProject](https://gitlab.com/costlocker/integrations/commit/cd27552), [Timesheet.Week](https://gitlab.com/costlocker/integrations/commit/bf1c5f8), [Timesheet.TrackedHours](https://gitlab.com/costlocker/integrations/commit/47a2e95)_

## v2.0.0 (_2018-11-22_)

* **Profitability report loads [billable hours](https://costlocker.docs.apiary.io/#introduction/changelog/september-2018) from API!**
    * BC - estimates are no longer shared between person activities, [new budgets](https://blog.costlocker.com/try-out-new-ways-to-budget-projects-in-costlocker-e926bfaa7bd6) are supported _(client rate is dynamic)_
    * _Costlocker_ - report can be replaced by [new business reports](https://blog.costlocker.com/new-business-perspectives-for-your-company-60cfb1287118), billable/non-billable hours are visible in [timesheet](https://blog.costlocker.cz/placené-vs-neplacené-hodiny-v-timesheetu-2e71a6b15c67)

## v1.2.0 (_2018-01-19_)

* person can have different settings per month in [`--personsSettings`](/tests/fixtures/persons.csv#L4)
* load data from multiple companies - `--host "https://new.costlocker.com|firstCompany|secondCompany"`
* define custom reports in [app/config.php](/app/config.default.php#L6)
* smaller improvements and bugfixes in Profitability report
* add [Dockerfile](/.docker/Dockerfile)

## v1.1.1 (_2017-08-31_)

* `--drive-client` - allow separating Google Drive client configuration (`client.json`, `token.json`)

## v1.1.0 (_2017-08-14_)

* **Export/Import**
    * **_Google Drive_** - upload report to Drive folder, load `--personsSettings` from Drive
    * Mailer refactoring - move to `Costlocker\Reports\Export`, catch `Swift_Mailer` exceptions, don't unlink sent file
    * Pretty XLS filename (without `(`, `)` and multiple dashes `--`)
* **Profitability report**
    * `--filter=Developer` - allow filtering persons by their position
    * add `People` tab with persons that tracked at least in one month
    * remove console exporter (by default `xls` format is used, but you can define your own exporter and enable it via `--format json`)
    * generated file does not contain end date, only start date
    * add link to Costlocker project
* Allow caching costlocker responses with `--cache` option
    * `--cache` - in verbose mode (`-vvv`) it also prints if costlocker is called or cached response is used
    * `bin/clear-cache` - clear responses cache
* _Bugfixes_
    * Fix exporting XLS with more than 26 columns

## v1.0.1 (_2017-04-10_)

* Add tags to summary report

## v1.0.0 (_2017-03-22_)

* Profitability reports - detailed, summary
* Reporters - XLSX export, CLI output
