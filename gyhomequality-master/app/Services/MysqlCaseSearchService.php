<?php

namespace App\Services;

use App\Model\PatientInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** MySQL implementation for 全病例-病例查询. */
class MysqlCaseSearchService
{
    private const BLLB_LIST = [1, 292, 294, 303, 329, 43, 79, 288, 18, 34, 87];

    private const PATIENT_COLUMNS = [
        'MED_REC_ID',
        'AAA28',
        'AAA04',
        'AAC11N',
        'AAB01',
        'AAC01',
    ];

    public static function normalSearch(Request $request)
    {
        $limit = max((int)$request->post('limit', 20), 1);
        $page = max((int)$request->post('page', 1), 1);
        $offset = ($page - 1) * $limit;
        $keyword = trim((string)$request->post('keyword', ''));
        $detail = (int)$request->post('detail', 1);

        if ($keyword === '') {
            return self::returnPatientPage(PatientInfo::query(), $limit, $offset);
        }

        $result = self::searchDetailRowsByKeyword($keyword, $limit, $offset, $detail === 1);
        return self::returnByDetailRows($result['rows'], $result['total'], $limit);
    }

    public static function searchData(Request $request)
    {
        $limit = max((int)$request->post('limit', 20), 1);
        $page = max((int)$request->post('page', 1), 1);
        $offset = ($page - 1) * $limit;
        $detail = (int)$request->post('detail', 1);

        $query = PatientInfo::query();
        self::applyHomePageFilters($query, $request);

        $field = $request->post('field', []);
        if (!is_array($field)) {
            $field = [];
        }
        self::applyDynamicFields($query, $field);

        $total = (clone $query)->count();
        $patients = $query
            ->select(self::PATIENT_COLUMNS)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        $medIds = array_values(array_filter(array_column($patients, 'MED_REC_ID')));
        $detailRows = $detail === 1 ? self::loadMatchedDetails($medIds, $field) : [];

        return self::returnData($patients, $detailRows, $total, $limit);
    }

    private static function applyHomePageFilters($query, Request $request)
    {
        self::applyOperationNameOrCode($query, 'ICD9_NAME', $request->post('ICD9_NAME', ''), true, true);
        self::applyOperationNameOrCode($query, 'ICD9_ID1', $request->post('ICD9_ID1', ''), false, true);
        self::applyRelationText($query, 'secondary_operation', 'AAA28', 'ICD9_NAME', $request->post('secondary_operation_ICD9_NAME', ''), true);
        self::applyRelationText($query, 'secondary_operation', 'AAA28', 'ICD9_ID1', $request->post('secondary_operation_ICD9_ID1', ''), false);
        self::applyRelationText($query, 'main_operation', 'AAA28', 'ICD9_NAME', $request->post('main_operation_ICD9_NAME', ''), true);
        self::applyRelationText($query, 'main_operation', 'AAA28', 'ICD9_ID1', $request->post('main_operation_ICD9_ID1', ''), false);
        self::applyRelationText($query, 'other_diagnosis', 'AAA28', 'ICD10_NAME', $request->post('other_diagnosis_ICD10_NAME', ''), true);
        self::applyRelationText($query, 'other_diagnosis', 'AAA28', 'ICD10_ID1', $request->post('other_diagnosis_ICD10_ID1', ''), false);

        $startTime = $request->post('AAC01_start', '');
        $endTime = $request->post('AAC01_end', '');
        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetween('patient_info.AAC01', [
                date('Y-m-d 00:00:00', strtotime($startTime)),
                date('Y-m-d 23:59:59', strtotime($endTime)),
            ]);
        }

