# Costlocker reports

```bash
# crontab
0 8 1 * * bin/console report --date "previous month" --host "https://new.costlocker.com|apiKey" --email "kamil@costlocker.com" 2>&1 >> report.log
```