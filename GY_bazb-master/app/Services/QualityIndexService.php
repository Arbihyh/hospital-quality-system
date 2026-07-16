<?php

namespace App\Services;

use App\Model\QualityIndex;
use Exception;


/**
 * 比例数据
 */
class QualityIndexService
{


    /**
     * @param array $where
     * @param int $type
     * @return mixed
     * @throws Exception
     * 获取绩效考核指标数据
     */
    public function getList(array $where = [], int $type = 0, $isExport = 0, $perPage = 1, $chuyuanshijianOrder = 0, $year = 0)
    {
        if ($type == 1) {

            $data = [
                1 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 1, 'year' => $year, 'category' => '' ],
                2 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 2, 'year' => $year, 'category' => ''],
                3 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 3, 'year' => $year, 'category' => ''],
                4 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 4, 'year' => $year, 'category' => ''],
                5 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 5, 'year' => $year, 'category' => ''],
                6 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 6, 'year' => $year, 'category' => ''],
                7 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 7, 'year' => $year, 'category' => ''],
                8 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 8, 'year' => $year, 'category' => ''],
                9 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 9, 'year' => $year, 'category' => ''],
                10 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 10, 'year' => $year, 'category' => '' ],
                11 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 11, 'year' => $year, 'category' => '' ],
                12 => ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0, 'month' => 12, 'year' => $year, 'category' => '' ],
            ];

            $res = QualityIndex::query()->where($where)->get();
            foreach ($res as $re) {

                $data[$re->month]['year'] = $re->year;
                $data[$re->month]['month'] = $re->month;
                $data[$re->month]['category'] = $re->category;


                if (!isset($data[$re->month]['fenzi'])) {
                    $data[$re->month]['fenzi'] = 0;
                }

                if ($re->zhuangtai) {
                    $data[$re->month]['fenzi'] += 1;
                }
                if (!isset($data[$re->month]['fenmu'])) {
                    $data[$re->month]['fenmu'] = 1;
                } else {
                    $data[$re->month]['fenmu'] += 1;
                }
            }

            foreach ($data as $k => $v) {
                if ($v['fenzi']) {
                    $data[$k]['radio'] = number_format(($v['fenzi'] / $v['fenmu']) * 100, 2);
                }
            }

            $fenzi = 0;
            $fenmu = 0;

            foreach ($data as $kk => $vv) {

                $fenzi += $vv['fenzi'];

                $fenmu += $vv['fenmu'];

                $year = $vv['year'];
            }

            if (!empty($data)) {
                $data[13]['fenzi'] = $fenzi;
                $data[13]['fenmu'] = $fenmu;
                $data[13]['radio'] = $fenzi ? number_format(($fenzi / $fenmu) * 100, 2) : 0;
                $data[13]['month'] = 0;
                $data[13]['year'] = $year;

                $data[13]['category'] = '';
            }

            $fdata = [];
            foreach ($data as $vvv) {
                $fdata[] = $vvv;
            }

            array_multisort(array_column($fdata, 'month'), SORT_ASC, $fdata);

            return $fdata;

        } else {

            if ($isExport) {
                $res = QualityIndex::query()->where($where)->get();
            } else {
                $q = QualityIndex::query()->where($where);
                if ($chuyuanshijianOrder) {
                    $q->orderBy('chuyuanshijian', $chuyuanshijianOrder == 1 ? 'asc' : 'desc');
                }

                $res = $q->paginate($perPage);

            }

            return $res;
        }


    }


    public function getInfo($zhuyuanhao, $category)
    {
        return QualityIndex::query()
            ->where('zhuyuanhao','=', $zhuyuanhao)
            ->where('category','=', $category)
            ->find();
    }
}
