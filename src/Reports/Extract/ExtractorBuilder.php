<?php

namespace Costlocker\Reports\Extract;

use Costlocker\Reports\Config\Enum;
use Costlocker\Reports\Transform\TransformToXls;
use Costlocker\Reports\Transform\TransformToHtml;

class ExtractorBuilder
{
    public static function buildFromJsonFile($file)
    {
        $json = json_decode(file_get_contents($file), true);
        return (new ExtractorBuilder($json['id']))
            ->publicPreview($json['preview']['image'])
            ->publicTitle($json['config']['title'])
            ->defaultDateRange($json['config']['dateRange'])
            ->defaultCustomConfig($json['customConfig']);
    }

    private $config;

    public function __construct($id)
    {
        $this->config = [
            'id' => $id,
            'formats' => [],
            'defaultReport' => [
                'config' => [
                    'title' => null,
                    'dateRange' => Enum::DATE_RANGE_ALLTIME,
                ],
                'customConfig' => [],
                'preview' => [
                    'image' => null,
                ],
            ],
        ];
    }

    public function publicTitle($title)
    {
        $this->config['defaultReport']['config']['title'] = $title;
        return $this;
    }

    public function publicPreview($image)
    {
        $this->config['defaultReport']['preview']['image'] = $image;
        return $this;
    }

    public function defaultDateRange($dateRange)
    {
        $this->config['defaultReport']['config']['dateRange'] = $dateRange;
        return $this;
    }

    public function defaultCustomConfig(array $customConfig)
    {
        $this->config['defaultReport']['customConfig'] = $customConfig;
        return $this;
    }

    public function transformToXls($class)
    {
        $this->checkClass($class, TransformToXls::class);
        $this->config['formats'][Enum::FORMAT_XLS] = $class;
        return $this;
    }

    public function transformToHtml($twigTemplate)
    {
        $this->config['formats'][Enum::FORMAT_HTML] = new TransformToHtml($twigTemplate);
        return $this;
    }

    private function checkClass($class, $interface)
    {
        if (!is_subclass_of($class, $interface)) {
            throw new \InvalidArgumentException("[{$class}] must implement/extend [{$interface}]");
        }
    }

    public function build()
    {
        return $this->config;
    }
}
