<?php

namespace App\Console\Commands;

use App\Model\YK_TYPK;
use Illuminate\Console\Command;
use App\Model\Yzb;
use App\Model\Setting;
use Illuminate\Support\Facades\Log;

class CleanYzbKjywKjjb extends Command
{
    protected $signature = 'command:YzbKjywKjjb {zyh?}';

    protected $description = '清洗医嘱本抗菌药物抗菌药的抗菌级别字段';

    public function handle()
    {
        $zyh = $this->argument('zyh');
        $zyh = $zyh ? $zyh : '';
        $lastId = Setting::query()->where('id', '=', 127)->value('content');
        $lastId = $lastId ?: 0;
        $this->getKjywYzbAndUpdateKjjb($zyh, $lastId);
        exit("执行完毕" . PHP_EOL);
    }

    /**
     *
     * 获取包含抗菌药物的医嘱数据
     * @param mixed $zyh
     * @param mixed $lastId
     * @return void
     */
    public function getKjywYzbAndUpdateKjjb($zyh = '', $lastId = 0)
    {
        $query = Yzb::query()->where('is_has_kjyw', 1);
        if ($zyh) {
            $query = $query->where('ZYH', $zyh);
        } else {
            $query = $query->where('id', '>', $lastId);
        }
        $data = $query->get(['id', 'YZMC', 'kjyw_name'])->toArray();
        if (!empty($data)) {
            $lastId = $this->updateKjjb($data);
            Setting::query()->where('id', '=', 127)->update(['content' => $lastId]);
        }
    }

    /**
     *
     * 更新抗菌级别
     * @param mixed $data
     * @return mixed
     */
    public function updateKjjb($data)
    {
        $last_id = 0;
        foreach ($data as $item) {
            $last_id = $item['id'];
            $kjjb = 0; //默认无
            $id = $item['id'];
            $YZMC = $item['YZMC'];
            $kjyw_name = $item['kjyw_name']; //这个名称是medicinal_info表的名称
            $typk = $this->getYkTYPK($kjyw_name);
            if (!empty($typk)) {
                foreach ($typk as $t) {
                    $kjjb = 9;//未知
                    if (strpos($YZMC, $t['YPMC']) !== false) {
                        $kjjb = $t['KJJB']; //找到了就是那个抗菌级别
                        if($kjjb){ break; }
                    }
                }
            }
            Log::info('清洗抗菌级别' . json_encode(['id' => $id, 'YZMC' => $YZMC, 'kjjb' => $kjjb], 256));
            Yzb::query()->where('id', $id)->update(['KJJB' => $kjjb]);
        }
        return $last_id;
    }

    /**
     * 根据medicinal_info表的名称获取药品库中的抗菌药物的药品名称和抗菌等级--注意：可能多个
     * @param mixed $kjyw_name
     * @return array
     */
    public function getYkTYPK($kjyw_name)
    {
        $yk_typk = YK_TYPK::query()->where('KSBZ', '>', 0)
            ->where('YPMC', 'like', '%' . $kjyw_name . '%')
            ->distinct()->get(['YPMC', 'KJJB'])->toArray();
        return $yk_typk ?? [];
    }
}
