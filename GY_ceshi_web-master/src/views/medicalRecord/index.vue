<template>
  <div class="bg-box" style="position: relative" :class="{ has_control: controlData.bSwitch }">
    <div class="bg-card">
      <el-row v-show="zyh === '0' && this.typePage != 'search'" type="flex" justify="space-between">
        <el-button type="primary" class="export-btn" size="small" @click="onReset">重新质控</el-button>
        <el-button type="primary" class="export-btn" size="small" @click="onToggle">质控结果</el-button>
      </el-row>
      <el-row type="flex" class="content">
        <div class="content-from" style="flex: 1">
          <h3 class="title">住院病案首页</h3>
          <div class="zyh">病案编号：{{ blInfo.patient_info.AAA28 }}</div>
          <!-- 基本信息 -->
          <table class="table">
            <tr>
              <td class="label ZA03" :class="{ jy: hasIntersection(zk_codes.jy, ['ZA03']), qz: hasIntersection(zk_codes.qz, ['ZA03']) }">医疗机构</td>
              <td colspan="5" ref="ZA03" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ZA03']), qz_bg: hasIntersection(active_zk_codes.qz, ['ZA03']) }">
                {{ blInfo.patient_other_info.ZA03 }}
              </td>
              <td class="label TYSHXYDM UNT_ID" :class="{ jy: hasIntersection(zk_codes.jy, ['TYSHXYDM', 'UNT_ID']), qz: hasIntersection(zk_codes.qz, ['TYSHXYDM', 'UNT_ID']) }">
                统一社会信用代码
              </td>
              <td
                colspan="5"
                ref="TYSHXYDM"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['TYSHXYDM', 'UNT_ID']), qz_bg: hasIntersection(active_zk_codes.qz, ['TYSHXYDM', 'UNT_ID']) }"
              >
                <NoValueInputVue :data="blInfo.patient_add.TYSHXYDM" />
                （组织机构机构代码：
                <NoValueInputVue :data="blInfo.patient_other_info.UNT_ID" />
                ）
              </td>
            </tr>
            <tr>
              <td class="label AAA26C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA26C']), qz: hasIntersection(zk_codes.qz, ['AAA26C']) }">医疗付费方式</td>
              <td colspan="5" ref="AAA26C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA26C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA26C']) }">
                {{ blInfo.patient_info.AAA26C }}
              </td>
              <td class="label JKKH" :class="{ jy: hasIntersection(zk_codes.jy, ['JKKH']), qz: hasIntersection(zk_codes.qz, ['JKKH']) }">健康卡号</td>
              <td colspan="3" ref="AAA29" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['JKKH']), qz_bg: hasIntersection(active_zk_codes.qz, ['JKKH']) }">
                {{ blInfo.patient_add.JKKH }}
              </td>
              <td class="label AAA29" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA29']), qz: hasIntersection(zk_codes.qz, ['AAA29']) }">住院次数</td>
              <td ref="AAA29" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA29']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA29']) }">
                {{ blInfo.patient_info.AAA29 }}
              </td>
            </tr>
            <tr>
              <td class="label AAA01" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA01']), qz: hasIntersection(zk_codes.qz, ['AAA01']) }">姓名</td>
              <td ref="AAA01" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA01']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA01']) }">
                {{ blInfo.patient_info.AAA01 }}
              </td>
              <td class="label AAA02C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA02C']), qz: hasIntersection(zk_codes.qz, ['AAA02C']) }">性别</td>
              <td ref="AAA02C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA02C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA02C']) }">
                {{ blInfo.patient_info.AAA02C }}
              </td>
              <td class="label AAA03" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA03']), qz: hasIntersection(zk_codes.qz, ['AAA03']) }">出生日期</td>
              <td colspan="3" ref="AAA03" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA03']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA03']) }">
                {{ blInfo.patient_info.AAA03 }}
              </td>
              <td class="label AAA04" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA04']), qz: hasIntersection(zk_codes.qz, ['AAA04']) }">年龄</td>
              <td ref="AAA04" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA04']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA04']) }">
                {{ blInfo.patient_info.AAA04 }}
              </td>
              <td class="label AAA40" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA40']), qz: hasIntersection(zk_codes.qz, ['AAA40']) }">天龄（不足1周岁）</td>
              <td :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA40']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA40']) }">
                {{ blInfo.patient_info.AAA40 }}
              </td>
            </tr>
            <tr>
              <td
                class="label AAA09 AAA10 AAA11"
                :class="{ jy: hasIntersection(zk_codes.jy, ['AAA09', 'AAA10', 'AAA11']), qz: hasIntersection(zk_codes.qz, ['AAA09', 'AAA10', 'AAA11']) }"
              >
                出生地
              </td>
              <td
                colspan="3"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA09', 'AAA10', 'AAA11']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA09', 'AAA10', 'AAA11']) }"
              >
                <span ref="AAA09">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA09 ? blInfo.patient_address_info.AAA09.replace('省', '') : ''" />
                  （省）
                </span>
                <span ref="AAA10">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA10 ? blInfo.patient_address_info.AAA10.replace('市', '') : ''" />
                  （市）
                </span>

                <span ref="AAA11" v-if="blInfo.patient_address_info.AAA11">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA11 ? blInfo.patient_address_info.AAA11.replace('县', '') : ''" />
                  （县）
                </span>
                <span ref="AAA11" v-else-if="blInfo.patient_address_info.AAA11">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA11 ? blInfo.patient_address_info.AAA11.replace('区', '') : ''" />
                  （区）
                </span>
                <span ref="AAA11" v-else>
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA11" />
                  （区）
                </span>
              </td>
              <td class="label AAA43 AAA44" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA43', 'AAA44']), qz: hasIntersection(zk_codes.qz, ['AAA43', 'AAA44']) }">籍贯</td>
              <td colspan="3" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA43', 'AAA44']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA43', 'AAA44']) }">
                <span ref="AAA43">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA43 ? blInfo.patient_address_info.AAA43.replace('省', '') : ''" />
                  （省）
                </span>
                <span ref="AAA44">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA44 ? blInfo.patient_address_info.AAA44.replace('市', '') : ''" />
                  （市）
                </span>
              </td>
              <td class="label AAA06C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA06C']), qz: hasIntersection(zk_codes.qz, ['AAA06C']) }">民族</td>
              <td ref="AAA06C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA06C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA06C']) }">
                {{
                  blInfo.patient_info.AAA06C === null || blInfo.patient_info.AAA06C === undefined || blInfo.patient_info.AAA06C.trim() === ''
                    ? blInfo.patient_info.AAA06C_MC
                    : blInfo.patient_info.AAA06C
                }}
              </td>
              <td class="label AAA05C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA05C']), qz: hasIntersection(zk_codes.qz, ['AAA05C']) }">国籍</td>
              <td ref="AAA05C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA05C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA05C']) }">
                {{ blInfo.patient_info.AAA05C }}
              </td>
            </tr>
            <tr>
              <td class="label SFZJLX" :class="{ jy: hasIntersection(zk_codes.jy, ['SFZJLX']), qz: hasIntersection(zk_codes.qz, ['SFZJLX']) }">身份证件类型</td>
              <td ref="SFZJLX" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['SFZJLX']), qz_bg: hasIntersection(active_zk_codes.qz, ['SFZJLX']) }">
                {{ blInfo.patient_add.SFZJLX }}
              </td>
              <td class="label AAA07" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA07']), qz: hasIntersection(zk_codes.qz, ['AAA07']) }">身份证号</td>
              <td ref="AAA07" colspan="5" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA07']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA07']) }">
                {{ blInfo.patient_info.AAA07 }}
              </td>
              <td class="label AAA18C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA18C']), qz: hasIntersection(zk_codes.qz, ['AAA18C']) }">职业</td>
              <td ref="AAA18C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA18C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA18C']) }">
                {{ blInfo.patient_work_info.AAA18C }}
              </td>
              <td class="label AAA08C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA08C']), qz: hasIntersection(zk_codes.qz, ['AAA08C']) }">婚姻</td>
              <td ref="AAA08C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA08C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA08C']) }">
                {{ blInfo.patient_info.AAA08C }}
              </td>
            </tr>
            <tr>
              <td
                class="label AAA48 AAA49 AAA50 AAA15"
                :class="{ jy: hasIntersection(zk_codes.jy, ['AAA48', 'AAA49', 'AAA50', 'AAA15']), qz: hasIntersection(zk_codes.qz, ['AAA48', 'AAA49', 'AAA50', 'AAA15']) }"
              >
                现住址
              </td>
              <td
                colspan="7"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, ['AAA48', 'AAA49', 'AAA50', 'AAA15']),
                  qz_bg: hasIntersection(active_zk_codes.qz, ['AAA48', 'AAA49', 'AAA50', 'AAA15']),
                }"
              >
                <span ref="AAA48">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA48 ? blInfo.patient_address_info.AAA48.replace('省', '') : ''" />
                  （省）
                </span>

                <span ref="AAA49">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA49 ? blInfo.patient_address_info.AAA49.replace('市', '') : ''" />
                  （市）
                </span>
                <span ref="AAA50" v-if="blInfo.patient_address_info.AAA50">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA50 ? blInfo.patient_address_info.AAA50.replace('县', '') : ''" />
                  （县）
                </span>
                <span ref="AAA50" v-else-if="blInfo.patient_address_info.AAA50">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA50 ? blInfo.patient_address_info.AAA50.replace('区', '') : ''" />
                  （区）
                </span>
                <span ref="AAA50" v-else>
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA50" />
                  （区）
                </span>
                <span ref="AAA15">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA15" />
                </span>
              </td>
              <td class="label AAA51" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA51']), qz: hasIntersection(zk_codes.qz, ['AAA51']) }">电话</td>
              <td ref="AAA51" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA51']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA51']) }">
                {{ blInfo.patient_address_info.AAA51 }}
              </td>
              <td class="label AAA17C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA17C']), qz: hasIntersection(zk_codes.qz, ['AAA17C']) }">邮编</td>
              <td ref="AAA17C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA17C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA17C']) }">
                {{ blInfo.patient_address_info.AAA17C }}
              </td>
            </tr>
            <tr>
              <td
                class="label AAA45 AAA46 AAA47 AAA12"
                :class="{ jy: hasIntersection(zk_codes.jy, ['AAA45', 'AAA46', 'AAA47', 'AAA12']), qz: hasIntersection(zk_codes.qz, ['AAA45', 'AAA46', 'AAA47', 'AAA12']) }"
              >
                户口地址
              </td>
              <td
                colspan="9"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, ['AAA45', 'AAA46', 'AAA47', 'AAA12']),
                  qz_bg: hasIntersection(active_zk_codes.qz, ['AAA45', 'AAA46', 'AAA47', 'AAA12']),
                }"
              >
                <span ref="AAA45">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA45 ? blInfo.patient_address_info.AAA45.replace('省', '') : ''" />
                  （省）
                </span>

                <!-- <span ref="AAA46">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA46 ? blInfo.patient_address_info.AAA46.replace('市', '') : ''" />
                </span> -->

                <span ref="AAA46">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA46 ? blInfo.patient_address_info.AAA46.replace('市', '') : ''" />
                  （市）
                </span>

                <span ref="AAA47" v-if="blInfo.patient_address_info.AAA47">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA47 ? blInfo.patient_address_info.AAA47.replace('县', '') : ''" />
                  （县）
                </span>
                <span ref="AAA47" v-else-if="blInfo.patient_address_info.AAA47">
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA47 ? blInfo.patient_address_info.AAA47.replace('区', '') : ''" />
                  （区）
                </span>
                <span ref="AAA47" v-else>
                  <NoValueInputVue :data="blInfo.patient_address_info.AAA47" />
                  （区）
                </span>
                <NoValueInputVue :data="blInfo.patient_address_info.AAA12" />
              </td>
              <td class="label AAA14C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA14C']), qz: hasIntersection(zk_codes.qz, ['AAA14C']) }">邮编</td>
              <td ref="AAA14C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA14C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA14C']) }">
                {{ blInfo.patient_address_info.AAA14C }}
              </td>
            </tr>
            <tr>
              <td class="label AAA19" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA19']), qz: hasIntersection(zk_codes.qz, ['AAA19']) }">工作单位及地址</td>
              <td ref="AAA19" colspan="7" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA19']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA19']) }">
                {{ blInfo.patient_work_info.AAA19 }}
              </td>
              <td class="label AAA20" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA20']), qz: hasIntersection(zk_codes.qz, ['AAA20']) }">单位电话</td>
              <td ref="AAA20" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA20']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA20']) }">
                {{ blInfo.patient_work_info.AAA20 }}
              </td>
              <td class="label AAA21C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA21C']), qz: hasIntersection(zk_codes.qz, ['AAA21C']) }">邮编</td>
              <td ref="AAA21C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA21C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA21C']) }">
                {{ blInfo.patient_work_info.AAA21C }}
              </td>
            </tr>
            <tr>
              <td class="label AAA22" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA22']), qz: hasIntersection(zk_codes.qz, ['AAA22']) }">联系人姓名</td>
              <td ref="AAA22" colspan="3" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA22']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA22']) }">
                {{ blInfo.patient_contacts_info.AAA22 }}
              </td>
              <td class="label AAA24" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA24']), qz: hasIntersection(zk_codes.qz, ['AAA24']) }">地址</td>
              <td ref="AAA24" colspan="3" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA24']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA24']) }">
                {{ blInfo.patient_contacts_info.AAA24 }}
              </td>
              <td class="label AAA23C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA23C']), qz: hasIntersection(zk_codes.qz, ['AAA23C']) }">关系</td>
              <td ref="AAA23C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA23C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA23C']) }">
                {{ blInfo.patient_contacts_info.AAA23C }}
              </td>
              <td class="label AAA25" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA25']), qz: hasIntersection(zk_codes.qz, ['AAA25']) }">电话</td>
              <td ref="AAA25" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA25']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA25']) }">
                {{ blInfo.patient_contacts_info.AAA25 }}
              </td>
            </tr>
            <tr>
              <td class="label AAB06C ZZYLJG" :class="{ jy: hasIntersection(zk_codes.jy, ['AAB06C', 'ZZYLJG']), qz: hasIntersection(zk_codes.qz, ['AAB06C', 'ZZYLJG']) }">
                入院途径
              </td>
              <td
                ref="AAB06C"
                colspan="11"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAB06C', 'ZZYLJG']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAB06C', 'ZZYLJG']) }"
              >
                <span ref="AAB06C" class="square">{{ blInfo.patient_info.AAB06C }}</span>
                <span class="mlr10" />
                1.急诊
                <span class="mlr10" />
                2.门诊
                <span class="mlr10" />
                3.其他医疗机构转入：
                <span ref="ZZYLJG">
                  <NoValueInputVue :data="blInfo.patient_info.QTZRMC" />
                </span>

                <span class="mlr10" />
                9.其他
              </td>
            </tr>
            <tr>
              <td class="label AEN01" :class="{ jy: hasIntersection(zk_codes.jy, ['AEN01']), qz: hasIntersection(zk_codes.qz, ['AEN01']) }">新生儿出生体重</td>
              <td ref="AEN01" colspan="5" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEN01']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEN01']) }">
                <NoValueInputVue :data="blInfo.patient_info.AEN01" />
                克
              </td>
              <td class="label AAA42" :class="{ jy: hasIntersection(zk_codes.jy, ['AAA42']), qz: hasIntersection(zk_codes.qz, ['AAA42']) }">新生儿入院体重</td>
              <td ref="AAA42" colspan="5" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAA42']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAA42']) }">
                <NoValueInputVue :data="blInfo.patient_info.AAA42" />
                克
              </td>
            </tr>
            <tr>
              <td class="label AAB01" :class="{ jy: hasIntersection(zk_codes.jy, ['AAB01']), qz: hasIntersection(zk_codes.qz, ['AAB01']) }">入院时间</td>
              <td ref="AAB01" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAB01']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAB01']) }">
                {{ blInfo.patient_info.AAB01 }}
              </td>
              <td class="label AAB02C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAB02C']), qz: hasIntersection(zk_codes.qz, ['AAB02C']) }">入院科别</td>
              <td ref="AAB02C" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAB02C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAB02C']) }">
                {{ blInfo.patient_hospital_info.AAB02C }}
              </td>
              <td class="label AAB11N" :class="{ jy: hasIntersection(zk_codes.jy, ['AAB11N']), qz: hasIntersection(zk_codes.qz, ['AAB11N']) }">病房</td>
              <td ref="AAB11N" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAB11N']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAB11N']) }">
                {{ blInfo.patient_hospital_info.AAB11N }}
              </td>
              <td class="label AAD01C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAD01C']), qz: hasIntersection(zk_codes.qz, ['AAD01C']) }">转科科别</td>
              <td ref="ZKKB" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAD01C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAD01C']) }">
                {{ displayZKKBMC }}
              </td>
            </tr>
            <tr>
              <td class="label AAC01" :class="{ jy: hasIntersection(zk_codes.jy, ['AAC01']), qz: hasIntersection(zk_codes.qz, ['AAC01']) }">出院时间</td>
              <td ref="AAC01" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAC01']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAC01']) }">
                {{ blInfo.patient_info.AAC01 }}
              </td>
              <td class="label AAC02C" :class="{ jy: hasIntersection(zk_codes.jy, ['AAC02C']), qz: hasIntersection(zk_codes.qz, ['AAC02C']) }">出院科别</td>
              <td ref="AAC02C" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAC02C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAC02C']) }">
                {{ blInfo.patient_hospital_info.AAC02C }}
              </td>
              <td class="label AAC11N" :class="{ jy: hasIntersection(zk_codes.jy, ['AAC11N']), qz: hasIntersection(zk_codes.qz, ['AAC11N']) }">病房</td>
              <td ref="AAC11N" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAC11N']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAC11N']) }">
                {{ blInfo.patient_info.AAC11N }}
              </td>
              <td class="label AAC04" :class="{ jy: hasIntersection(zk_codes.jy, ['AAC04']), qz: hasIntersection(zk_codes.qz, ['AAC04']) }">实际住院天数</td>
              <td ref="AAC04" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AAC04']), qz_bg: hasIntersection(active_zk_codes.qz, ['AAC04']) }">
                {{ blInfo.patient_info.AAC04 }}
              </td>
            </tr>
            <tr>
              <td class="label ABA01C" :class="{ jy: hasIntersection(zk_codes.jy, ['ABA01N']), qz: hasIntersection(zk_codes.qz, ['ABA01N']) }">门(急)诊诊断</td>
              <td ref="ABA01N" colspan="5" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ABA01N']), qz_bg: hasIntersection(active_zk_codes.qz, ['ABA01N']) }">
                {{ blInfo.patient_medical_info.ABA01N }}
              </td>
              <td class="label ABA01N" :class="{ jy: hasIntersection(zk_codes.jy, ['ABA01C']), qz: hasIntersection(zk_codes.qz, ['ABA01C']) }">疾病编码</td>
              <td ref="ABA01C" colspan="5" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ABA01C']), qz_bg: hasIntersection(active_zk_codes.qz, ['ABA01C']) }">
                {{ blInfo.patient_medical_info.ABA01C }}
              </td>
            </tr>
          </table>
          <div class="oper-box">
            <IconBtnVue>
              <el-image class="icon_btn" :src="require('../../assets/images/sort.png')" fit="contain"></el-image>
            </IconBtnVue>
            <IconBtnVue style="margin-right: 0">
              <el-image class="icon_btn" :src="require('../../assets/images/setting.png')" fit="contain"></el-image>
            </IconBtnVue>
          </div>
          <!-- 诊断信息 -->
          <table class="table">
            <tr>
              <th class="label" colspan="3" :class="{ jy: judgeZD(zk_codes.jy, 'ICD10_NAME'), qz: judgeZD(zk_codes.qz, 'ICD10_NAME') }">出院诊断</th>
              <th class="label" :class="{ jy: judgeZD(zk_codes.jy, 'ICD10_ID1'), qz: judgeZD(zk_codes.qz, 'ICD10_ID1') }">疾病编码</th>
              <th class="label" :class="{ jy: judgeZD(zk_codes.jy, 'RYQK'), qz: judgeZD(zk_codes.qz, 'RYQK') }">入院病情</th>
              <th class="label" colspan="3" :class="{ jy: judgeZD(zk_codes.jy, 'ICD10_NAME'), qz: judgeZD(zk_codes.qz, 'ICD10_NAME') }">出院诊断</th>
              <th class="label" :class="{ jy: judgeZD(zk_codes.jy, 'ICD10_ID1'), qz: judgeZD(zk_codes.qz, 'ICD10_ID1') }">疾病编码</th>
              <th class="label" :class="{ jy: judgeZD(zk_codes.jy, 'RYQK'), qz: judgeZD(zk_codes.qz, 'RYQK') }">入院病情</th>
            </tr>
            <template v-if="Array.isArray(zdList) && zdList.length > 0">
              <tr v-for="i of zdRows" :key="'zd' + i">
                <td
                  ref="ABC01N"
                  colspan="3"
                  :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`zd-${i - 1}-ICD10_NAME`]), qz_bg: hasIntersection(active_zk_codes.qz, [`zd-${i - 1}-ICD10_NAME`]) }"
                >
                  {{ zdList[i - 1] ? `${zdLevel(i)}` + (zdList[i - 1].ICD10_NAME ? zdList[i - 1].ICD10_NAME : '') : '' }}
                </td>
                <td
                  ref="ICD10_ID1"
                  class="center"
                  :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`zd-${i - 1}-ICD10_ID1`]), qz_bg: hasIntersection(active_zk_codes.qz, [`zd-${i - 1}-ICD10_ID1`]) }"
                >
                  {{ zdList[i - 1] ? zdList[i - 1].ICD10_ID1 : '' }}
                </td>
                <td
                  ref="RYQK"
                  class="center"
                  :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`zd-${i - 1}-RYQK`]), qz_bg: hasIntersection(active_zk_codes.qz, [`zd-${i - 1}-RYQK`]) }"
                >
                  {{ zdList[i - 1] ? zdList[i - 1].RYQK : '' }}
                </td>
                <td
                  ref="ICD10_NAME"
                  colspan="3"
                  :class="{
                    jy_bg: hasIntersection(active_zk_codes.jy, [`zd-${zdRows + i - 1}-ICD10_NAME`]),
                    qz_bg: hasIntersection(active_zk_codes.qz, [`zd-${zdRows + i - 1}-ICD10_NAME`]),
                  }"
                >
                  {{ zdList[zdRows + i - 1] ? `${zdLevel(zdRows + i - 1)}` + zdList[zdRows + i - 1].ICD10_NAME : i === 1 ? '其他诊断：' : '' }}
                </td>
                <td
                  ref="ICD10_ID1"
                  class="center"
                  :class="{
                    jy_bg: hasIntersection(active_zk_codes.jy, [`zd-${zdRows + i - 1}-ICD10_ID1`]),
                    qz_bg: hasIntersection(active_zk_codes.qz, [`zd-${zdRows + i - 1}-ICD10_ID1`]),
                  }"
                >
                  {{ zdList[zdRows + i - 1] ? zdList[zdRows + i - 1].ICD10_ID1 : '' }}
                </td>
                <td
                  ref="RYQK"
                  class="center"
                  :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`zd-${zdRows + i - 1}-RYQK`]), qz_bg: hasIntersection(active_zk_codes.qz, [`zd-${zdRows + i - 1}-RYQK`]) }"
                >
                  {{ zdList[zdRows + i - 1] ? zdList[zdRows + i - 1].RYQK : '' }}
                </td>
              </tr>
            </template>

            <tr>
              <!-- <td class="label">入院病情</td> -->
              <td colspan="10">
                入院病情：1.有
                <span class="mlr10" />
                2.临床未确定
                <span class="mlr10" />
                3.情况不明
                <span class="mlr10" />
                4.无
              </td>
            </tr>
            <tr>
              <td class="label ABG01N" :class="{ jy: hasIntersection(zk_codes.jy, ['ABG01N']), qz: hasIntersection(zk_codes.qz, ['ABG01N']) }">损伤、中毒的外部原因</td>
              <td ref="ABG01N" colspan="7" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ABG01N']), qz_bg: hasIntersection(active_zk_codes.qz, ['ABG01N']) }">
                {{ blInfo.patient_info.ABG01N }}
              </td>
              <td class="label ABG01C" :class="{ jy: hasIntersection(zk_codes.jy, ['ABG01C']), qz: hasIntersection(zk_codes.qz, ['ABG01C']) }">疾病编码</td>
              <td ref="ABG01C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ABG01C']), qz_bg: hasIntersection(active_zk_codes.qz, ['ABG01C']) }">
                {{ blInfo.patient_info.ABG01C }}
              </td>
            </tr>
            <tr>
              <td class="label ABF01N" :class="{ jy: hasIntersection(zk_codes.jy, ['ABF01N']), qz: hasIntersection(zk_codes.qz, ['ABF01N']) }">病理诊断</td>
              <td ref="ABF01N" colspan="7" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ABF01N']), qz_bg: hasIntersection(active_zk_codes.qz, ['ABF01N']) }">
                {{ blInfo.patient_medical_info.ABF01N }}
              </td>
              <td class="label ABF01C" :class="{ jy: hasIntersection(zk_codes.jy, ['ABF01C']), qz: hasIntersection(zk_codes.qz, ['ABF01C']) }">疾病编码</td>
              <td ref="ABF01C" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ABF01C']), qz_bg: hasIntersection(active_zk_codes.qz, ['ABF01C']) }">
                {{ blInfo.patient_medical_info.ABF01C }}
              </td>
            </tr>
            <tr>
              <td class="label ABF02C" :class="{ jy: hasIntersection(zk_codes.jy, ['ABF02C']), qz: hasIntersection(zk_codes.qz, ['ABF02C']) }">最高诊断依据</td>
              <td ref="ABF02C" colspan="4" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ABF02C']), qz_bg: hasIntersection(active_zk_codes.qz, ['ABF02C']) }">
                {{ blInfo.patient_medical_info.ABF02C }}
              </td>
              <td class="label ABF04" :class="{ jy: hasIntersection(zk_codes.jy, ['ABF04']), qz: hasIntersection(zk_codes.qz, ['ABF04']) }">病理号</td>
              <td ref="ABF04" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ABF04']), qz_bg: hasIntersection(active_zk_codes.qz, ['ABF04']) }">
                {{ blInfo.patient_medical_info.ABF04 }}
              </td>
              <td class="label ICD" :class="{ jy: hasIntersection(zk_codes.jy, ['ICD']), qz: hasIntersection(zk_codes.qz, ['ICD']) }">ICD-0-3</td>
              <td ref="ICD" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ICD']), qz_bg: hasIntersection(active_zk_codes.qz, ['ICD']) }">
                {{ blInfo.patient_add.ICD }}
              </td>
            </tr>
            <tr>
              <td class="label AEB02C AEB01" :class="{ jy: hasIntersection(zk_codes.jy, ['AEB02C', 'AEB01']), qz: hasIntersection(zk_codes.qz, ['AEB02C', 'AEB01']) }">药物过敏</td>
              <td
                ref="AEB02C"
                colspan="4"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEB02C', 'AEB01']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEB02C', 'AEB01']) }"
              >
                <span class="square">{{ blInfo.patient_medical_info.AEB02C }}</span>
                <span ref="AEB02C" class="mlr10" />
                1.无
                <span ref="AEB01" class="mlr10" />
                2.有
                <span class="mlr10" />
                3.-
                <span style="margin-left: 40px">过敏药物：</span>
                <span ref="AEB01">
                  <NoValueInputVue :data="blInfo.patient_medical_info.AEB01" />
                </span>
              </td>
              <td class="label AEI01C" :class="{ jy: hasIntersection(zk_codes.jy, ['AEI01C']), qz: hasIntersection(zk_codes.qz, ['AEI01C']) }">死亡患者尸检</td>
              <td ref="AEI01C" colspan="4" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEI01C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEI01C']) }">
                <span class="square">{{ blInfo.patient_add.SFCH }}</span>
                <span class="mlr10" />
                1.是
                <span class="mlr10" />
                2.否
                <span class="mlr10" />
                3.-
              </td>
            </tr>
            <tr>
              <td class="label AEG01C" :class="{ jy: hasIntersection(zk_codes.jy, ['AEG01C']), qz: hasIntersection(zk_codes.qz, ['AEG01C']) }">血型</td>
              <td ref="AEG01C" colspan="4" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEG01C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEG01C']) }">
                <span class="square">{{ blInfo.patient_medical_info.AEG01C }}</span>
                <span class="mlr10" />
                1.A
                <span class="mlr10" />
                2.B
                <span class="mlr10" />
                3.O
                <span class="mlr10" />
                4.AB
                <span class="mlr10" />
                5.不详
                <span class="mlr10" />
                6.未查
              </td>
              <td class="label AEG02C" :class="{ jy: hasIntersection(zk_codes.jy, ['AEG02C']), qz: hasIntersection(zk_codes.qz, ['AEG02C']) }">Rh</td>
              <td ref="AEG02C" colspan="4" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEG02C']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEG02C']) }">
                <span class="square">{{ blInfo.patient_medical_info.AEG02C }}</span>
                <span class="mlr10" />
                1.阴
                <span class="mlr10" />
                2.阳
                <span class="mlr10" />
                3.不详
                <span class="mlr10" />
                4.未查
              </td>
            </tr>
            <tr>
              <td
                class="label TJHL YJHL EJHL SJHL"
                :class="{ jy: hasIntersection(zk_codes.jy, ['TJHL', 'YJHL', 'EJHL', 'SJHL']), qz: hasIntersection(zk_codes.qz, ['TJHL', 'YJHL', 'EJHL', 'SJHL']) }"
              >
                护理级别
              </td>
              <td
                colspan="9"
                ref="TJHL"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, ['TJHL', 'YJHL', 'EJHL', 'SJHL']),
                  qz_bg: hasIntersection(active_zk_codes.qz, ['TJHL', 'YJHL', 'EJHL', 'SJHL']),
                }"
              >
                <span ref="TJHL">
                  1.特级护理
                  <NoValueInputVue :data="blInfo.patient_add.TJHL" />
                  天
                </span>
                <!-- <span></span>" class="mlr10" /> -->
                <span ref="YJHL">
                  2.一级护理
                  <NoValueInputVue :data="blInfo.patient_add.YJHL" />
                  天
                </span>
                <!-- <span class="mlr10" /> -->
                <span ref="EJHL">
                  3.二级护理
                  <NoValueInputVue :data="blInfo.patient_add.EJHL" />
                  天
                </span>

                <!-- <span class="mlr10" /> -->
                <span ref="SJHL">
                  4.三级护理
                  <NoValueInputVue :data="blInfo.patient_add.SJHL" />
                  天
                </span>
              </td>
            </tr>
            <tr>
              <td class="label AEE01" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE01']), qz: hasIntersection(zk_codes.qz, ['AEE01']) }">科主任</td>
              <td ref="AEE01" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE01']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE01']) }">
                {{ blInfo.patient_doctor_info.AEE01 }}
              </td>
              <td class="label AEE01_CODE" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE01_CODE']), qz: hasIntersection(zk_codes.qz, ['AEE01_CODE']) }">科主任编码</td>
              <td ref="AEE01_CODE" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE01_CODE']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE01_CODE']) }">
                {{ blInfo.patient_doctor_info.AEE01_CODE }}
              </td>
              <td class="label ZZYISXM" :class="{ jy: hasIntersection(zk_codes.jy, ['ZZYISXM']), qz: hasIntersection(zk_codes.qz, ['ZZYISXM']) }">医疗组长</td>
              <td ref="ZZYISXM" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ZZYISXM']), qz_bg: hasIntersection(active_zk_codes.qz, ['ZZYISXM']) }">
                {{ blInfo.patient_doctor_info.ZZYISXM }}
              </td>
              <td class="label AEE02" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE02']), qz: hasIntersection(zk_codes.qz, ['AEE02']) }">主任(副主任)医师</td>
              <td ref="AEE02" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE02']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE02']) }">
                {{ blInfo.patient_doctor_info.AEE02 }}
              </td>
              <td class="label AEE02_CODE" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE02_CODE']), qz: hasIntersection(zk_codes.qz, ['AEE02_CODE']) }">主任(副主任)医师编码</td>
              <td ref="AEE02_CODE" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE02_CODE']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE02_CODE']) }">
                {{ blInfo.patient_doctor_info.AEE02_CODE }}
              </td>
            </tr>
            <tr>
              <td class="label AEE03" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE03']), qz: hasIntersection(zk_codes.qz, ['AEE03']) }">主治医师</td>
              <td ref="AEE03" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE03']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE03']) }">
                {{ blInfo.patient_doctor_info.AEE03 }}
              </td>
              <td class="label AEE03_CODE" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE03_CODE']), qz: hasIntersection(zk_codes.qz, ['AEE03_CODE']) }">主治医师编码</td>
              <td ref="AEE03_CODE" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE03_CODE']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE03_CODE']) }">
                {{ blInfo.patient_doctor_info.AEE03_CODE }}
              </td>
              <td class="label AEE04" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE04']), qz: hasIntersection(zk_codes.qz, ['AEE04']) }">住院医师</td>
              <td ref="AEE04" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE04']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE04']) }">
                {{ blInfo.patient_doctor_info.AEE04 }}
              </td>
              <td class="label AEE04_CODE" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE04_CODE']), qz: hasIntersection(zk_codes.qz, ['AEE04_CODE']) }">住院医师编码</td>
              <td ref="AEE04_CODE" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE04_CODE']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE04_CODE']) }">
                {{ blInfo.patient_doctor_info.AEE04_CODE }}
              </td>
              <td class="label"></td>
              <td></td>
            </tr>
            <tr>
              <td class="label AEE10" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE10']), qz: hasIntersection(zk_codes.qz, ['AEE10']) }">责任护士</td>
              <td ref="AEE10" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE10']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE10']) }">
                {{ blInfo.patient_doctor_info.AEE10 }}
              </td>
              <td class="label AEE10_CODE" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE10_CODE']), qz: hasIntersection(zk_codes.qz, ['AEE10_CODE']) }">责任护士编码</td>
              <td ref="AEE10_CODE" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE10_CODE']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE10_CODE']) }">
                {{ blInfo.patient_add.ZRHSBM }}
              </td>
              <td class="label AEE05" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE05']), qz: hasIntersection(zk_codes.qz, ['AEE05']) }">进修医生</td>
              <td ref="AEE05" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE05']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE05']) }">
                {{ blInfo.patient_doctor_info.AEE05 }}
              </td>
              <td class="label AEE07" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE07']), qz: hasIntersection(zk_codes.qz, ['AEE07']) }">实习医师</td>
              <td ref="AEE07" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE07']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE07']) }">
                {{ blInfo.patient_doctor_info.AEE07 }}
              </td>
              <td class="label AEE08" :class="{ jy: hasIntersection(zk_codes.jy, ['AEE08']), qz: hasIntersection(zk_codes.qz, ['AEE08']) }">编码员</td>
              <td ref="AEE08" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEE08']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEE08']) }">
                {{ blInfo.patient_doctor_info.AEE08 }}
              </td>
            </tr>
            <tr>
              <td class="label AED01C" :class="{ jy: hasIntersection(zk_codes.jy, ['AED01C']), qz: hasIntersection(zk_codes.qz, ['AED01C']) }">病案质量</td>
              <td ref="AED01C" colspan="3">
                <span class="square">{{ blInfo.patient_medical_info.AED01C }}</span>
                <span class="mlr10" />
                1.甲
                <span class="mlr10" />
                2.乙
                <span class="mlr10" />
                3.丙
              </td>
              <td class="label AED02" :class="{ jy: hasIntersection(zk_codes.jy, ['AED02']), qz: hasIntersection(zk_codes.qz, ['AED02']) }">质控医师</td>
              <td ref="AED02" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AED02']), qz_bg: hasIntersection(active_zk_codes.qz, ['AED02']) }">
                {{ blInfo.patient_doctor_info.AED02 }}
              </td>
              <td class="label AED03" :class="{ jy: hasIntersection(zk_codes.jy, ['AED03']), qz: hasIntersection(zk_codes.qz, ['AED03']) }">质控护士</td>
              <td ref="AED03" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AED03']), qz_bg: hasIntersection(active_zk_codes.qz, ['AED03']) }">
                {{ blInfo.patient_doctor_info.AED03 }}
              </td>
              <td class="label AED04" :class="{ jy: hasIntersection(zk_codes.jy, ['AED04']), qz: hasIntersection(zk_codes.qz, ['AED04']) }">质控时间</td>
              <td ref="AED04" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AED04']), qz_bg: hasIntersection(active_zk_codes.qz, ['AED04']) }">
                {{ blInfo.patient_doctor_info.AED04 }}
              </td>
            </tr>
          </table>
          <div class="oper-box">
            <IconBtnVue>
              <el-image class="icon_btn" :src="require('../../assets/images/sort.png')" fit="contain"></el-image>
            </IconBtnVue>
            <IconBtnVue style="margin-right: 0">
              <el-image class="icon_btn" :src="require('../../assets/images/setting.png')" fit="contain"></el-image>
            </IconBtnVue>
          </div>
          <!-- 手术信息 -->
          <table class="table shoushu">
            <tr>
              <th rowspan="2" class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'ICD9_ID1'), qz: judgeZD(zk_codes.qz, 'ICD9_ID1') }">手术及操作编码</th>
              <th rowspan="2" class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'OPE_DATE'), qz: judgeZD(zk_codes.qz, 'OPE_DATE') }">手术及操作日期</th>
              <th colspan="2" rowspan="2" class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'ICD9_NAME'), qz: judgeZD(zk_codes.qz, 'ICD9_NAME') }">手术及操作名称</th>
              <th rowspan="2" class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'OPE_LEVEL'), qz: judgeZD(zk_codes.qz, 'OPE_LEVEL') }">手术级别</th>
              <th rowspan="2" class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'OPE_TYPE'), qz: judgeZD(zk_codes.qz, 'OPE_TYPE') }">手术类型</th>
              <th rowspan="2" class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'SSPB'), qz: judgeZD(zk_codes.qz, 'SSPB') }">手术判别</th>
              <th
                colspan="3"
                class="label th25"
                :class="{
                  jy: hasIntersection(zk_codes.jy, ['OPE_MAN_NAME', 'FRIST_ASSISTANT_NAME', 'SECOND_ASSISTANT_NAME']),
                  qz: hasIntersection(zk_codes.qz, ['OPE_MAN_NAME', 'FRIST_ASSISTANT_NAME', 'SECOND_ASSISTANT_NAME']),
                }"
              >
                手术及操作人员
              </th>
              <th rowspan="2" class="label th25" :class="{ jy: hasIntersection(zk_codes.jy, ['QKDJ', 'YHDJ']), qz: hasIntersection(zk_codes.qz, ['QKDJ', 'YHDJ']) }">
                切口愈合等级
              </th>
              <th rowspan="2" class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'HOCUS_WAY_ID'), qz: judgeZD(zk_codes.qz, 'HOCUS_WAY_ID') }">麻醉方式</th>
              <th rowspan="2" class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'HOCUS_MAN_NAME'), qz: judgeZD(zk_codes.qz, 'HOCUS_MAN_NAME') }">麻醉医师</th>
            </tr>
            <tr>
              <th class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'OPE_MAN_NAME'), qz: judgeZD(zk_codes.qz, 'OPE_MAN_NAME') }">术者</th>
              <th class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'FRIST_ASSISTANT_NAME'), qz: judgeZD(zk_codes.qz, 'FRIST_ASSISTANT_NAME') }">Ⅰ助</th>
              <th class="label th25" :class="{ jy: judgeZD(zk_codes.jy, 'SECOND_ASSISTANT_NAME'), qz: judgeZD(zk_codes.qz, 'SECOND_ASSISTANT_NAME') }">Ⅱ助</th>
            </tr>
            <tr v-for="(item, index) of formatSSdata(blInfo.secondary_operation, blInfo.main_operation)" :key="'ss' + index">
              <td
                ref="ICD9_ID1"
                class="center"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-ICD9_ID1`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-ICD9_ID1`]) }"
              >
                {{ item.ICD9_ID1 }}
              </td>
              <td
                ref="OPE_DATE"
                class="center"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-OPE_DATE`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-OPE_DATE`]) }"
              >
                {{ item.OPE_DATE }}
              </td>
              <td
                ref="ICD9_NAME"
                class="center"
                colspan="2"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-ICD9_NAME`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-ICD9_NAME`]) }"
              >
                {{ item.ICD9_NAME }}
              </td>
              <td
                ref="OPE_LEVEL"
                class="center"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-OPE_LEVEL`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-OPE_LEVEL`]) }"
              >
                {{ item.OPE_LEVEL }}
              </td>
              <td
                class="center"
                ref="OPE_TYPE"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-OPE_TYPE`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-OPE_TYPE`]) }"
              >
                {{ item.OPE_TYPE }}
              </td>
              <td
                class="center"
                ref="SSPB"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-SSPB`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-SSPB`]) }"
              >
                {{ item.SSPB }}
              </td>
              <td
                class="center"
                ref="OPE_MAN_NAME"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-OPE_MAN_NAME`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-OPE_MAN_NAME`]) }"
              >
                {{ item.OPE_MAN_NAME }}
              </td>
              <td
                class="center"
                ref="FRIST_ASSISTANT_NAME"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-FRIST_ASSISTANT_NAME`]),
                  qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-FRIST_ASSISTANT_NAME`]),
                }"
              >
                {{ item.FRIST_ASSISTANT_NAME }}
              </td>
              <td
                class="center"
                ref="SECOND_ASSISTANT_NAME"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-SECOND_ASSISTANT_NAME`]),
                  qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-SECOND_ASSISTANT_NAME`]),
                }"
              >
                {{ item.SECOND_ASSISTANT_NAME }}
              </td>
              <td
                class="center"
                ref="QKDJ"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-QKDJ`, `ss-${index}-YHDJ`]),
                  qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-QKDJ`, `ss-${index}-YHDJ`]),
                }"
              >
                {{ item.QKDJ }} / {{ item.YHDJ }}
              </td>
              <td
                class="center"
                ref="HOCUS_WAY_ID"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-HOCUS_WAY_ID`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-HOCUS_WAY_ID`]) }"
              >
                {{ item.HOCUS_WAY_ID }}
              </td>
              <td
                class="center"
                ref="HOCUS_WAY_NAME"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, [`ss-${index}-HOCUS_MAN_NAME`]), qz_bg: hasIntersection(active_zk_codes.qz, [`ss-${index}-HOCUS_MAN_NAME`]) }"
              >
                {{ item.HOCUS_MAN_NAME }}
              </td>
            </tr>
            <template v-if="blInfo.secondary_operation.length < 8">
              <tr v-for="item of 8 - blInfo.secondary_operation.length" :key="'ss2' + item">
                <td class="center"></td>
                <td class="center"></td>
                <td class="center" colspan="2"></td>
                <td class="center"></td>
                <td class="center"></td>
                <td class="center"></td>
                <td class="center"></td>
                <td class="center"></td>
                <td class="center"></td>
                <td class="center"></td>
                <td class="center"></td>
                <td class="center"></td>
              </tr>
            </template>
            <tr>
              <td class="label RJSS" :class="{ jy: hasIntersection(zk_codes.jy, ['RJSS']), qz: hasIntersection(zk_codes.qz, ['RJSS']) }">是否为日间手术</td>
              <td ref="RJSS" colspan="5" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['RJSS']), qz_bg: hasIntersection(active_zk_codes.qz, ['RJSS']) }">
                <span class="square">{{ blInfo.main_operation.RJSS }}</span>
                <span class="mlr10" />
                1.是
                <span class="mlr10" />
                2.否
              </td>
              <td class="label AEL01" :class="{ jy: hasIntersection(zk_codes.jy, ['AEL01']), qz: hasIntersection(zk_codes.qz, ['AEL01']) }">有创呼吸机使用时间</td>
              <td ref="AEL01" colspan="6" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEL01']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEL01']) }">
                {{ blInfo.patient_medical_info.AEL01 }}
              </td>
            </tr>
            <tr>
              <td class="label LCLJ" :class="{ jy: hasIntersection(zk_codes.jy, ['LCLJ']), qz: hasIntersection(zk_codes.qz, ['LCLJ']) }">临床路径：入径情况</td>
              <td ref="LCLJ" colspan="5" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['LCLJ']), qz_bg: hasIntersection(active_zk_codes.qz, ['LCLJ']) }">
                <span class="square">{{ blInfo.patient_add.LCLJ }}</span>
                <span class="mlr10" />
                1.是
                <span class="mlr10" />
                2.否
              </td>
              <td class="label WCQK" :class="{ jy: hasIntersection(zk_codes.jy, ['WCQK']), qz: hasIntersection(zk_codes.qz, ['WCQK']) }">完成情况</td>
              <td ref="WCQK" colspan="2" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['WCQK']), qz_bg: hasIntersection(active_zk_codes.qz, ['WCQK']) }">
                <span class="square">{{ blInfo.patient_add.WCQK }}</span>
                <span class="mlr10" />
                1.完成
                <span class="mlr10" />
                2.退出
              </td>
              <td class="label BYQK" :class="{ jy: hasIntersection(zk_codes.jy, ['BYQK']), qz: hasIntersection(zk_codes.qz, ['BYQK']) }">变异情况</td>
              <td ref="BYQK" colspan="3" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['BYQK']), qz_bg: hasIntersection(active_zk_codes.qz, ['BYQK']) }">
                <span class="square">{{ blInfo.patient_add.BYQK }}</span>
                <span class="mlr10" />
                1.有
                <span class="mlr10" />
                2.无
              </td>
            </tr>
            <tr>
              <td
                class="label AEJ01 AEJ02 AEJ03 AEJ04 AEJ05 AEJ06"
                :class="{
                  jy: hasIntersection(zk_codes.jy, ['AEJ01', 'AEJ02', 'AEJ03', 'AEJ04', 'AEJ05', 'AEJ06']),
                  qz: hasIntersection(zk_codes.qz, ['AEJ01', 'AEJ02', 'AEJ03', 'AEJ04', 'AEJ05', 'AEJ06']),
                }"
              >
                颅脑损伤患者昏迷时间
              </td>
              <td
                colspan="12"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, ['AEJ01', 'AEJ02', 'AEJ03', 'AEJ04', 'AEJ05', 'AEJ06']),
                  qz_bg: hasIntersection(active_zk_codes.qz, ['AEJ01', 'AEJ02', 'AEJ03', 'AEJ04', 'AEJ05', 'AEJ06']),
                }"
              >
                <span ref="AEJ01">
                  入院前：
                  <NoValueInputVue :data="blInfo.patient_medical_info.AEJ01" />
                  天
                </span>
                <span ref="AEJ02">
                  <NoValueInputVue :data="blInfo.patient_medical_info.AEJ02" />
                  小时
                </span>
                <span ref="AEJ03">
                  <NoValueInputVue :data="blInfo.patient_medical_info.AEJ03" />
                  分钟
                </span>

                <span class="mlr10" />
                <span ref="AEJ04">
                  入院后：
                  <NoValueInputVue :data="blInfo.patient_medical_info.AEJ04" />
                  天
                </span>
                <span ref="AEJ05">
                  <NoValueInputVue :data="blInfo.patient_medical_info.AEJ05" />
                  小时
                </span>
                <span ref="AEJ06">
                  <NoValueInputVue :data="blInfo.patient_medical_info.AEJ06" />
                  分钟
                </span>
              </td>
            </tr>
            <tr>
              <th class="label" colspan="4">
                重症监护病房类型
                <br />
                （CCU、RICU、SICU、NICU、PICU、EICU、MICU、其他）
              </th>
              <th class="label" colspan="3">
                进重症监护室时间
                <br />
                年月日时分
              </th>
              <th class="label" colspan="3">
                出重症监护室时间
                <br />
                年月日时分
              </th>
              <th class="label" colspan="3">
                合计
                <br />
                小时
              </th>
            </tr>
            <tr v-for="(item, index) of blInfo.icu" :key="'icu' + index">
              <td ref="IS_MAIN_WAY" colspan="4" class="center">{{ item.IS_MAIN_WAY }}</td>
              <td ref="IN_TIME" colspan="3" class="center">{{ item.IN_TIME }}</td>
              <td ref="OUT_TIME" colspan="3" class="center">{{ item.OUT_TIME }}</td>
              <td ref="HJXS" colspan="3" class="center">{{ item.HJXS }}</td>
            </tr>
            <template v-if="3 - blInfo.icu.length">
              <tr v-for="item of 3 - blInfo.icu.length" :key="'zz' + item">
                <td colspan="4" class="center"></td>
                <td colspan="3" class="center"></td>
                <td colspan="3" class="center"></td>
                <td colspan="3" class="center"></td>
              </tr>
            </template>
            <tr>
              <td class="label AEM01C AEM02" :class="{ jy: hasIntersection(zk_codes.jy, ['AEM01C', 'AEM02']), qz: hasIntersection(zk_codes.qz, ['AEM01C', 'AEM02']) }">离院方式</td>
              <td
                ref="AEM01C"
                colspan="12"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEM01C', 'AEM02']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEM01C', 'AEM02']) }"
              >
                <span class="square">{{ blInfo.patient_info.AEM01C }}</span>
                <span class="mlr10" />
                1.医嘱离院
                <span class="mlr10" />
                2.医嘱转院，拟接收医疗机构名称：
                <NoValueInputVue :data="blInfo.patient_hospital_info.AEM02" />
                <span class="mlr10" />
                3.医嘱转社区卫生服务机构/乡镇卫生院，拟接收医疗机构名称：
                <NoValueInputVue :data="blInfo.patient_hospital_info.AEM02" />
                <span class="mlr10" />
                4.非医嘱离院
                <span class="mlr10" />
                5.死亡
                <span class="mlr10" />
                9.其他
                <!-- <span class="mlr10" />
                1.医嘱离院
                <span class="mlr10" />
                2.医嘱转院，拟接收医疗机构名称：
                <NoValueInputVue :data="blInfo.patient_info.AEM01C == 2 ? blInfo.patient_hospital_info.AEM02 : ''" />
                <span class="mlr10" />
                3.医嘱转社区卫生服务机构/乡镇卫生院，拟接收医疗机构名称：
                <NoValueInputVue :data="blInfo.patient_info.AEM01C == 3 ? blInfo.patient_hospital_info.AEM02 : ''" />
                <span class="mlr10" />
                4.非医嘱离院
                <span class="mlr10" />
                5.死亡
                <span class="mlr10" />
                9.其他 -->
              </td>
            </tr>
            <tr>
              <td class="label AEM03C AEM04" :class="{ jy: hasIntersection(zk_codes.jy, ['AEM03C', 'AEM04']), qz: hasIntersection(zk_codes.qz, ['AEM03C', 'AEM04']) }">
                是否有出院31天内再住院计划
              </td>
              <td
                ref="AEM03C"
                colspan="12"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['AEM03C', 'AEM04']), qz_bg: hasIntersection(active_zk_codes.qz, ['AEM03C', 'AEM04']) }"
              >
                <span class="square">{{ blInfo.patient_hospital_info.AEM03C }}</span>
                <span class="mlr10" />
                1.无
                <span class="mlr10" />
                <span ref="AEM04">
                  2.有，目的：
                  <NoValueInputVue :data="blInfo.patient_hospital_info.AEM04" />
                </span>
              </td>
            </tr>
          </table>
          <div class="oper-box">
            <IconBtnVue style="margin-right: 0">
              <span class="text_btn" @click="toCostPage">查看费用详情</span>
            </IconBtnVue>
          </div>
          <!-- 费用信息 -->
          <table class="table">
            <tr>
              <td class="label ADA01 ADA0101" :class="{ jy: hasIntersection(zk_codes.jy, ['ADA01', 'ADA0101']), qz: hasIntersection(zk_codes.qz, ['ADA01', 'ADA0101']) }">
                住院费用（元）
              </td>
              <td colspan="9" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['ADA01', 'ADA0101']), qz_bg: hasIntersection(active_zk_codes.qz, ['ADA01', 'ADA0101']) }">
                <span ref="ADA01">
                  总费用:
                  <NoValueInputVue :data="blInfo.patient_info.ADA01" />
                </span>
                <span ref="ADA0101">
                  （自付金额：
                  <NoValueInputVue :data="blInfo.patient_cost_info.ADA0101" />
                  ）
                </span>
              </td>
            </tr>
            <tr>
              <td
                class="label D11 D12 D13 D14"
                :class="{ jy: hasIntersection(zk_codes.jy, ['D11', 'D12', 'D13', 'D14']), qz: hasIntersection(zk_codes.qz, ['D11', 'D12', 'D13', 'D14']) }"
              >
                1.综合医疗服务费
              </td>
              <td
                colspan="9"
                ref="D11"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['D11', 'D12', 'D13', 'D14']), qz_bg: hasIntersection(active_zk_codes.qz, ['D11', 'D12', 'D13', 'D14']) }"
              >
                <span ref="D11">
                  （1）一般医疗服务费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D11" />
                </span>

                <span ref="D12">
                  （2）一般治疗操作费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D12" />
                </span>
                <span ref="D13">
                  （3）护理费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D13" />
                </span>

                <span ref="D14">
                  （4）其他费用：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D14" />
                </span>
              </td>
            </tr>
            <tr>
              <td
                class="label D15 D16 D17 D18"
                :class="{ jy: hasIntersection(zk_codes.jy, ['D15', 'D16', 'D17', 'D18']), qz: hasIntersection(zk_codes.qz, ['D15', 'D16', 'D17', 'D18']) }"
              >
                2.诊断类
              </td>
              <td
                colspan="9"
                :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['D15', 'D16', 'D17', 'D18']), qz_bg: hasIntersection(active_zk_codes.qz, ['D15', 'D16', 'D17', 'D18']) }"
              >
                <span ref="D15">
                  （5）病理诊断费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D15" />
                </span>
                <span ref="D16">
                  （6）实验室诊断费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D16" />
                </span>
                <span ref="D17">
                  （7）影像学诊断费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D17" />
                </span>
                <span ref="D18">
                  （8）临床诊断项目费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D18" />
                </span>
              </td>
            </tr>
            <tr>
              <td
                class="label D19 D19X01 D20 D20X01 D20X02"
                :class="{
                  jy: hasIntersection(zk_codes.jy, ['D19', 'D19X01', 'D20', 'D20X01', 'D20X02']),
                  qz: hasIntersection(zk_codes.qz, ['D19', 'D19X01', 'D20', 'D20X01', 'D20X02']),
                }"
              >
                3.治疗类
              </td>
              <td
                colspan="9"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, ['D19', 'D19X01', 'D20', 'D20X01', 'D20X02']),
                  qz_bg: hasIntersection(active_zk_codes.qz, ['D19', 'D19X01', 'D20', 'D20X01', 'D20X02']),
                }"
              >
                <span ref="D19">
                  （9）非手术治疗项目费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D19" />
                </span>
                <span ref="D19X01">
                  （临床物理治疗费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D19X01" />
                </span>
                <span ref="D20">
                  ） （10）手术治疗费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D20" />
                </span>
                <span ref="D20X01">
                  （麻醉费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D20X01" />
                </span>
                <span ref="D20X02">
                  手术费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D20X02" />
                </span>
              </td>
            </tr>
            <tr>
              <td class="label D21" :class="{ jy: hasIntersection(zk_codes.jy, ['D21']), qz: hasIntersection(zk_codes.qz, ['D21']) }">4.康复类</td>
              <td ref="D21" colspan="9" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['D21']), qz_bg: hasIntersection(active_zk_codes.qz, ['D21']) }">
                （11）康复费：
                <NoValueInputVue :data="blInfo.patient_cost_info.D21" />
              </td>
            </tr>
            <tr>
              <td class="label D22" :class="{ jy: hasIntersection(zk_codes.jy, ['D22']), qz: hasIntersection(zk_codes.qz, ['D22']) }">5.中医类</td>
              <td ref="D22" colspan="9" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['D22']), qz_bg: hasIntersection(active_zk_codes.qz, ['D22']) }">
                （12）中医治疗费：
                <NoValueInputVue :data="blInfo.patient_cost_info.D22" />
              </td>
            </tr>
            <tr>
              <td class="label D23 D23X01" :class="{ jy: hasIntersection(zk_codes.jy, ['D23', 'D23X01']), qz: hasIntersection(zk_codes.qz, ['D23', 'D23X01']) }">6.西药类</td>
              <td colspan="9" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['D23', 'D23X01']), qz_bg: hasIntersection(active_zk_codes.qz, ['D23', 'D23X01']) }">
                <span ref="D23">
                  （13）西药费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D23" />
                </span>
                <span ref="D23X01">
                  （抗菌药物费用：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D23X01" />
                </span>
              </td>
            </tr>
            <tr>
              <td class="label D24 D25" :class="{ jy: hasIntersection(zk_codes.jy, ['D24', 'D25']), qz: hasIntersection(zk_codes.qz, ['D24', 'D25']) }">7.中药类</td>
              <td colspan="9" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['D24', 'D25']), qz_bg: hasIntersection(active_zk_codes.qz, ['D24', 'D25']) }">
                <span ref="D24">
                  （14）中成药费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D24" />
                </span>
                <span ref="D25">
                  （15）中草药费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D25" />
                </span>
              </td>
            </tr>
            <tr>
              <td
                class="label D26 D27 D28 D29 D30"
                :class="{ jy: hasIntersection(zk_codes.jy, ['D26', 'D27', 'D28', 'D29', 'D30']), qz: hasIntersection(zk_codes.qz, ['D26', 'D27', 'D28', 'D29', 'D30']) }"
              >
                8.血液和血液制品类
              </td>
              <td
                colspan="9"
                :class="{
                  jy_bg: hasIntersection(active_zk_codes.jy, ['D26', 'D27', 'D28', 'D29', 'D30']),
                  qz_bg: hasIntersection(active_zk_codes.qz, ['D26', 'D27', 'D28', 'D29', 'D30']),
                }"
              >
                <span ref="D26">
                  （16）血费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D26" />
                </span>
                <span ref="D27">
                  （17）白蛋白类制品费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D27" />
                </span>
                <span ref="D28">
                  （18）球蛋白类制品费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D28" />
                </span>
                <span ref="D29">
                  （19）凝血因子类制品费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D29" />
                </span>
                <span ref="D30">
                  （20）细胞因子类制品费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D30" />
                </span>
              </td>
            </tr>
            <tr>
              <td class="label D31 D32 D33" :class="{ jy: hasIntersection(zk_codes.jy, ['D31', 'D32', 'D33']), qz: hasIntersection(zk_codes.qz, ['D31', 'D32', 'D33']) }">
                9.耗材类
              </td>
              <td colspan="9" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['D31', 'D32', 'D33']), qz_bg: hasIntersection(active_zk_codes.qz, ['D31', 'D32', 'D33']) }">
                <span ref="D31">
                  （21）检查用一次性医用材料费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D31" />
                </span>
                <span ref="D32">
                  （22）治疗用一次性医用材料费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D32" />
                </span>
                <span ref="D33">
                  （23）手术用一次性医用材料费：
                  <NoValueInputVue :data="blInfo.patient_cost_info.D33" />
                </span>
              </td>
            </tr>
            <tr>
              <td class="label D34" :class="{ jy: hasIntersection(zk_codes.jy, ['D34']), qz: hasIntersection(zk_codes.qz, ['D34']) }">10.其他类</td>
              <td ref="D34" colspan="9" :class="{ jy_bg: hasIntersection(active_zk_codes.jy, ['D34']), qz_bg: hasIntersection(active_zk_codes.qz, ['D34']) }">
                （24）其他费：
                <NoValueInputVue :data="blInfo.patient_cost_info.D34" />
              </td>
            </tr>
          </table>

          <div v-if="data">
            <div class="bottom-time bottom-time-top">
              <div class="bottom-time-listw">
                <span class="bottom-time-bold">医生签名:</span>
                <span>{{ data.doctor_name }}</span>
              </div>
            </div>
            <div class="bottom-time bottom-time-top">
              <div class="bottom-time-list" v-if="data.CJSJ">
                <span class="bottom-time-bold">创建时间:</span>
                <span>{{ data.CJSJ }}</span>
              </div>
              <div class="bottom-time-list" v-if="data.ZXSJ">
                <span class="bottom-time-bold">首次签名时间:</span>
                <span>{{ data.ZXSJ }}</span>
              </div>
            </div>
            <div class="bottom-time bottom-time-botom" v-if="data.WCSJ">
              <div class="bottom-time-list">
                <span class="bottom-time-bold">末次修改时间:</span>
                <span>{{ data.WCSJ }}</span>
              </div>
              <div class="bottom-time-list">
                <span class="bottom-time-bold">业务时间:</span>
                <span v-if="data.YWSJ">{{ data.YWSJ }}</span>
              </div>
            </div>
          </div>
        </div>

        <ControlDrawerVue
          ref="ControlDrawerVue"
          v-if="controlData.bSwitch"
          :data="controlData"
          @close="controlData.bSwitch = false"
          @zk="handleZK"
          @codes="handleCodes"
          @zkTest="zkTest"
        />
      </el-row>
    </div>
  </div>
