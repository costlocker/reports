
# Changelog

## v1.1.0 (_WIP_)

* Profitability report
    * `--filter=Developer` - allow filtering persons by their position
    * add `People` tab with persons that tracked at least in one month
    * remove console exporter (by default `xls` format is used, but you can define your own exporter and enable it via `--format json`)
* Allow caching costlocker responses with `--cache` option
    * `--cache` - in verbose mode (`-vvv`) it also prints if costlocker is called or cached response is used
    * `bin/clear-cache` - clear responses cache
* Export
    * Mailer refactoring - move to `Costlocker\Reports\Export`, catch `Swift_Mailer` exceptions, don't unlink sent file

## v1.0.1 (_2017-04-10_)

* Add tags to summary report

## v1.0.0 (_2017-03-22_)

* Profitability reports - detailed, summary
* Reporters - XLSX export, CLI output