        self::applyRange($query, 'patient_info.AAC04', $request->post('AAC04_start', null), $request->post('AAC04_end', null));
        self::applyRange($query, 'patient_info.AAA04', $request->post('AAA04_start', null), $request->post('AAA04_end', null));
    }

    private static function applyDynamicFields($query, array $fields)
    {
        $must = [];
        $should = [];
        $mustNot = [];

        foreach ($fields as $item) {
            if (!is_array($item) || !isset($item['key']) || !isset($item['value']) || $item['value'] === '') {
                continue;
            }

            $selectType = isset($item['select_type']) ? (string)$item['select_type'] : '0';
            if ($selectType === '1') {
                $should[] = $item;
            } elseif ($selectType === '2') {
                $mustNot[] = $item;
            } else {
                $must[] = $item;
            }
        }

        foreach ($must as $item) {
            $query->where(function ($sub) use ($item) {
                self::applyOneDynamicField($sub, $item, 'and');
            });
        }

        if (!empty($should)) {
            $query->where(function ($sub) use ($should) {
                foreach ($should as $item) {
                    self::applyOneDynamicField($sub, $item, 'or');
                }
            });
        }

        foreach ($mustNot as $item) {
            $query->where(function ($sub) use ($item) {
                self::applyOneDynamicField($sub, $item, 'not');
            });
        }
    }

    private static function applyOneDynamicField($query, array $item, $boolean)
    {
        $key = (string)$item['key'];
        $value = (string)$item['value'];
        $exact = isset($item['type']) && (string)$item['type'] !== '0';

        if ($key === 'AAC11N') {
            if ($value === '全部') {
                return;
            }
            self::whereColumnValue($query, 'patient_info.AAC11N', $value, false, $boolean);
            return;
        }

        if ($key === '全部') {
            self::whereEmrContent($query, self::BLLB_LIST, $value, $exact, $boolean);
            return;
        }

        if (in_array((int)$key, self::BLLB_LIST, true)) {
            self::whereEmrContent($query, [(int)$key], $value, $exact, $boolean);
            return;
        }

        if ($key === '49') {
            self::whereDetailTable($query, 'yzb', 'ZYH', 'YZMC', $value, $exact, $boolean);
            return;
        }

        self::whereDetailTable($query, 'fee_detailed', 'AAA28', 'FYMC', $value, $exact, $boolean);
    }

    private static function applyOperationNameOrCode($query, $column, $value, $fuzzy, $bothTables)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return;
        }

        $query->where(function ($sub) use ($column, $value, $fuzzy) {
            self::applyRelationText($sub, 'secondary_operation', 'AAA28', $column, $value, $fuzzy, 'or');
            self::applyRelationText($sub, 'main_operation', 'AAA28', $column, $value, $fuzzy, 'or');
        });
    }

    private static function applyRelationText($query, $table, $foreignKey, $column, $value, $fuzzy = true, $boolean = 'and')
    {
        $value = trim((string)$value);
        if ($value === '') {
            return;
        }

        $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
        $query->{$method}(function ($exists) use ($table, $foreignKey, $column, $value, $fuzzy) {
            $exists->select(DB::raw(1))
                ->from($table)
                ->whereColumn($table . '.' . $foreignKey, 'patient_info.MED_REC_ID');
            self::whereValue($exists, $table . '.' . $column, $value, $fuzzy);
        });
    }

    private static function applyRange($query, $column, $start, $end)
    {
        if ($start === null || $start === '' || $end === null || $end === '') {
            return;
        }
        $query->whereBetween($column, [$start, $end]);
    }

    private static function whereColumnValue($query, $column, $value, $exact, $boolean)
    {
        $operator = $exact ? '=' : 'like';
        $needle = $exact ? $value : '%' . self::escapeLike($value) . '%';

        if ($boolean === 'or') {
            $query->orWhere($column, $operator, $needle);
        } elseif ($boolean === 'not') {
            $query->where($column, 'not like', '%' . self::escapeLike($value) . '%');
        } else {
            $query->where($column, $operator, $needle);
        }
    }

    private static function whereDetailTable($query, $table, $foreignKey, $column, $value, $exact, $boolean)
    {
        $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
        if ($boolean === 'not') {
            $method = 'whereNotExists';
        }

        $query->{$method}(function ($exists) use ($table, $foreignKey, $column, $value, $exact) {
            $exists->select(DB::raw(1))
                ->from($table)
                ->whereColumn($table . '.' . $foreignKey, 'patient_info.MED_REC_ID');
            self::whereValue($exists, $table . '.' . $column, $value, !$exact);
        });
    }

    private static function whereEmrContent($query, array $bllbList, $value, $exact, $boolean)
    {
        $method = $boolean === 'or' ? 'orWhereExists' : 'whereExists';
        if ($boolean === 'not') {
            $method = 'whereNotExists';
        }

        $query->{$method}(function ($exists) use ($bllbList, $value, $exact) {
            $exists->select(DB::raw(1))
                ->from('EMR_BL_BL01')
                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                ->whereColumn('EMR_BL_BL01.JZHM', 'patient_info.MED_REC_ID')
                ->whereIn('EMR_BL_BL01.BLLB', $bllbList);
            self::whereValue($exists, 'EMR_BL_BLXG.HJNR', $value, !$exact);
        });
    }

    private static function whereValue($query, $column, $value, $fuzzy)
    {
        if ($fuzzy) {
            $query->where($column, 'like', '%' . self::escapeLike($value) . '%');
        } else {
            $query->where($column, '=', $value);
        }
    }

    private static function searchDetailRowsByKeyword($keyword, $limit, $offset, $highlight)
    {
        $bindings = [];
        $like = '%' . self::escapeLike($keyword) . '%';
        $bllbMarks = implode(',', array_fill(0, count(self::BLLB_LIST), '?'));

        $sql = "
            SELECT * FROM (
                SELECT
                    EMR_BL_BL01.JZHM AS MED_REC_ID,
                    'EMR_BL_BL01' AS mark,
                    EMR_BL_BL01.BLLB AS BLLB,
                    EMR_BL_BLXG.HJNR AS HJNR,
                    NULL AS FYMC,
                    NULL AS FYSL,
                    NULL AS JFRQ,
                    NULL AS ZJE,
                    NULL AS FYKS,
                    NULL AS FYXH,
                    NULL AS ZFJE,
                    NULL AS FYDJ,
                    NULL AS FYGB,
                    NULL AS SYFYGB,
                    NULL AS YZMC,
                    NULL AS BRKS,
                    NULL AS KZSJ,
                    NULL AS YZQX,
                    NULL AS KZKS,
                    NULL AS KZYS,
                    NULL AS XZJDGH,
                    NULL AS TZSJ
                FROM EMR_BL_BL01
                LEFT JOIN EMR_BL_BLXG ON EMR_BL_BL01.BLBH = EMR_BL_BLXG.BLBH
                WHERE EMR_BL_BL01.BLLB IN ($bllbMarks)
                  AND EMR_BL_BLXG.HJNR LIKE ?
                UNION ALL
                SELECT
                    fee_detailed.AAA28 AS MED_REC_ID,
                    'fee_detailed' AS mark,
                    NULL AS BLLB,
                    NULL AS HJNR,
                    fee_detailed.FYMC,
                    fee_detailed.FYSL,
                    fee_detailed.JFRQ,
                    fee_detailed.ZJE,
                    fee_detailed.FYKS,
                    fee_detailed.FYXH,
                    fee_detailed.ZFJE,
                    fee_detailed.FYDJ,
                    fee_detailed.FYGB,
                    fee_detailed.SYFYGB,
                    NULL AS YZMC,
                    NULL AS BRKS,
                    NULL AS KZSJ,
                    NULL AS YZQX,
                    NULL AS KZKS,
                    NULL AS KZYS,
                    NULL AS XZJDGH,
                    NULL AS TZSJ
                FROM fee_detailed
                WHERE fee_detailed.FYMC LIKE ?
                UNION ALL
                SELECT
                    yzb.ZYH AS MED_REC_ID,
                    'yzb' AS mark,
                    NULL AS BLLB,
                    NULL AS HJNR,
                    NULL AS FYMC,
                    NULL AS FYSL,
                    NULL AS JFRQ,
                    NULL AS ZJE,
                    NULL AS FYKS,
                    NULL AS FYXH,
                    NULL AS ZFJE,
                    NULL AS FYDJ,
                    NULL AS FYGB,
                    NULL AS SYFYGB,
                    yzb.YZMC,
                    yzb.BRKS,
                    yzb.KZSJ,
                    yzb.YZQX,
                    yzb.KZKS,
                    yzb.KZYS,
                    yzb.XZJDGH,
                    yzb.TZSJ
                FROM yzb
                WHERE yzb.YZMC LIKE ?
            ) t
            WHERE t.MED_REC_ID IS NOT NULL AND t.MED_REC_ID != ''
            LIMIT ? OFFSET ?";

        $bindings = array_merge(self::BLLB_LIST, [$like, $like, $like, $limit, $offset]);

        $countSql = "
            SELECT COUNT(1) AS aggregate FROM (
                SELECT EMR_BL_BL01.JZHM AS MED_REC_ID
                FROM EMR_BL_BL01
                LEFT JOIN EMR_BL_BLXG ON EMR_BL_BL01.BLBH = EMR_BL_BLXG.BLBH
                WHERE EMR_BL_BL01.BLLB IN ($bllbMarks)
                  AND EMR_BL_BLXG.HJNR LIKE ?
                UNION ALL
                SELECT fee_detailed.AAA28 AS MED_REC_ID FROM fee_detailed WHERE fee_detailed.FYMC LIKE ?
                UNION ALL
                SELECT yzb.ZYH AS MED_REC_ID FROM yzb WHERE yzb.YZMC LIKE ?
            ) c
            WHERE c.MED_REC_ID IS NOT NULL AND c.MED_REC_ID != ''";
        $countBindings = array_merge(self::BLLB_LIST, [$like, $like, $like]);

        $rows = DB::select($sql, $bindings);
        $countRow = DB::selectOne($countSql, $countBindings);
        $rows = array_map(function ($row) use ($keyword, $highlight) {
            return self::formatDetailRow((array)$row, $keyword, $highlight);
        }, $rows);

        return [
            'rows' => $rows,
            'total' => $countRow ? (int)$countRow->aggregate : 0,
        ];
    }

    private static function loadMatchedDetails(array $medIds, array $fields)
    {
        if (empty($medIds)) {
            return [];
        }

        $detailRows = [];
        foreach ($fields as $item) {
            if (!is_array($item) || !isset($item['key']) || !isset($item['value']) || $item['value'] === '') {
                continue;
            }
            $key = (string)$item['key'];
            $value = (string)$item['value'];
            $exact = isset($item['type']) && (string)$item['type'] !== '0';

            if ($key === '全部' || in_array((int)$key, self::BLLB_LIST, true)) {
                $bllb = $key === '全部' ? self::BLLB_LIST : [(int)$key];
                $rows = DB::table('EMR_BL_BL01')
                    ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->whereIn('EMR_BL_BL01.JZHM', $medIds)
                    ->whereIn('EMR_BL_BL01.BLLB', $bllb);
                self::whereValue($rows, 'EMR_BL_BLXG.HJNR', $value, !$exact);
                foreach ($rows->get(['EMR_BL_BL01.JZHM as MED_REC_ID', 'EMR_BL_BL01.BLLB', 'EMR_BL_BLXG.HJNR'])->toArray() as $row) {
                    $detailRows[] = self::formatDetailRow((array)$row + ['mark' => 'EMR_BL_BL01'], $value, true);
                }
            } elseif ($key === '49') {
                $rows = DB::table('yzb')->whereIn('ZYH', $medIds);
                self::whereValue($rows, 'YZMC', $value, !$exact);
                foreach ($rows->get(['ZYH as MED_REC_ID', 'YZMC', 'BRKS', 'KZSJ', 'YZQX', 'KZKS', 'KZYS', 'XZJDGH', 'TZSJ'])->toArray() as $row) {
                    $detailRows[] = self::formatDetailRow((array)$row + ['mark' => 'yzb'], $value, true);
                }
            } elseif ($key !== 'AAC11N') {
                $rows = DB::table('fee_detailed')->whereIn('AAA28', $medIds);
                self::whereValue($rows, 'FYMC', $value, !$exact);
                foreach ($rows->get(['AAA28 as MED_REC_ID', 'FYMC', 'FYSL', 'JFRQ', 'ZJE', 'FYKS', 'FYXH', 'ZFJE', 'FYDJ', 'FYGB', 'SYFYGB'])->toArray() as $row) {
                    $detailRows[] = self::formatDetailRow((array)$row + ['mark' => 'fee_detailed'], $value, true);
                }
            }
        }

        return $detailRows;
    }

    private static function returnPatientPage($query, $limit, $offset)
    {
        $total = (clone $query)->count();
        $patients = $query
            ->select(self::PATIENT_COLUMNS)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return self::returnData($patients, [], $total, $limit);
    }

    private static function returnByDetailRows(array $detailRows, $total, $limit)
    {
        $medIds = [];
        foreach ($detailRows as $row) {
            if (!empty($row['MED_REC_ID']) && !in_array($row['MED_REC_ID'], $medIds, true)) {
                $medIds[] = $row['MED_REC_ID'];
            }
        }

        $patients = empty($medIds)
            ? []
            : PatientInfo::query()->whereIn('MED_REC_ID', $medIds)->get(self::PATIENT_COLUMNS)->toArray();

        $patients = self::sortPatientsByMedIds($patients, $medIds);
        return self::returnData($patients, $detailRows, $total, $limit);
    }

    private static function returnData(array $patients, array $detailRows, $total, $limit)
    {
        $data = [
            'total_page' => $limit > 0 ? (int)ceil($total / $limit) : 0,
            'total' => (int)$total,
            'list' => [],
            'detail' => [],
        ];

        $detailsByMedId = [];
        foreach ($detailRows as $row) {
            if (!empty($row['MED_REC_ID'])) {
                $detailsByMedId[$row['MED_REC_ID']][] = $row;
            }
        }

        foreach ($patients as $patient) {
            $data['list'][] = $patient;
            $medId = $patient['MED_REC_ID'];
            $emr = [];
            $fee = [];
            $yzb = [];

            if (!empty($detailsByMedId[$medId])) {
                foreach ($detailsByMedId[$medId] as $row) {
                    if ($row['mark'] === 'EMR_BL_BL01') {
                        $emr[] = $row;
                    } elseif ($row['mark'] === 'fee_detailed') {
                        $fee[] = $row;
                    } elseif ($row['mark'] === 'yzb') {
                        $yzb[] = $row;
                    }
                }
            }

            $data['detail'][] = [
                'EMR_BL_BL01' => $emr,
                'FeeDetailed' => $fee,
                'YZB' => $yzb,
                'AAA28' => $patient['AAA28'],
                'MED_REC_ID' => $patient['MED_REC_ID'],
                'AAC01' => $patient['AAC01'],
            ];
        }

        return ToolsService::returnData(200, ['data' => $data]);
    }

    private static function formatDetailRow(array $row, $keyword, $highlight)
    {
        $mark = isset($row['mark']) ? $row['mark'] : '';
        $data = ['mark' => $mark, 'MED_REC_ID' => isset($row['MED_REC_ID']) ? $row['MED_REC_ID'] : ''];

        if ($mark === 'EMR_BL_BL01') {
            $data['BLLB'] = isset($row['BLLB']) ? (string)$row['BLLB'] : '';
            $value = isset($row['HJNR']) ? (string)$row['HJNR'] : '';
            $data['HJNR'] = $highlight ? [self::highlight($value, $keyword)] : $value;
            return $data;
        }

        if ($mark === 'fee_detailed') {
            foreach (['FYMC', 'FYSL', 'JFRQ', 'ZJE', 'FYKS', 'FYXH', 'ZFJE', 'FYDJ', 'FYGB', 'SYFYGB'] as $field) {
                $data[$field] = isset($row[$field]) ? $row[$field] : null;
            }
            if ($highlight && isset($data['FYMC'])) {
                $data['FYMC'] = [self::highlight((string)$data['FYMC'], $keyword)];
            }
            return $data;
        }

        if ($mark === 'yzb') {
            foreach (['YZMC', 'BRKS', 'KZSJ', 'YZQX', 'KZKS', 'KZYS', 'XZJDGH', 'TZSJ'] as $field) {
                $data[$field] = isset($row[$field]) ? $row[$field] : null;
            }
            if ($highlight && isset($data['YZMC'])) {
                $data['YZMC'] = [self::highlight((string)$data['YZMC'], $keyword)];
            }
            return $data;
        }

        return $data;
    }

    private static function sortPatientsByMedIds(array $patients, array $medIds)
    {
        $map = [];
        foreach ($patients as $patient) {
            $map[$patient['MED_REC_ID']] = $patient;
        }

        $sorted = [];
        foreach ($medIds as $medId) {
            if (isset($map[$medId])) {
                $sorted[] = $map[$medId];
            }
        }

        return $sorted;
    }

    private static function highlight($text, $keyword)
    {
        if ($keyword === '') {
            return $text;
        }

        return str_replace($keyword, "<font color='red'>" . $keyword . '</font>', $text);
    }

    private static function escapeLike($value)
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
