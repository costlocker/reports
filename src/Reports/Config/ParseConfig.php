<?php

namespace Costlocker\Reports\Config;

use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\ValidationError;
use Costlocker\Reports\ReportsRegistry;

class ParseConfig
{
    private $registry;
    private $exportDir;

    public function __construct(ReportsRegistry $r, $exportDir = '')
    {
        $this->registry = $r;
        $this->exportDir = $exportDir;
    }

    public function __invoke(array $config)
    {
        $errors = $this->validateConfig($config);
        if ($errors) {
            return [true, $errors];
        }
        $dates = $this->getDates(
            $config['config']['dateRange'],
            array_map(
                function ($date) {
                    return \DateTime::createFromFormat('Y-m-d', $date);
                },
                $config['config']['customDates'] ?? []
            )
        );
        $loaders = [
            'email' => $config['export']['email'] ?? null,
            'googleDrive' => isset($config['export']['googleDrive']['folderId'])
                ? ($config['export']['googleDrive'] + [
                    'uniqueReportId' => $this->getUniqueId($config['config']['dateRange'], $dates)
                ])
                : null,
        ];
        $settings = [
            'ETL' => $this->registry->getETL($config['reportType'], $config['config']['format'] ?? null, $loaders),
            'dates' => $config['config']['dateRange'] == Enum::DATE_RANGE_WEEK
                ? [$dates[0]] : $dates,
            'title' => $this->expandTitle($config['config']['title'], reset($dates) ?: null, end($dates) ?: null),
            'currency' => $config['config']['currency'] ?? Enum::CURRENCY_CZK,
            'customConfig' => $this->transformCustomConfig($config['customConfig'] ?? []),
            'export' => [
                'filesystem' => $this->buildFilePath($config['export']['filename']),
            ] + $loaders,
        ];
        return [false, $settings];
    }

    private function getDates($dateRange, array $customDates)
    {
        if ($dateRange == Enum::DATE_RANGE_WEEK) {
            $day = $customDates[0] ?? new \DateTime('saturday last week');
            list($firstDay, $lastDay) = Dates::getWeek($day);
            return [$firstDay, $lastDay];
        } elseif ($dateRange == Enum::DATE_RANGE_YEAR) {
            $lastMonth = new \DateTime('first day of last month');
            return $this->getDates(
                Enum::DATE_RANGE_MONTHS,
                [
                    \DateTime::createFromFormat('Y-m-d', $lastMonth->format('Y-01-01')),
                    \DateTime::createFromFormat('Y-m-d', $lastMonth->format('Y-m-01'))
                ]
            );
        } elseif ($dateRange == Enum::DATE_RANGE_MONTHS) {
            if (count($customDates) != 2) {
                throw new \RuntimeException(
                    Enum::DATE_RANGE_MONTHS . ' must define 2 dates in config.customDates'
                );
            }
            $lastMonth = new \DateTime('last month');
            return Dates::getMonthsBetween($customDates[0], $customDates[1]);
        }
        return [];
    }

    private function getUniqueId($dateRange, array $dates)
    {
        $const = 'strval';
        if ($dateRange == Enum::DATE_RANGE_WEEK) {
            return "{$const(Enum::DATE_RANGE_WEEK)}-{$const($dates[0]->format('Y-m-d'))}";
        } elseif ($dateRange == Enum::DATE_RANGE_YEAR) {
            // must use same year when last month is February, March etc.
            return "{$const(Enum::DATE_RANGE_YEAR)}-{$const($dates[0]->format('Y'))}";
        } elseif ($dateRange == Enum::DATE_RANGE_MONTHS) {
            // dates are good enough, customConfig (with filter) can't be overriden, but new report must be created
            return json_encode(array_map(
                function (\DateTime $d) {
                    return $d->format('Y-m-d');
                },
                $dates
            ));
        }
        return 'alltime';
    }

    private function expandTitle($staticTitle, \DateTime $firstDate = null, \DateTime $lastDate = null)
    {
        if (!$firstDate) {
            return $staticTitle;
        }
        $placeholders = [
            '{YEAR}' => $firstDate->format('Y'),
            '{WEEK}' => $firstDate->format('W'),
        ];
        $dynamicTitle = str_replace(array_keys($placeholders), array_values($placeholders), $staticTitle);

        $dates = [
            '{FIRST(' => $firstDate,
            '{LAST(' => $lastDate,
        ];
        $formatterEnd = ')}';
        foreach ($dates as $formatterStart => $date) {
            $position = mb_strpos($dynamicTitle, $formatterStart, 0, 'UTF-8');
            while (is_int($position)) {
                $start = $position + strlen($formatterStart);
                $end = mb_strpos($dynamicTitle, $formatterEnd, $start, 'UTF-8');
                $format = mb_substr($dynamicTitle, $start, $end - $start, 'UTF-8');
                
                $dynamicTitle = str_replace(
                    "{$formatterStart}{$format}{$formatterEnd}",
                    $date->format($format),
                    $dynamicTitle
                );
                $position = strpos($dynamicTitle, $formatterStart);
            }
        }

        return $dynamicTitle;
    }

    private function transformCustomConfig(array $rawConfig)
    {
        $config = [];
        foreach ($rawConfig as $data) {
            if ($data['format'] == 'json') {
                $json = is_string($data['value']) ? $data['value'] : json_encode($data['value']);
                $config[$data['key']] = json_decode($json, true);
            } else {
                $config[$data['key']] = $data['value'];
            }
        }
        return $config;
    }

    private function buildFilePath($filename)
    {
        $normalizedName = str_replace([' ', '(', ')', '/'], '-', $filename);
        $prettyName = str_replace('--', '-', trim($normalizedName, '-'));
        return "{$this->exportDir}/{$prettyName}";
    }

    private function validateConfig(array $rawConfig)
    {
        $schema = str_replace(
            '"{DYNAMIC_REPORTS_ENUM}"',
            '["' . implode('", "', $this->registry->getAvailableTypes()) . '"]',
            file_get_contents(__DIR__ . '/schema.json')
        );

        $validator = new Validator();
        $result = $validator->schemaValidation(
            json_decode(json_encode($rawConfig), false),
            Schema::fromJsonString($schema),
            -1 // load all errors...
        );
        if ($result->isValid()) {
            return [];
        } else {
            return array_map(
                function (ValidationError $e) {
                    return implode('.', $e->dataPointer()) . ': ' . json_encode($e->keywordArgs(), JSON_PRETTY_PRINT);
                },
                $result->getErrors()
            );
        }
    }
}
