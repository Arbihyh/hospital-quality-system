<?php

namespace App\Exports;

use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ExportData extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithColumnFormatting
{
    private $title;
    private $listData;
    private $textColumns;

    public function __construct($title, $listData, array $textColumns = [])
    {
        $this->title = $title;
        $this->listData = $listData;
        $this->textColumns = array_map('strtoupper', $textColumns);
    }

    public function array(): array
    {
        $data = [$this->title, $this->listData];
        return $data;
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
