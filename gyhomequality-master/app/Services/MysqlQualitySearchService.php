<?php

namespace App\Services;

use App\Model\PatientInfo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** MySQL implementation for the medical-record home-page search. */
class MysqlQualitySearchService
{
    private const RELATIONS = [
        'patient_hospital_info' => ['patient_hospital_info', 'AAA28'],
        'patient_doctor_info' => ['patient_doctor_info', 'AAA28'],
        'main_diagnosis' => ['main_diagnosis', 'AAA28'],
        'other_diagnosis' => ['other_diagnosis', 'AAA28'],
        'main_operation' => ['main_operation', 'AAA28'],
        'secondary_operation' => ['secondary_operation', 'AAA28'],
        'patient_medical_info' => ['patient_medical_info', 'AAA28'],
        'icu' => ['icu', 'AAA28'],
        'patient_add' => ['patient_add', 'AAA28'],
        'error' => ['error', 'AAA28'],
        'bl01' => ['EMR_BL_BL01', 'JZHM'],
    ];

    public static function getList(
        $patientInfo, $patientHospitalInfo, $patientDoctorInfo, $mainDiagnosis,
        $otherDiagnosis, $mainOperation, $secondaryOperation, $patientMedicalInfo,
        $icu, $patientAdd, $emrBl01, $bllbInfo, $isExport, $offset, $limit,
        $LNSSQ, $LNSSH, $error, $zyts, $page, $patientMedicalInfoAEL01,
        $operation, $bm, $rangeName
    ) {
        $query = PatientInfo::query();

        $hospitalGroups = self::mergeGroups(
            $patientHospitalInfo,
            self::onlyFields($zyts, ['AAB01']),
            self::onlyFields($patientInfo, ['AAC02C', 'AAB02C', 'AAD01C'])
        );
        $patientInfo = self::exceptFields($patientInfo, ['AAC02C', 'AAB02C', 'AAD01C']);

        $directGroups = self::mergeGroups(
            $patientInfo,
            self::onlyFields($zyts, ['AAC01', 'AAA04', 'AAA40', 'AAC04'])
        );
        self::applyDirectGroups($query, $directGroups, 'patient_info');

        self::applyRelationGroups($query, 'patient_hospital_info', $hospitalGroups);
        self::applyRelationGroups($query, 'patient_doctor_info', $patientDoctorInfo);
        self::applyRelationGroups($query, 'main_diagnosis', $mainDiagnosis);
        self::applyRelationGroups($query, 'other_diagnosis', $otherDiagnosis);
        self::applyRelationGroups($query, 'main_operation', $mainOperation);
        self::applyRelationGroups($query, 'secondary_operation', $secondaryOperation);
        self::applyRelationGroups($query, 'patient_medical_info', $patientMedicalInfo);
        self::applyRelationGroups($query, 'patient_medical_info', $patientMedicalInfoAEL01);
        self::applyRelationGroups($query, 'icu', $icu);
        self::applyRelationGroups($query, 'patient_add', $patientAdd);
        self::applyRelationGroups($query, 'error', $error);
        self::applyRelationGroups($query, 'bl01', self::mergeGroups($emrBl01, $bllbInfo));
        self::applyOperationGroups($query, $operation);
        self::applyComaFilter($query, $LNSSQ, ['AEJ01', 'AEJ02', 'AEJ03']);
        self::applyComaFilter($query, $LNSSH, ['AEJ04', 'AEJ05', 'AEJ06']);
        self::applyCodeRange($query, $bm, $rangeName);
        self::applyGlobalOr($query, $directGroups, [
            'patient_hospital_info' => $hospitalGroups,
            'patient_doctor_info' => $patientDoctorInfo,
            'main_diagnosis' => $mainDiagnosis,
            'other_diagnosis' => $otherDiagnosis,
            'main_operation' => $mainOperation,
            'secondary_operation' => $secondaryOperation,
            'patient_medical_info' => self::mergeGroups($patientMedicalInfo, $patientMedicalInfoAEL01),
            'icu' => $icu,
            'patient_add' => $patientAdd,
            'error' => $error,
            'bl01' => self::mergeGroups($emrBl01, $bllbInfo),
        ], $operation);

        $count = (clone $query)->distinct()->count('patient_info.MED_REC_ID');
        $aggregate = (clone $query)->first([
            DB::raw('AVG(patient_info.AAC04) AS avg_stay'),
            DB::raw('AVG(patient_info.ADA01) AS avg_fee'),
            DB::raw('SUM(patient_info.AAC04) AS sum_stay'),
            DB::raw('SUM(patient_info.ADA01) AS sum_fee'),
        ]);
        $deathCount = (clone $query)->where('patient_info.AEM01C', 5)
            ->distinct()->count('patient_info.MED_REC_ID');

        $realOffset = max(0, ((int)$page - 1) * (int)$limit);
        $ids = (clone $query)
            ->select('patient_info.MED_REC_ID')
            ->distinct()
            ->orderBy('patient_info.AAC01', 'desc')
            ->offset($realOffset)
            ->limit((int)$limit)
            ->pluck('patient_info.MED_REC_ID')
            ->toArray();

        $list = QualityService::getMedicalListInAAA28($ids);
        $order = array_flip($ids);
        usort($list, static function ($a, $b) use ($order) {
            return ($order[$a['MED_REC_ID']] ?? PHP_INT_MAX) <=> ($order[$b['MED_REC_ID']] ?? PHP_INT_MAX);
        });
        $list = QualityService::fee(ToolsService::codeTransformationList(QualityService::$listCode1, $list));

        return [
            'list' => $list,
            'count' => $count,
            'AEM01C' => $deathCount,
            'ARG_STAY' => sprintf('%.2f', $aggregate->avg_stay ?? 0),
            'ARG_F_D' => sprintf('%.2f', $aggregate->avg_fee ?? 0),
            'SUM_ARG_STAY' => sprintf('%.2f', $aggregate->sum_stay ?? 0),
            'SUM_ARG_F_D' => sprintf('%.2f', $aggregate->sum_fee ?? 0),
        ];
    }

