<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddHomePageFieldsToTableDictSy extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('table_dict_sy')
            ->where('parent_field', 907)
            ->whereIn('field', [
                'TNM',
                'FYLX',
                'HXB',
                'XXB',
                'LCD',
                'XJ',
                'QX',
                'ZTX_HXB',
                'CCS',
                'SSS',
                'HSS',
                'HBS',
                'HCV_AB',
                'HIV_AB',
                'TP_AB',
                'SSRJSS',
                'SFFJHZRY',
            ])
            ->delete();

        $rows = [
            ['parent_field' => 901, 'field' => 'TNM', 'field_name' => 'TNM/肿瘤分期', 'field_v2' => 'TNM', 'field_zk' => 'TNM', 'sort' => 139],
            ['parent_field' => 901, 'field' => 'FYLX', 'field_name' => '反应类型', 'field_v2' => 'FYLX', 'field_zk' => 'FYLX', 'sort' => 140],
            ['parent_field' => 901, 'field' => 'HXB', 'field_name' => '红细胞（u）', 'field_v2' => 'HXB', 'field_zk' => 'HXB', 'sort' => 141],
            ['parent_field' => 901, 'field' => 'XXB', 'field_name' => '血小板（u）', 'field_v2' => 'XXB', 'field_zk' => 'XXB', 'sort' => 142],
            ['parent_field' => 901, 'field' => 'LCD', 'field_name' => '冷沉淀（u）', 'field_v2' => 'LCD', 'field_zk' => 'LCD', 'sort' => 143],
            ['parent_field' => 901, 'field' => 'XJ', 'field_name' => '血浆（ml）', 'field_v2' => 'XJ', 'field_zk' => 'XJ', 'sort' => 144],
            ['parent_field' => 901, 'field' => 'QX', 'field_name' => '全血（u）', 'field_v2' => 'QX', 'field_zk' => 'QX', 'sort' => 145],
            ['parent_field' => 901, 'field' => 'ZTX_HXB', 'field_name' => '自体血（红细胞）（ml）', 'field_v2' => 'ZTX_HXB', 'field_zk' => 'ZTX_HXB', 'sort' => 146],
            ['parent_field' => 901, 'field' => 'CCS', 'field_name' => '储存式（ml）', 'field_v2' => 'CCS', 'field_zk' => 'CCS', 'sort' => 147],
            ['parent_field' => 901, 'field' => 'SSS', 'field_name' => '稀释式（ml）', 'field_v2' => 'SSS', 'field_zk' => 'SSS', 'sort' => 148],
            ['parent_field' => 901, 'field' => 'HSS', 'field_name' => '回收式（ml）', 'field_v2' => 'HSS', 'field_zk' => 'HSS', 'sort' => 149],
            ['parent_field' => 901, 'field' => 'HBS', 'field_name' => 'HBsAg', 'field_v2' => 'HBS', 'field_zk' => 'HBS', 'sort' => 150],
            ['parent_field' => 901, 'field' => 'HCV_AB', 'field_name' => 'HCV-Ab', 'field_v2' => 'HCV_AB', 'field_zk' => 'HCV_AB', 'sort' => 151],
            ['parent_field' => 901, 'field' => 'HIV_AB', 'field_name' => 'HIV-Ab', 'field_v2' => 'HIV_AB', 'field_zk' => 'HIV_AB', 'sort' => 152],
            ['parent_field' => 901, 'field' => 'TP_AB', 'field_name' => 'TP-Ab', 'field_v2' => 'TP_AB', 'field_zk' => 'TP_AB', 'sort' => 153],
            ['parent_field' => 901, 'field' => 'SSRJSS', 'field_name' => '是否为日间病房', 'field_v2' => 'SSRJSS', 'field_zk' => 'SSRJSS', 'sort' => 154],
            ['parent_field' => 901, 'field' => 'SFFJHZRY', 'field_name' => '是否非计划再入院', 'field_v2' => 'SFFJHZRY', 'field_zk' => 'SFFJHZRY', 'sort' => 155],
            ['parent_field' => 912, 'field' => 'SFFJHZRY', 'field_name' => '主手术是否为日间手术', 'field_v2' => 'SFFJHZRY', 'field_zk' => 'SFFJHZRY', 'sort' => 164],
            ['parent_field' => 912, 'field' => 'SFWRJBF', 'field_name' => '主手术是否为日间操作', 'field_v2' => 'SFWRJBF', 'field_zk' => 'SFWRJBF', 'sort' => 165],
            ['parent_field' => 912, 'field' => 'SFFJHZSS', 'field_name' => '主手术是否非计划再手术', 'field_v2' => 'SFFJHZSS', 'field_zk' => 'SFFJHZSS', 'sort' => 166],
            ['parent_field' => 913, 'field' => 'SFFJHZRY', 'field_name' => '其他手术是否为日间手术', 'field_v2' => 'SFFJHZRY', 'field_zk' => 'SFFJHZRY', 'sort' => 181],
            ['parent_field' => 913, 'field' => 'SFWRJBF', 'field_name' => '其他手术是否为日间操作', 'field_v2' => 'SFWRJBF', 'field_zk' => 'SFWRJBF', 'sort' => 182],
            ['parent_field' => 913, 'field' => 'SFFJHZSS', 'field_name' => '其他手术是否非计划再手术', 'field_v2' => 'SFFJHZSS', 'field_zk' => 'SFFJHZSS', 'sort' => 183],
        ];

        foreach ($rows as $row) {
            DB::table('table_dict_sy')->updateOrInsert(
                [
                    'parent_field' => $row['parent_field'],
                    'field' => $row['field'],
                ],
                [
                    'field_name' => $row['field_name'],
                    'status' => 1,
                    'type' => 2,
                    'remark' => '',
                    'zyh_field' => '',
                    'ispush' => '0',
                    'sort' => $row['sort'],
                    'field_v2' => $row['field_v2'],
                    'field_zk' => $row['field_zk'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('table_dict_sy')
            ->where(function ($query) {
                $query->where('parent_field', 901)
                    ->whereIn('field', [
                        'TNM',
                        'FYLX',
                        'HXB',
                        'XXB',
                        'LCD',
                        'XJ',
                        'QX',
                        'ZTX_HXB',
                        'CCS',
                        'SSS',
                        'HSS',
                        'HBS',
                        'HCV_AB',
                        'HIV_AB',
                        'TP_AB',
                        'SSRJSS',
                        'SFFJHZRY',
                    ]);
            })
            ->orWhere(function ($query) {
                $query->whereIn('parent_field', [912, 913])
                    ->whereIn('field', ['SFFJHZRY', 'SFWRJBF', 'SFFJHZSS']);
            })
            ->delete();
    }
}
