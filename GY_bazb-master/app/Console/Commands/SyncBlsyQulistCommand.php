<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Model\BlsyQulist;
use App\Model\EMR_BL_BLSY;
use App\Services\CaseService;
use App\Services\MysqlDataSync\sanyuan\HomeData;
use Illuminate\Support\Facades\Log;

class SyncBlsyQulistCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:blsy-qulist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '循环监控BlsyQulist表并同步病程签名数据';

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
     * 判断是否到达结束时间
     *
     * @return bool
     */
    private function shouldStop(): bool
    {
        $now = date('H:i');
        return $now >= self::END_TIME;
    }

    /**
     * 结束时间（时:分）
     */
    const END_TIME = '23:55';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("开始监控 BlsyQulist 表...");

        // 实例化 HomeData 获取 Oracle 连接
        $homeData = new HomeData();
        $homeData->getConnect2();

        if (!HomeData::$con2) {
            $this->error("Oracle 连接(con2)获取失败，请检查配置！");
            return;
        }
        $caseService = app(CaseService::class);

        while (true) {
            // 检查是否到达结束时间
            if ($this->shouldStop()) {
                $this->info(sprintf("[%s] 已到达结束时间 %s，任务正常退出", date('Y-m-d H:i:s'), self::END_TIME));
                break;
            }

            // 查询表里是否有数据
            $records = BlsyQulist::all();

            if ($records->isNotEmpty()) {
                $this->info(sprintf("[%s] 查到 %d 条数据，先等待 10s...", date('Y-m-d H:i:s'), $records->count()));
                // 如果查到数据先等待10s
                sleep(10);

                $count = $records->count();
                foreach ($records as $index => $record) {
                    // 处理每条记录前检查时间
                    if ($this->shouldStop()) {
                        $this->info(sprintf("[%s] 已到达结束时间 %s，任务正常退出", date('Y-m-d H:i:s'), self::END_TIME));
                        break 2; // 跳出两层循环
                    }

                    $zyh = $record->ZYH;
                    $blbh = $record->BLBH;
                    $this->info(sprintf("[%s] 开始处理 blbh: %s", date('Y-m-d H:i:s'), $blbh));

                    try {
                        // 插入逻辑
                        $sql = "SELECT JLXH,QMLX,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJTIME,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJTIME,FG_ACTIVE,QMYS FROM EMR_BL_BLSY WHERE BLBH = '{$blbh}'";
                        $resultSet = oci_parse(HomeData::$con2, $sql);

                        if ($resultSet) {
                            oci_execute($resultSet, OCI_DEFAULT);
                            $data = [];
                            while ($row = oci_fetch_assoc($resultSet)) {
                                $data[] = $row;
                            }

                            if (!empty($data)) {
                                //如果不为空先删除历史的签名数据
                                EMR_BL_BLSY::query()->where('BLBH3', $blbh)->delete();
                                foreach ($data as $v) {
                                    $QMMC = 0;
                                    switch ($v['QMYS'] ?? '') {
                                        case '主刀医师签名':
                                            $QMMC = 6;
                                            break;
                                        case '医师签名':
                                        case '科室负责人签名':
                                        case '主持人签名':
                                        case '会诊专家1签名':
                                        case '会诊专家2签名':
                                        case '会诊专家3签名':
                                        case '会诊专家签名':
                                        case '医师签名-1':
                                        case '医师签名-2':
                                        case '医师签名-3':
                                        case '审核医师签名':
                                        case '麻醉医师签名':
                                            $QMMC = 5;
                                            break;
                                        case '患者签名':
                                        case '患者1签名':
                                        case '患者2签名':
                                        case '患方0签名':
                                        case '患方1签名':
                                        case '患方2签名':
                                        case '患方3签名':
                                        case '陈述者签名':
                                            $QMMC = 3;
                                            break;
                                        case '被授权人签名':
                                        case '被授权者签名':
                                        case '被授权人0签名':
                                        case '被授权者1签名':
                                        case '被授权人2签名':
                                            $QMMC = 4;
                                            break;
                                        case '患者手写意见1':
                                        case '患方手写意见':
                                        case '患者手写意见':
                                        case '患方手写意见1':
                                        case '患者手写意见2':
                                        case '患方手写意见2':
                                        case '患者手写意见3':
                                        case '患方手写意见3':
                                            $QMMC = 1;
                                            break;
                                        case '护士签名':
                                            $QMMC = 7;
                                            break;
                                    }

                                    $insertData = [
                                        'JLXH' => $v['JLXH'],
                                        'BLBH3' => $v['BLBH'],
                                        'BLBH' => $v['BLBH'],
                                        'SYYS' => $v['SYYS'],
                                        'SYSJ' => $v['SYSJTIME'],
                                        'JLSJ' => $v['JLSJTIME'],
                                        'FG_ACTIVE' => $v['FG_ACTIVE'],
                                        'QMLX' => ($v['QMLX'] ?? null) == 1 ? 1 : 2,
                                        'QMMC' => $QMMC,
                                        'QMYS' => $v['QMYS'] ?? '',
                                    ];
                                    EMR_BL_BLSY::query()->updateOrInsert(
                                        ['JLXH' => $insertData['JLXH'], 'BLBH3' => $insertData['BLBH3']],
                                        $insertData
                                    );
                                }
                                $this->info("    - 成功同步 " . count($data) . " 条签名数据");
                                //执行质控
                                $this->info("    - 开始执行质控");
                                $caseService->qualityContrlV2('', '', [$zyh], 4, $blbh);
                                $this->info("    - 质控完成");
                            } else {
                                $this->info("    - Oracle端未查询到签名数据");
                            }
                        } else {
                            $this->error("    - OCI SQL 解析失败！");
                        }
                    } catch (\Exception $e) {
                        $this->error("    - 处理失败: " . $e->getMessage());
                        Log::error("同步 BlsyQulist 报错", ['blbh' => $blbh, 'msg' => $e->getMessage()]);
                    }

                    // 完成一条删除一条
                    $record->delete();
                    $this->info("    - 已完成并删除该条记录");

                    // 处理完每条记录后检查时间
                    if ($this->shouldStop()) {
                        $this->info(sprintf("[%s] 已到达结束时间 %s，任务正常退出", date('Y-m-d H:i:s'), self::END_TIME));
                        break 2; // 跳出两层循环
                    }

                    // 每个blbh间间隔10s
                    if ($index < $count - 1) {
                        $this->info("    - 等待 10s 处理下一个 blbh...");
                        sleep(10);
                    }
                }

                $this->info(sprintf("[%s] 执行完本次查到的数据，等待 10s 继续监控...", date('Y-m-d H:i:s')));
                // 执行完本次查到的，然后等待10s继续监控
                sleep(10);
            } else {
                // 如果没有查到数据，短暂休眠避免死循环空转拉高CPU
                sleep(3);
            }
        }
    }
}
