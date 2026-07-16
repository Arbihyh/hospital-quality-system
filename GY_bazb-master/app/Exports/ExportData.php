<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ExportData implements FromArray
{
    private $title;
    private $listData;

    public function __construct($title, $listData)
    {
        $this->title = $title;
        $this->listData = $listData;
    }

    public function array(): array
    {
        $data = [$this->title, $this->listData];
        return $data;
    }
}
