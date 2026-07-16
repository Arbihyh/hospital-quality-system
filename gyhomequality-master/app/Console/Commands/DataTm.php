<?php

namespace App\Console\Commands;

use App\Model\BA_BASE;
use App\Model\BA_RECEIVE;
use App\Model\BLLB1;
use App\Model\BLLB292;
use App\Model\BLLB303;
use App\Model\BLLB303_303;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BL01_NEW;
use App\Model\MS_BRDA;
use App\Model\MS_CF01;
use App\Model\MZJL;
use App\Model\OMR_BL01;
use App\Model\OmrQuality;
use App\Model\Pacs;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoV2;
use App\Model\PatientWorkInfo;
use App\Model\Setting;
use App\Model\V_JMGS_TESTRESULT;
use App\Model\V_JMGS_YMresult;
use App\Model\YJ_ZY01;
use Illuminate\Console\Command;

class DataTm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tm:all';

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
       echo'脱敏开始';
        // patient_info
        $this->patientInfo();
        echo '表 patient_info 脱敏完成'.PHP_EOL;

        // BA_BASE
        $this->BA_BASE();
        echo '表 BA_BASE 脱敏完成'.PHP_EOL;

        // BA_RECEIVE
        $this->BA_RECEIVE();
        echo '表 BA_RECEIVE 脱敏完成'.PHP_EOL;

        // EMR_BL_BL01
        $this->EMR_BL_BL01();
        echo '表 EMR_BL_BL01 脱敏完成'.PHP_EOL;

        // EMR_BL_BL01_NEW
        $this->EMR_BL_BL01_NEW();
        echo '表 EMR_BL_BL01_NEW 脱敏完成'.PHP_EOL;

        // MS_BRDA
        $this->MS_BRDA();
        echo '表 MS_BRDA 脱敏完成'.PHP_EOL;

        // OMR_BL01
        $this->OMR_BL01();
        echo '表 OMR_BL01 脱敏完成'.PHP_EOL;

        // PACS
        $this->PACS();
        echo '表 PACS 脱敏完成'.PHP_EOL;

        // V_JMGS_TESTRESULT
        $this->V_JMGS_TESTRESULT();
        echo '表 V_JMGS_TESTRESULT 脱敏完成'.PHP_EOL;

        // V_JMGS_YMresult
        $this->V_JMGS_YMresult();
        echo '表 V_JMGS_YMresult 脱敏完成'.PHP_EOL;

        // bllb1
        $this->bllb1();
        echo '表 bllb1 脱敏完成'.PHP_EOL;

        // bllb292
        $this->bllb292();
        echo '表 bllb292 脱敏完成'.PHP_EOL;

        // bllb303_303
        $this->bllb303_303();
        echo '表 bllb303_303 脱敏完成'.PHP_EOL;

        // mzjl
        $this->mzjl();
        echo '表 mzjl 脱敏完成'.PHP_EOL;

        // omr_quality
        $this->omr_quality();
        echo '表 omr_quality 脱敏完成'.PHP_EOL;

        // patient_address_info
        $this->patient_address_info();
        echo '表 patient_address_info 脱敏完成'.PHP_EOL;

        // patient_contacts_info
        $this->patient_contacts_info();
        echo '表 patient_contacts_info 脱敏完成'.PHP_EOL;

        // patient_work_info
        $this->patient_work_info();
        echo '表 patient_work_info 脱敏完成'.PHP_EOL;

        // patient_info_v2
        $this->patient_info_v2();
        echo '表 patient_info_v2 脱敏完成'.PHP_EOL;

        // YJ_ZY01
        $this->YJ_ZY01();
        echo '表 YJ_ZY01 脱敏完成'.PHP_EOL;

        // MS_CF01
        $this->MS_CF01();
        echo '表 MS_CF01 脱敏完成'.PHP_EOL.PHP_EOL;

        echo "数据脱敏完成";
    }

    public function patientInfo()
    {
        $setName = 'tm_patient_info_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = PatientInfo::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','AAA01','AAA07'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $AAA01 = !empty($val['AAA01']) ? desensitize($val['AAA01'], 1, 1, '*') : '';
                $AAA07 = !empty($val['AAA07']) ? desensitize($val['AAA07'], 6, 8, '*') : '';
                $saveData = [
                    'AAA01' => $AAA01,
                    'AAA07' => $AAA07,
                ];
                PatientInfo::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function BA_BASE()
    {
        $setName = 'tm_ba_base_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = BA_BASE::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','brxm','brsfzh'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $brxm = !empty($val['brxm']) ? desensitize($val['brxm'], 1, 1, '*') : '';
                $brsfzh= !empty($val['brsfzh']) ? desensitize($val['brsfzh'], 6, 8, '*') : '';
                $saveData = [
                    'brxm' => $brxm,
                    'brsfzh' => $brsfzh,
                ];
                BA_BASE::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function BA_RECEIVE()
    {
        $setName = 'tm_ba_receive_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = BA_RECEIVE::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->get(['id','brxm','brsfzh'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $brxm = !empty($val['brxm']) ? desensitize($val['brxm'], 1, 1, '*') : '';
                $brsfzh= !empty($val['brsfzh']) ? desensitize($val['brsfzh'], 6, 8, '*') : '';
                $saveData = [
                    'brxm' => $brxm,
                    'brsfzh' => $brsfzh,
                ];
                BA_RECEIVE::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function EMR_BL_BL01()
    {
        $setName = 'tm_emr_bl_bl01_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = EMR_BL_BL01::query()
                ->where('BLBH','>',$lastId)
                ->orderBy('BLBH')
                ->limit(500)
                ->get(['BLBH','BRXM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['BLBH'];
                $BRXM = !empty($val['BRXM']) ? desensitize($val['BRXM'], 1, 1, '*') : '';
                $saveData = [
                    'BRXM' => $BRXM,
                ];
                EMR_BL_BL01::query()->where('BLBH','=',$val['BLBH'])->update($saveData);
            }
            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function EMR_BL_BL01_NEW()
    {
        $setName = 'tm_emr_bl_bl01_new_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = EMR_BL_BL01_NEW::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','BRXM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $BRXM = !empty($val['BRXM']) ? desensitize($val['BRXM'], 1, 1, '*') : '';
                $saveData = [
                    'BRXM' => $BRXM,
                ];
                EMR_BL_BL01_NEW::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function MS_BRDA()
    {
        $setName = 'tm_ms_brda_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = MS_BRDA::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','BRXM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $BRXM = !empty($val['BRXM']) ? desensitize($val['BRXM'], 1, 1, '*') : '';
                $saveData = [
                    'BRXM' => $BRXM,
                ];
                MS_BRDA::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function MS_CF01()
    {
        $setName = 'tm_ms_cf01_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = MS_CF01::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','BRXM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $BRXM = !empty($val['BRXM']) ? desensitize($val['BRXM'], 1, 1, '*') : '';
                $saveData = [
                    'BRXM' => $BRXM,
                ];
                MS_CF01::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function OMR_BL01()
    {
        $setName = 'tm_omr_bl01_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = OMR_BL01::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','xm','SFZH'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $xm = !empty($val['xm']) ? desensitize($val['xm'], 1, 1, '*') : '';
                $SFZH = mb_strlen($val['SFZH'])>14 ? desensitize($val['SFZH'], 6, 8, '*') : '';
                $saveData = [
                    'xm' => $xm,
                    'SFZH' => $SFZH,
                ];
                OMR_BL01::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function PACS()
    {
        $setName = 'tm_pacs_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = PACS::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','BRXM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $BRXM = !empty($val['BRXM']) ? desensitize($val['BRXM'], 1, 1, '*') : '';
                $saveData = [
                    'BRXM' => $BRXM,
                ];
                PACS::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function V_JMGS_TESTRESULT()
    {
        $setName = 'tm_v_jmgs_testresult_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = V_JMGS_TESTRESULT::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','XM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $XM = !empty($val['XM']) ? desensitize($val['XM'], 1, 1, '*') : '';
                $saveData = [
                    'XM' => $XM,
                ];
                V_JMGS_TESTRESULT::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function V_JMGS_YMresult()
    {
        $setName = 'tm_v_jmgs_ymresult_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = V_JMGS_YMresult::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','XM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $XM = !empty($val['XM']) ? desensitize($val['XM'], 1, 1, '*') : '';
                $saveData = [
                    'XM' => $XM,
                ];
                V_JMGS_YMresult::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function YJ_ZY01()
    {
        $setName = 'tm_yj_zy01_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = YJ_ZY01::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','BRXM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $BRXM = !empty($val['BRXM']) ? desensitize($val['BRXM'], 1, 1, '*') : '';
                $saveData = [
                    'BRXM' => $BRXM,
                ];
                YJ_ZY01::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function bllb1()
    {
        $setName = 'tm_bllb1_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = BLLB1::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','XM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $XM = !empty($val['XM']) ? desensitize($val['XM'], 1, 1, '*') : '';
                $saveData = [
                    'XM' => $XM,
                ];
                BLLB1::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function bllb292()
    {
        $setName = 'tm_bllb292_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = BLLB292::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','XM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $XM = !empty($val['XM']) ? desensitize($val['XM'], 1, 1, '*') : '';
                $saveData = [
                    'XM' => $XM,
                ];
                BLLB292::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function bllb303_303()
    {
        $setName = 'tm_bllb303_303_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = BLLB303_303::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','XM'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $XM = !empty($val['XM']) ? desensitize($val['XM'], 1, 1, '*') : '';
                $saveData = [
                    'XM' => $XM,
                ];
                BLLB303_303::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function mzjl()
    {
        $setName = 'tm_mzjl_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = MZJL::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','NAME'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $NAME = !empty($val['NAME']) ? desensitize($val['NAME'], 1, 1, '*') : '';
                $saveData = [
                    'NAME' => $NAME,
                ];
                MZJL::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function omr_quality()
    {
        $setName = 'tm_omr_quality_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = OmrQuality::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','xm','SFZH'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $xm = !empty($val['xm']) ? desensitize($val['xm'], 1, 1, '*') : '';
                $SFZH = !empty($val['SFZH']) ? desensitize($val['SFZH'], 6, 8, '*') : '';
                $saveData = [
                    'xm' => $xm,
                    'SFZH' => $SFZH,
                ];
                OmrQuality::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function patient_address_info()
    {
        $setName = 'tm_patient_address_info_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = PatientAddressInfo::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','AAA51'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $AAA51 = !empty($val['AAA51']) ? desensitize($val['AAA51'], 3, 4, '*') : '';
                $saveData = [
                    'AAA51' => $AAA51,
                ];
                PatientAddressInfo::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function patient_contacts_info()
    {
        $setName = 'tm_patient_contacts_info_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = PatientContactsInfo::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','AAA22','AAA25'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $AAA22 = !empty($val['AAA22']) ? desensitize($val['AAA22'], 1, 1, '*') : '';
                $AAA25 = !empty($val['AAA25']) ? desensitize($val['AAA25'], 3, 4, '*') : '';
                $saveData = [
                    'AAA22' => $AAA22,
                    'AAA25' => $AAA25,
                ];
                PatientContactsInfo::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function patient_work_info()
    {
        $setName = 'tm_patient_work_info_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = PatientWorkInfo::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','AAA20'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $AAA20 = !empty($val['AAA20']) ? desensitize($val['AAA20'], 3, 4, '*') : '';
                $saveData = [
                    'AAA20' => $AAA20,
                ];
                PatientWorkInfo::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }

    public function patient_info_v2()
    {
        $setName = 'tm_patient_info_v2_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        while (true) {
            $data = PatientInfoV2::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id','AAA01','AAA07','AAA51','AAA20','AAA22','AAA25'])->toArray();
            if (empty($data)) {
                break;
            }

            foreach ($data as $val) {
                $lastId = $val['id'];
                $AAA01 = !empty($val['AAA01']) ? desensitize($val['AAA01'], 1, 1, '*') : '';
                $AAA07 = !empty($val['AAA07']) ? desensitize($val['AAA07'], 6, 8, '*') : '';
                $AAA51 = !empty($val['AAA51']) ? desensitize($val['AAA51'], 3, 4, '*') : '';
                $AAA20 = !empty($val['AAA20']) ? desensitize($val['AAA20'], 3, 4, '*') : '';
                $AAA22 = !empty($val['AAA22']) ? desensitize($val['AAA22'], 1, 1, '*') : '';
                $AAA25 = !empty($val['AAA25']) ? desensitize($val['AAA25'], 3, 4, '*') : '';
                $saveData = [
                    'AAA01' => $AAA01,
                    'AAA07' => $AAA07,
                    'AAA51' => $AAA51,
                    'AAA20' => $AAA20,
                    'AAA22' => $AAA22,
                    'AAA25' => $AAA25,
                ];
                PatientInfoV2::query()->where('id','=',$val['id'])->update($saveData);
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
    }


}