    private static function applyDirectGroups($query, array $groups, string $table): void
    {
        foreach ($groups['and'] ?? [] as $item) {
            self::applyItem($query, $item, $table, 'and', false);
        }
        foreach ($groups['no'] ?? [] as $item) {
            self::applyItem($query, $item, $table, 'and', true);
        }
    }

    private static function applyRelationGroups($query, string $relation, array $groups): void
    {
        if (empty($groups)) {
            return;
        }
        foreach (['and' => false, 'no' => true] as $group => $negate) {
            if (empty($groups[$group])) {
                continue;
            }
            self::relationExists($query, $relation, $groups[$group], 'and', $negate);
        }
    }

    private static function relationExists($query, string $relation, array $items, string $boolean, bool $negate): void
    {
        [$table, $foreignKey] = self::RELATIONS[$relation];
        $method = $negate ? 'whereNotExists' : 'whereExists';
        $query->{$method}(function (Builder $sub) use ($table, $foreignKey, $items, $boolean) {
            $sub->select(DB::raw(1))->from($table)
                ->whereColumn($table . '.' . $foreignKey, 'patient_info.MED_REC_ID');
            if ($boolean === 'or') {
                $sub->where(function ($nested) use ($items, $table) {
                    foreach ($items as $item) {
                        self::applyItem($nested, $item, $table, 'or', false);
                    }
                });
            } else {
                foreach ($items as $item) {
                    self::applyItem($sub, $item, $table, 'and', false);
                }
            }
        });
    }

    private static function applyItem($query, $item, string $table, string $boolean, bool $negate): void
    {
        if (isset($item[0]) && is_array($item[0])) {
            $query->{$boolean === 'or' ? 'orWhere' : 'where'}(function ($nested) use ($item, $table, $negate) {
                foreach ($item as $part) {
                    self::applyItem($nested, $part, $table, 'and', $negate);
                }
            });
            return;
        }
        if (empty($item['select_field'])) {
            return;
        }
        $field = self::column($table, $item['select_field']);
        $type = $item['select_type'] ?? 'term';
        $value = $item['field_value'] ?? null;
        $prefix = $boolean === 'or' ? 'or' : '';

        if ($type === 'terms') {
            $method = $prefix . ($negate ? 'WhereNotIn' : 'WhereIn');
            $query->{$method}($field, (array)$value);
        } elseif ($type === 'exists' || $type === 'not_exists') {
            $wantNull = (($type === 'not_exists') xor $negate);
            $query->{$prefix . ($wantNull ? 'WhereNull' : 'WhereNotNull')}($field);
        } elseif (in_array($type, ['gte', 'lte', 'gt', 'lt'], true)) {
            $operators = ['gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<'];
            $query->{$prefix . 'Where'}($field, $negate ? self::invert($operators[$type]) : $operators[$type], $value);
        } elseif ($type === 'match_phrase_prefix') {
            $query->{$prefix . 'Where'}($field, $negate ? 'not like' : 'like', $value . '%');
        } elseif ($type === 'match_phrase') {
            $query->{$prefix . 'Where'}($field, $negate ? 'not like' : 'like', '%' . $value . '%');
        } else {
            $query->{$prefix . 'Where'}($field, $negate ? '<>' : '=', $value);
        }
    }

    private static function applyOperationGroups($query, array $groups): void
    {
        foreach ($groups['and'] ?? [] as $item) {
            $query->where(function ($nested) use ($item) {
                self::relationExists($nested, 'main_operation', [$item], 'and', false);
                $nested->orWhereExists(function (Builder $sub) use ($item) {
                    $sub->select(DB::raw(1))->from('secondary_operation')
                        ->whereColumn('secondary_operation.AAA28', 'patient_info.MED_REC_ID');
                    self::applyItem($sub, $item, 'secondary_operation', 'and', false);
                });
            });
        }
        foreach ($groups['no'] ?? [] as $item) {
            self::relationExists($query, 'main_operation', [$item], 'and', true);
            self::relationExists($query, 'secondary_operation', [$item], 'and', true);
        }
    }

