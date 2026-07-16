<?php

namespace App\Console\Commands;

use App\Model\Pacs;
use App\Model\Yj;
use App\Model\SecondaryOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Pacssync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:pacssync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $page = 1;
        $limit = 100;
        while (true) {
			echo $page ."\n";
            $offset = ($page - 1) * $limit;
            $keys = Pacs::query()
                ->offset($offset)
                ->limit($limit)
                ->orderBy('SQDH', 'desc')
				->pluck('SQDH');
            if ($keys){
				$new_keys = array();
                $keys = $keys->toArray();
				foreach($keys as $key=>$val) {
					$new_keys[$val] = $val;
				}
            }else{
                exit;
            }
            if(empty($new_keys)) {
                exit;
            }
            $keys1 = implode(',',$new_keys);
			echo $keys1 . "\n";

			if(is_array($new_keys)) {
				$list = Yj::query()
					->select('ZYH','SQDH')
					->wherein('SQDH', $new_keys)
					->get();
				if ($list){
					$list = $list->toArray();
				}else{
					break;
				}


				foreach($list as $key=>$val) {
					Pacs::query()->where('SQDH', $val['SQDH'])->update(['ZYH' => $val['ZYH']]);	
				}
			}

            
            $page++;
        }
        return 0;
    }
}
