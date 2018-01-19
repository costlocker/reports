
# Changelog

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