</template>

<script>
import ControlDrawerVue from './components/index/ControlDrawer.vue';
import IconBtnVue from './components/index/IconBtn.vue';
import NoValueInputVue from './components/index/NoValueInput.vue';
import axios3 from '@/axios/index3';
export default {
  components: {
    IconBtnVue,
    NoValueInputVue,
    ControlDrawerVue,
  },
  props: {
    zyh: {
      type: String,
      default: '0',
    },
    data: {
      type: null,
      default: false,
    },
  },
  data() {
    return {
      typePage: 'default',
      controlData: {
        bSwitch: false,
        zyh: '',
        rule_id: '',
      },
      blInfo: {
        patient_info: {},
        patient_add: {},
        patient_address_info: {
          id: '',
          AAA28: '',
          AAA09: '',
          AAA10: '',
          AAA11: '',
          AAA43: '',
          AAA44: '',
          AAA45: '',
          AAA46: '',
          AAA47: '',
          AAA48: '',
          AAA49: '',
          AAA50: '',
        },
        patient_contacts_info: {},
        patient_cost_info: {},
        patient_doctor_info: {},
        patient_hospital_info: {},
        patient_medical_info: {},
        patient_other_info: {},
        patient_work_info: {},
        main_diagnosis: {},
        other_diagnosis: [],
        main_operation: {},
        secondary_operation: [],
        icu: [],
      },
      zk_codes: {
        qz: [],
        jy: [],
      },
      active_zk_codes: {
        qz: [],
        jy: [],
      },
    };
  },
  computed: {
    displayZKKBMC() {
      const { patient_hospital_info } = this.blInfo || {};
      if (!patient_hospital_info) return '';
      const zkkbmcValues = Object.keys(patient_hospital_info)
        .filter(key => key.startsWith('ZKKBMC'))
        .map(key => patient_hospital_info[key])
        .filter(value => value && value.trim() !== '');
      return zkkbmcValues.join('，');
    },
    zdList() {
      // 包含主要和其他
      const { other_diagnosis, main_diagnosis } = this.blInfo;
      const other_list = JSON.parse(JSON.stringify(other_diagnosis));
      other_list.unshift(main_diagnosis);
      return other_list;
    },
    // zdRows() {
    //   const count = this.zdList.length / 2;
    //   return count < 11 ? 11 : count;
    // },
    zdRows() {
      // 确保 zdList 是数组
      if (!Array.isArray(this.zdList)) return 11;

      const count = Math.ceil(this.zdList.length / 2);
      // 确保返回值是有效的正整数
      return Math.max(11, Math.min(100, count)); // 增加上限防止过大的渲染
    },
  },
  created() {
    this.controlData.zyh = this.zyh != '0' ? this.zyh : this.$route.query.zyh;
    this.controlData.rule_id = this.$route.query.rule_id;
    this.controlData.bSwitch = this.zyh == '0' ? true : false;
    this.typePage = this.$route.query.type;
    if (this.typePage == 'search') {
      this.controlData.bSwitch = false;
    }
  },
  mounted() {
    this.getData();
  },
  methods: {
    //重新质控
    onReset() {
      this.$confirm('此操作将对病案首页进行重新质控，是否继续?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      })
        .then(() => {
          this.$axios4.post('/zzTest', { ZYH: this.controlData.zyh }).then(res => {
            if (res.code == 200) {
              this.$refs.ControlDrawerVue.getData(); //重新加载质控内容数据
              this.$message.success('重新质控成功');
            }
          });
        })
        .catch(() => {
          this.$message({
            type: 'info',
            message: '已取消质控',
          });
        });
    },
    // 手术合并
    formatSSdata(arr, obj) {
      const list = arr.concat([obj]).reverse();
      return list;
    },
    // 判断诊断级别
    zdLevel(i) {
      let str;
      if (i === 1) {
        str = '主要诊断：';
      } else if (i === 2) {
        str = '其他诊断：';
      } else if (i === this.zdRows) {
        str = '其他诊断：';
      } else {
        str = '';
      }
      return str;
    },
    // 是否显示质控栏
    onToggle() {
      this.controlData.zyh = this.zyh != '0' ? this.zyh : this.$route.query.zyh;
      this.controlData.bSwitch = !this.controlData.bSwitch;
    },
    // 获取详情
    getData() {
      const params = {
        ZYH: this.zyh != '0' ? this.zyh : this.$route.query.zyh,
      };
      this.$axios.post('/bmy/getBlDetails', params).then(res => {
        // this.blInfo = res.data;
        // 确保诊断数据存在且为数组
        if (!Array.isArray(res.data.other_diagnosis)) {
          res.data.other_diagnosis = [];
        }
        this.blInfo = res.data;
      });
    },
    // 质控栏锚点及高亮ICD10_ID1,ICD9_ID1
    handleZK(val) {
      console.log('点击质控接收的', val);
      const { anchor, codes } = val;
      const { key, field } = anchor;
      this.active_zk_codes = codes;
      if (key === 'user') {
        document.querySelector(`.${field}`).scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else if (key === 'zd') {
        // document.querySelector(`.zd-${anchor.DIA_ORDER}-${field}`).scrollIntoView({ behavior: 'smooth', block: 'center' })
        // todo
      } else if (key === 'ss') {
        // todo
        // document.querySelector(`.ss-${anchor.OPE_ORDER}-${field}`).scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
    },
    zkTest(item) {
      console.log('zkTest', item);
      if (item.error_field == 'ICD10_NAME' || item.error_field == 'ICD10_ID1' || item.error_field == 'RYQK') {
        item.error_field = 'ICD10_ID1';
      }
      if (
        item.error_field == 'ICD9_ID1' ||
        item.error_field == 'OPE_DATE' ||
        item.error_field == 'ICD9_NAME' ||
        item.error_field == 'OPE_LEVEL' ||
        item.error_field == 'OPE_TYPE' ||
        item.error_field == 'SSPB'
      ) {
        item.error_field = 'ICD9_ID1';
      }
      let a = document.querySelectorAll('.choose-twinkle');
      let b = document.querySelectorAll('.choose-twinkle-1');
      let eleClass = [...a, ...b];

      for (let item = 0; item < eleClass.length; item++) {
        eleClass[item].className = 'label';
      }

      if (Array.isArray(this.$refs[item.error_field])) {
        this.$refs[item.error_field][0].className = item.category <= 1 ? 'choose-twinkle' : 'choose-twinkle-1';
        this.$refs[item.error_field][0].scrollIntoView({ block: 'center', behavior: 'smooth' });
      } else {
        // this.$nextTick(() => {
        //   item.basis.forEach(e => {
        //     this.$refs[e].className = item.category <= 1 ? 'choose-twinkle' : 'choose-twinkle-1';
        //   });
        //   this.$refs[item.error_field].scrollIntoView({ block: 'center', behavior: 'smooth' });
        // });
        if (item.error_field == 'ICD10_ID1' || item.error_field == 'ICD9_ID1') {
          this.$nextTick(() => {
            item.basis.forEach(e => {
              this.$refs[e].className = item.category <= 1 ? 'choose-twinkle' : 'choose-twinkle-1';
            });
            this.$refs[item.error_field].scrollIntoView({ block: 'center', behavior: 'smooth' });
          });
        } else {
          this.$refs[item.error_field].className = item.category <= 1 ? 'choose-twinkle' : 'choose-twinkle-1';
          this.$refs[item.error_field].scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
      }
    },
    handleCodes(codes) {
      this.$set(this, 'zk_codes', codes);
      this.getData();
      console.log('进页面接收的', codes);
    },
    // 判断诊断
    judgeZD(arr, str) {
      let result = false;
      arr.map(item => {
        if (item.includes(str)) {
          result = true;
        }
      });
      return result;
    },
    // 判断两个数组是否存在交际
    hasIntersection(arr1, arr2) {
      // 在arr1中筛选出arr2中存在的元素
      const filtered = arr1.filter(item => arr2.includes(item));
      // 如果筛选后的数组长度大于0，则存在交集
      return filtered.length > 0;
    },
    // 查看费用详情
    toCostPage() {
      this.$router.push({ name: 'Cost', query: { zyh: this.zyh != '0' ? this.zyh : this.$route.query.zyh } });
    },
  },
};
</script>

<style lang="scss" scoped>
.bg-box {
  transition: all 0.3s;
}

.has_control {
  padding-right: 426px;
}

.title {
  font-size: 24px;
  font-family: Source Han Sans CN-Bold, Source Han Sans CN;
  font-weight: bold;
  color: #333333;
  text-align: center;
  line-height: 40px;
  margin-bottom: 20px;
}

.bg-card {
  position: relative;
  height: 100%;

  .content {
    height: calc(100% - 34px);

    .content-from {
      height: 100%;
      overflow-y: auto;

      &::-webkit-scrollbar {
        width: 16px !important;
        height: 16px !important;
      }
    }
  }
}

.btn-wrapper {
  position: absolute;
  top: 20px;
  right: 20px;
}

.zyh {
  font-size: 16px;
  font-family: Source Han Sans CN-Regular, Source Han Sans CN;
  font-weight: 400;
  color: #333333;
  text-align: right;
  margin-bottom: 20px;
}

.icon_btn {
  width: 20px;
  height: 20px;
  display: block;
}

.text_btn {
  font-size: 14px;
  font-family: Source Han Sans CN-Regular, Source Han Sans CN;
  font-weight: 400;
  color: #185da6;
  line-height: 20px;
}

.oper-box {
  text-align: right;
}

.mlr10 {
  margin: 0 10px;
}

.table {
  width: 100%;
  // min-width: 600px;
  table-layout: fixed;
  empty-cells: show;
  border-collapse: collapse;
  margin: 0 auto;

  th,
  td {
    border: 1px solid #666666;
    font-size: 14px;
    box-sizing: border-box;
    height: 40px;
    color: #333333;
    padding: 0 12px;
    word-break: break-all;

    &.jy_bg {
      background: rgba($color: #178691, $alpha: 0.2);
    }

    &.qz_bg {
      background: rgba($color: #ed3028, $alpha: 0.2);
    }
  }

  td.label {
    width: 140px;
    height: 40px;
    color: #666666;
    background-color: #ebebeb;
    text-align: center;

    &.jy {
      color: #178691;
      font-weight: bold;
    }

    &.qz {
      color: #fb5c4f;
      font-weight: bold;
    }
  }

  th.label {
    height: 40px;
    color: #666666;
    background-color: #ebebeb;
    text-align: center;

    &.jy {
      color: #178691;
      font-weight: bold;
    }

    &.qz {
      color: #fb5c4f;
      font-weight: bold;
    }
  }

  .center {
    text-align: center;
  }
}

.shoushu {
  .th25 {
    height: 25px !important;
  }
}

.square {
  display: inline-block;
  width: 20px;
  height: 20px;
  text-align: center;
  border: 1px solid #707070;
  border-radius: 2px;
  vertical-align: middle;
}

.choose-twinkle {
  // background: red;
  // font-size: 20px;
  // color: red;
  // font-weight: 600;
  background: #f5f3df;
  // border: 2px solid red;
}

.choose-twinkle-1 {
  // font-size: 20px;
  // color: red;
  // font-weight: 600;
  background: #f5f3df;
  // border: 2px solid #e26e01;
}
</style>
