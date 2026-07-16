<template>
  <div id="MyDiv">
    <!-- <div class="cont-title-description">{{data.ZA03}}</div> -->
    <div class="cont-title-description">住院病案首页</div>
    <!--  -->
    <!--  -->
    <div class="cont-title">
      <div />
      <!-- <div>
            <span>医疗机构</span>
            <span ref="UNT_ID">(组织机构代码：{{ data.UNT_ID }})</span>
          </div> -->
      <div class="refachInput">
        <span ref="AAA28">病案号:</span>
        <span v-if="!ifFile">{{ data.AAA28 }}</span>
        <el-input v-else v-model="data.AAA28" class="width170" placeholder="请输入内容" />
      </div>
    </div>
    <div>
      <el-row type="flex" class="row-bg" justify="space-around">
        <el-col :span="8">
          <div class="refachInput">
            <span ref="ZA03">医疗机构名称:</span>
            <span v-if="!ifFile">{{ data.ZA03 }}</span>
            <!-- data.ZA03 -->
            <el-input v-else v-model="data.ZA03" class="width200" placeholder="请输入内容" />
          </div>
        </el-col>
        <el-col :span="7">
          <div class="refachInput">
            <span ref="TYSHXYDM">统一社会信用代码:</span>
            <span v-if="!ifFile">{{ data.TYSHXYDM }}</span>
            <el-input v-else v-model="data.TYSHXYDM" class="width150" placeholder="请输入内容" />
          </div>
        </el-col>
        <el-col :span="7">
          <div class="refachInput" style="display: flex">
            <span v-if="!ifFile" ref="UNT_ID">
              (组织机构代码：
              <span>
                <span v-if="data.UNT_ID">{{ data.UNT_ID }})</span>
                <span v-else>-)</span>
              </span>
            </span>

            <!-- data.AAA27 -->
            <div v-else>
              <el-input v-model="data.UNT_ID" class="width170" placeholder="请输入内容" />
            </div>
          </div>
        </el-col>
        <!-- <el-col :span="6">

  </el-col> -->
      </el-row>
      <el-row type="flex" class="row-bg" justify="space-around">
        <el-col :span="8">
          <div class="refachInput">
            <span ref="AAA26C">医疗付费方式:</span>
            <span v-if="!ifFile">{{ data.AAA26C }}</span>
            <el-input v-else v-model="data.AAA26C" class="width150" placeholder="请输入内容" />
          </div>
        </el-col>
        <el-col :span="7">
          <div class="refachInput">
            <span ref="JKKH">健康卡号:</span>
            <span v-if="!ifFile">{{ data.JKKH ? data.JKKH : ' —  ' }}</span>
            <!-- data.AAA27 -->
            <el-input v-else v-model="data.JKKH" class="width170" placeholder="请输入内容" />
          </div>
        </el-col>
        <el-col :span="7">
          <div class="refachInput">
            <span>第</span>
            <span v-if="!ifFile">{{ data.AAA29 }}</span>
            <el-input v-else v-model="data.AAA29" class="width100" style="width: 80px" placeholder="请输入内容" />
            <span>次住院</span>
          </div>
        </el-col>
      </el-row>
    </div>

    <div class="cont-edit-dev" />
    <div class="table">
      <table ref="tables">
        <tr>
          <td ref="AAA01" class="table-label table-label1">姓名</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA01 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA01" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA02C" class="table-label table-label2">性别</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA02C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA02C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA03" class="table-label table-label3">出生日期</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA03 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA03" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA04" class="table-label table-label4">年龄</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA04 }}岁</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA04" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AAA40" class="table-label table-label6">年龄(年龄不足一周岁的年龄)</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA40 }}天</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA40" class="width100 tableReach" placeholder="请输入内容" />
          </td>

          <td ref="AAA05C" class="table-label table-label5">国籍</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA05C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA05C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA11" class="table-label table-label7">出生地</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA09 }}{{ data.AAA10 }}{{ data.AAA11 }}</td>
          <td v-else class="table-value">
            <div style="display: flex; align-items: center">
              <el-input v-model="data.AAA09" class="width100 tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA10" class="width100 tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA11" class="width100 tableReach" placeholder="请输入内容" />
            </div>
          </td>
          <!-- <td class="table-label table-label7" ref="AEM01C">新生儿出生体重</td>
              <td class="table-value" v-if="!ifFile">{{ data.AEM01C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AEM01C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td> -->
          <td ref="AAA44" class="table-label table-label18">籍贯</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA43 }}{{ data.AAA44 }}</td>
          <td v-else class="table-value">
            <div style="display: flex; align-items: center">
              <el-input v-model="data.AAA43" class="width100 tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA44" class="width100 tableReach" placeholder="请输入内容" />
            </div>
          </td>
          <!-- <td class="table-label table-label8" ref="AAA42">新生儿入院体重</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA42 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA42" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td> -->
        </tr>
        <tr>
          <td ref="AAA06C" class="table-label table-label111">民族</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA06C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA06C" class="width100 tableReach" placeholder="请输入内容" />
          </td>

          <td ref="SFZJLX" class="table-label table-label12">身份证类别</td>
          <td v-if="!ifFile" class="table-value">{{ data.SFZJLX ? data.SFZJLX : '居民身份证' }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.SFZJLX" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA07" class="table-label table-label12">证件号</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA07 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA07" style="padding: 0px !important; font-size: 13px !important" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA18C" class="table-label table-label12">职业</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA18C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA18C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AAA08C" class="table-label table-label13">婚姻</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA08C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA08C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA15" class="table-label table-label13">现住址</td>
          <td v-if="!ifFile" class="table-value" colspan="3">{{ data.AAA48 }}{{ data.AAA49 }}{{ data.AAA50 }}{{ data.AAA15 }}</td>
          <td v-else class="table-value" colspan="3">
            <div style="display: flex; align-items: center">
              <el-input v-model="data.AAA48" style="width: 200px !important" class="width100 tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA49" style="width: 200px !important" class="width100 tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA50" style="width: 200px !important" class="width100 tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA15" class="width100 tableReach" placeholder="请输入内容" />
            </div>
          </td>
          <td ref="AAA51" class="table-label table-label13">电话</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA51 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA51" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AAA17C" class="table-label table-label13">邮编</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA17C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA17C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA45" class="table-label table-label17">户口地址</td>
          <td v-if="!ifFile" class="table-value" colspan="3">{{ data.AAA45 }}{{ data.AAA46 }}{{ data.AAA47 }}{{ data.AAA12 }}</td>
          <td v-else class="table-value" colspan="3">
            <div style="display: flex; align-items: center">
              <el-input v-model="data.AAA45" style="width: 200px !important" class="tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA46" style="width: 200px !important" class="width100 tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA47" style="width: 200px !important" class="width100 tableReach" placeholder="请输入内容" />
              <el-input v-model="data.AAA12" class="width100 tableReach" placeholder="请输入内容" />
            </div>
          </td>
          <td ref="AAA14C" class="table-label table-label19">邮编</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA14C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA14C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AAA19" class="table-label table-label20">工作单位及地址</td>
          <td v-if="!ifFile" class="table-value" colspan="3">{{ data.AAA19 }}</td>
          <td v-else class="table-value" colspan="3">
            <el-input v-model="data.AAA19" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA20" class="table-label table-label21">单位电话</td>
          <td v-if="!ifFile" class="table-value" colspan="3">{{ data.AAA20 }}</td>
          <td v-else class="table-value" colspan="3">
            <el-input v-model="data.AAA20" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AAA21C" class="table-label table-label22">邮编</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA21C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA21C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AEN01" class="table-label table-label7">新生儿出生体重</td>
          <td v-if="!ifFile" class="table-value">{{ data.AEN01 }}克</td>
          <td v-else class="table-value">
            <el-input v-model="data.AEN01" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA42" class="table-label table-label8">新生儿入院体重</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA42 }}克</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA42" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA22" class="table-label table-label23">联系人姓名</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA22 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA22" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AAA23C" class="table-label table-label24">关系</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA23C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA23C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA24" class="table-label table-label25">地址</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA24 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA24" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAA25" class="table-label table-label26">电话</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAA25 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAA25" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <!-- <td class="table-label table-label26" ref="AAA25">邮编</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA25 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA25" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td> -->
          <td ref="AAB06C" class="table-label table-label27">入院途径</td>
          <td v-if="!ifFile" class="table-value" colspan="3">{{ data.AAB06C }}</td>
          <td v-else class="table-value" colspan="3">
            <el-input v-model="data.AAB06C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AAB01" class="table-label table-label27">入院时间</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAB01 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAB01" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAB02C" class="table-label table-label27">入院科别</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAB02C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAB02C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="BFRY" class="table-label table-label27">病房</td>
          <td v-if="!ifFile" class="table-value">{{ data.BFRY }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.BFRY" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="ZKKB" class="table-label table-label27">转科科别</td>
          <td v-if="!ifFile" class="table-value">{{ data.ZKKB }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.ZKKB" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AAC01" class="table-label table-label27">出院时间</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAC01 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAC01" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAC02C" class="table-label table-label27">出院科别</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAC02C }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAC02C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAC03" class="table-label table-label27">病房</td>
          <td v-if="!ifFile" class="table-value">{{ data.AAC03 }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.AAC03" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AAC04" class="table-label table-label27">实际住院</td>
          <td v-if="!ifFile" class="table-value">
            {{ data.AAC04 }}
            <span v-if="data.AAC04">天</span>
          </td>
          <td v-else class="table-value">
            <div style="display: flex; align-items: center; justify-content: center">
              <el-input v-model="data.AAC04" style="width: 50px !important" class="width70 tableReach" placeholder="请输入" />
              天
            </div>
          </td>
        </tr>
        <tr>
          <td ref="ABA01N" class="table-label table-label28" style="font-weight: bold">门(急)诊诊断</td>
          <td v-if="!ifFile" class="table-value">{{ data.ABA01N }}</td>
          <td v-else class="table-value">
            <el-input v-model="data.ABA01N" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="ABA01C" class="table-label table-label29" style="font-weight: bold">疾病编码</td>
          <td v-if="!ifFile" class="table-value" colspan="5">{{ data.ABA01C }}</td>
          <td v-else class="table-value" colspan="5">
            <el-input v-model="data.ABA01C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
      </table>
    </div>
    <div class="table">
      <table>
        <tr>
          <td class="table-labelon table-label30" style="font-weight: bold">出院诊断</td>
          <td class="table-labelon table-label31" style="font-weight: bold">疾病编码</td>
          <td ref="ABC03C" class="table-labelon table-label32" colspan="3" style="font-weight: bold; width: 600px">入院病情</td>
          <!-- <td class="table-labelon table-label33"  style="font-weight:bold;">出院诊断</td>
              <td class="table-labelon table-label34"  style="font-weight:bold;">疾病编码</td>
              <td class="table-labelon table-label35"  style="font-weight:bold;">入院病情</td> -->
        </tr>
        <!--  diagnosis -->
        <tr v-for="(item, index) in data.diagnosis" :key="index">
          <td v-if="item.class == 'main'" class="table-valueon" style="display: flex; align-items: center">
            <p v-if="item.class == 'main'" style="padding-left: 5px; font-weight: bold">主要诊断：</p>
            <p v-if="!ifFile">{{ item.class == 'main' ? item.ICD10_NAME : '' }}</p>
            <div v-else>
              <el-input v-model="item.ICD10_NAME" class="width100 tableReaches" placeholder="请输入内容" />
            </div>
          </td>
          <td v-else class="table-valueon" style="display: flex; align-items: center">
            <p style="padding-left: 5px">其他诊断：</p>
            <p v-if="!ifFile">{{ item.class == 'other' ? item.ICD10_NAME : '' }}</p>
            <div v-else>
              <el-input v-model="item.ICD10_NAME" class="width100 tableReaches" placeholder="请输入内容" />
            </div>
          </td>
          <!-- <td class="table-valueon"  v-if="!ifFile" >{{ item.FRIST_ASSISTANT_NAME }}</td>
              <td class="table-valueon"  v-if="ifFile"> <el-input class="width80 tableReach" v-model="item.FRIST_ASSISTANT_NAME" placeholder="请输入内容"></el-input></td> -->
          <!-- <td class="table-valueon FA6400">{{ item.ABF01C }}</td> -->
          <td v-if="!ifFile" class="table-valueon">{{ item.ICD10_ID1 }}</td>
          <td v-if="ifFile" class="table-valueon"><el-input v-model="item.ICD10_ID1" class="width80 tableReach" placeholder="请输入内容" /></td>

          <td v-if="!ifFile" class="table-valueon" colspan="3">{{ item.RYQK }}</td>
          <td v-if="ifFile" class="table-valueon" colspan="3"><el-input v-model="item.RYQK" class="width80 tableReach" placeholder="请输入内容" /></td>
          <!-- <td class="table-valueon"  v-if="!ifFile" >{{ item.icd10_name }}</td>
              <td class="table-valueon"  v-if="ifFile"> <el-input class="width80 tableReach" v-model="item.icd10_name" placeholder="请输入内容"></el-input></td> -->
          <!-- <td class="table-valueon" style="display: flex;height:50px;border-right:none!important;border-left:none!important;border-top:none!important; align-items: center">
                <p style="padding-left: 5px" v-if="item.class != 'main'"></p>
                <p v-if="!ifFile">{{ item.class == 'otherS' ? item.ICD10_NAMES : '' }}</p>
                <div v-else>
                  <el-input v-if="item.class == 'othSer'" v-model="item.ICD10_NAMEs" class="width100 tableReaches" placeholder="请输入内容"></el-input>
                </div>
              </td> -->
          <!-- <td class="table-valueon" v-if="!ifFile">{{ item.ABC01CS }}</td>
              <td class="table-valueon" v-if="ifFile"><el-input class="width80 tableReach" v-model="item.ABC01CSS" placeholder="请输入内容"></el-input></td>
              <td class="table-valueon" v-if="!ifFile">{{ item.ORG_STATE }}</td>
              <td class="table-valueon" v-if="ifFile"><el-input class="width80 tableReach" v-model="item.ORG_STATE" placeholder="请输入内容"></el-input></td> -->
        </tr>
        <!-- <tr v-if="data.diagnosis.length == 0">
              <td class="table-valueon" style="display: flex; align-items: center"></td>
              <td class="table-valueon"></td>
              <td class="table-valueon"></td>
              <td
                class="table-valueon"
                style="display: flex; height: 50px; border-right: none !important; border-left: none !important; border-top: none !important; align-items: center"
              ></td>
              <td class="table-valueon"></td>
              <td class="table-valueon"></td>
            </tr> -->
        <tr>
          <td class="table-value" colspan="6">
            <div style="text-align: left; margin-left: 25px">
              <span style="font-weight: bold">入院病情选项说明：</span>
              1.有 2.临床未确定 3.情况不明 4.无
            </div>
          </td>
        </tr>
        <tr>
          <td ref="ABG01N" class="table-value table-label" colspan="1" style="font-weight: bold">损伤、中毒的外部原因:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.ABG01N }}</div>
            <el-input v-else v-model="data.ABG01N" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="ABG01C" class="table-value table-label" colspan="1" style="font-weight: bold">疾病编码</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.ABG01C }}</div>
            <el-input v-else v-model="data.ABG01C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!-- <tr>
              <td class="table-value" colspan="3" v-if="!ifFile" ref="ICD">ICD-0-3:{{ data.ICD }}</td>
              <td class="table-value" colspan="1" v-if="ifFile">ICD-0-3:</td>
              <td class="table-value" colspan="2" v-if="ifFile">
                <el-input class="width100 tableReach" v-model="data.ICD" placeholder="请输入内容"></el-input>
              </td>
            </tr> -->
        <tr>
          <td ref="ABF01N" class="table-value table-label" colspan="1" style="font-weight: bold">病理诊断:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.ABF01N }}</div>
            <el-input v-else v-model="data.ABF01N" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="ABF01C" class="table-value table-label" colspan="1" style="font-weight: bold">病理诊断编码</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.ABF01C }}</div>
            <el-input v-else v-model="data.ABF01C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="Yj" class="table-value table-label" colspan="1" style="font-weight: bold; color: #ccc">最高诊断依据:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.YJ }}</div>
            <!-- <el-input v-else class="width100 tableReach" v-model="data.ABF04" placeholder="请输入内容"></el-input> -->
          </td>
          <td ref="ABF04" class="table-value table-label" colspan="1" style="font-weight: bold">病理号</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.ABF04 }}</div>
            <el-input v-else v-model="data.ABF04" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AEB02C" class="table-value table-label" colspan="1" style="font-weight: bold">药物过敏(1.无 2.有 3.-):</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile" style="display: flex; align-items: center">
              <span v-if="data.AEB02C == '无'">{{ data.AEB02C }}</span>
              <span v-else-if="data.AEB02C == '有'">{{ data.AEB02C }},{{ data.AEB01 }}</span>
              <span v-else>-</span>
            </div>
            <div v-else style="display: flex; align-items: center">
              <el-input v-model="data.AEB02C" class="width100 tableReach" style="width: 80px !important" placeholder="请输入内容" />
              <div v-if="data.AEB02C == '无'" style="display: flex; align-items: center" />
              <div v-else-if="data.AEB02C == '有'" style="display: flex; align-items: center">
                <el-input v-model="data.AEB01" class="width100 tableReach" placeholder="请输入内容" />
              </div>
            </div>
          </td>
          <td ref="AEI01C" class="table-value table-label" colspan="1" style="font-weight: bold">死亡患者尸检(1.是 2.否 3.-):</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEI01C }}</div>
            <el-input v-else v-model="data.AEI01C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="AEG01C" class="table-value table-label" colspan="1" style="font-weight: bold">血型(1.A 2.B 3.O 4.AB 5.不详 6.未查):</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEG01C }}</div>
            <el-input v-else v-model="data.AEG01C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AEG02C" class="table-value table-label" colspan="1" style="font-weight: bold">Rh(1.阴 2.阳 3.不详 4.未查):</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEG02C }}</div>
            <el-input v-else v-model="data.AEG02C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <tr>
          <td ref="Hljb" class="table-value table-label" colspan="1" style="font-weight: bold; border-right: 0">护理级别：</td>
          <td v-if="!ifFile" class="table-value" colspan="6" style="">
            <div>
              1.特级护理 {{ data.TJHL ? data.TJHL : ' - ' }}天 2.I级护理 {{ data.YJHL ? data.YJHL : ' - ' }}(天) 3.II级护理 {{ data.EJHL ? data.EJHL : ' - ' }}天 4.III级护理
              {{ data.SJHL ? data.SJHL : ' - ' }}天
            </div>
          </td>
          <td v-if="ifFile" class="table-value" colspan="6" style="border-left: 0">
            <div style="width: 100%; display: flex; align-items: center; justify-content: center">
              <div>
                1.特级护理
                <el-input v-model="data.TJHL" style="width: 100px" class="width100 tableReach" placeholder="请输入内容" />
                天 2.I级护理
                <el-input v-model="data.YJHL" style="width: 100px" class="width100 tableReach" placeholder="请输入内容" />
                天 3.II级护理
                <el-input v-model="data.EJHL" style="width: 100px" class="width100 tableReach" placeholder="请输入内容" />
                天 4.III级护理
                <el-input v-model="data.SJHL" style="width: 100px" class="width100 tableReach" placeholder="请输入内容" />
                天
              </div>
            </div>
          </td>
        </tr>
        <!--  -->
        <tr>
          <td ref="AEE01" class="table-value table-label" colspan="1" style="font-weight: bold">科主任:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE01 }}</div>
            <el-input v-else v-model="data.AEE01" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AEE01_CODE" class="table-value table-label" colspan="1" style="font-weight: bold">科主任编码：</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE01_CODE }}</div>
            <el-input v-else v-model="data.AEE01_CODE" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!--  -->
        <!--  -->
        <tr>
          <td ref="YLZZ" class="table-value table-label" colspan="1" style="font-weight: bold">医疗组长:</td>
          <td class="table-value" colspan="2">
            <!-- <el-input :disabled="!ifFile" class="width100 tableReach"  v-model="data.YLZZ" placeholder="请输入内容"></el-input> -->
          </td>
          <td ref="AEE02" class="table-value table-label" colspan="1" style="font-weight: bold">主任(副主任)医师：</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE02 }}</div>
            <el-input v-else v-model="data.AEE02" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!--  -->
        <!--  -->
        <tr>
          <td ref="AEE02_CODE" class="table-value table-label" colspan="1" style="font-weight: bold">主任(副主任)医师编码:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE02_CODE }}</div>
            <el-input v-else v-model="data.AEE02_CODE" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AEE03" class="table-value table-label" colspan="1" style="font-weight: bold">主治医师:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE03 }}</div>
            <el-input v-else v-model="data.AEE03" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!--  -->
        <!--  -->
        <tr>
          <td ref="AEE03_CODE" class="table-value table-label" colspan="1" style="font-weight: bold">主治医师编码:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE03_CODE }}</div>
            <el-input v-else v-model="data.AEE03_CODE" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AEE04" class="table-value table-label" colspan="1" style="font-weight: bold">住院医师:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE04 }}</div>
            <el-input v-else v-model="data.AEE04" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!--  -->
        <!--  -->
        <tr>
          <td ref="AEE04_CODE" class="table-value table-label" colspan="1" style="font-weight: bold">住院医师编码:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE04_CODE }}</div>
            <el-input v-else v-model="data.AEE04_CODE" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AEE10" class="table-value table-label" colspan="1" style="font-weight: bold">责任护士:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE10 }}</div>
            <el-input v-else v-model="data.AEE10" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!--  -->
        <!--  -->
        <tr>
          <td ref="ZRHSBM" class="table-value table-label" colspan="1" style="font-weight: bold">责任护士编码:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.ZRHSBM }}</div>
            <el-input v-else v-model="data.ZRHSBM" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AEE05" class="table-value table-label" colspan="1" style="font-weight: bold">进修医师:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE05 }}</div>
            <el-input v-else v-model="data.AEE05" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!--  -->
        <!--  -->
        <tr>
          <td ref="AEE07" class="table-value table-label" colspan="1" style="font-weight: bold">实习医师:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AEE07 }}</div>
            <el-input v-else v-model="data.AEE07" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="BMY" class="table-value table-label" colspan="1" style="font-weight: bold">编码员:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.BMY }}</div>
            <el-input v-else v-model="data.BMY" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!--  -->
        <!--  -->
        <!--  -->
        <tr>
          <td ref="AED01C" class="table-value table-label" colspan="1" style="font-weight: bold">病案质量(1.甲 2.乙 3.丙)：</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AED01C }}</div>
            <el-input v-else v-model="data.AED01C" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="ZKRQ" class="table-value table-label" colspan="1" style="font-weight: bold">质控日期:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.ZKRQ }}</div>
            <el-input v-else v-model="data.ZKRQ" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
        <!-- 优化建议 -->
        <tr>
          <td ref="AED02" class="table-value table-label" colspan="1" style="font-weight: bold">质控医师:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AED02 }}</div>
            <el-input v-else v-model="data.AED02" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AED03" class="table-value table-label" colspan="1" style="font-weight: bold">质控护士:</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.AED03 }}</div>
            <el-input v-else v-model="data.AED03" class="width100 tableReach" placeholder="请输入内容" />
          </td>
        </tr>
      </table>
      <table>
        <tr>
          <td ref="ICD9_ID1" class="table-labelon table-labelon1" style="font-weight: bold">手术及操作编码</td>
          <td ref="OPE_DATE" class="table-labelon" style="font-weight: bold">手术及操作日期</td>
          <td ref="OPE_LEVEL" class="table-labelon" style="font-weight: bold">手术级别</td>
          <td ref="ICD9_NAME" class="table-labelon" style="font-weight: bold">手术及操作名称</td>
          <td ref="SSLX" class="table-labelon" style="font-weight: bold">手术类型</td>
          <td ref="SSPB" class="table-labelon" style="font-weight: bold">手术判别</td>
          <td class="table-labelon" style="padding: 0px; border: 0">
            <tr>
              <td class="table-labelon" colspan="3" style="border-left: 0; font-weight: bold; border-right: 0">手术及操作人员</td>
            </tr>
            <tr>
              <td ref="OPE_MAN_NAME" class="table-labelon width150" style="font-weight: bold">术者</td>
              <td ref="FRIST_ASSISTANT_NAME" class="table-labelon width150" style="font-weight: bold">I助</td>
              <td ref="SECOND_ASSISTANT_NAME" class="table-labelon width150" style="font-weight: bold">II助</td>
            </tr>
          </td>
          <td ref="INCISION_GRADE_ID" class="table-labelon" style="font-weight: bold">切口愈合等级</td>
          <td ref="HOCUS_WAY_ID" class="table-labelon" style="font-weight: bold">麻醉方式</td>
          <td ref="HOCUS_MAN_NAME" class="table-labelon" style="font-weight: bold">麻醉医师</td>
        </tr>
        <tr v-for="(item, index) in data.operation" :key="index">
          <td class="table-value">
            <div v-if="ifFile">
              <el-input v-model="item.ICD9_ID1" class="width140 tableReach" placeholder="请输入内容" />
            </div>
            <div v-else>
              {{ item.ICD9_ID1 }}
            </div>
          </td>
          <td class="table-value">
            <div v-if="ifFile">
              <el-input v-model="item.OPE_DATE" class="width150 tableReach" style="width: 120px !important; padding: 0px !important" placeholder="请输入内容" />
            </div>
            <div v-else>
              {{ item.OPE_DATE }}
            </div>
          </td>
          <td class="table-value">
            <div v-if="ifFile">
              <el-input v-model="item.OPE_LEVEL" class="width150 tableReach" placeholder="请输入内容" />
            </div>
            <div v-else>
              {{ item.OPE_LEVEL }}
            </div>
          </td>
          <td class="table-value">
            <div v-if="ifFile">
              <el-input v-model="item.ICD9_NAME" class="width100 tableReach" placeholder="请输入内容" />
            </div>
            <div v-else>
              {{ item.ICD9_NAME }}
            </div>
          </td>
          <td class="table-value">
            <div v-if="ifFile">
              <el-input v-model="item.SSLX" class="width100 tableReach" placeholder="请输入内容" />
            </div>
            <div v-else>
              {{ item.SSLX }}
            </div>
          </td>
          <td class="table-value">
            <div v-if="ifFile">
              <el-input v-model="item.SSPB" class="width100 tableReach" placeholder="请输入内容" />
            </div>
            <div v-else>
              {{ item.SSPB }}
            </div>
          </td>
          <td class="table-value">
            <tr>
              <td class="table-value width100" style="border-bottom: 0; border-top: 0">
                <div v-if="!ifFile" style="width: 80px">
                  {{ item.OPE_MAN_NAME }}
                </div>
                <div v-else>
                  <el-input v-model="item.OPE_MAN_NAME" class="tabright" style="width: 80px" placeholder="请输入" />
                </div>
              </td>
              <td class="table-value width100" style="border-bottom: 0; border-top: 0">
                <div v-if="!ifFile" style="width: 80px">
                  {{ item.FRIST_ASSISTANT_NAME }}
                </div>
                <div v-else>
                  <el-input v-model="item.FRIST_ASSISTANT_NAME" class="tabright" style="width: 80px" placeholder="请输入" />
                </div>
              </td>
              <td class="table-value width100" style="border-bottom: 0; border-top: 0">
                <div v-if="!ifFile" style="width: 80px">
                  {{ item.SECOND_ASSISTANT_NAME }}
                </div>
                <div v-else>
                  <el-input v-model="item.SECOND_ASSISTANT_NAME" class="tabright" style="width: 80px" placeholder="请输入" />
                </div>
              </td>
            </tr>
          </td>
          <td class="table-value">
            <div v-if="ifFile">
              <el-input v-model="item.INCISION_GRADE_ID" class="width150 tableReach" placeholder="请输入内容" />
            </div>
            <div v-else>
              {{ item.INCISION_GRADE_ID }}
            </div>
          </td>
          <td v-if="!ifFile" class="table-value">{{ item.HOCUS_WAY_ID }}</td>
          <td v-if="ifFile" class="table-value">
            <el-input v-model="item.HOCUS_WAY_ID" class="width150 tableReach" placeholder="请输入" />
          </td>
          <td v-if="!ifFile" class="table-value">{{ item.HOCUS_MAN_NAME }}</td>
          <td v-if="ifFile" class="table-value">
            <el-input v-model="item.HOCUS_MAN_NAME" class="tableReach" placeholder="请输入" />
          </td>
        </tr>
        <tr v-if="data.operation && data.operation.length == 0">
          <td class="table-value" />
          <td class="table-value" />
          <td class="table-value" />
          <td class="table-value" />
          <td class="table-value" />
          <td class="table-value" style="padding: 0px">
            <tr>
              <td class="table-value" style="padding: 0px; border: 0" />
              <td class="table-value" style="padding: 0px; border: 0" />
              <td class="table-value" style="padding: 0px; border: 0" />
            </tr>
          </td>
          <td class="table-value" />
          <td class="table-value" />
          <td class="table-value" />
        </tr>
        <!-- end -->
        <tr>
          <td ref="RJSS" class="table-value" colspan="3" style="font-weight: bold">是否为日间手术(1.是 2.否):</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.RJSS }}</div>
            <el-input v-else v-model="data.RJSS" class="width100 tableReach" placeholder="请输入内容" />
          </td>
          <td ref="AEL01" class="table-value" colspan="3" style="font-weight: bold">有创呼吸机使用时间</td>
          <td class="table-value" colspan="2">
            <div style="display: flex; align-items: center; justify-content: center">
              <div v-if="!ifFile">{{ data.AEL01 }}</div>
              <el-input v-else v-model="data.AEL01" class="width100 tableReach" placeholder="请输入" />
              <span style="width: 100px">小时</span>
            </div>
          </td>
        </tr>
        <tr>
          <td ref="LCLJ" class="table-value" colspan="3" style="font-weight: bold">临床路径(1.是 2.否):</td>
          <td class="table-value" colspan="2">
            <div v-if="!ifFile">{{ data.LCLJ }}</div>
            <el-input v-else v-model="data.LCLJ" class="width100 tableReach" placeholder="请输入" />
          </td>
          <td ref="WCQK" class="table-value" colspan="3" style="font-weight: bold">完成情况(1.完成 2.退出):</td>
          <td class="table-value" colspan="2">
            <div style="display: flex; align-items: center; justify-content: center">
              <div v-if="!ifFile">{{ data.LCLJ }}</div>
              <el-input v-else v-model="data.WCQK" class="width100 tableReach" style="width: 150px" placeholder="请输入内容" />
            </div>
          </td>
        </tr>

        <tr>
          <td ref="BYQK" class="table-value" colspan="3" style="font-weight: bold; border-right: 0">变异情况:</td>
          <td class="table-value" colspan="7" style="border-left: 0">{{ data.BYQK }}1.有 2.无</td>
          <!-- <td class="table-value" colspan="6" style="border-left: 0;" v-if="ifFile">
                <div style="display:flex;align-items: center;justify-content: center;">
                 <el-input class="width100 tableReach"  v-model="data.AEL01" placeholder="请输入内容"></el-input>
                </div>
              </td> -->
        </tr>

        <tr>
          <td class="table-value" colspan="2" style="font-weight: bold">颅脑损伤患者昏迷时间：</td>
          <td v-if="!ifFile" class="table-value" colspan="3" style="border-right: 0; border-left: 0">入院前:{{ data.AEJ01 }}天{{ data.AEJ02 }}小时{{ data.AEJ03 }}分</td>
          <td v-if="ifFile" class="table-value" colspan="4" style="border-left: 0">
            <span style="font-size: 12px">入院前:</span>
            <el-input v-model="data.AEJ01" class="width150 tableReach" placeholder="请输入" />
            <span ref="AEJ01">天</span>
            <el-input v-model="data.AEJ02" class="width150 tableReach" placeholder="请输入" />
            <span ref="AEJ02">小时</span>
            <el-input v-model="data.AEJ03" class="width150 tableReach" placeholder="请输入" />
            <span ref="AEJ03">分</span>
          </td>
          <td v-if="!ifFile" class="table-value" colspan="1" style="border-left: 0; border-right: 0">入院后:{{ data.AEJ04 }}天{{ data.AEJ05 }}小时{{ data.AEJ06 }}分</td>
          <td v-if="ifFile" class="table-value" colspan="8" style="border-left: 0">
            入院后：
            <el-input v-model="data.AEJ04" class="width150 tableReach" placeholder="请输入" />
            <span ref="AEJ04">天</span>
            <el-input v-model="data.AEJ05" class="width150 tableReach" placeholder="请输入" />
            <span ref="AEJ05">小时</span>
            <el-input v-model="data.AEJ06" class="width150 tableReach" placeholder="请输入" />
            <span ref="AEJ06">分</span>
          </td>
          <td v-if="!ifFile" class="table-value" colspan="5" style="border-left: 0" />
          <!-- <td class="table-value" colspan="5" style="border-left: 0" v-if="ifFile"></td> -->
        </tr>
        <tr>
          <td ref="IS_MAIN_WAY" class="table-labelon" colspan="3">
            <div class="table-labelonSize" style="margin: 10px 0px; font-weight: bold">重症监护室名称</div>
            <div class="table-labelonSize" style="font-size: 12px; margin: 10px 0px; font-weight: bold">CCU,RICU,SICU,NICU,PICU,EICU,MICU,其他</div>
          </td>
          <td ref="IN_TIME" class="table-labelon" colspan="3">
            <div class="table-labelonSize" style="margin: 10px 0px; font-weight: bold">进重症监护室时间(年月日时分)</div>
          </td>
          <td ref="OUT_TIME" class="table-labelon" colspan="2">
            <div class="table-labelonSize" style="margin: 10px 0px; font-weight: bold">出重症监护室时间(年月日时分)</div>
          </td>
          <td ref="HJ" class="table-labelon" colspan="1">
            <div class="table-labelonSize" style="margin: 10px 0px; font-weight: bold">(合计/小时)</div>
          </td>
        </tr>
        <tr>
          <td v-if="!ifFile" class="table-value" colspan="3">{{ data.IS_MAIN_WAY }}</td>
          <td v-if="ifFile" class="table-value" colspan="3">
            <el-input v-model="data.IS_MAIN_WAY" class="width100 tableReach" placeholder="请编辑输入内容" />
          </td>
          <td v-if="!ifFile" class="table-value" colspan="3">{{ data.IN_TIME }}</td>
          <td v-if="ifFile" class="table-value" colspan="3">
            <el-input v-model="data.IN_TIME" class="width100 tableReach" placeholder="请编辑输入内容" />
          </td>
          <td v-if="!ifFile" class="table-value" colspan="2">{{ data.OUT_TIME }}</td>
          <td v-if="ifFile" class="table-value" colspan="2">
            <el-input v-model="data.OUT_TIME" class="width100 tableReach" placeholder="请编辑输入内容" />
          </td>
          <td v-if="!ifFile" class="table-value" colspan="1">{{ data.HJ }}</td>
          <td v-if="ifFile" class="table-value" colspan="1">
            <!-- <el-input class="width100 tableReach" v-model="data.AEL01" placeholder="请输入内容"></el-input> -->
          </td>
        </tr>
        <!--  -->
        <tr>
          <td ref="AEM01C" class="table-value" colspan="10">
            <div style="display: flex; align-items: center; justify-content: left; padding-left: 10px">
              <div v-if="!ifFile" style="font-weight: bold">离院方式：{{ data.AEM01C }}</div>
              <div v-else style="font-weight: bold; display: flex; align-items: center; justify-content: left; padding-left: 10px">
                离院方式：
                <el-input v-model="data.AEM01C" class="width200 tableReach" style="width: 80px" placeholder="请输入内容" />
                <div v-if="ifFile && data.AEM01C == 2" style="display: flex; align-items: center; justify-content: center">
                  <span style="width: 350px">医嘱转院，拟接收医疗机构名称：</span>
                  <el-input v-model="data.AEM02" class="width200 tableReach" placeholder="请输入内容" />
                </div>
                <div v-else-if="!ifFile && data.AEM01C == 3" style="display: flex; align-items: center; justify-content: center">
                  <span style="width: 700px">医嘱转社区卫生服务机构乡镇卫生院，拟接收医疗机构名称：</span>
                  <el-input v-model="data.AEM02" class="width200 tableReach" placeholder="请输入内容" />
                </div>
                <!-- <div v-else-if="data.AEM01C == '医嘱离院'" style="display: flex; align-items: center; justify-content: center">
                      <span style="width: auto">医嘱离院</span>
                    </div> -->
                <div v-else-if="!ifFile && data.AEM01C == 4" style="display: flex; align-items: center; justify-content: center">
                  <span style="width: auto">非医嘱离院</span>
                </div>
                <div v-else style="display: flex; align-items: center; justify-content: center">
                  <span style="width: auto">其他</span>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <tr>
          <td ref="AEM03C" class="table-value" colspan="10" style="text-align: left">
            <div style="display: flex; align-items: center; justify-content: left; padding-left: 10px">
              <div v-if="!ifFile" style="text-align: left; font-weight: bold">是否有出院31天内再住院计划:{{ data.AEM03C }};{{ data.AEM04 }}</div>
              <div v-else style="display: flex; text-align: left; align-items: center; font-weight: bold; justify-content: left; padding-left: 10px">
                是否有出院31天内再住院计划:
                <el-input v-model="data.AEM03C" class="width200 tableReach" style="width: 80px" placeholder="请输入内容" />
                <div v-if="!ifFile && data.AEM03C == '有'" style="display: flex; align-items: center; justify-content: left; padding-left: 10px">
                  <span style="width: 150px">有,目的</span>
                  <el-input v-model="data.AEM04" class="width200 tableReach" placeholder="请输入内容" />
                </div>
                <div v-else-if="!ifFile && data.AEM03C == '无'" style="display: flex; align-items: center; justify-content: left; padding-left: 10px">
                  <span style="width: auto">无</span>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <!-- <tr>
              <td class="table-value" colspan="2" ref="AEM01C">离院方式(1.医嘱离院 2.医嘱转院 3.):</td>
              <td class="table-value" colspan="3">
                <div v-if="!ifFile">{{ data.AEM01C }}</div>
                <div v-else style="display:flex;">
                  <el-input class="width100 tableReach" style="width:80px;" v-model="data.AEM01C" placeholder="请输入"></el-input>
                  <div v-if="data.AEM01C == 2" style="display:flex;">
                    <span>医嘱转院，拟接收医疗机构名称：</span>
                    <el-input class="width100 tableReac1h" v-model="data.AEM02" placeholder="请输入内容"></el-input>
                  </div>
                  <div v-else-if="data.AEM01C == 3" style="display:flex;">
                    医嘱转社区卫生服务机构乡镇卫生院，拟接收医疗机构名称
                    <el-input class="width100 tableReach" v-model="data.AEM02" placeholder="请输入内容"></el-input>
                  </div>

                </div>
              </td>

              <td class="table-value" colspan="3" ref="AEM04">是否有出院31天内再住院计划:</td>
              <td class="table-value" colspan="2">
                <div v-if="!ifFile">{{ data.AEM04 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEM04" placeholder="请输入内容"></el-input>
              </td>
            </tr> -->
        <!--  -->
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold">住院费用(总费用)：</td>
          <td v-if="!ifFile" class="table-value" colspan="8">
            <span ref="ADA01">总费用: {{ data.ADA01 }}</span>
            <span ref="ADA0101">(自付金额:{{ data.ADA0101 }})</span>
            <span class="table-value-look" @click="jump">查看明细</span>
          </td>
          <td v-if="ifFile" ref="ADA01" class="table-value" colspan="2">总费用</td>
          <td v-if="ifFile" class="table-value" colspan="2">
            <el-input v-model="data.ADA01" class="width100 tableReach" placeholder="请编辑输入内容" />
          </td>
          <td v-if="ifFile" ref="ADA0101" class="table-value" colspan="1">自付金额</td>
          <td v-if="ifFile" class="table-value" colspan="3">
            <el-input v-model="data.ADA0101" class="width100 tableReach" placeholder="请编辑输入内容" />
          </td>
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold" rowspan="2">1.综合医疗服务费:</td>
          <td ref="D11" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(1)一般医疗服务费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D11" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D11 }}
              </div>
            </div>
          </td>
          <td ref="D12" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(2)一般治疗操作费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D12" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D12 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td ref="D13" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(3)护理费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D13" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D13 }}
              </div>
            </div>
          </td>
          <td ref="D14" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(4)其他费用:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D14" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D14 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold" rowspan="2">2.诊断类:</td>
          <td ref="D15" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(5)病理诊断费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D15" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D15 }}
              </div>
            </div>
          </td>
          <td ref="D16" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(6)实验室诊断费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D16" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D16 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td ref="D17" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(7)影像学诊断费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D17" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D17 }}
              </div>
            </div>
          </td>
          <td ref="D18" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(8)临床诊断项目费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D18" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D18 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold">3.治疗类：</td>
          <td ref="D19" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">
                (9)非手术治疗项目费:
                <span v-if="!ifFile">{{ data.D19 }}</span>
              </span>
              <div v-if="ifFile" style="display: flex; align-items: center">
                <el-input v-model="data.D19" class="width200 tableReachCCC" style="width: 120px !important" placeholder="请输入内容" />
                <span style="text-align: left">(临床物理治疗费:):</span>
                <el-input v-model="data.D19X01" class="width200 tableReachCCC" style="width: 130px !important" placeholder="请输入内容" />
              </div>
              <div v-else>
                <span>(临床物理治疗费:):</span>
                {{ data.D19X01 }}):
              </div>
            </div>
          </td>
          <td ref="D20" class="table-value" colspan="4">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(10)手术治疗费:</span>
              <div v-if="ifFile" style="display: flex; align-items: center">
                <el-input v-model="data.D20" class="width150 tableReachCCC" style="width: 130px !important" placeholder="请输入内容" />
                <span>(麻醉费:</span>
                <el-input v-model="data.D20X01" class="width150 tableReachCCC" style="width: 130px !important" placeholder="请输入内容" />
                <span>手术费:</span>
                <el-input v-model="data.D20X02" class="width150 tableReachCCC" style="width: 130px !important" placeholder="请输入内容" />
                )
              </div>
              <div v-else>
                {{ data.D20 }}(
                <span>(麻醉费:</span>
                {{ data.D20X01 }}
                <span>手术费:</span>
                {{ data.D20X02 }})
              </div>
            </div>
          </td>
        </tr>
        <!--  -->
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold">4.康复类：</td>
          <td ref="D21" class="table-value" colspan="4" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span style="padding-left: 10px">(11)康复费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D21" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D21 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="4" style="border-left: 0; border-top: 0" />
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold">5.中医类：</td>
          <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D22" style="padding-left: 10px">(12)中医治疗费用:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D22" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D22 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="4" style="border-left: 0; border-top: 0" />
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold">6.西药类：</td>
          <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D23" style="padding-left: 10px">(13)“西药费”:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D23" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D23 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="4" style="border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D23X01" style="padding-left: 10px">(抗菌药物费用):</span>
              <div v-if="ifFile">
                <el-input v-model="data.D23X01" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D23X01 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold">7.中药类：</td>
          <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D24" style="padding-left: 10px">(14)中成药费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D24" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D24 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="4" style="border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D25" style="padding-left: 10px">(15)中草药费用:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D25" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D25 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" rowspan="3" style="font-weight: bold">8.血液和血液制品类：</td>
          <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D26" style="padding-left: 10px">(16)血费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D26" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D26 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="4" style="border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D27" style="padding-left: 10px">(17)白蛋白类制品类费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D27" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D27 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D28" style="padding-left: 10px">(18)球蛋白类制品费</span>
              <div v-if="ifFile">
                <el-input v-model="data.D28" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D28 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="4" style="border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D29" style="padding-left: 10px">(19)凝血因子类制品费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D29" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D29 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D30" style="padding-left: 10px">(20)细胞因子类制品费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D30" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D30 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="4" style="border-left: 0; border-top: 0" />
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold">9.耗材类:</td>
          <td class="table-value" colspan="3" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D31" style="padding-left: 10px">(21)检查用一次性医用材料费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D31" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D31 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="3" style="border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D32" style="padding-left: 10px">(22)治疗用一次性医用材料费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D32" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D32 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="3" style="border-left: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D33" style="padding-left: 10px">(23)手术用一次性医用材料费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D33" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D33 }}
              </div>
            </div>
          </td>
        </tr>
        <tr class="tablexs">
          <td class="table-value" colspan="1" style="font-weight: bold">10.其他类:</td>
          <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
            <div style="display: flex; align-items: center">
              <span ref="D34" style="padding-left: 10px">(24)其他费:</span>
              <div v-if="ifFile">
                <el-input v-model="data.D34" class="width200 tableReachCCC" placeholder="请输入内容" />
              </div>
              <div v-else>
                {{ data.D34 }}
              </div>
            </div>
          </td>
          <td class="table-value" colspan="4" style="border-left: 0; border-top: 0" />
        </tr>
        <tr>
          <td class="table-value" colspan="10" style="text-align: left; border-top: 0">
            <div class="msgBoxDetail" style="padding-left: 10px; margin: 10px 0px">
              <span>
                <span style="font-weight: bold">说明:</span>
                (一) 医疗付费方式：1.城镇职工基本医疗保险
              </span>
              <span>2.城镇居民基本医疗保险</span>
              <span>3.新型农村合作医疗</span>
              <span>4.贫困救助</span>
              <span>5.商业医疗保险</span>
              <span>6.全公费</span>
              <span>7全自费</span>
              <span>8其他社会保险</span>
              <span>9.其他</span>
            </div>
            <div style="padding-left: 10px; text-indent: 3.7em; margin: 10px 0px">(二) 凡可由医院信息系统提供住院费用清单的，住院病案首页中可不填写“住院费用”。</div>
          </td>
        </tr>
        <!-- <tr>
               <td class="table-value" colspan="10" style="text-align: left; border-top: 0">
                   手术判别:  <div v-if="!ifFile" ref="SSPB">{{ data.SSPB }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.SSPB" placeholder="请输入内容"></el-input>
               </td>
            </tr> -->
      </table>
    </div>
    <div class="bottom-time bottom-time-top">
      <div class="bottom-time-listw">
        <span class="bottom-time-bold">医生签名:</span>
        <span>{{ data.doctor_name }}</span>
      </div>
    </div>
    <div class="bottom-time bottom-time-top">
      <div v-if="data.CJSJ" class="bottom-time-list">
        <span class="bottom-time-bold">创建时间:</span>
        <span>{{ data.CJSJ }}</span>
      </div>
      <div v-if="data.ZXSJ" class="bottom-time-list">
        <span class="bottom-time-bold">修改时间:</span>
        <span>{{ data.ZXSJ }}</span>
      </div>
    </div>
    <div v-if="data.WCSJ" class="bottom-time bottom-time-botom">
      <div class="bottom-time-list">
        <span class="bottom-time-bold">完成时间:</span>
        <span>{{ data.WCSJ }}</span>
      </div>
    </div>
  </div>
</template>
<script>
// import OtherComponent from '@/components/OtherComponent'
export default {
  name: '',
  components: {
    // OtherComponent
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {
    data: {
      type: null,
      default: false
    },
    ifFile: {
      type: null,
      default: false
    }
  },
  computed: {},
  watch: {},
  beforeCreate() {
    // 生命周期钩子：组件实例刚被创建，组件属性计算之前，如 data 属性等
  },
  created() {
    // 生命周期钩子：组件实例创建完成，属性已绑定，但 DOM 还未生成，el 属性还不存在
    // 初始化渲染页面
  },
  beforeMount() {
    // 生命周期钩子：模板编译/挂载之前
  },
  mounted() {
    // 生命周期钩子：模板编译、挂载之后（此时不保证已在 document 中）
  },
  beforeUpate() {
    // 生命周期钩子：组件更新之前
  },
  updated() {
    // 生命周期钩子：组件更新之后
  },
  activated() {
    // 生命周期钩子：keep-alive 组件激活时调用
  },
  deactivated() {
    // 生命周期钩子：keep-alive 组件停用时调用
  },
  beforeDestroy() {
    // 生命周期钩子：实例销毁前调用
  },
  destroyed() {
    // 生命周期钩子：实例销毁后调用
  },
  errorCaptured(err, vm, info) {
    // 生命周期钩子：当捕获一个来自子孙组件的错误时被调用。此钩子会收到三个参数：错误对象、发生错误的组件实例以及一个包含错误来源信息的字符串。
    console.log(err, vm, info)
  },
  methods: {
    jump() {
      const { path } = this.$route
      let toPath
      if (['/hospital-caseViews', '/hospital-details'].includes(path)) {
        toPath = '/hospital-chargeDetails'
      } else if (path === '/whitelist-caseViews') {
        toPath = '/whitelist-chargeDetails'
      } else if (path === '/embedIndex-caseViews') {
        toPath = '/embedIndex-chargeDetails'
      } else if (path === '/reviewIndex-caseViews') {
        toPath = '/reviewIndex-chargeDetails'
      } else {
        toPath = '/ChargeDetails'
      }
      // status = 1 代表不能复制文本
      this.$router.push({ path: toPath, query: { MED_REC_ID: this.data.MED_REC_ID }})
    },
    getBlankIndex(item) {
      if (item.error_field == 'AAA11' || item.error_field == 'AAA10' || item.error_field == 'AAA09') {
        item.error_field = 'AAA11'
      }
      if (item.error_field == 'ICD10_ID1' || item.error_field == 'ABC01C') {
        item.error_field = 'ICD10_ID1'
      }
      const eleClass = document.querySelectorAll('.choose-twinkle')
      console.log(eleClass)
      for (let item = 0; item < eleClass.length; item++) {
        console.log(eleClass[item])
        eleClass[item].className = 'table-label'
      }
      if (Array.isArray(this.$refs[item.error_field])) {
        this.$refs[item.error_field][0].className = 'choose-twinkle'
        this.$refs[item.error_field][0].scrollIntoView({ block: 'start', behavior: 'smooth' })
      } else {
        this.$refs[item.error_field].className = 'choose-twinkle'
        this.$refs[item.error_field].scrollIntoView({ block: 'start', behavior: 'smooth' })
      }
    }
  }
}
</script>
<style lang="scss" scoped>
#MyDiv {
  padding: 0;
  margin: 0 15px;
}
.cont-title {
  display: flex;
  justify-content: space-between;
  span {
    padding: 5px;
  }
}
.cont-left {
  //   flex: 1;
  //   min-height: 650px;
  //   margin: 0 15px;
  //   height: 800px;
  //   overflow-y: scroll;
  background: #ffffff;
  // border: 1px solid #e2e2e2;
  padding: 10px;
}
.cont-title-description {
  margin: 20px;
  font-size: 24px;
  font-weight: bold;
  color: #2c3240;
  //   width: 1200px;
  text-align: center;
}
.cont-edit {
  margin: 20px;
  font-weight: bold;
  color: #2c3240;
  span {
    padding: 0 10px;
    font-size: 14px;
  }
}
.cont-edit-dev {
  margin: 20px;
  font-weight: bold;
  color: #2c3240;
  display: flex;
  justify-content: space-between;
  align-items: center;
  div {
    display: flex;
    // min-width: 300px;
    align-items: center;
  }
  span {
    white-space: nowrap;
    padding: 0 10px;
    font-size: 12px;
  }
}
table {
  min-width: 1000px;
  table-layout: fixed;
  empty-cells: show;
  border-collapse: collapse;
  margin: 0 auto;
  margin-bottom: 30px;
  td {
    border: 1px solid #dddddd;
    // padding-left: 16px;
    text-align: center;
    font-size: 12px;
    box-sizing: border-box;
  }
  .table-label {
    width: 150px;
    height: 40px;
    color: #666666;
    background-color: #f9f9f9;
  }
  .table-value {
    width: 210px;
    height: 40px;
    color: #333333;
    // padding-right: 16px;
    text-align: center;
  }
}
.ColorRed {
  color: red !important;
  font-weight: bold;
}
.table-labelon {
  width: 300px;
  height: 40px;
  color: #666666;
  background-color: #f9f9f9;
}
.table-valueon {
  width: 300px;
  height: 40px;
  font-size: 12px;
  color: #333333;
  text-align: center;
  margin: 0;
  padding: 0;
}
.FA6400 {
  color: #fa6400;
  font-size: 12px;
}
.table-value-p {
  width: 100%;
  padding: 5px;
  display: flex;
  justify-content: space-around;
  span {
    min-width: 200px;
    font-size: 12px;
  }
}
.cont-reight-top-title {
  margin-top: 18px;
  font-size: 22px;
  font-weight: bold;
  color: #333333;
  width: 100%;
  text-align: center;
}

.width150 {
  width: 70px !important;
}
.width1000 {
  width: 150px !important;
}
.width200 {
  width: 200px !important;
}
.width100 {
  // width: 1000px;
  width: 100%;
  height: 100%;
  border-top: 0;
}
.width170 {
  width: 170px;
}
.table-labelonSize {
  font-size: 13px;
}
.choose-twinkle {
  // background: red;
  font-size: 20px;
  color: red;
  font-weight: 600;
  background: yellow;
}
</style>
