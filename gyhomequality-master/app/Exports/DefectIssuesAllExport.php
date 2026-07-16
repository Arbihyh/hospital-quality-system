<?php

namespace App\Exports;

use Generator;
use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DefectIssuesAllExport extends DefaultValueBinder implements FromGenerator, WithHeadings, WithCustomValueBinder, WithColumnFormatting
{
    /**
     * @var array
     */
    private $title;

    /**
     * @var \Closure
     */
    private $rowGenerator;

    /**
     * @var array
     */
    private $textColumns;

    /**
     * @param array    $title
     * @param \Closure $rowGenerator 返回 Generator 的闭包
     */
    public function __construct(array $title, \Closure $rowGenerator, array $textColumns = [])
    {
        $this->title = $title;
        $this->rowGenerator = $rowGenerator;
        $this->textColumns = array_map('strtoupper', $textColumns);
    }

    public function headings(): array
    {
        return $this->title;
    }

    public function generator(): Generator
    {
        $generator = ($this->rowGenerator)();

        foreach ($generator as $row) {
            yield $row;
        }
    }

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getRow() > 1 && in_array($cell->getColumn(), $this->textColumns, true)) {
            $cell->setValueExplicit((string)$value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach ($this->textColumns as $column) {
            $formats[$column] = NumberFormat::FORMAT_TEXT;
        }

        return $formats;
    }
}
