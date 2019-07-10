<?php

namespace Costlocker\Reports;

use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\ExtractorBuilder;
use Costlocker\Reports\Transform\Transformer;
use Costlocker\Reports\Load\Loader;

class ReportsRegistry
{
    public static function autoload(array $loaders = [])
    {
        $extractors = array_keys(array_filter(
            include __DIR__ . '/../../vendor/composer/autoload_classmap.php',
            function ($class) {
                return substr($class, -strlen('Extractor')) === 'Extractor' // limit number of autoloaded classes
                    && is_subclass_of($class, Extractor::class);
            },
            ARRAY_FILTER_USE_KEY
        ));
        return new ReportsRegistry($extractors, $loaders);
    }

    private $reports = [];
    private $loaders;

    public function __construct(array $extractors, array $loaders)
    {
        foreach ($extractors as $e) {
            if (!is_subclass_of($e, Extractor::class)) {
                throw new \InvalidArgumentException("[{$e}] must implement [Extractor]");
            }
            $this->addExtractor($e, $e::getConfig());
        }
        $this->loaders = $loaders;
    }

    private function addExtractor($class, ExtractorBuilder $builder)
    {
        $config = [Extractor::class => $class] + $builder->build();
        if (array_key_exists($config['id'], $this->reports)) {
            throw new \InvalidArgumentException("Report [{$config['id']}] is already defined!");
        }
        $this->reports[$config['id']] = $config;
    }

    public function getETL($reportType, $customFormat, array $loaderConfig)
    {
        $format = $customFormat ?: $this->getDefaultFormat($reportType);
        return [
            Extractor::class => $this->reports[$reportType][Extractor::class],
            Transformer::class => $this->reports[$reportType]['formats'][$format],
            Loader::class => $this->getActiveLoaders($loaderConfig),
        ];
    }

    private function getActiveLoaders(array $activeLoaders)
    {
        $loaders = [];
        foreach ($activeLoaders as $key => $config) {
            if (!$config || !array_key_exists($key, $this->loaders) || !($this->loaders[$key] instanceof Loader)) {
                continue;
            }
            $loaders[$key] = $this->loaders[$key];
        }
        return $loaders;
    }

    public function getDefaultFormat($reportType)
    {
        return array_keys($this->reports[$reportType]['formats'])[0];
    }

    public function getPublicTitle($reportType)
    {
        return $this->getDefaultReport($reportType)['config']['title'];
    }

    public function getDefaultReport($reportType)
    {
        return $this->reports[$reportType]['defaultReport'];
    }

    public function getAvailableTypes()
    {
        return array_keys($this->reports);
    }
}
