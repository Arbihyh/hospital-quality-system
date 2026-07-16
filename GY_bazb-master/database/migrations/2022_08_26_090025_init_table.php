<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class InitTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //主治医师数据表
        if (!Schema::hasTable('indications_data')){
            Schema::create('indications_data',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->integer('year')->index()->comment('年份');
                $table->integer('month')->index()->comment('月份');
                $table->integer('indications_id')->index()->comment('主治医师编码');
                $table->integer('total_medical')->default(0)->comment('总病案数');
                $table->integer('total_error_medical')->default(0)->comment('累计有问题病案数');
                $table->integer('error_medical')->default(0)->comment('现存有问题病案数');
                $table->integer('logic_error_medical')->default(0)->comment('逻辑性问题病案数');
                $table->integer('standard_error_medical')->default(0)->comment('规范性有问题病案数');
                $table->integer('code_error_medical')->default(0)->comment('编码性有问题病案数');
                $table->tinyInteger('status')->default(0)->comment('状态0：正常1：停用');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //主诊组数据表
        if (!Schema::hasTable('attending_group_data')){
            Schema::create('attending_group_data',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->integer('year')->index()->comment('年份');
                $table->integer('month')->index()->comment('月份');
                $table->integer('attending_group_id')->index()->comment('主诊组ID');
                $table->integer('total_medical')->default(0)->comment('总病案数');
                $table->integer('total_error_medical')->default(0)->comment('累计有问题病案数');
                $table->integer('error_medical')->default(0)->comment('现存有问题病案数');
                $table->integer('complete_error_medical')->default(0)->comment('完整性问题病案数');
                $table->integer('logic_error_medical')->default(0)->comment('逻辑性问题病案数');
                $table->integer('standard_error_medical')->default(0)->comment('规范性有问题病案数');
                $table->integer('code_error_medical')->default(0)->comment('编码性有问题病案数');
                $table->tinyInteger('status')->default(0)->comment('状态0：正常1：停用');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //住院医师数据表
        if (!Schema::hasTable('hospital_data')){
            Schema::create('hospital_data',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->integer('year')->index()->comment('年份');
                $table->integer('month')->index()->comment('月份');
                $table->integer('hospital_id')->index()->comment('住院医师编码');
                $table->integer('total_medical')->default(0)->comment('总病案数');
                $table->integer('total_error_medical')->default(0)->comment('累计有问题病案数');
                $table->integer('error_medical')->default(0)->comment('现存有问题病案数');
                $table->integer('logic_error_medical')->default(0)->comment('逻辑性问题病案数');
                $table->integer('standard_error_medical')->default(0)->comment('规范性有问题病案数');
                $table->integer('code_error_medical')->default(0)->comment('编码性有问题病案数');
                $table->tinyInteger('status')->default(0)->comment('状态0：正常1：停用');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //编码员数据表
        if (!Schema::hasTable('coder_data')){
            Schema::create('coder_data',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->integer('year')->index()->comment('年份');
                $table->integer('month')->index()->comment('月份');
                $table->integer('coder_id')->index()->comment('编码员编码');
                $table->integer('total_medical')->default(0)->comment('总病案数');
                $table->integer('code_error_medical')->default(0)->comment('编码问题病案数');
                $table->integer('total_error_medical')->default(0)->comment('累计有问题病案数');
                $table->integer('error_medical')->default(0)->comment('现存有问题病案数');
                $table->integer('scores')->default(0)->comment('编码员分数');
                $table->tinyInteger('status')->default(0)->comment('状态0：正常1：停用');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //科室数据
        if (!Schema::hasTable('department_data')){
            Schema::create('department_data',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->integer('year')->index()->comment('年份');
                $table->integer('month')->index()->comment('月份');
                $table->integer('department_id')->index()->comment('科室ID');
                $table->integer('total_medical')->default(0)->comment('总病案数');
                $table->integer('total_error')->default(0)->comment('总缺陷数');
                $table->float('total_score',8,1)->default(0)->comment('总得分');
                $table->integer('error_exist')->default(0)->comment('现存缺陷数');
                $table->integer('max_score')->default(0)->comment('最高得分');
                $table->integer('min_score')->default(0)->comment('最低得分');
                $table->integer('total_error_medical')->default(0)->comment('累计有问题病案数');
                $table->integer('error_medical')->default(0)->comment('现存有问题病案数');
                $table->tinyInteger('status')->default(0)->comment('状态0：正常1：停用');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //病案统计
        if (!Schema::hasTable('count')){
            Schema::create('count',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->integer('year')->index()->comment('年份');
                $table->integer('month')->index()->comment('月份');
                $table->integer('total_medical')->default(0)->comment('总病案数');
                $table->integer('cumulative_medical')->default(0)->comment('累计有问题病案数');
                $table->integer('error_medical')->default(0)->comment('现存有问题病案数');
                $table->integer('average_error')->default(0)->comment('平均缺陷');
                $table->integer('total_score')->default(0)->comment('总得分');
                $table->integer('total_score_qa')->default(0)->comment('质控后总得分');
                $table->integer('excellent')->default(0)->comment('优(>90)');
                $table->integer('excellent_qa')->default(0)->comment('质控后-优(>90)');
                $table->integer('good')->default(0)->comment('良(70-90)');
                $table->integer('good_qa')->default(0)->comment('质控后-良(70-90)');
                $table->integer('fail')->default(0)->comment('差(<70)');
                $table->integer('fail_qa')->default(0)->comment('质控后-差(<70)');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //病案分数
        if (!Schema::hasTable('patient_score')){
            Schema::create('count',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->default('')->comment('住院号');
                $table->float('score',8,1)->default(0)->comment('病案得分');
                $table->tinyInteger('is_error')->default(0)->comment('是否问题病案1是0否');
                $table->tinyInteger('level')->default(0)->comment('病案等级');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者信息
        if (!Schema::hasTable('patient_info')){
            Schema::create('patient_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('唯一标识');
                $table->string('AAA28',20)->index()->comment('病案号');
                $table->string('AAA01',50)->comment('患者姓名');
                $table->integer('AAA02C')->comment('患者性别');
                $table->string('AAA03',50)->comment('出生日期');
                $table->integer('AAA04')->comment('年龄');
                $table->string('AAA05C',4)->comment('国籍');
                $table->integer('AAA40')->comment('不足一周岁年龄');
                $table->string('AAA42',10)->comment('新生儿入院体重(克)');
                $table->string('AEN01',10)->comment('新生儿出生体重(克)');
                $table->string('AAA06C',10)->comment('民族代码');
                $table->string('AAA07',20)->comment('身份证号');
                $table->string('AAA08C',4)->comment('婚姻状况代码');
                $table->string('AEM01C',4)->comment('离院方式代码');
                $table->string('AAC01',20)->comment('出院时间');
                $table->string('AAB01',20)->comment('入院时间');
                $table->string('AAC11N',100)->comment('出院医院内部科室名称');
                $table->integer('AAC04')->comment('实际住院(天)');
                $table->string('ADA01',20)->comment('总费用');
                $table->string('ADA0101',20)->comment('自付金额');
                $table->integer('AAA29')->comment('住院次数');
                $table->string('ABG01C')->comment('损伤和中毒外部原因编码');
                $table->string('ABG01N')->comment('损伤和中毒外部原因名称');
                $table->string('AAB06C',20)->comment('入院途径代码');
                $table->string('ABC01N',200)->comment('出院主要诊断名称');
                $table->string('AAA26C',32)->comment('医疗付费方式代码');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者联系信息
        if (!Schema::hasTable('patient_other_info')){
            Schema::create('patient_other_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('AAB07C',20)->index()->comment('入院诊断id');
                $table->string('AAB07N',200)->index()->comment('入院诊断名称');
                $table->string('AAB07',20)->index()->comment('入院时情况');
                $table->string('AAB07D',20)->index()->comment('入院后确诊日期');
                $table->string('ABD04',200)->index()->comment('医院感染名称');
                $table->string('ABD051',20)->index()->comment('门诊与出院诊断符合情况');
                $table->string('ABD052',20)->index()->comment('术前与术后诊断符合情况');
                $table->string('ABD053',20)->index()->comment('临床与病理诊断符合情况');
                $table->string('ABD054',20)->index()->comment('放射与病理诊断符合情况');
                $table->string('ZB09',20)->index()->comment('手机');
                $table->string('ZB08',32)->comment('邮箱');
                $table->string('ZB07',200)->comment('记录数');
                $table->string('ZB06',20)->comment('填报日期');
                $table->string('ZB05',20)->comment('填报人联系电话	');
                $table->string('ZB04',200)->comment('填报人');
                $table->string('ZB03',20)->comment('数据月份');
                $table->string('ZB02',20)->comment('数据年份');
                $table->string('ZB01C',20)->comment('报表代码ID');
                $table->string('ZA04',20)->comment('单位负责人');
                $table->string('MED_REC_ID',20)->comment('病案首页ID');//?
                $table->string('UNT_ID',20)->comment('组织机构代码');
                $table->string('ZA03',20)->comment('医疗机构名称');
                $table->integer('AFA01')->comment('抢救次数');
                $table->integer('AFA02')->comment('成本次数');
                $table->string('AFA03',4)->comment('HBsAg');
                $table->string('AFA04',4)->comment('HCV-Ab');
                $table->string('AFA05',4)->comment('HIV-Ab');
                $table->string('AFA06',4)->comment('是否随诊');
                $table->string('AFA07',4)->comment('是否求教病例');
                $table->string('AFA08',4)->comment('是否输血反应');
                $table->integer('AFA09')->comment('随诊期限周');
                $table->integer('AFA10')->comment('随诊期限月');
                $table->integer('AFA11')->comment('随诊期限年');
                $table->string('AFA12',4)->comment('手术治疗检查诊断为本院第一例');
                $table->string('ZB10',20)->comment('填报版本');
                $table->text('ZB11')->comment('填报说明');
                $table->string('IS_VALID',4)->comment('有效标识');
                $table->string('SYN_DATE',20)->comment('获取时间');
                $table->string('QU_STATE',4)->comment('是否采集,00:未采集、');
                $table->string('DATA_STATE',4)->comment('病案采集状态,00:新增、01：修改、02：删除');
                $table->string('BALANCEID',20)->index()->comment('病案流水号');
                $table->string('AKC021',10)->index()->comment('人群类型');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者医疗信息
        if (!Schema::hasTable('patient_medical_info')){
            Schema::create('patient_medical_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('ABA01C',32)->comment('门(急)诊诊断编码');
                $table->string('ABA01N',200)->comment('门（急）诊诊断名称');
                $table->string('ABC03C',32)->comment('入院病情代码');
                $table->string('ABF01C',32)->comment('病理诊断编码(M码)ID');
                $table->string('ABF01N',200)->comment('病理诊断名称');
                $table->string('ABF04',50)->comment('病理号');
                $table->string('ABF02C',32)->comment('最高诊断依据代码');
                $table->string('ABF03C',32)->comment('分化程度编码');
                $table->string('ABH01C',4)->comment('肿瘤分期是否不详');
                $table->string('ABH0201C',32)->comment('肿瘤分期 TID');
                $table->string('ABH0202C',32)->comment('肿瘤分期 NID');
                $table->string('ABH0203C',32)->comment('肿瘤分期 MID');
                $table->string('ABH03C',200)->comment('0～Ⅳ肿瘤分期ID');
                $table->string('AEB02C',4)->comment('有无药物过敏');
                $table->string('AEB01',200)->comment('过敏药物');
                $table->string('AED01C',200)->comment('病案质量代码');
                $table->string('AEG01C',4)->comment('血型代码');
                $table->string('AEG02C',4)->comment('Rh 代码');
                $table->string('AEG04',20)->comment('红细胞(单位)');
                $table->string('AEG05',20)->comment('血小板(袋)');
                $table->string('AEG06',20)->comment('血浆(ml)');
                $table->string('AEG07',20)->comment('全血(ml)');
                $table->string('AEG08',20)->comment('其它(ml)');
                $table->string('AEJ01',20)->comment('颅脑损伤患者入院前昏迷时间（天）');
                $table->string('AEJ02',20)->comment('颅脑损伤患者入院前昏迷时间（小时）');
                $table->string('AEJ03',20)->comment('颅脑损伤患者入院前昏迷时间（分钟）');
                $table->string('AEJ04',20)->comment('颅脑损伤患者入院后昏迷时间（天）');
                $table->string('AEJ05',20)->comment('颅脑损伤患者入院后昏迷时间（小时）');
                $table->string('AEJ06',20)->comment('颅脑损伤患者入院后昏迷时间（分钟）');
                $table->string('AEL01',20)->comment('呼吸机使用时间(小时)');
                $table->string('AEN02C',32)->comment('新生儿出生缺陷诊断');
                $table->string('AEN02N',100)->comment('新生儿出生缺陷诊断名称');
                $table->string('AEI09',4)->comment('日常生活能力评定量得分（入院）');
                $table->string('AEI10',4)->comment('日常生活能力评定量得分（出院）');
                $table->text('AEI08')->comment('备注');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者住院信息
        if (!Schema::hasTable('patient_hospital_info')){
            Schema::create('patient_hospital_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('AAA30',32)->comment('住院号');
                $table->string('ABC01C',32)->comment('出院时主要诊断编码');
                $table->string('AAA27',40)->comment('医疗保险手册(卡)号');
                $table->string('AAC001',40)->comment('医保个人编号');
                $table->string('AAB01',20)->comment('入院时间（时）');
                $table->string('AAB02C',32)->comment('入院科别代码');
                $table->string('AAB03',200)->comment('入院病房');
                $table->string('AAB11C',32)->comment('入院医院内部科室代码');
                $table->string('AAB11N',100)->comment('入院医院内部科室名称');
                $table->string('AAC02C',32)->comment('出院科别代码');
                $table->string('AAC03',200)->comment('出院病房');
                $table->string('AAC11C',32)->comment('出院医院内部科室代码');
                $table->string('AAD01C',32)->comment('转经科别代码');
                $table->string('AEM02',132)->comment('医嘱转院、转社区、卫生院机编码ID');
                $table->string('AEM03C',4)->comment('是否有出院31日内再住院计划');
                $table->string('AEM04',512)->comment('31日内再住院目的');
                $table->string('AEI01C',4)->comment('是否尸检代码');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者医生信息
        if (!Schema::hasTable('patient_doctor_info')){
            Schema::create('patient_doctor_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('AED02',40)->comment('质控医师姓名');
                $table->string('AED03',40)->comment('质控护士姓名');
                $table->string('AED04',20)->comment('病案质量检查日期');
                $table->string('AEE01',40)->comment('科主任姓名');
                $table->string('AEE01_CODE',40)->comment('科主任编码');
                $table->string('AEE02',40)->comment('主(副主)任医师姓名');
                $table->string('AEE03',40)->comment('主治医师姓名');
                $table->string('AEE11',27)->comment('主诊医师执业证书编码');
                $table->string('AEE09',40)->comment('主诊医师姓名');
                $table->string('AEE04',40)->comment('住院医师姓名');
                $table->string('AEE05',40)->comment('进修医师姓名');
                $table->string('AEE07',40)->comment('实习医师姓名');
                $table->string('AEE08',40)->comment('编码员姓名');
                $table->string('AEE10',40)->comment('责任护士姓名');
                $table->string('CODE_DATE',20)->comment('编码完成时间');
                $table->string('COMPLETION_DATE',20)->comment('病历完成时间');
                $table->string('SIGN_IN_DATE',20)->comment('病案签收时间');
                $table->string('QUALITY_CONTROL',20)->comment('终末质控完成时间');
                $table->string('AEE02_CODE',50)->comment('主（副主）任医师工号');
                $table->string('AEE03_CODE',50)->comment('主治医师工号');
                $table->string('AEE04_CODE',50)->comment('住院医师工号');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者地址相关信息
        if (!Schema::hasTable('patient_address_info')){
            Schema::create('patient_address_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('AAA09',50)->comment('出生地省（区、市）');
                $table->string('AAA10',50)->comment('出生地市');
                $table->string('AAA11',50)->comment('出生地县');
                $table->string('AAA43',50)->comment('籍贯省（区、市）');
                $table->string('AAA44',50)->comment('籍贯市');
                $table->string('AAA45',50)->comment('户籍省（区、市）');
                $table->string('AAA46',50)->comment('户籍市');
                $table->string('AAA47',50)->comment('户籍县');
                $table->string('AAA12',200)->comment('户籍详细地址');
                $table->string('AAA13C',6)->comment('户籍地址区县编码');
                $table->string('AAA33C',12)->comment('户籍街道乡镇代码');
                $table->string('AAA14C',6)->comment('户籍地址邮政编码');
                $table->string('AAA15',200)->comment('现住址详细地址（居住半年以上）');
                $table->string('AAA48',50)->comment('现住址省（区、市）');
                $table->string('AAA49',50)->comment('现住址市');
                $table->string('AAA50',50)->comment('现住址县');
                $table->string('AAA16C',6)->comment('现住址区县编码');
                $table->string('AAA36C',12)->comment('现住址街道乡镇代码');
                $table->string('AAA51',20)->comment('现住址电话');
                $table->string('AAA17C',6)->comment('现住址邮政编码');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者工作信息
        if (!Schema::hasTable('patient_work_info')){
            Schema::create('patient_work_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('AAA18C',32)->comment('职业代码');
                $table->string('AAA19',200)->comment('工作单位及地址');
                $table->string('AAA20',20)->comment('工作单位电话');
                $table->string('AAA21C',50)->comment('工作单位邮政编码');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者联系人信息
        if (!Schema::hasTable('patient_contacts_info')){
            Schema::create('patient_contacts_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('AAA22',50)->comment('联系人姓名');
                $table->string('AAA23C',32)->comment('联系人关系代码');
                $table->string('AAA24',200)->comment('联系人地址');
                $table->string('AAA25',50)->comment('联系人电话');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者重症信息
        if (!Schema::hasTable('icu')){
            Schema::create('icu',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->integer('IS_MAIN_WAY')->comment('重症监护室代码');
                $table->string('IN_TIME',20)->comment('监护室进入日期时间');
                $table->string('OUT_TIME',20)->comment('监护室退出日期时间');
                $table->string('AREA_ID',32)->comment('所属区域ID');
                $table->string('BATCH_ID',32)->comment('导入批次号');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者主要诊断信息
        if (!Schema::hasTable('main_diagnosis')){
            Schema::create('main_diagnosis',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('ICD10_ID1',50)->comment('诊断编码');
                $table->string('ICD10_NAME',200)->comment('出院诊断名称');
                $table->integer('DIA_ORDER')->comment('诊断次序');
                $table->string('AREA_ID',32)->comment('所属区域ID');
                $table->string('BATCH_ID',32)->comment('导入批次号');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者其他诊断信息
        if (!Schema::hasTable('other_diagnosis')){
            Schema::create('other_diagnosis',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('ICD10_ID1',50)->comment('诊断编码');
                $table->string('ICD10_NAME',200)->comment('出院诊断名称');
                $table->integer('DIA_ORDER')->comment('诊断次序');
                $table->string('AREA_ID',32)->comment('所属区域ID');
                $table->string('BATCH_ID',32)->comment('导入批次号');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者费用信息
        if (!Schema::hasTable('patient_cost_info')){
            Schema::create('patient_cost_info',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('ADA0101',20)->comment('自付金额');
                $table->string('AAE040',20)->comment('结算时间');
                $table->string('D11',10)->comment('一般医疗服务费');
                $table->string('D12',10)->comment('一般治疗操作费');
                $table->string('D13',10)->comment('护理费');
                $table->string('D14',10)->comment('综合医疗服务类其他费用');
                $table->string('D15',10)->comment('病理诊断费');
                $table->string('D16',10)->comment('实验室诊断费');
                $table->string('D17',10)->comment('影像学诊断费');
                $table->string('D18',10)->comment('临床诊断项目费');
                $table->string('D19',10)->comment('非手术治疗项目费');
                $table->string('D19X01',10)->comment('其中:临床物理治疗费');
                $table->string('D20',10)->comment('手术治疗费');
                $table->string('D20X01',10)->comment('其中：麻醉费');
                $table->string('D20X02',10)->comment('其中：手术费');
                $table->string('D21',10)->comment('康复费');
                $table->string('D22',10)->comment('中医治疗费');
                $table->string('D23',10)->comment('西药费');
                $table->string('D23X01',10)->comment('其中：抗菌药物费');
                $table->string('D24',10)->comment('中成药费');
                $table->string('D25',10)->comment('中草药费');
                $table->string('D26',10)->comment('血费');
                $table->string('D27',10)->comment('白蛋白类制品费');
                $table->string('D28',10)->comment('球蛋白类制品费');
                $table->string('D29',10)->comment('凝血因子类制品费');
                $table->string('D30',10)->comment('细胞因子类制品费');
                $table->string('D31',10)->comment('检查用一次性医用材料费');
                $table->string('D32',10)->comment('治疗用一次性医用材料费');
                $table->string('D33',10)->comment('手术用一次性医用材料费');
                $table->string('D34',10)->comment('其他费');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者主要手术信息
        if (!Schema::hasTable('main_operation')){
            Schema::create('main_operation',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('AAA28',20)->index()->comment('病案号');
                $table->string('ICD9_ID1',20)->comment('手术或操作ID');
                $table->string('ICD9_NAME',100)->comment('手术或操作名称');
                $table->string('OPE_DATE',20)->comment('手术或操作日期');
                $table->string('OPE_MAN_NAME',50)->comment('主刀医师姓名');
                $table->string('OPE_MAN_CODE',50)->comment('主刀医师编码');
                $table->string('FRIST_ASSISTANT_CODE',50)->comment('一助医师编码');
                $table->string('FRIST_ASSISTANT_NAME',50)->comment('一助医师姓名');
                $table->string('SECOND_ASSISTANT_CODE',50)->comment('二助医师编码');
                $table->string('SECOND_ASSISTANT_NAME',10)->comment('二助医师姓名');
                $table->string('HOCUS_WAY_ID',32)->comment('麻醉方式');
                $table->string('INCISION_GRADE_ID',32)->comment('切口愈合等级');
                $table->string('HOCUS_MAN_CODE',50)->comment('麻醉医师编码');
                $table->string('HOCUS_MAN_NAME',50)->comment('麻醉医师名称');
                $table->string('START_TIME',20)->comment('手术开始时间');
                $table->string('END_TIME',20)->comment('手术结束时间');
                $table->integer('OPE_ORDER')->comment('手术顺序号');
                $table->integer('OPE_LEVEL')->comment('手术级别');
                $table->string('RJSS')->comment('是否为日间手术');
                $table->string('AREA_ID',10)->comment('所属区域ID');
                $table->string('BATCH_ID',32)->comment('导入批次号');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //患者次要要手术信息
        if (!Schema::hasTable('secondary_operation')){
            Schema::create('secondary_operation',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('AAA28',20)->index()->comment('病案号');
                $table->string('ICD9_ID1',20)->comment('手术或操作ID');
                $table->string('ICD9_NAME',100)->comment('手术或操作名称');
                $table->string('OPE_DATE',20)->comment('手术或操作日期');
                $table->string('OPE_MAN_NAME',50)->comment('主刀医师姓名');
                $table->string('OPE_MAN_CODE',50)->comment('主刀医师编码');
                $table->string('FRIST_ASSISTANT_CODE',50)->comment('一助医师编码');
                $table->string('FRIST_ASSISTANT_NAME',50)->comment('一助医师姓名');
                $table->string('SECOND_ASSISTANT_CODE',50)->comment('二助医师编码');
                $table->string('SECOND_ASSISTANT_NAME',10)->comment('二助医师姓名');
                $table->string('HOCUS_WAY_ID',32)->comment('麻醉方式');
                $table->string('INCISION_GRADE_ID',32)->comment('切口愈合等级');
                $table->string('HOCUS_MAN_CODE',50)->comment('麻醉医师编码');
                $table->string('HOCUS_MAN_NAME',50)->comment('麻醉医师名称');
                $table->string('START_TIME',20)->comment('手术开始时间');
                $table->string('END_TIME',20)->comment('手术结束时间');
                $table->integer('OPE_ORDER')->comment('手术顺序号');
                $table->integer('OPE_LEVEL')->comment('手术级别');
                $table->string('RJSS')->comment('是否为日间手术');
                $table->string('AREA_ID',10)->comment('所属区域ID');
                $table->string('BATCH_ID',32)->comment('导入批次号');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        //质控错误
        if (!Schema::hasTable('error')){
            Schema::create('error',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->integer('year')->index()->comment('年份');
                $table->integer('month')->index()->comment('月份');
                $table->string('ZYH',20)->index()->comment('病案号');
                $table->string('desc',512)->comment('缺陷描述');
                $table->string('error_field',20)->index()->comment('缺陷字段');
                $table->string('error_name',20)->index()->comment('缺陷字段名称');
                $table->tinyInteger('level')->default(0)->comment('缺陷等级');
                $table->tinyInteger('type')->default(0)->comment('缺陷分类0患者基本信息1诊疗信息2费用信息');
                $table->tinyInteger('error_type')->default(0)->comment('缺陷类型0逻辑性1规范性2编码');
                $table->integer('error_rule')->index()->comment('错误规则ID');
                $table->float('down',2,1)->comment('扣分');
                $table->string('coder_id')->comment('编码员ID');
                $table->string('AAC11C')->comment('科室编码');
                $table->tinyInteger('status')->default(0)->comment('修改状态');
                $table->tinyInteger('source')->default(0)->comment('来源 0-病案 1-结算清单');
                $table->tinyInteger('category')->default(0)->comment('缺陷类别0:A类,1:B类,2:C类,3D类');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        if (!Schema::hasTable('error_rule')){
            Schema::create('error_rule',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->string('auth',20)->index()->comment('验证字段');
                $table->string('field',20)->index()->comment('验证字段名称');
                $table->string('rule',512)->comment('验证规则');
                $table->string('relation',20)->comment('关联字段');
                $table->string('relation_rule',512)->comment('关联规则');
                $table->tinyInteger('level')->comment('错误等级');
                $table->string('desc',512)->comment('规则描述');
                $table->float('down',2,1)->comment('扣分');
                $table->tinyInteger('type')->default(0)->comment('缺陷分类0患者基本信息1诊疗信息2费用信息');
                $table->tinyInteger('error_type')->default(0)->comment('缺陷类型0逻辑性1规范性2编码');
                $table->tinyInteger('category')->default(0)->comment('缺陷类别0:A类,1:B类,2:C类,3D类');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
        if (!Schema::hasTable('error_data')){
            Schema::create('error_data',function (Blueprint $table){
                $table->bigIncrements('id');
                $table->integer('error_rule')->index()->comment('错误规则ID');
                $table->integer('count')->comment('病案缺陷数量');
                $table->integer('year')->index()->comment('年份');
                $table->integer('month')->index()->comment('月份');
                $table->tinyInteger('type')->default(0)->comment('缺陷分类0患者基本信息1诊疗信息2费用信息');
                $table->tinyInteger('source')->default(0)->comment('来源 0-病案 1-结算清单');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('AAC01')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
