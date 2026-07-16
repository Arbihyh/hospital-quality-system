<?php

namespace App\Console\Commands;

use App\Services\OperationRelationsService;
use Illuminate\Console\Command;

class OperationRelationsAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:operation-follow-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '添加手术关联关系';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('default_socket_timeout', 24 * 60 * 60);
        $filePath = dirname(public_path(''))."/app/Console/Commands/fee.csv";
        //$filePath = 'fee.csv';
        $data = $this->getCsvData($filePath);
        foreach ($data as $index=>$row){
            if(!is_array($row)){
                break;
            }
            //print_r($row);
            $price = [];
            $price[] = $row[4] ?? '';
            $price[] = $row[5] ?? '';
            $price[] = $row[6] ?? '';
            $price = array_filter($price);
            $price = implode(',', $price);
            echo $index . PHP_EOL;
            //print_r($row);
            OperationRelationsService::addOperationRelations($row[1], $row[2], $row[0], $price, $row[3]);
        }
    }

    function getCsvData($filePath){
        //return [];
        $handle = fopen( $filePath, "rb" );
        $data = [];
        while (!feof($handle)) {
            $data[] = fgetcsv($handle);
        }
        fclose($handle);
        return $data;
    }
}