    private static function applyGlobalOr($query, array $directGroups, array $relations, array $operation): void
    {
        $hasOr = !empty($directGroups['or']) || !empty($operation['or']);
        foreach ($relations as $groups) {
            $hasOr = $hasOr || !empty($groups['or']);
        }
        if (!$hasOr) {
            return;
        }

        $query->where(function ($root) use ($directGroups, $relations, $operation) {
            $used = false;
            foreach ($directGroups['or'] ?? [] as $item) {
                self::applyItem($root, $item, 'patient_info', $used ? 'or' : 'and', false);
                $used = true;
            }
            foreach ($relations as $relation => $groups) {
                foreach ($groups['or'] ?? [] as $item) {
                    [$table, $foreignKey] = self::RELATIONS[$relation];
                    $method = $used ? 'orWhereExists' : 'whereExists';
                    $root->{$method}(function (Builder $sub) use ($table, $foreignKey, $item) {
                        $sub->select(DB::raw(1))->from($table)
                            ->whereColumn($table . '.' . $foreignKey, 'patient_info.MED_REC_ID');
                        self::applyItem($sub, $item, $table, 'and', false);
                    });
                    $used = true;
                }
            }
            foreach ($operation['or'] ?? [] as $item) {
                foreach (['main_operation', 'secondary_operation'] as $relation) {
                    [$table, $foreignKey] = self::RELATIONS[$relation];
                    $method = $used ? 'orWhereExists' : 'whereExists';
                    $root->{$method}(function (Builder $sub) use ($table, $foreignKey, $item) {
                        $sub->select(DB::raw(1))->from($table)
                            ->whereColumn($table . '.' . $foreignKey, 'patient_info.MED_REC_ID');
                        self::applyItem($sub, $item, $table, 'and', false);
                    });
                    $used = true;
                }
            }
        });
    }

    private static function applyComaFilter($query, array $filter, array $fields): void
    {
        $mode = $filter['and'] ?? null;
        if (!$mode) {
            return;
        }
        $query->whereExists(function (Builder $sub) use ($mode, $fields) {
            $sub->select(DB::raw(1))->from('patient_medical_info')
                ->whereColumn('patient_medical_info.AAA28', 'patient_info.MED_REC_ID')
                ->where(function ($nested) use ($mode, $fields) {
                    foreach ($fields as $field) {
                        $method = $mode === 'exists' ? 'orWhereNotNull' : 'whereNull';
                        $nested->{$method}('patient_medical_info.' . $field);
                    }
                });
        });
    }

    private static function applyCodeRange($query, array $bm, $rangeName): void
    {
        if (empty($bm['regexp']) || !is_numeric($rangeName)) {
            return;
        }
        $map = [1 => ['main_diagnosis', 'ICD10_ID1'], 2 => ['other_diagnosis', 'ICD10_ID1'],
            3 => ['main_operation', 'ICD9_ID1'], 4 => ['secondary_operation', 'ICD9_ID1'],
            5 => ['main_operation', 'ICD9_ID1'], 6 => ['main_diagnosis', 'ICD10_ID1']];
        if (!isset($map[(int)$rangeName])) {
            return;
        }
        [$relation, $field] = $map[(int)$rangeName];
        [$table, $foreignKey] = self::RELATIONS[$relation];
        $query->whereExists(function (Builder $sub) use ($table, $foreignKey, $field, $bm) {
            $sub->select(DB::raw(1))->from($table)
                ->whereColumn($table . '.' . $foreignKey, 'patient_info.MED_REC_ID')
                ->whereRaw($table . '.' . $field . ' REGEXP ?', [$bm['regexp']]);
        });
    }

    private static function onlyFields(array $groups, array $fields): array
    {
        return self::filterFields($groups, $fields, true);
    }

    private static function exceptFields(array $groups, array $fields): array
    {
        return self::filterFields($groups, $fields, false);
    }

    private static function filterFields(array $groups, array $fields, bool $include): array
    {
        $result = [];
        foreach (['and', 'or', 'no'] as $group) {
            foreach ($groups[$group] ?? [] as $item) {
                $field = $item['select_field'] ?? '';
                if (in_array($field, $fields, true) === $include) {
                    $result[$group][] = $item;
                }
            }
        }
        return $result;
    }

    private static function mergeGroups(...$sets): array
    {
        $result = [];
        foreach ($sets as $groups) {
            foreach (['and', 'or', 'no'] as $group) {
                $result[$group] = array_merge($result[$group] ?? [], $groups[$group] ?? []);
            }
        }
        return $result;
    }

    private static function column(string $table, string $field): string
    {
        $field = str_replace('.keyword', '', $field);
        $parts = explode('.', $field);
        return $table . '.' . end($parts);
    }

    private static function invert(string $operator): string
    {
        return ['>=' => '<', '<=' => '>', '>' => '<=', '<' => '>='][$operator];
    }
}
