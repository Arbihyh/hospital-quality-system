<template>
  <!-- style="user-select: none" -->
  <div id="MyDiv"  :class="{ nocopy: $route.query.status }">
    <!-- 建坤：先注释，后期再说 -->
    <!-- <div class="header">
      <template v-if="!$route.query.status">
        <el-button type="success" v-if="!ifFile" @click="funEdit">编辑</el-button>
        <el-button type="warning" v-if="ifFile" @click="funNext">保存</el-button>
      </template>
</div> -->
    <div class="cont_container">
      <div class="cont-left">
        <div class="contLeft-title-top">
          <span class="contLeft-title-fj">附件1</span>

          <div class="contLeft-title-rbox">
            <div class="title-rbox-f">
              <div class="refachInput">
                <span ref="ZA03" class="homeTitle-i-t">医疗机构名称</span>
                <span v-if="!ifFile" class="homeTitle-i-x">{{ data.ZA03 }}</span>
                <!-- data.ZA03 -->
                <el-input v-else class="width200" v-model="data.ZA03" placeholder="请输入内容"></el-input>
              </div>
            </div>
            <div class="refachInput" style="display: flex">
              <span ref="UNT_ID" v-if="!ifFile" class="homeTitle-i-t">
                ( 组织机构代码：
                <span class="homeTitle-i-x">
                  <span>{{ data.UNT_ID }}</span>
                </span>
                {{ data.UNT_ID ? '' : '-' }})
              </span>
              <!-- data.AAA27 -->
              <div v-else>
                <el-input class="width170" v-model="data.UNT_ID" placeholder="请输入内容"></el-input>
              </div>
            </div>
          </div>
        </div>
        <div class="cont-title-description">住院病案首页</div>
        <!--  -->
        <div class="contLeft-title-top">
          <div class="refachInput">
            <span ref="AAA26C" class="homeTitle-i-t">医疗付费方式</span>
            <span v-if="!ifFile" class="homeTitle-i-x">{{ data.AAA26C }}</span>
            <el-input v-else v-model="data.AAA26C" class="width150" placeholder="请输入内容"></el-input>
          </div>
          <!-- <div class="refachInput" style="margin-left: 12px;">
            <span ref="TYSHXYDM" class="homeTitle-i-t">统一社会信用代码:</span>
            <span v-if="!ifFile" class="homeTitle-i-x">{{ data.TYSHXYDM }}</span>
            <el-input v-else v-model="data.TYSHXYDM" class="width150" placeholder="请输入内容"></el-input>
          </div> -->
        </div>
        <!--  -->
        <div class="cont-title contLeft-title-top" style="margin-top: 10px">
          <div class="refachInput">
            <span ref="AAA29" class="homeTitle-i-t" style="padding-right: 28px">健康卡号</span>
            <span v-if="!ifFile" class="homeTitle-i-x">{{ data.AAA27 ? data.AAA27 : ' — ' }}</span>
            <!-- data.AAA27 -->
            <el-input v-else class="width170" v-model="data.AAA27" placeholder="请输入内容"></el-input>
          </div>
          <div class="refachInput">
            <span class="homeTitle-i-t">第</span>
            <span v-if="!ifFile" class="homeTitle-i-x">{{ data.AAA29 }}</span>
            <el-input v-else v-model="data.AAA29" class="width100" style="width: 80px" placeholder="请输入内容"></el-input>
            <span class="homeTitle-i-t">次住院</span>
          </div>
          <div class="refachInput">
            <span ref="AAA28" class="homeTitle-i-t">病案号</span>
            <span v-if="!ifFile" class="homeTitle-i-x">{{ data.AAA28 }}</span>
            <el-input v-else v-model="data.AAA28" class="width170" placeholder="请输入内容"></el-input>
          </div>
        </div>

        <div class="cont-edit-dev"></div>
        <div class="table">
          <table ref="tables">
            <tr>
              <td class="table-label table-label1">姓名</td>
              <td class="table-value" v-if="!ifFile" ref="AAA01">{{ data.AAA01 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA01" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label2">性别</td>
              <td class="table-value" v-if="!ifFile" ref="AAA02C">{{ data.AAA02C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA02C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label3">出生日期</td>
              <td class="table-value" v-if="!ifFile" ref="AAA03">{{ data.AAA03 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA03" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label4">年龄</td>
              <td class="table-value" v-if="!ifFile" ref="AAA04">{{ data.AAA04 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA04" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label6">天龄（＜28天）</td>
              <td class="table-value" v-if="!ifFile" ref="AAA40">{{ data.AAA40 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA40" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>

              <td class="table-label table-label5">国籍</td>
              <td class="table-value" v-if="!ifFile" ref="AAA05C">{{ data.AAA05C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA05C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label7">出生地</td>
              <td class="table-value" v-if="!ifFile" ref="AAA11">{{ data.AAA09 }}{{ data.AAA10 }}{{ data.AAA11 }}</td>
              <td class="table-value" v-else>
                <div style="display: flex; align-items: center">
                  <el-input v-model="data.AAA09" class="width100 tableReach" placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA10" class="width100 tableReach" placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA11" class="width100 tableReach" placeholder="请输入内容"></el-input>
                </div>
              </td>
              <!-- <td class="table-label table-label7" ref="AEM01C">新生儿出生体重</td>
              <td class="table-value" v-if="!ifFile">{{ data.AEM01C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AEM01C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td> -->
              <td class="table-label table-label18">籍贯</td>
              <td class="table-value" v-if="!ifFile" ref="AAA44">{{ data.GG }}</td>
              <td class="table-value" v-else>
                <div style="display: flex; align-items: center">
                  <el-input v-model="data.AAA43" class="width100 tableReach" placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA44" class="width100 tableReach" placeholder="请输入内容"></el-input>
                </div>
              </td>
              <!-- <td class="table-label table-label8" ref="AAA42">新生儿入院体重</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA42 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA42" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td> -->
            </tr>
            <tr>
              <td class="table-label table-label111">民族</td>
              <td class="table-value" v-if="!ifFile" ref="AAA06C">{{ data.AAA06C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA06C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>

              <td class="table-label table-label12">身份证类别</td>
              <td class="table-value" v-if="!ifFile" ref="SFZJLX">{{ data.SFZJLX ? data.SFZJLX : '居民身份证' }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.SFZJLX" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label12">证件号</td>
              <td class="table-value" v-if="!ifFile" ref="AAA07">{{ data.AAA07 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA07" style="padding: 0px !important; font-size: 13px !important"
                  class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label12">职业</td>
              <td class="table-value" v-if="!ifFile" ref="AAA18C">{{ data.AAA18C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA18C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label13">婚姻</td>
              <td class="table-value" v-if="!ifFile" ref="AAA08C">{{ data.AAA08C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA08C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label13">现住址</td>
              <td class="table-value" colspan="3" v-if="!ifFile" ref="AAA49">{{ data.AAA48 }}{{ data.AAA49 }}{{
                data.AAA50
              }}{{ data.AAA15 }}</td>
              <td class="table-value" colspan="3" v-else>
                <div style="display: flex; align-items: center">
                  <el-input v-model="data.AAA48" style="width: 200px !important" class="width100 tableReach"
                    placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA49" style="width: 200px !important" class="width100 tableReach"
                    placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA50" style="width: 200px !important" class="width100 tableReach"
                    placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA15" class="width100 tableReach" placeholder="请输入内容"></el-input>
                </div>
              </td>
              <td class="table-label table-label13">电话</td>
              <td class="table-value" v-if="!ifFile" ref="AAA51">{{ data.AAA51 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA51" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label13">邮编</td>
              <td class="table-value" v-if="!ifFile" ref="AAA17C">{{ data.AAA17C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA17C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label17">户口地址</td>
              <td class="table-value" colspan="3" v-if="!ifFile" ref="AAA47">{{ data.AAA45 }}{{ data.AAA46 }}{{
                data.AAA47
              }}{{ data.AAA12 }}</td>
              <td class="table-value" colspan="3" v-else>
                <div style="display: flex; align-items: center">
                  <el-input v-model="data.AAA45" style="width: 200px !important" class="tableReach"
                    placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA46" style="width: 200px !important" class="width100 tableReach"
                    placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA47" style="width: 200px !important" class="width100 tableReach"
                    placeholder="请输入内容"></el-input>
                  <el-input v-model="data.AAA12" class="width100 tableReach" placeholder="请输入内容"></el-input>
                </div>
              </td>
              <td class="table-label table-label19">邮编</td>
              <td class="table-value" v-if="!ifFile" ref="AAA14C">{{ data.AAA14C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA14C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label20">工作单位及地址</td>
              <td class="table-value" colspan="3" v-if="!ifFile" ref="AAA19">{{ data.AAA19 }}</td>
              <td class="table-value" colspan="3" v-else>
                <el-input v-model="data.AAA19" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label21">单位电话</td>
              <td class="table-value" colspan="3" v-if="!ifFile" ref="AAA20">{{ data.AAA20 }}</td>
              <td class="table-value" colspan="3" v-else>
                <el-input v-model="data.AAA20" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label22">邮编</td>
              <td class="table-value" v-if="!ifFile" ref="AAA21C">{{ data.AAA21C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA21C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label7">新生儿出生体重</td>
              <td class="table-value" v-if="!ifFile" ref="AEN01">{{ data.AEN01 }}克</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AEN01" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label8">新生儿入院体重</td>
              <td class="table-value" v-if="!ifFile" ref="AAA42">{{ data.AAA42 }}克</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA42" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label23">联系人姓名</td>
              <td class="table-value" v-if="!ifFile" ref="AAA22">{{ data.AAA22 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA22" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label24">关系</td>
              <td class="table-value" v-if="!ifFile" ref="AAA23C">{{ data.AAA23C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA23C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label25">地址</td>
              <td class="table-value" v-if="!ifFile" ref="AAA24">{{ data.AAA24 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA24" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label26">电话</td>
              <td class="table-value" v-if="!ifFile" ref="AAA25">{{ data.AAA25 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA25" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <!-- <td class="table-label table-label26" ref="AAA25">邮编</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA25 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA25" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td> -->
              <td class="table-label table-label27">入院途径</td>
              <td class="table-value" colspan="3" v-if="!ifFile" ref="AAB06C">{{ data.AAB06C }}</td>
              <td class="table-value" colspan="3" v-else>
                <el-input v-model="data.AAB06C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label27">入院时间</td>
              <td class="table-value" v-if="!ifFile" ref="AAB01">{{ data.AAB01 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAB01" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label27">入院科别</td>
              <td class="table-value" v-if="!ifFile" ref="AAB02C">{{ data.AAB02C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAB02C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label27">病房</td>
              <td class="table-value" v-if="!ifFile" ref="BFRY">{{ data.BFRY }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.BFRY" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label27">转科科别</td>
              <td class="table-value" v-if="!ifFile" ref="ZKKB">{{ data.AAD01C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAD01C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label27">出院时间</td>
              <td class="table-value" v-if="!ifFile" ref="AAC01">{{ data.AAC01 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAC01" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label27">出院科别</td>
              <td class="table-value" v-if="!ifFile" ref="AAC02C">{{ data.AAC02C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAC02C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label27">病房</td>
              <td class="table-value" v-if="!ifFile" ref="AAC03">{{ data.AAC03 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAC03" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label27">实际住院</td>
              <td class="table-value" v-if="!ifFile" ref="AAC04">
                {{ data.AAC04 }}
                <span v-if="data.AAC04">天</span>
              </td>
              <td class="table-value" v-else>
                <div style="display: flex; align-items: center; justify-content: center">
                  <el-input v-model="data.AAC04" style="width: 50px !important" class="width70 tableReach"
                    placeholder="请输入"></el-input>
                  天
                </div>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label28" style="font-weight: bold">门(急)诊诊断</td>
              <td class="table-value" v-if="!ifFile" ref="ABA01N">{{ data.ABA01N }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.ABA01N" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label29" style="font-weight: bold">疾病编码</td>
              <td class="table-value" colspan="5" v-if="!ifFile" ref="ABA01C">{{ data.ABA01C }}</td>
              <td class="table-value" colspan="5" v-else>
                <el-input v-model="data.ABA01C" class="width100 tableReach" placeholder="请输入内容"></el-input>
              </td>
            </tr>
          </table>
        </div>

        <div class="table">
          <table>
            <tr>
              <td class="table-labelon table-label30" style="font-weight: bold">出院诊断</td>
              <td class="table-labelon table-label31" style="font-weight: bold">疾病编码</td>
              <td class="table-labelon table-label32" colspan="3" style="font-weight: bold; width: 600px">入院病情</td>
              <!-- <td class="table-labelon table-label33"  style="font-weight:bold;">出院诊断</td>
              <td class="table-labelon table-label34"  style="font-weight:bold;">疾病编码</td>
              <td class="table-labelon table-label35"  style="font-weight:bold;">入院病情</td> -->
            </tr>
            <!--  diagnosis -->
            <tr v-for="(item, index) in data.diagnosis" :key="index">
              <td class="table-valueon" v-if="item.class == 'main'" ref="ABC01N">
                <div style="display: flex; align-items: center">
                  <p style="padding-left: 5px; font-weight: bold" v-if="item.class == 'main'">主要诊断：</p>
                  <p v-if="!ifFile">{{ item.class == 'main' ? item.ICD10_NAME : '' }}</p>
                  <div v-else>
                    <el-input v-model="item.ICD10_NAME" class="width100 tableReaches" placeholder="请输入内容"></el-input>
                  </div>
                </div>
              </td>
              <td class="table-valueon" v-else style="display: flex; align-items: center">
                <p style="padding-left: 5px">其他诊断：</p>
                <p v-if="!ifFile">{{ item.class == 'other' ? item.ICD10_NAME : '' }}</p>
                <div v-else>
                  <el-input v-model="item.ICD10_NAME" class="width100 tableReaches" placeholder="请输入内容"></el-input>
                </div>
              </td>
              <!-- <td class="table-valueon"  v-if="!ifFile" >{{ item.FRIST_ASSISTANT_NAME }}</td>
              <td class="table-valueon"  v-if="ifFile"> <el-input class="width80 tableReach" v-model="item.FRIST_ASSISTANT_NAME" placeholder="请输入内容"></el-input></td> -->
              <!-- <td class="table-valueon FA6400">{{ item.ABF01C }}</td> -->
              <td class="table-valueon table-label" ref="ICD10_ID1" v-if="!ifFile">{{ item.ICD10_ID1 }}</td>
              <td class="table-valueon" v-if="ifFile"><el-input class="width80 tableReach" v-model="item.ICD10_ID1"
                  placeholder="请输入内容"></el-input></td>

              <td class="table-valueon" v-if="!ifFile" ref="ABC03C" colspan="3">{{ item.RYQK }}</td>
              <td class="table-valueon" v-if="ifFile" colspan="3"><el-input class="width80 tableReach"
                  v-model="item.RYQK" placeholder="请输入内容"></el-input></td>
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
              <td class="table-value table-label" colspan="1" style="font-weight: bold; width: 20%">损伤、中毒的外部原因:</td>
              <td class="table-value" colspan="2" style="width: 30%" ref="ABG01N">
                <div v-if="!ifFile">{{ data.WBYY }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.WBYY" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold; width: 20%">疾病编码</td>
              <td class="table-value" ref="ABG01C" colspan="2" style="width: 30%">
                <div v-if="!ifFile">{{ data.H23 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.H23" placeholder="请输入内容"></el-input>
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
              <td class="table-value table-label" colspan="1" style="font-weight: bold">病理诊断:</td>
              <td class="table-value" colspan="2" ref="ABF01N">
                <div v-if="!ifFile">{{ data.ABF01N }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.ABF01N" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">病理诊断编码</td>
              <td class="table-value" colspan="2" ref="ABF01C">
                <div v-if="!ifFile">{{ data.ABF01C }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.ABF01C" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">最高诊断依据:</td>
              <td class="table-value" colspan="2" ref="YJ">
                <div v-if="!ifFile">{{ data.ZLLB }}</div>
                <!-- <el-input v-else class="width100 tableReach" v-model="data.ABF04" placeholder="请输入内容"></el-input> -->
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">病理号</td>
              <td class="table-value" colspan="2" ref="ABF04">
                <div v-if="!ifFile">{{ data.ABF04 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.ABF04" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">药物过敏(1.无 2.有 3.-):</td>
              <td class="table-value" colspan="2" ref="AEB01">
                <div v-if="!ifFile" style="align-items: center; text-align: center">
                  <span style="white-space: pre-wrap">{{ data.AEB02C }} &nbsp;&nbsp;</span>
                  <!-- <span v-if="data.AEB02C == '有'">
                    过敏药物：
                    <span>{{ data.AEB01 }}</span>
                  </span> -->
                  <span>; {{ data.AEB01 }}</span>
                </div>
                <div v-else style="display: flex; align-items: center">
                  <el-input class="width100 tableReach" style="width: 80px !important" v-model="data.AEB02C"
                    placeholder="请输入内容"></el-input>
                  <!-- <div v-if="data.AEB02C == '无'" style="display: flex; align-items: center"></div>
                  <div v-else-if="data.AEB02C == '有'" style="display: flex; align-items: center">
                    <el-input class="width100 tableReach" v-model="data.AEB01" placeholder="请输入内容"></el-input>
                  </div> -->
                </div>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">死亡患者尸检(1.是 2.否 3.-):</td>
              <td class="table-value" colspan="2" ref="AEI01C">
                <div v-if="!ifFile">{{ data.AEI01C }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEI01C" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">血型(1.A 2.B 3.O 4.AB 5.不详 6.未查):
              </td>
              <td class="table-value" colspan="2" ref="AEG01C">
                <div v-if="!ifFile">{{ data.AEG01C }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEG01C" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">Rh(1.阴 2.阳 3.不详 4.未查):</td>
              <td class="table-value" colspan="2" ref="AEG02C">
                <div v-if="!ifFile">{{ data.AEG02C }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEG02C" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold; border-right: 0">护理级别：</td>
              <td class="table-value" colspan="6" style="" v-if="!ifFile" ref="YJHL">
                <div>
                  1.特级护理 {{ data.TJHL ? data.TJHL : ' - ' }}天 2.I级护理 {{ data.YJHL ? data.YJHL : ' - ' }}(天) 3.II级护理 {{
                    data.EJHL ? data.EJHL : ' - ' }}天 4.III级护理
                  {{ data.SJHL ? data.SJHL : ' - ' }}天
                </div>
              </td>
              <td class="table-value" colspan="6" style="border-left: 0" v-if="ifFile">
                <div style="width: 100%; display: flex; align-items: center; justify-content: center">
                  <div>
                    1.特级护理
                    <el-input style="width: 100px" class="width100 tableReach" v-model="data.TJHL"
                      placeholder="请输入内容"></el-input>
                    天 2.I级护理
                    <el-input style="width: 100px" class="width100 tableReach" v-model="data.YJHL"
                      placeholder="请输入内容"></el-input>
                    天 3.II级护理
                    <el-input style="width: 100px" class="width100 tableReach" v-model="data.EJHL"
                      placeholder="请输入内容"></el-input>
                    天 4.III级护理
                    <el-input style="width: 100px" class="width100 tableReach" v-model="data.SJHL"
                      placeholder="请输入内容"></el-input>
                    天
                  </div>
                </div>
              </td>
            </tr>
            <!--  -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">科主任:</td>
              <td class="table-value" colspan="2" ref="AEE01">
                <div v-if="!ifFile">{{ data.AEE01 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE01" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">科主任编码：</td>
              <td class="table-value" colspan="2" ref="AEE01_CODE">
                <div v-if="!ifFile">{{ data.AEE01_CODE }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE01_CODE" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <!--  -->
            <!--  -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">医疗组长:</td>
              <td class="table-value" colspan="2" ref="YLZZ">
                <div v-if="!ifFile">{{ data.YLZZ }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.YLZZ" placeholder="请输入内容"></el-input>
                <!-- <el-input :disabled="!ifFile" class="width100 tableReach"  v-model="data.YLZZ" placeholder="请输入内容"></el-input> -->
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">主任(副主任)医师：</td>
              <td class="table-value" colspan="2" ref="AEE02">
                <div v-if="!ifFile">{{ data.AEE02 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE02" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <!--  -->
            <!--  -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">主任(副主任)医师编码:</td>
              <td class="table-value" colspan="2" ref="AEE02_CODE">
                <div v-if="!ifFile">{{ data.AEE02_CODE }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE02_CODE" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">主治医师:</td>
              <td class="table-value" colspan="2" ref="AEE03">
                <div v-if="!ifFile">{{ data.AEE03 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE03" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <!--  -->
            <!--  -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">主治医师编码:</td>
              <td class="table-value" colspan="2" ref="AEE03_CODE">
                <div v-if="!ifFile">{{ data.AEE03_CODE }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE03_CODE" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">住院医师:</td>
              <td class="table-value" colspan="2" ref="AEE04">
                <div v-if="!ifFile">{{ data.AEE04 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE04" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <!--  -->
            <!--  -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">住院医师编码:</td>
              <td class="table-value" colspan="2" ref="AEE04_CODE">
                <div v-if="!ifFile">{{ data.AEE04_CODE }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE04_CODE" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">责任护士:</td>
              <td class="table-value" colspan="2" ref="AEE10">
                <div v-if="!ifFile">{{ data.AEE10 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE10" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <!--  -->
            <!--  -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">责任护士编码:</td>
              <td class="table-value" colspan="2" ref="ZRHSBM">
                <div v-if="!ifFile">{{ data.ZRHSBM }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.ZRHSBM" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">进修医师:</td>
              <td class="table-value" colspan="2" ref="AEE05">
                <div v-if="!ifFile">{{ data.AEE05 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE05" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <!--  -->
            <!--  -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">实习医师:</td>
              <td class="table-value" colspan="2" ref="AEE07">
                <div v-if="!ifFile">{{ data.AEE07 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AEE07" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">编码员:</td>
              <td class="table-value" colspan="2" ref="BMY">
                <div v-if="!ifFile">{{ data.BMY }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.BMY" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <!--  -->
            <!--  -->
            <!--  -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">病案质量(1.甲 2.乙 3.丙)：</td>
              <td class="table-value" colspan="2" ref="AED01C">
                <div v-if="!ifFile">{{ data.AED01C }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AED01C" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">质控日期:</td>
              <td class="table-value" colspan="2" ref="ZKRQ">
                <div v-if="!ifFile">{{ data.ZKRQ }}</div>
                <el-input class="width100 tableReach" v-else v-model="data.ZKRQ" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <!-- 优化建议 -->
            <tr>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">质控医师:</td>
              <td class="table-value" colspan="2" ref="AED02">
                <div v-if="!ifFile">{{ data.AED02 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AED02" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value table-label" colspan="1" style="font-weight: bold">质控护士:</td>
              <td class="table-value" colspan="2" ref="AED03">
                <div v-if="!ifFile">{{ data.AED03 }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.AED03" placeholder="请输入内容"></el-input>
              </td>
            </tr>
          </table>
          <table>
            <tr>
              <td class="table-labelon table-labelon1" style="font-weight: bold">手术及操作编码</td>
              <td class="table-labelon" ref="OPE_DATE" style="font-weight: bold">手术及操作日期</td>
              <td class="table-labelon" ref="OPE_LEVEL" style="font-weight: bold">手术级别</td>
              <td class="table-labelon" ref="ICD9_NAME" style="font-weight: bold">手术及操作名称</td>
              <td class="table-labelon" ref="SSLX" style="font-weight: bold">手术类型</td>
              <!-- <td class="table-labelon" ref="SSPB" style="font-weight: bold">手术判别</td> -->
              <td class="table-labelon" style="padding: 0px; border: 0">
            <tr>
              <td class="table-labelon" colspan="3" style="border-left: 0; font-weight: bold; border-right: 0">手术及操作人员
              </td>
            </tr>
            <tr>
              <td class="table-labelon width150" style="font-weight: bold" ref="OPE_MAN_NAME">术者</td>
              <td class="table-labelon width150" style="font-weight: bold" ref="FRIST_ASSISTANT_NAME">I助</td>
              <td class="table-labelon width150" style="font-weight: bold" ref="SECOND_ASSISTANT_NAME">II助</td>
            </tr>
            </td>
            <td class="table-labelon" ref="INCISION_GRADE_ID" style="font-weight: bold">切口愈合等级</td>
            <td class="table-labelon" ref="HOCUS_WAY_ID" style="font-weight: bold">麻醉方式</td>
            <td class="table-labelon" style="font-weight: bold">麻醉医师</td>
            </tr>
            <tr v-for="(item, index) in data.operation" :key="index">
              <td class="table-value" ref="ICD9_ID1">
                <div v-if="ifFile">
                  <el-input class="width140 tableReach" v-model="item.ICD9_ID1" placeholder="请输入内容"></el-input>
                </div>
                <div v-else>
                  {{ item.ICD9_ID1 }}
                </div>
              </td>
              <td class="table-value" ref="OPE_DATE">
                <div v-if="ifFile">
                  <el-input class="width150 tableReach" style="width: 120px !important; padding: 0px !important"
                    v-model="item.OPE_DATE" placeholder="请输入内容"></el-input>
                </div>
                <div v-else>
                  {{ item.OPE_DATE }}
                </div>
              </td>
              <td class="table-value" ref="OPE_LEVEL">
                <div v-if="ifFile">
                  <el-input class="width150 tableReach" v-model="item.OPE_LEVEL" placeholder="请输入内容"></el-input>
                </div>
                <div v-else>
                  {{ item.OPE_LEVEL }}
                </div>
              </td>
              <td class="table-value" ref="ICD9_NAME">
                <div v-if="ifFile">
                  <el-input class="width100 tableReach" v-model="item.ICD9_NAME" placeholder="请输入内容"></el-input>
                </div>
                <div v-else>
                  {{ item.ICD9_NAME }}
                </div>
              </td>
              <td class="table-value" ref="SSLX">
                <div v-if="ifFile">
                  <el-input class="width100 tableReach" v-model="item.SSLX" placeholder="请输入内容"></el-input>
                </div>
                <div v-else>
                  {{ item.SSLX }}
                </div>
              </td>
              <!-- <td class="table-value">
                <div v-if="ifFile">
                  <el-input class="width100 tableReach" v-model="item.SSPB" placeholder="请输入内容"></el-input>
                </div>
                <div v-else>
                  {{ item.SSPB }}
                </div>
              </td> -->
              <td class="table-value">
            <tr>
              <td class="table-value width100" style="border-bottom: 0; border-top: 0">
                <div v-if="!ifFile" ref="OPE_MAN_NAME" style="width: 80px">
                  {{ item.OPE_MAN_NAME }}
                </div>
                <div v-else>
                  <el-input class="tabright" style="width: 80px" v-model="item.OPE_MAN_NAME"
                    placeholder="请输入"></el-input>
                </div>
              </td>
              <td class="table-value width100" style="border-bottom: 0; border-top: 0">
                <div v-if="!ifFile" ref="FRIST_ASSISTANT_NAME" style="width: 80px">
                  {{ item.FRIST_ASSISTANT_NAME }}
                </div>
                <div v-else>
                  <el-input class="tabright" style="width: 80px" v-model="item.FRIST_ASSISTANT_NAME"
                    placeholder="请输入"></el-input>
                </div>
              </td>
              <td class="table-value width100" style="border-bottom: 0; border-top: 0">
                <div v-if="!ifFile" ref="SECOND_ASSISTANT_NAME" style="width: 80px">
                  {{ item.SECOND_ASSISTANT_NAME }}
                </div>
                <div v-else>
                  <el-input class="tabright" style="width: 80px" v-model="item.SECOND_ASSISTANT_NAME"
                    placeholder="请输入"></el-input>
                </div>
              </td>
            </tr>
            </td>
            <td class="table-value">
              <div v-if="ifFile">
                <el-input class="width150 tableReach" v-model="item.INCISION_GRADE_ID" placeholder="请输入内容"></el-input>
              </div>
              <div v-else ref="INCISION_GRADE_ID">
                {{ item.INCISION_GRADE_ID }}
              </div>
            </td>
            <td class="table-value" v-if="!ifFile" ref="HOCUS_WAY_ID">{{ item.HOCUS_WAY_ID }}</td>
            <td class="table-value" v-if="ifFile">
              <el-input class="width150 tableReach" v-model="item.HOCUS_WAY_ID" placeholder="请输入"></el-input>
            </td>
            <td class="table-value" v-if="!ifFile" ref="HOCUS_MAN_CODE">{{ item.HOCUS_MAN_NAME }}</td>
            <td class="table-value" v-if="ifFile">
              <el-input class="tableReach" v-model="item.HOCUS_MAN_NAME" placeholder="请输入"></el-input>
            </td>
            </tr>
            <tr v-if="!data.operation || data.operation.length == 0">
              <td class="table-value"></td>
              <td class="table-value"></td>
              <td class="table-value"></td>
              <td class="table-value"></td>
              <td class="table-value"></td>
              <td class="table-value" style="padding: 0px">
            <tr>
              <td class="table-value" style="padding: 0px; border: 0"></td>
              <td class="table-value" style="padding: 0px; border: 0"></td>
              <td class="table-value" style="padding: 0px; border: 0"></td>
            </tr>
            </td>
            <td class="table-value"></td>
            <td class="table-value"></td>
            <td class="table-value"></td>
            </tr>
            <!-- end -->
            <tr>
              <td class="table-value" colspan="3" style="font-weight: bold">是否为日间手术(1.是 2.否):</td>
              <td class="table-value" colspan="2" ref="RJSS">
                <div v-if="!ifFile">{{ data.RJSS }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.RJSS" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-value" colspan="3" style="font-weight: bold">有创呼吸机使用时间</td>
              <td class="table-value" colspan="2" ref="AEL01">
                <div style="display: flex; align-items: center; justify-content: center">
                  <div v-if="!ifFile">{{ data.AEL01 }}</div>
                  <el-input v-else class="width100 tableReach" v-model="data.AEL01" placeholder="请输入"></el-input>
                  <span style="width: 100px">小时</span>
                </div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="3" style="font-weight: bold">临床路径(1.是 2.否):</td>
              <td class="table-value" colspan="2" ref="LCLJ">
                <div v-if="!ifFile">{{ data.LCLJ }}</div>
                <el-input v-else class="width100 tableReach" v-model="data.LCLJ" placeholder="请输入"></el-input>
              </td>
              <td class="table-value" colspan="3" style="font-weight: bold">完成情况(1.完成 2.退出):</td>
              <td class="table-value" colspan="2" ref="WCQK">
                <div style="display: flex; align-items: center; justify-content: center">
                  <div v-if="!ifFile">{{ data.WCQK }}</div>
                  <el-input v-else class="width100 tableReach" style="width: 150px" v-model="data.WCQK"
                    placeholder="请输入内容"></el-input>
                </div>
              </td>
            </tr>

            <tr>
              <td class="table-value" colspan="3" style="font-weight: bold; border-right: 0">变异情况(1.有 2.无):</td>
              <td class="table-value" colspan="7" ref="BYQK" style="border-left: 0">{{ data.BYQK }}</td>
            </tr>

            <tr>
              <td class="table-value" colspan="2" style="font-weight: bold">颅脑损伤患者昏迷时间：</td>
              <td class="table-value" colspan="3" style="border-right: 0; border-left: 0" ref="AEJ03" v-if="!ifFile">
                入院前:{{ data.AEJ01 }}天{{ data.AEJ02 }}小时{{ data.AEJ03 }}分
              </td>
              <td class="table-value" colspan="4" style="border-left: 0" v-if="ifFile">
                <span style="font-size: 12px">入院前:</span>
                <el-input class="width150 tableReach" v-model="data.AEJ01" placeholder="请输入"></el-input>
                <span ref="AEJ01">天</span>
                <el-input class="width150 tableReach" v-model="data.AEJ02" placeholder="请输入"></el-input>
                <span ref="AEJ02">小时</span>
                <el-input class="width150 tableReach" v-model="data.AEJ03" placeholder="请输入"></el-input>
                <span ref="AEJ03">分</span>
              </td>
              <td class="table-value" colspan="1" style="border-left: 0; border-right: 0" v-if="!ifFile">入院后:{{
                data.AEJ04
              }}天{{ data.AEJ05 }}小时{{ data.AEJ06 }}分</td>
              <td class="table-value" colspan="8" style="border-left: 0" v-if="ifFile" ref="AEJ04">
                入院后：
                <el-input class="width150 tableReach" v-model="data.AEJ04" placeholder="请输入"></el-input>
                <span ref="AEJ04">天</span>
                <el-input class="width150 tableReach" v-model="data.AEJ05" placeholder="请输入"></el-input>
                <span ref="AEJ05">小时</span>
                <el-input class="width150 tableReach" v-model="data.AEJ06" placeholder="请输入"></el-input>
                <span ref="AEJ06">分</span>
              </td>
              <td class="table-value" colspan="5" style="border-left: 0" v-if="!ifFile"></td>
            </tr>
            <tr>
              <td class="table-labelon" colspan="3" ref="IS_MAIN_WAY">
                <div class="table-labelonSize" style="margin: 10px 0px; font-weight: bold">重症监护室名称</div>
                <div class="table-labelonSize" style="font-size: 12px; margin: 10px 0px; font-weight: bold">
                  CCU,RICU,SICU,NICU,PICU,EICU,MICU,其他</div>
              </td>
              <td class="table-labelon" colspan="3" ref="IN_TIME">
                <div class="table-labelonSize" style="margin: 10px 0px; font-weight: bold">进重症监护室时间(年月日时分)</div>
              </td>
              <td class="table-labelon" colspan="2" ref="OUT_TIME">
                <div class="table-labelonSize" style="margin: 10px 0px; font-weight: bold">出重症监护室时间(年月日时分)</div>
              </td>
              <td class="table-labelon" colspan="2" ref="HJ">
                <div class="table-labelonSize" style="margin: 10px 0px; font-weight: bold">(合计/小时)</div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="3" v-if="!ifFile">{{ data.IS_MAIN_WAY }}</td>
              <td class="table-value" colspan="3" v-if="ifFile" ref="IS_MAIN_WAY">
                <el-input class="width100 tableReach" v-model="data.IS_MAIN_WAY" placeholder="请编辑输入内容"></el-input>
              </td>
              <td class="table-value" colspan="3" v-if="!ifFile" ref="IN_TIME">{{ data.IN_TIME }}</td>
              <td class="table-value" colspan="3" v-if="ifFile">
                <el-input class="width100 tableReach" v-model="data.IN_TIME" placeholder="请编辑输入内容"></el-input>
              </td>
              <td class="table-value" colspan="2" v-if="!ifFile" ref="OUT_TIME">{{ data.OUT_TIME }}</td>
              <td class="table-value" colspan="2" v-if="ifFile">
                <el-input class="width100 tableReach" v-model="data.OUT_TIME" placeholder="请编辑输入内容"></el-input>
              </td>
              <td class="table-value" colspan="2" v-if="!ifFile" ref="HJ">{{ data.HJ }}</td>
              <td class="table-value" colspan="2" v-if="ifFile"></td>
            </tr>
            <!--  -->
            <tr>
              <td class="table-value" colspan="10" ref="AEM01C">
                <div
                  style="display: flex; align-items: center; justify-content: left; padding-left: 10px; padding-top: 10px; padding-bottom: 10px">
                  <div v-if="!ifFile" style="font-weight: bold">
                    离院方式：
                    <span style="font-weight: normal">
                      {{ data.AEM01C }}
                    </span>
                    <!-- <span v-if="data.YZZY_YLJG" style="width: 350px; margin-left: 400px; color: black;font-weight: bold">拟接收医疗机构名称：
                      <span style="font-weight: normal">{{data.YZZY_YLJG}}</span>
                    </span> -->
                    <span style="font-weight: normal">； {{ data.YZZY_YLJG }}</span>
                  </div>
                  <div v-else
                    style="font-weight: bold; display: flex; align-items: center; justify-content: left; padding-left: 10px">
                    离院方式：
                    <el-input class="width200 tableReach" style="width: 80px" v-model="data.AEM01C"
                      placeholder="请输入内容"></el-input>
                    <div v-if="ifFile && data.AEM01C == 2"
                      style="display: flex; align-items: center; justify-content: center">
                      <span style="width: 350px">医嘱转院，拟接收医疗机构名称：</span>
                      <el-input class="width200 tableReach" v-model="data.AEM02" placeholder="请输入内容"></el-input>
                    </div>
                    <div v-else-if="!ifFile && data.AEM01C == 3"
                      style="display: flex; align-items: center; justify-content: center">
                      <span style="width: 700px">医嘱转社区卫生服务机构乡镇卫生院，拟接收医疗机构名称：</span>
                      <el-input class="width200 tableReach" v-model="data.AEM02" placeholder="请输入内容"></el-input>
                    </div>
                    <div v-else-if="!ifFile && data.AEM01C == 4"
                      style="display: flex; align-items: center; justify-content: center">
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
              <td class="table-value" colspan="10" style="text-align: left">
                <div style="display: flex; align-items: center; justify-content: left; padding-left: 10px">
                  <div style="text-align: left; font-weight: bold" ref="AEM03C" v-if="!ifFile">
                    是否有出院31天内再住院计划:
                    <span style="font-weight: normal; white-space: pre-wrap">{{ formattedAEM03C }} ; {{ formattedAEM04
                    }}&nbsp;&nbsp;</span>
                  </div>
                  <div v-else
                    style="display: flex; text-align: left; align-items: center; font-weight: bold; justify-content: left; padding-left: 10px">
                    是否有出院31天内再住院计划:
                    <el-input class="width200 tableReach" style="width: 80px" v-model="data.AEM03C"
                      placeholder="请输入内容"></el-input>
                    <div v-if="!ifFile && data.AEM03C == '有'"
                      style="display: flex; align-items: center; justify-content: left; padding-left: 10px">
                      <span style="width: 150px">有,目的</span>
                      <el-input class="width200 tableReach" v-model="data.AEM04" placeholder="请输入内容"></el-input>
                    </div>
                    <div v-else-if="!ifFile && data.AEM03C == '无'"
                      style="display: flex; align-items: center; justify-content: left; padding-left: 10px">
                      <span style="width: auto">无</span>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
            <!--  -->
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold">住院费用(总费用)：</td>
              <td class="table-value" colspan="8" v-if="!ifFile" ref="ADA01">
                <span ref="ADA01">总费用: {{ data.ADA01 }}</span>
                <span ref="ADA0101">(自付金额:{{ data.ADA0101 }})</span>
                <span class="table-value-look" @click="jump">查看明细</span>
              </td>
              <td class="table-value" colspan="2" v-if="ifFile">总费用</td>
              <td class="table-value" colspan="2" v-if="ifFile" ref="ADA01">
                <el-input class="width100 tableReach" v-model="data.ADA01" placeholder="请编辑输入内容"></el-input>
              </td>
              <td class="table-value" colspan="1" v-if="ifFile">自付金额</td>
              <td class="table-value" colspan="3" v-if="ifFile" ref="ADA0101">
                <el-input class="width100 tableReach" v-model="data.ADA0101" placeholder="请编辑输入内容"></el-input>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold" rowspan="2">1.综合医疗服务费:</td>
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(1)一般医疗服务费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D11" placeholder="请输入内容"></el-input>
                  </div>
                  <div v-else ref="D11">
                    {{ data.D11 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(2)一般治疗操作费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D12" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D12" v-else>
                    {{ data.D12 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(3)护理费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D13" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D13" v-else>
                    {{ data.D13 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(4)其他费用:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D14" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D14" v-else>
                    {{ data.D14 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold" rowspan="2">2.诊断类:</td>
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(5)病理诊断费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D15" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D15" v-else>
                    {{ data.D15 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(6)实验室诊断费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D16" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D16" v-else>
                    {{ data.D16 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(7)影像学诊断费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D17" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D17" v-else>
                    {{ data.D17 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(8)临床诊断项目费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D18" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D18" v-else>
                    {{ data.D18 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold">3.治疗类：</td>
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">
                    (9)非手术治疗项目费:
                    <span v-if="!ifFile" ref="D19">{{ data.D19 }}</span>
                  </span>
                  <div v-if="ifFile" style="display: flex; align-items: center">
                    <el-input class="width200 tableReachCCC" style="width: 120px !important" v-model="data.D19"
                      placeholder="请输入内容"></el-input>
                    <span style="text-align: left">(临床物理治疗费:):</span>
                    <el-input class="width200 tableReachCCC" style="width: 130px !important" v-model="data.D19X01"
                      placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D19X01" v-else>
                    <span>(临床物理治疗费:):</span>
                    {{ data.D19X01 }}):
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(10)手术治疗费:</span>
                  <div v-if="ifFile" style="display: flex; align-items: center">
                    <el-input class="width150 tableReachCCC" v-model="data.D20" style="width: 130px !important"
                      placeholder="请输入内容"></el-input>
                    <span>(麻醉费:</span>
                    <el-input class="width150 tableReachCCC" v-model="data.D20X01" style="width: 130px !important"
                      placeholder="请输入内容"></el-input>
                    <span>手术费:</span>
                    <el-input class="width150 tableReachCCC" v-model="data.D20X02" style="width: 130px !important"
                      placeholder="请输入内容"></el-input>
                    )
                  </div>
                  <div ref="D20" v-else>
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
              <td class="table-value" colspan="2" style="font-weight: bold">4.康复类：</td>
              <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px">(11)康复费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D21" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D21" v-else>
                    {{ data.D21 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4" style="border-left: 0; border-top: 0"></td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold">5.中医类：</td>
              <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D22">(12)中医治疗费用:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D22" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D22" v-else>
                    {{ data.D22 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4" style="border-left: 0; border-top: 0"></td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold">6.西药类：</td>
              <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D23">(13)“西药费”:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D23" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D23" v-else>
                    {{ data.D23 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4" style="border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D23X01">(抗菌药物费用):</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D23X01" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D23X01" v-else>
                    {{ data.D23X01 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold">7.中药类：</td>
              <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D24">(14)中成药费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D24" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D24" v-else>
                    {{ data.D24 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4" style="border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D25">(15)中草药费用:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D25" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D25" v-else>
                    {{ data.D25 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" rowspan="3" style="font-weight: bold">8.血液和血液制品类：</td>
              <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D26">(16)血费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D26" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D26" v-else>
                    {{ data.D26 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4" style="border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D27">(17)白蛋白类制品类费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D27" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D27" v-else>
                    {{ data.D27 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D28">(18)球蛋白类制品费</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D28" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D28" v-else>
                    {{ data.D28 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4" style="border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D29">(19)凝血因子类制品费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D29" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D29" v-else>
                    {{ data.D29 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D30">(20)细胞因子类制品费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D30" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D30" v-else>
                    {{ data.D30 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4" style="border-left: 0; border-top: 0"></td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold">9.耗材类:</td>
              <td class="table-value" colspan="2" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D31">(21)检查用一次性医用材料费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D31" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D31" v-else>
                    {{ data.D31 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="3" style="border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D32">(22)治疗用一次性医用材料费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D32" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D32" v-else>
                    {{ data.D32 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="3" style="border-left: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D33">(23)手术用一次性医用材料费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D33" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D33" v-else>
                    {{ data.D33 }}
                  </div>
                </div>
              </td>
            </tr>
            <tr class="tablexs">
              <td class="table-value" colspan="2" style="font-weight: bold">10.其他类:</td>
              <td class="table-value" colspan="4" style="border-right: 0; border-top: 0">
                <div style="display: flex; align-items: center">
                  <span style="padding-left: 10px" ref="D34">(24)其他费:</span>
                  <div v-if="ifFile">
                    <el-input class="width200 tableReachCCC" v-model="data.D34" placeholder="请输入内容"></el-input>
                  </div>
                  <div ref="D34" v-else>
                    {{ data.D34 }}
                  </div>
                </div>
              </td>
              <td class="table-value" colspan="4" style="border-left: 0; border-top: 0"></td>
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
                <div style="padding-left: 10px; text-indent: 3.7em; margin: 10px 0px">(二)
                  凡可由医院信息系统提供住院费用清单的，住院病案首页中可不填写“住院费用”。</div>
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


        <div v-if="zyh != '0' || zyh != 0">
            <div class="bottom-time bottom-time-top">
              <div class="bottom-time-listw">
                <span class="bottom-time-bold">医生签名:</span>
                <span>{{ signature.doctor_name }}</span>
              </div>
            </div>
            <div class="bottom-time bottom-time-top">
              <div class="bottom-time-list" v-if="signature.CJSJ">
                <span class="bottom-time-bold">创建时间:</span>
                <span>{{ signature.CJSJ }}</span>
              </div>
              <div class="bottom-time-list" v-if="signature.ZXSJ">
                <span class="bottom-time-bold">首次签名时间:</span>
                <span>{{ signature.ZXSJ }}</span>
              </div>
            </div>
            <div class="bottom-time bottom-time-botom" v-if="signature.WCSJ">
              <div class="bottom-time-list">
                <span class="bottom-time-bold">末次修改时间:</span>
                <span>{{ signature.WCSJ }}</span>
              </div>
              <div class="bottom-time-list">
                <span class="bottom-time-bold">业务时间:</span>
                <span v-if="signature.YWSJ">{{ signature.YWSJ }}</span>
              </div>
            </div>
          </div>
      </div>
      <!-- status 不存在 意味着不脱敏、医院自助查询 -->
      <div v-if="zyh === '0' || zyh === 0">
        <div class="cont-reight" v-if="!$route.query.status && isControl">
          <div class="cont-reight-top">
            <div class="cont-reight-btn">病案首页</div>
          </div>
          <!-- 评分 -->
          <div class="storeBox">
            <div class="text">{{ score }}</div>
            <div class="spa">
              <!-- 0优 1良 2中 3差 -->
              <div class="spaview" v-if="score >= 97">优</div>
              <div class="spaview" v-else-if="score >= 90 && score <= 96">良</div>
              <div class="spaview" v-else-if="score >= 75 && score <= 89">中</div>
              <div class="spaview" v-else>差</div>
            </div>
          </div>

          <!-- end -->
          <!-- 强制 -->
          <div class="flexNox">
            <div class="Yradio">
              <div class="Yradiobox"></div>
              强制
            </div>
            <div class="Rradio">
              <div class="Rradiobox"></div>
              建议
            </div>
          </div>

          <!-- end -->
          <div class="cont-reight-bottom" @click="getBlankIndex(item)" v-for="(item, index) in errorList" :key="index">
            <div style="display: flex;">
              <div
                :class="{ 'cont-reight-bottom-title': item.level == 0, 'cont-reight-bottom-title-null': item.level == 1 }">
                {{ item.category == 0 ? 'A类' : null }}
                {{ item.category == 1 ? 'B类' : null }}
                {{ item.category == 2 ? 'C类' : null }}
                {{ item.category == 3 ? 'D类' : null }}
                <br />
                -{{ item.down }}
              </div>
              <div class="cont-reight-bottom-conter">
                <p>字段：{{ item.error_name }}</p>
                <p>提示：{{ item.desc }}</p>
              </div>
            </div>
            <!--   -->
            <!-- <div class="zkyj" v-if="Array.isArray(item.basis) && item.basis.filter(str => !!str).length">
              <span class="bold">质控依据&gt;&gt;</span>
              <span v-for="(basis, index) in item.basis" :key="index">
                {{ basis }}
                <template v-if="index !== item.basis.length - 1"><br></template>
              </span>
            </div> -->
            <QualityControlBasis :basis-data="item.basis" :initially-expanded="item.show" 
            :index="index" content-type="1" @toggle="clickListItem" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import { data } from 'autoprefixer';
import { getHomeData } from '@/api/qc';
import QualityControlBasis from '@/components/quality-control-basis/index.vue'

// import OtherComponent from '@/components/OtherComponent'
export default {
  components: {
    // OtherComponent
    QualityControlBasis
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {
    zyh: {
      type: String,
      default: '0',
    },
  },
  data() {
    return {
      data: '',
      signature:{},
      errorList: [],
      valData: '',
      score: 0,
      ifFile: false,
      isControl: false,
      type_name: '',
      error_field: '',
    };
  },
  computed: {
    formattedAEM03C() {
      return `  ${this.data.AEM03C}  `;
    },

    formattedAEM04() {
      return `  ${this.data.AEM04}  `;
    },
  },
  watch: {},
  beforeCreate() {
    // 生命周期钩子：组件实例刚被创建，组件属性计算之前，如 data 属性等
  },
  created() {
    // 生命周期钩子：组件实例创建完成，属性已绑定，但 DOM 还未生成，el 属性还不存在
    this.type_name = this.zyh === '0'?(this.$route.query.type_name ? this.$route.query.type_name : '') : 'lc'; // 'lc' 临床
    this.error_field = this.$route.query.error_field ? this.$route.query.error_field : '';
    // 初始化渲染页面
    // this.$nextTick(() => {
    //   // 禁用右键
    //   document.oncontextmenu = new Function('event.returnValue=false');
    //   // 禁用选择
    //   document.onselectstart = new Function('event.returnValue=false');
    // });

    this.isControl = localStorage.getItem('isControl') == 'true' ? true : false;
  },
  beforeMount() {
    // 生命周期钩子：模板编译/挂载之前
  },
  mounted() {
    this.valData = this.zyh === '0' ? this.storageGet('getData') : this.zyh;
    if (this.valData) {
        if (this.type_name == 'lc') {
          this.funQuery_lc();
          this.getQualityResult(this.setColor);
        } else {
          this.funQuery(this.setColor);
        }
      }

    // 生命周期钩子：模板编译、挂载之后(此时不保证已在 document 中)
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
    console.log(err, vm, info);
  },
  methods: {
    jump() {
      const { path } = this.$route;
      let toPath;
      if (['/hospital-caseViews', '/hospital-details'].includes(path)) {
        toPath = '/hospital-chargeDetails';
      } else if (['/whitelist-caseViews', '/whitelist-details'].includes(path)) {
        toPath = '/whitelist-chargeDetails';
      } else {
        toPath = '/ChargeDetails';
      }
      // status = 1 代表不能复制文本
      this.$router.push({ path: toPath, query: { MED_REC_ID: this.valData } });
    },

     clickListItem(idx, quality_type) {
      this.errorList[idx].show = !this.errorList[idx].show
      this.$forceUpdate();
    },
    funNext() {
      this.$axios.post('/medicalRecordEdit', this.data).then(res => {
        console.log(res);
        setTimeout(() => {
          this.$message('修改成功');
          if (this.type_name == 'lc') {
            this.funQuery_lc();
            this.getQualityResult(this.setColor);
          } else {
            this.funQuery(this.setColor);
          }
          this.ifFile = false;
        }, 500);
      });
    },
    funEdit() {
      this.ifFile = true;
    },
    // 之前的方法
    getBlankIndex(item) {
      console.log('getBlankIndex', item);
      if (item.error_field == 'AAA11' || item.error_field == 'AAA10' || item.error_field == 'AAA09') {
        item.error_field = 'AAA11';
      }
      if (item.error_field == 'AEB02C') {
        item.error_field = 'AEB01';
      }
      if (item.error_field == 'ICD10_ID1' || item.error_field == 'RYQK') {
        item.error_field = 'ICD10_ID1';
      }
      if (item.error_field == 'ICD10_ID1') {
        item.error_field = 'ABC03C';
      }
      if (item.error_field == 'ABC03C') {
        item.error_field = 'AEB01';
      }

      if (item.error_field == 'AAA45' || item.error_field == 'AAA46' || item.error_field == 'AAA47' || item.error_field == 'AAA12') {
        item.error_field = 'AAA47';
      }

      if (item.error_field == 'AAA48' || item.error_field == 'AAA49' || item.error_field == 'AAA50' || item.error_field == 'AAA15') {
        item.error_field = 'AAA49';
      }
      //"MZFS
      if (item.error_field == 'MZFS') {
        item.error_field = 'HOCUS_WAY_ID';
      }

      if (item.error_field == 'AAA04') {
        item.error_field = 'AAA18C';
      }

      if (item.error_field == 'ICD10_NAME') {
        item.error_field = 'ABC01N';
      }

      if (
        item.error_field == 'ICD9_ID1' ||
        // item.error_field == 'OPE_DATE' ||
        item.error_field == 'ICD9_NAME' ||
        // item.error_field == 'OPE_LEVEL' ||
        item.error_field == 'OPE_TYPE' ||
        item.error_field == 'SSPB'
      ) {
        item.error_field = 'ICD9_ID1';
      }
      let a = document.querySelectorAll('.choose-twinkle');
      let b = document.querySelectorAll('.choose-twinkle-1');
      let eleClass = [...a, ...b];
      console.log(eleClass);

      for (let item = 0; item < eleClass.length; item++) {
        console.log(eleClass[item]);
        eleClass[item].className = 'table-label';
      }
      if (Array.isArray(this.$refs[item.error_field])) {
        this.$refs[item.error_field][0].className = item.category <= 1 ? 'choose-twinkle' : 'choose-twinkle-1';
        this.$refs[item.error_field][0].scrollIntoView({ block: 'center', behavior: 'smooth' });
      } else {
        // this.$nextTick(() => {
        //   item.basis.forEach(e => {
        //     this.$refs[e].className = item.category <= 1 ? 'choose-twinkle' : 'choose-twinkle-1';
        //   });
        //   this.$refs[item.error_field].scrollIntoView({ block: 'start', behavior: 'smooth' });
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
    // 加载数据后渲染颜色
    setColor() {
      let errorList = this.errorList;
      for (var i = 0; i < errorList.length; i++) {
        this.$refs[errorList[i].error_field].style.color = errorList[i].category <= 1 ? 'red' : '#e26e01';
      }
    },

    funQuery(f) {
      let pramse = {
        id: this.zyh === '0' ? this.valData : this.zyh,
      };
      if (this.$route.query.status) {
        pramse.is_tm = 1;
      }
      this.$axios.post('/medical_record', pramse).then(res => {
        this.data = res.data;
        this.score = res.data.score;
        this.errorList = res.data.error;
        if ( this.errorList && Array.isArray(this.errorList)) {
          this.errorList.map((item) => {
            item.show = true
          })
        }

        // if (this.data.diagnosis && this.data.diagnosis.length < 22) {
        //   for (let i = 0; i < 22 - this.data.diagnosis.length; i++) {
        //     this.data.diagnosis.push({
        //       ICD10_ID1: '',
        //       ICD10_NAME: '',
        //       RYQK: '',
        //       class: 'other',
        //       id: '',
        //     });
        //   }
        // }

        const hasMainClass = this.data.diagnosis.some(item => item.class === 'main');

        // 如果不存在main类型，添加一个到数组开头
        if (!hasMainClass) {
          this.data.diagnosis.unshift({
            ICD10_ID1: '',
            ICD10_NAME: '',
            RYQK: '',
            class: 'main', // 指定为主要诊断
            id: '',
          });
        }

        // 继续填充剩余的空诊断项，直到总数达到22

        const currentLength = this.data.diagnosis.length;
        const targetLength = currentLength > 10 ? currentLength : 10;
        console.log("主要诊断", currentLength, targetLength);
        for (let i = 0; i < targetLength - currentLength; i++) {
          this.data.diagnosis.push({
            ICD10_ID1: '',
            ICD10_NAME: '',
            RYQK: '',
            class: 'other',
            id: '',
          });
        }

        if (this.data.diagnosis == null || this.data.diagnosis.length == 0) {
          this.data.diagnosis = [];
          this.data.diagnosis.push(
            {
              ICD10_ID1: '',
              ICD10_NAME: '',
              RYQK: '',
              class: 'main',
              id: '',
            },
            {
              ICD10_ID1: '',
              ICD10_NAME: '',
              RYQK: '',
              class: 'other',
              id: '',
            },
          );
        }

        if (this.data.operation && this.data.operation.length < 8) {
          for (let i = 0; i < targetLength - this.data.operation.length; i++) {
            this.data.operation.push({
              FRIST_ASSISTANT_NAME: '',
              HOCUS_MAN_NAME: '',
              HOCUS_WAY_ID: '',
              ICD9_ID1: '',
              ICD9_NAME: '',
              INCISION_GRADE_ID: '',
              OPE_DATE: '',
              OPE_LEVEL: '',
              OPE_MAN_NAME: '',
              OPE_ORDER: '',
              RJSS: '',
              SECOND_ASSISTANT_NAME: '',
              SSLX: '',
              class: 'other',
              id: '',
              type: '',
            });
          }
        }
        // console.log(this.data.operation);
        return f(this.errorList);
      });
    },

    // 临床数据
    funQuery_lc() {
      let pramse = {
        ZYH: this.zyh === '0' ? this.valData : this.zyh,
      };
      if (this.$route.query.status) {
        pramse.is_tm = 1;
      }
      this.$axios.post('/home_sz_quality/blInfo', pramse).then(res => {
        this.data = res.data;
        // console.log("========>",this.data.diagnosis.length)
        // if (this.data.diagnosis && this.data.diagnosis.length < 22) {
        //   for (let i = 0; i < 22 - this.data.diagnosis.length; i++) {
        //     this.data.diagnosis.push({
        //       ICD10_ID1: '',
        //       ICD10_NAME: '',
        //       RYQK: '',
        //       class: 'other',
        //       id: '',
        //     });
        //   }
        // }
        // 检查是否存在class=main的数据
        const hasMainClass = this.data.diagnosis.some(item => item.class === 'main');

        // 如果不存在main类型，添加一个到数组开头
        if (!hasMainClass) {
          this.data.diagnosis.unshift({
            ICD10_ID1: '',
            ICD10_NAME: '',
            RYQK: '',
            class: 'main', // 指定为主要诊断
            id: '',
          });
        }

        // 继续填充剩余的空诊断项，直到总数达到22
        //const targetLength = 22;
        const currentLength = this.data.diagnosis.length;
        const targetLength = currentLength > 10 ? currentLength : 10;
        console.log("主要诊断", currentLength, targetLength);
        for (let i = 0; i < targetLength - currentLength; i++) {
          this.data.diagnosis.push({
            ICD10_ID1: '',
            ICD10_NAME: '',
            RYQK: '',
            class: 'other',
            id: '',
          });
        }
        if (this.data.diagnosis == null || this.data.diagnosis.length == 0) {
          this.data.diagnosis = [];
          this.data.diagnosis.push(
            {
              ICD10_ID1: '',
              ICD10_NAME: '',
              RYQK: '',
              class: 'main',
              id: '',
            },
            {
              ICD10_ID1: '',
              ICD10_NAME: '',
              RYQK: '',
              class: 'other',
              id: '',
            },
          );
        }
        if (this.data.operation && this.data.operation.length < 8) {
          for (let i = 0; i < targetLength - this.data.operation.length; i++) {
            this.data.operation.push({
              FRIST_ASSISTANT_NAME: '',
              HOCUS_MAN_NAME: '',
              HOCUS_WAY_ID: '',
              ICD9_ID1: '',
              ICD9_NAME: '',
              INCISION_GRADE_ID: '',
              OPE_DATE: '',
              OPE_LEVEL: '',
              OPE_MAN_NAME: '',
              OPE_ORDER: '',
              RJSS: '',
              SECOND_ASSISTANT_NAME: '',
              SSLX: '',
              class: 'other',
              id: '',
              type: '',
            });
          }
        }
        // console.log(this.data.operation);
      });

      console.log("this.zyh",this.zyh)
      if (this.zyh != '0'){
        getHomeData({id: this.zyh}).then(res => {
        // console.log("getHomeData",res.data);
        // this.mainHomeData = res.data;
        // this.is_active_blbh = this.mainHomeData.MED_REC_ID
        this.signature = res.data
      });
      }
    },
    //临床质控结果
    getQualityResult(f) {
      let pramse = {
        ZYH: this.valData,
      };
      this.$axios.post('/home_sz_quality/qualityResult', pramse).then(res => {
        this.score = res.data.score.score;
        this.errorList = res.data.list;
        if ( this.errorList && Array.isArray(this.errorList)) {
          this.errorList.map((item) => {
            item.show = true
          })
        }

        // 找出匹配的项并将其移到列表首位
        const targetIndex = this.errorList.findIndex(item => item.error_field === this.error_field);

        if (targetIndex !== -1) {
          // 从原位置删除并插入到首位
          const targetItem = this.errorList.splice(targetIndex, 1)[0];
          this.errorList.unshift(targetItem);
        }
        return f(this.errorList);
      });
    },
  },
};
</script>
<style lang="scss" scoped>
#MyDiv {
  margin: 0;
  padding: 0;
}

.header {
  margin: 15px 20px;
  text-align: right;
  display: flex;
  justify-content: flex-end;
}

.cont_container {
  //   margin-top: 60px;
  display: flex;
  justify-content: center;
  //   flex-direction: column;
  //   align-items: center;
}

.cont-left {
  flex: 1;
  min-height: 650px;
  margin: 0 15px;
  // height: 800px;
  // min-width: 1200px !important;
  //overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  padding: 16px 10px 10px 10px;
}

.cont-reight {
  width: 300px;
  min-height: 650px;
  height: 800px;
  overflow-x: hidden;
  overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  box-sizing: border-box;
  padding: 0 10px;
}

.cont-title {
  display: flex;
  justify-content: space-between;

  span {
    padding: 5px;
  }
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
  margin: 10px;
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
  // min-width: 1000px;
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
  // width: 300px;
  height: 50px;
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

.cont-reight-top-cont {
  margin: 8px 0 0 20px;

  p {
    font-size: 12px;
    font-weight: bold;
    color: #333333;
    padding: 3px 0;
  }
}

.cont-reight-btn {
  margin-top: 20px;
  width: 100%;
  height: 43px;
  background: #d3e3ff;
  border-radius: 8px;
  font-size: 14px;
  font-weight: bold;
  color: #333333;
  text-align: center;
  line-height: 43px;
}

.cont-reight-bottom {
  margin: 24px 7px;
  // display: flex;
  cursor: pointer;
}

.cont-reight-bottom-title {
  width: 70px;
  background: #ffdfdf;
  border-right: 3px solid #ff0000;
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  color: #da1515;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cont-reight-bottom-title-null {
  width: 70px;
  background: #fffbec;
  border-right: 3px solid #fa6400;
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  color: #ff5700;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cont-reight-bottom-conter {
  margin-left: 8px;
  min-height: 70px;
  display: flex;
  justify-content: space-around;
  flex-direction: column;
  width: 100%;

  p {
    font-size: 13px;
    color: #333333;
  }
}

.zkyj {
  font-size: 14px;
  font-family: Source Han Sans CN-Regular, Source Han Sans CN;
  color: #ed3028;
  line-height: 20px;
  cursor: pointer;
}

.bold {
  font-size: 14px !important;
  font-weight: 600;
}

//
.storeBox {
  height: 80px;
  line-height: 80px;
  font-size: 30px;
  text-align: center;
  align-items: center;
  position: relative;
  margin: 30px auto;
  border: 1px solid #ccc;
  box-sizing: border-box;
  overflow: hidden;

  .text {
    font-size: 30px;
  }

  .spa {
    overflow: hidden;
  }

  .spa::before {
    content: '';
    width: 0;
    height: 0;
    border: 30px solid transparent;
    border-right: 30px solid red;
    -webkit-transform: rotate(135deg);
    transform: rotate(135deg);
    color: #fff;
    position: absolute;
    right: -30px;
    top: -30px;
    cursor: pointer;
  }

  .flexTable {
    display: flex;
    align-items: center;
  }

  .spaview {
    position: absolute;
    right: 5px;
    top: -1px;
    font-size: 13px;
    text-align: right;
    height: 30px;
    line-height: 30px;
    width: 100px;
    color: #fff;
  }
}

.flexNox {
  width: 300px;
  display: flex;
  margin: 0 auto;
  justify-content: center;
  align-items: center;

  .Yradiobox {
    width: 10px;
    background: red;
    margin-right: 5px;
    height: 10px;
    border-radius: 50%;
  }

  .Rradiobox {
    width: 10px;
    background: #fa6400;
    height: 10px;
    margin-right: 5px;
    border-radius: 50%;
  }

  .Yradio {
    width: 80px;
    display: flex;
    align-items: center;
    text-align: center;
  }

  .Rradio {
    width: 80px;
    display: flex;
    align-items: center;
    text-align: center;
  }
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

.choose-cool {
  color: red;
}

.choose-cool-1 {
  color: #e26e01;
}

.table-value-look {
  padding-left: 12px;
  color: #ff0000;
  cursor: pointer;
}

// ==============  新增文本样式  ==============

.contLeft-title-top {
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  padding: 0 10px;
}

.contLeft-title-fj {
  width: auto;
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  align-items: center;
}

.contLeft-title-rbox {
  flex: 1;
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  justify-content: flex-end;
}

.title-top-f {
  flex: 1;
}

.contLeft-title-top .title-rbox-f {
  flex: 1;
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  justify-content: flex-end;
  margin-right: 16px;
}

.title-top-200 {
  width: 200px;
}

.contLeft-title-top .title-top-300 {
  width: 300px;
}

.refachInput span {
  height: auto;
  line-height: 30px;
  padding: 0;
}

.refachInput>span {
  height: 30px;
  line-height: 30px;
  font-weight: 100;
}

.refachInput .homeTitle-i-t {
  font-weight: bold;
}

.refachInput .homeTitle-i-x {
  border-bottom: 1px solid #dbdbdb;
  padding-right: 12px;
  margin-left: 2px;
  min-width: 70px;
}

.bottom-time-top {
  margin-top: 30px;
}
.bottom-time-botom {
  margin-bottom: 30px;
}
.bottom-time {
  width: 100%;
  display: flex;
  justify-content: flex-start;
  align-items: center;
  margin-bottom: 10px;
  .bottom-time-bold {
    font-weight: bold;
  }
  .bottom-time-list {
    width: 50%;
  }
}
</style>
