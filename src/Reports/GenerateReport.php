<?php

namespace Costlocker\Reports;

use Costlocker\Reports\ReportSettings;
use Costlocker\Reports\Config\ParseConfig;
use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\Costlocker\HttpClient;
use Costlocker\Reports\Extract\Costlocker\InmemoryCacheClient;
use Costlocker\Reports\Extract\Costlocker\CompositeClient;
use Costlocker\Reports\Extract\Costlocker\CostlockerUrlGenerator;
use Costlocker\Reports\Transform\Transformer;
use Costlocker\Reports\Load\Loader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;

/** @SuppressWarnings(PHPMD.CouplingBetweenObjects) */
class GenerateReport
{
    private $parseConfig;
    private $logger;

    public function __construct(ParseConfig $c, LoggerInterface $l)
    {
        $this->parseConfig = $c;
        $this->logger = $l;
    }

    public function __invoke(array $config, GenerateReportPresenter $presenter)
    {
        try {
            $this->generate($config, $presenter);
        } catch (\Exception $e) {
            $this->logger->error('Unknown generator error', ['exception' => $e]);
            return $presenter->error('Unknown error', [get_class($e), $e->getMessage()]);
        }
    }

    private function generate(array $config, GenerateReportPresenter $presenter)
    {
        list($isInvalid, $jsonSettings) = $this->parseConfig->__invoke($config);
        if ($isInvalid) {
            return $presenter->error('Invalid JSON configuration', $jsonSettings);
        }

        $presenter->startExtracting($jsonSettings);
        list($settings, $reports) = $this->extract($config, $jsonSettings, $presenter);

        $presenter->startTransforming();
        $file = $this->transform($reports, $settings, $jsonSettings);

        $presenter->startLoading();
        $exports = $this->load($file, $jsonSettings);

        $presenter->finish($exports);
    }

    private function buildClient(array $costlocker)
    {
        $clients = [];
        foreach ($costlocker['tokens'] as $apiKey) {
            $tenantClient = HttpClient::build($costlocker['host'], $apiKey);
            $clients[] = new InmemoryCacheClient($tenantClient);
        }
        $client = new CompositeClient($clients);
        return [
            $client,
            new CostlockerUrlGenerator($client, $costlocker['host'])
        ];
    }

    private function extract(array $config, array $jsonSettings, GenerateReportPresenter $presenter)
    {
        list($client, $urlGenerator) = $this->buildClient($config['costlocker']);

        $settings = new ReportSettings();
        $settings->currency = $jsonSettings['currency'];
        $settings->customConfig = $jsonSettings['customConfig'];
        $settings->costlocker = $urlGenerator;

        $extractor = new $jsonSettings['ETL'][Extractor::class]($client);
        $reports = [];
        foreach ($jsonSettings['dates'] as $date) {
            $settings->date = clone $date;
            $reports[] = $extractor($settings);
            $presenter->finishExtracting($date);
        }
        if (!$reports) { // DATE_RANGE_ALLTIME has no dates
            $reports[] = $extractor($settings);
        }
        $settings->date = null;

        return [$settings, $reports];
    }

    private function transform(array $reports, ReportSettings $settings, $jsonSettings)
    {
        $filenameWithoutExtension = $jsonSettings['export']['filesystem'];
        $transformerDefinition = $jsonSettings['ETL'][Transformer::class];
        $settings->title = $jsonSettings['title'];

        if (is_subclass_of($transformerDefinition, Transform\TransformToXls::class)) {
            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);
            $transformer = new $transformerDefinition($spreadsheet);
            foreach ($reports as $report) {
                $transformer($report, $settings);
            }
            $transformer->after($settings);
            $path = "{$filenameWithoutExtension}.xlsx";
            $isPrecalculated = $settings->customConfig['isXlsPrecalculated'] ?? true;
            if (!$isPrecalculated) {
                foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                    foreach ($worksheet->getColumnDimensions() as $column) {
                        $column->setAutoSize(false);
                    }
                }
            }
            IOFactory::createWriter($spreadsheet, 'Xlsx')
                ->setPreCalculateFormulas($isPrecalculated)
                ->save($path);
        } elseif ($transformerDefinition instanceof Transform\TransformToHtml) {
            $html = $transformerDefinition($reports, $settings);
            $path = "{$filenameWithoutExtension}.html";
            file_put_contents($path, $html);
        }
        return $path;
    }

    private function load($file, array $jsonSettings)
    {
        $results = [
            'filesystem' => realpath($file),
        ];
        foreach ($jsonSettings['ETL'][Loader::class] as $key => $loader) {
            $results[$key] = $loader($file, $jsonSettings['title'], $jsonSettings['export'][$key]);
        }
        return $results;
    }
}
