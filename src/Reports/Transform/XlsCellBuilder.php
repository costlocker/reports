<?php

namespace Costlocker\Reports\Transform;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class XlsCellBuilder
{
    private $value;
    private $format = NumberFormat::FORMAT_GENERAL;
    private $url;
    private $conditionals = [];
    private $defaultStyle = [];
    private $customStyle = [];

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function url($url)
    {
        $this->url = $url;
        return $this;
    }

    public function format($format)
    {
        $this->format = $format;
        return $this;
    }

    public function evaluateNumber()
    {
        return $this->conditionals([
            $this->compareToZero(Conditional::OPERATOR_LESSTHAN, 'ff0000'),
            $this->compareToZero(Conditional::OPERATOR_GREATERTHAN, '0000ff'),
        ]);
    }

    private function compareToZero($operator, $color)
    {
        $conditional = new Conditional();
        $conditional
            ->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType($operator)
            ->addCondition('0')
            ->getStyle()->applyFromArray([
                'font' => [
                    'color' => [
                        'rgb' => $color
                    ]
                ],
            ]);
        return $conditional;
    }

    public function conditionals($conditionals)
    {
        $this->conditionals = $conditionals;
        return $this;
    }

    public function defaultStyle($backgroundColor = null, $alignment = null)
    {
        $styles = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => [
                        'rgb' => '000000'
                    ],
                ],
            ],
        ];
        if ($backgroundColor) {
            $styles += [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => $backgroundColor != 'transparent' ? $backgroundColor : null,
                    ],
                ],
                'alignment' => [
                    'horizontal' => $alignment,
                ],
            ];
        }
        $this->defaultStyle = array_replace_recursive($styles, $this->defaultStyle);
        return $this;
    }

    public function highlight()
    {
        return $this->customStyle([
            'font' => [
                'bold' => true,
                'color' => [
                    'rgb' => 'ff0000'
                ]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'ffff00'
                ],
            ],
        ]);
    }

    public function customStyle(array $style)
    {
        $this->customStyle = $style;
        return $this;
    }

    public function build(Cell $cell)
    {
        $cell->setValue($this->value);
        $cell->getStyle()->getNumberFormat()->setFormatCode($this->format);
        if ($this->url) {
            $cell->getHyperlink()->setUrl($this->url);
        }
        if ($this->conditionals) {
            $cell->getStyle()->setConditionalStyles($this->conditionals);
        }
        $style = array_replace_recursive($this->defaultStyle, $this->customStyle);
        if ($style) {
            $cell->getStyle()->applyFromArray($style);
        }
    }
}
