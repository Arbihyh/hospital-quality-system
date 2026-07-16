<?php

namespace App\Console\Commands\Lanling;

use App\Model\EMR_BL_BL01;
use App\Services\MysqlDataSync\lanling\DigitalMedicalInformationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDigitalMedicalInformation extends Command
{
    /**
     * The name and signature of the console command.
     * 同步数字医信数据
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:lanling_sync_data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步数字医信数据';

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
     *
     */
    public function handle()
    {
        //同步输血记录
        DigitalMedicalInformationService::getInstance()->getTransfuseRecords();
    }

}
