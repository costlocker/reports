# Costlocker reports

## Usage

```bash
# crontab
0 8 1 * * bin/console report profitability --monthStart "previous month" --monthEnd "previous month" --host "https://new.costlocker.com|apiKey" --email "kamil@costlocker.com" --currency EUR 2>&1 >> report.log
0 8 1 1 * bin/console report profitability --monthStart "now - 12 months" --monthEnd "now - 1 month" --host "https://new.costlocker.com|apiKey" --email "kamil@costlocker.com" --personsSettings var/2fresh/persons.csv 2>&1 >> report.log
```

## Inspiro

API don't return profit, so table must be filled from [real DB](https://gitlab.costlocker.io/costlocker/backend/blob/develop/app/model/Report/ReportMain.php#L535)

1. Generate report

```bash
bin/console report inspiro --monthStart "2016-12" --monthEnd "2017-01" --host "https://app.costlocker.com|superSecretToken" --email save
```

2. Paste unformatted results

```sql
SELECT
    client.name,
    SUM(CASE WHEN state = 1 THEN 1 ELSE 0 END) AS count_finished,
    '=' || SUM(CASE WHEN state = 1 THEN revenue_act_sum + revenue_exp_sum - disc ELSE 0 END) as revenue_finished,
    '=C' || (row_number() OVER () + 2) || '-' || SUM(CASE WHEN state = 1 THEN COALESCE(costs_exp_sum, 0) ELSE 0 END) as revenues_minus_expenses_finished,
    '=' || SUM(CASE WHEN state = 1 THEN COALESCE(revenue_act_sum + revenue_exp_sum - disc - COALESCE(costs_act_tracked_sum, 0), 0) - costs_exp_sum ELSE 0 END) as profit_finished,
    SUM(CASE WHEN state = 0 THEN 1 ELSE 0 END) AS count_running,
    '=' || SUM(CASE WHEN state = 0 THEN revenue_act_sum + revenue_exp_sum - disc ELSE 0 END) as revenue_running,
    '=G' || (row_number() OVER () + 2) || '-' || SUM(CASE WHEN state = 0 THEN COALESCE(costs_exp_sum, 0) ELSE 0 END) as revenues_minus_expenses_running,
    '=' || SUM(CASE WHEN state = 0 THEN COALESCE(revenue_act_sum + revenue_exp_sum - disc - COALESCE(costs_act_tracked_sum, 0), 0) - costs_exp_sum ELSE 0 END) as profit_running
FROM project
JOIN client ON client_id = client.id
WHERE project.type = 0 AND project.tenant_id = 17
    AND (
        (project.state = 0 AND project.da_start >= '2016-01-01') OR 
        (project.state = 1 AND project.da_end >= '2016-01-01'))
    AND (
        (project.state = 0 AND project.da_start <= '2016-12-31') OR 
        (project.state = 1 AND project.da_end <= '2016-12-31')
    ) 
GROUP BY client.name
ORDER BY client.name
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
