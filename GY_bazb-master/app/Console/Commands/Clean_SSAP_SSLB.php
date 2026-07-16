<?php

namespace App\Console\Commands;

use App\Model\ICD9;
use Illuminate\Console\Command;

class Clean_SSAP_SSLB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'clean_ssap_sslb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗手术安排里面的手术类别';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        echo $this->description." start: ".date('Y-m-d H:i:s')."\n";
        $page = 1;
        $pageSize = 1000;
        while (true){
            $data = \App\Model\SM_SSAP::query()->paginate($pageSize,["*"],'page',$page)->toArray();
            $data = $data['data'];
            if (empty($data)){
                break;
            }
            foreach ($data as $item){
                //$SSDM = \App\Model\GY_SSDM::query()->where('SSNM','=',$item['SSNM'])->value('SSDM');
                //if (!empty($SSDM)){
                    $sslb = ICD9::query()->where('SSCZBM', 'like', '%'.$item['ICD9_SSCZBM'].'%')->first(['SSLB','SSCZBM','SSCZMC']);
                    if (!empty($sslb)){
                        $sslb = $sslb->toArray();
                        echo $item['id'] ." - ".date('Y-m-d H:i:s')."\n";
                        \App\Model\SM_SSAP::query()->where('id', '=', $item['id'])->update(['ICD9_SSLB'=>$sslb['SSLB'],'ICD9_SSCZBM'=>$sslb['SSCZBM'],'ICD9_SSCZMC'=>$sslb['SSCZMC']]);
                    }
                //}
            }
            $page++;
        }
        echo $this->description." end: ".date('Y-m-d H:i:s')."\n";
        exit();
    }
}