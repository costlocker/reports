<?php

namespace Costlocker\Reports\Transform;

use Costlocker\Reports\ReportSettings;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

class TransformToHtml implements Transformer
{
    private $twigTemplate;

    public function __construct($twigTemplate)
    {
        $this->twigTemplate = realpath($twigTemplate);
        if (!file_exists($this->twigTemplate)) {
            throw new \InvalidArgumentException("[{$twigTemplate}] does not exist");
        }
    }

    public function __invoke(array $reports, ReportSettings $settings)
    {
        $loader = new FilesystemLoader(dirname($this->twigTemplate));
        $twig = new Environment($loader);
        return $twig->render(
            basename($this->twigTemplate),
            [
                'reports' => $reports,
                'settings' => $settings,
            ]
        );
    }
}
