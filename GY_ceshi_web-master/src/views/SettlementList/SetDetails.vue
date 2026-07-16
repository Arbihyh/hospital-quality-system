<template>
  <div id="MyDiv">
    <div class="header">
      <el-button type="success" v-if="!ifFile" @click="funEdit">编辑</el-button>
      <el-button type="warning" v-if="ifFile" @click="funNext">保存</el-button>
      <el-button @click="getback">返回</el-button>
    </div>
    <div class="cont_container">
      <div class="cont-left">
        <div class="cont-title">
        </div>
        <div class="cont-title-description">山东省烟台市医疗保障基金结算清单</div>
        <div class="cont-edit-dev">
          <div class="flexTable HeadBoxWidth">
            <span ref="YLJG">定点医疗机构名称</span>
            <span v-if="!ifFile"></span>
            <!-- <el-input v-else v-model="data.AAA27" placeholder="请输入内容"></el-input> -->
          </div>
           <div class="flexTable HeadBoxWidth">
            <span ref="YLJGDM">定点医疗机构代码</span>
            <span v-if="!ifFile"></span>
            <!-- <el-input v-else v-model="data.AAA27" placeholder="请输入内容"></el-input> -->
          </div >
            <div class="flexTable HeadBoxWidth">
            <span ref="QDLSH">清单流水号</span>
            <span v-if="!ifFile"></span>
            <!-- <el-input v-else v-model="data.AAA27" placeholder="请输入内容"></el-input> -->
          </div>
         
         
        </div>
          <div class="cont-edit-dev">
               <div class="flexTable HeadBoxWidth">
            <span ref="YBBH">医保编号</span>
            <span v-if="!ifFile"></span>
            <!-- <el-input v-else v-model="data.AAA27" placeholder="请输入内容"></el-input> -->
          </div>
          <div class="flexTable HeadBoxWidth">
            <span ref="AAA28">病案号</span>
            <span v-if="!ifFile">{{ data.AAA28 }}</span>
            <!-- <el-input v-else v-model="data.AAA28" placeholder="请输入内容"></el-input> -->
          </div>
          <div class="flexTable HeadBoxWidth">
            <span ref="YBJSDJ">医保结算等级</span>
            <span v-if="!ifFile"></span>
            <!-- <el-input v-else v-model="data.AAA28" placeholder="请输入内容"></el-input> -->
          </div>
          </div>
          <div class="cont-edit-dev">
             <div class="flexTable HeadBoxWidth">
            <span ref="SBSJ">申报时间</span>
            <span v-if="!ifFile">{{ data.AAA28 }}</span>
            <!-- <el-input v-else v-model="data.AAA28" placeholder="请输入内容"></el-input> -->
          </div>
          </div>
 <div class="TableHead">一.基本信息</div>
        <div class="table" >
         
          <table ref="tables">
            <tr>
              <td class="table-label table-label1" ref="AAA01">姓名</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA01 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA01" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label2" ref="AAA02C">性别</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA02C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA02C" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label3" ref="AAA03">出生日期</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA03 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA03" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label4" ref="AAA04">年龄</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA04 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA04" class="width100" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              <td class="table-label table-label5" ref="AAA05C">国籍</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA05C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA05C" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label6" ref="AAA40">年龄(年龄不足一周岁的年龄)</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA40 }}(天)</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA40" class="width100" placeholder="请输入内容"></el-input>
              </td>
              
              <td class="table-label table-label7" ref="AAA06C">民族</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA06C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA06C" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label8" ref="ZJLB">患者证件类别</td>
              <td class="table-value" v-if="!ifFile">{{ data.ZJLB }}</td>
              <td class="table-value" v-else>
                <!-- <el-input v-model="data.ZJLB" class="width100" placeholder="请输入内容"></el-input> -->
              </td>
            </tr>
            <tr>
              <td class="table-label table-label9" ref="AAA07">患者证件号码</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA07 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA07" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label10" ref="AAA18C">职业</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA18C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA18C" class="width100" placeholder="请输入内容"></el-input>
              </td>
            
              <td class="table-label table-label12" ref="AAA15">现住址</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA15 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA15" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label13" ref="AAA19">工作单位及地址</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA19 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA19" class="width100" placeholder="请输入内容"></el-input>
              </td>
            </tr>
            <tr>
              
              <td class="table-label table-label14" ref="AAA20">单位电话</td>
              <td class="table-value"  v-if="!ifFile">{{ data.AAA20 }}</td>
              <td class="table-value"  v-else>
                <el-input v-model="data.AAA20" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label15" ref="AAA21C">邮编</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA21C }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA21C" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label16" ref="AAA22">联系人姓名</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA22 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA22" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label17" ref="GX">关系</td>
              <td class="table-value" v-if="!ifFile">{{ data.GX }}</td>
              <td class="table-value" v-else>
                <!-- <el-input v-model="data.AAA16C" class="width100" placeholder="请输入内容"></el-input> -->
              </td>
            </tr>
            <tr>
              <td class="table-label table-label18" ref="AAA24">地址</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA24 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA24" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label19" ref="AAA25">电话</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA25 }}</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA25" class="width100" placeholder="请输入内容"></el-input>
              </td>
                <td class="table-label table-label20" ref="YBLX">医保类型</td>
              <td class="table-value" v-if="!ifFile">{{ data.YBLX }}</td>
              <td class="table-value" v-else>
                <!-- <el-input v-model="data.AAA19" class="width100" placeholder="请输入内容"></el-input> -->
              </td>
               <td class="table-label table-label21" ref="TSRYLX">特殊人员类型</td>
              <td class="table-value" v-if="!ifFile">{{ data.TSRYLX }}</td>
              <td class="table-value" v-else>
                <!-- <el-input v-model="data.AAA20" class="width100" placeholder="请输入内容"></el-input> -->
              </td>
            </tr>
            <tr>
              <td class="table-label table-label22" ref="CBD">参保地</td>
              <td class="table-value" v-if="!ifFile">{{ data.CBD }}</td>
              <td class="table-value" v-else>
                <!-- <el-input v-model="data.CBD" class="width100" placeholder="请输入内容"></el-input> -->
              </td>
              <td class="table-label table-label23" ref="XSRRYLX">新生儿入院类型</td>
              <td class="table-value" v-if="!ifFile">{{ data.XSRRYLX }}</td>
              <td class="table-value" v-else>
                <!-- <el-input v-model="data.AAA22" class="width100" placeholder="请输入内容"></el-input> -->
              </td>
              <td class="table-label table-label24" ref="AEN01">新生儿出生体重</td>
              <td class="table-value" v-if="!ifFile">{{ data.AEN01 }}(克)</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AEN01" class="width100" placeholder="请输入内容"></el-input>
              </td>
              <td class="table-label table-label25" ref="AAA42">新生儿入院体重</td>
              <td class="table-value" v-if="!ifFile">{{ data.AAA42 }}(克)</td>
              <td class="table-value" v-else>
                <el-input v-model="data.AAA42" class="width100" placeholder="请输入内容"></el-input>
              </td>
            </tr>
          </table>
        </div>
         <div class="TableHead">二.门诊慢特病诊疗信息</div>
        <div class="table">
          <table ref="tables" style="width:100%;">
            <tr>
              <td class="table-labelon" colspan="12" ref="ZDKB">诊断科别</td>
              <td class="table-labelon" v-if="!ifFile" colspan="12" ref="JZRQ">就诊日期{{ data.AAA23C }}</td>
              <td class="table-labelon" v-else colspan="12" ref="JZRQ">
                就诊日期 
                <!-- <el-input  v-model="data.AAA23C" class="width200 eInput" placeholder="请输入内容"></el-input> -->
              </td>
            </tr>
            <tr>
              <td class="table-labelon table-label30" colspan="6">病种名称</td>
              <td class="table-labelon table-label31" colspan="6"> 病种代码</td>
              <td class="table-labelon table-label32" colspan="6">手术及操作名称</td>
              <td class="table-labelon table-label33" colspan="6">手术及操作编码</td>
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon" colspan="6">
                <p class="FA6400"></p>
              </td>
              <td class="table-valueon FA6400" colspan="6"></td>
              <td class="table-valueon" colspan="6"></td>
              <td class="table-valueon" colspan="6"></td>
            </tr>
          </table>
          </div>
          <div class="TableHead">三.住院诊疗信息</div>


          <!--  -->
          <div class="table">
          <table>
            <tr>
              <td class="table-value" colspan="24">
                <div class="flexTable">
              <span style="padding-right:10px;">住院医疗类型：1.住院 2.日间手术</span> 
              <div v-if="!ifFile">{{ data.AEM03C }}</div>
              <div v-if="ifFile">
                 <!-- <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input> -->
              </div></div>

              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="24" ref="AAB06C">
                <div class="flexTable">
              <span style="padding-right:10px;">入院途径(1.急诊 2.门诊 3.其他医疗结构转入 4.其他)</span> 
              <div v-if="!ifFile">{{ data.AAB06C }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AAB06C" placeholder="请输入内容"></el-input></div></div>
              </td>
           </tr>
            <tr>
              <td class="table-value" colspan="24">
                <div class="flexTable">
              <span style="padding-right:10px;">治疗类别(1.西医 2.中医(2.1 中医 2.2 民族医)) 3.中西医:</span> 
              <div v-if="!ifFile">{{ data.ZLLB }}</div>
              <div v-if="ifFile"> 
                <!-- <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input> -->
                </div></div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="4" ref="AAB01">
                <div class="flexTable">
              <span style="padding-right:10px;">入院时间:</span> 
              <div v-if="!ifFile">{{ data.AAB01 }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AAB01" placeholder="请输入内容"></el-input></div></div>
              </td>
              <td class="table-value" colspan="7" ref="AAB02C">
                <div class="flexTable">
              <span style="padding-right:10px;">入院科别:</span> 
              <div v-if="!ifFile">{{ data.AAB02C }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AAB02C" placeholder="请输入内容"></el-input></div></div>
              </td>
              <td class="table-value" colspan="8" ref="ZKKB">
                <div class="flexTable">
              <span style="padding-right:10px;">转科科别:</span> 
              <div v-if="!ifFile">{{ data.ZKKB }}</div>
              <div v-if="ifFile"> <el-input v-model="data.ZKKB" placeholder="请输入内容"></el-input></div></div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="4" ref="CYSJ">
              <div class="flexTable">
              <span style="padding-right:10px;">出院时间:</span> 
              <div v-if="!ifFile">{{ data.CYSJ }}</div>
              <div v-if="ifFile"> <el-input v-model="data.CYSJ" placeholder="请输入内容"></el-input></div></div>
              </td>
              <td class="table-value" colspan="6" ref="AAC02C">
              <div class="flexTable">
              <span style="padding-right:10px;">出院科别:</span> 
              <div v-if="!ifFile">{{ data.AAC02C }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AAC02C" placeholder="请输入内容"></el-input></div></div>
              </td>
              <td class="table-value" colspan="8" ref="AAC04">
              <div class="flexTable">
              <span style="padding-right:10px;">实际住院:</span> 
              <div v-if="!ifFile">{{ data.AAC04 }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AAC04" placeholder="请输入内容"></el-input></div></div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="8" ref="XYZD">
              <div class="flexTable">
              <span style="padding-right:10px;">门(急)诊诊断(西医诊断):</span> 
              <div v-if="!ifFile">{{ data.XYZD }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.XYZD" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
              <td class="table-value" colspan="9" ref="JBDM">
              <div class="flexTable">
              <span style="padding-right:10px;">疾病代码:</span> 
              <div v-if="!ifFile">{{ data.JBDM }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="9" ref="ZYZD">
              <div class="flexTable">
              <span style="padding-right:10px;">门(急)诊诊断(中医诊断):</span> 
              <div v-if="!ifFile">{{ data.ZYZD }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.ZYZD" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
              <td class="table-value" colspan="9" ref="JBDM">
              <div class="flexTable">
              <span style="padding-right:10px;">疾病代码:</span> 
              <div v-if="!ifFile">{{ data.JBDM }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="1" ref="ABF01N">
              <div class="flexTable">
              <span style="padding-right:10px;">出院西医诊断:</span> 
             </div>
              </td>
              <td class="table-value" colspan="1" ref="ABF01N">
              <div class="flexTable">
              <span style="padding-right:10px;">疾病代码:</span> 
             </div>
              </td>
              <td class="table-value" colspan="8" ref="ABF01N">
              <div class="flexTable">
              <span style="padding-right:10px;">入院病情::</span> 
             </div>
              </td>
              <td class="table-value" colspan="1" ref="ABF01N">
              <div class="flexTable">
              <span style="padding-right:10px;">出院中医诊断:</span> 
             </div>
              </td>
              <td class="table-value" colspan="1" ref="ABF01N">
              <div class="flexTable">
              <span style="padding-right:10px;">疾病代码:</span> 
             </div>
              </td>
              <td class="table-value" colspan="6" ref="ABF01N">
              <div class="flexTable">
              <span style="padding-right:10px;">入院病情::</span> 
             </div>
              </td>
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">主要诊断{{ item.FRIST_ASSISTANT_NAME }}</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{ data.ABF01C }}</td>
              <td class="table-valueon"  colspan="8" >{{ data.ABC03C }}</td>
              <td class="table-valueon"  colspan="1" >主病{{ data.ABC01N }}</td>
              <td class="table-valueon"  colspan="1" >{{ data.ABC01N }}</td>
              <td class="table-valueon"  colspan="6" >{{ data.ABC01N }}</td> 
            </tr>
            <!-- <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">主要诊断{{ item.FRIST_ASSISTANT_NAME }}</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{ data.ABF01C }}</td>
              <td class="table-valueon"  colspan="8" >{{ data.ABC03C }}</td>
              <td class="table-valueon"  colspan="1" >主病{{ data.ABC01N }}</td>
              <td class="table-valueon"  colspan="1" >{{ data.ABC01N }}</td>
              <td class="table-valueon"  colspan="6" >{{ data.ABC01N }}</td> 
            </tr> -->
            <tr>
              <td class="table-value" colspan="24">
                <div class="flexTable">
              <span style="padding-right:10px;">诊断代码计数</span> 
              <div v-if="!ifFile">{{ data.ZDDMJS }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.ZDDMJS" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
              <tr>
             <td class="table-value" colspan="8" ref="AEG02C">主要手术及操作名称:{{ data.AEG02C }}</td>
            <td class="table-value" colspan="1" ref="AEG02C">主要手术及操作代码:{{ data.AEG02C }}</td>
            <td class="table-value" colspan="1"  ref="AEG02C">麻醉方式:{{ data.AEG02C }}</td>
            <td class="table-value" colspan="1" ref="AEG02C">术者医师姓名:{{ data.AEG02C }}</td>
            <td class="table-value" colspan="1" ref="AEG02C">术者医师代码:{{ data.AEG02C }}</td>
            <td class="table-value" colspan="1"  ref="AEG02C">麻醉医师姓名:{{ data.AEG02C }}</td>
            <td class="table-value" colspan="1"  ref="AEG02C">麻醉医师代码:{{ data.AEG02C }}</td>
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="8" >
                <p class="FA6400">{{ item.ICD9_NAME }}</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{ data.ICD9_ID1 }}</td>
              <td class="table-valueon"  colspan="1" >{{ data.HOCUS_WAY_ID }}</td>
              <td class="table-valueon"  colspan="1" >{{ data.OPE_MAN_NAME }}</td>
              <td class="table-valueon"  colspan="1" >{{ data.OPE_MAN_NAME }}</td>
              <td class="table-valueon"  colspan="1" >{{ data.HOCUS_MAN_NAME }}</td> 
              <td class="table-valueon"  colspan="1" >{{ data.HOCUS_MAN_NAME }}</td> 
            </tr>
               <tr>
                <!-- {{ data.AEG02C }} -->
              <td class="table-value" colspan="8" ref="SJCZQZSJ">
              <div class="flexTable">
              <span style="padding-right:10px;">手术操作起止时间:</span> 
              <div v-if="!ifFile">{{ data.SJCZQZSJ }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>

              <td class="table-value" colspan="8" ref="MZSJ">
              <div class="flexTable">
              <span style="padding-right:10px;">麻醉起止时间:</span> 
              <div v-if="!ifFile">{{ data.MZSJ }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
               <tr>
                <!-- {{ data.AEG02C }} -->
              <td class="table-value" colspan="24">
                <div class="flexTable">
              <span style="padding-right:10px;">手术及操作代码计数：</span> 
              <div v-if="!ifFile">{{ data.SJCZDMJS }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
               <tr>
                <!-- {{ data.AEG02C }} -->
              <td class="table-value" colspan="24">
                <div class="flexTable">
              <span style="padding-right:10px;">呼吸机使用天数：</span> 
              <div v-if="!ifFile">{{ data.AEL01 }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AEL01" placeholder="请输入内容"></el-input></div></div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="4">
                <div class="flexTable">
              <span style="padding-right:10px;">颅脑损伤患者昏迷时间：</span> 
             </div>
              </td>
              <td class="table-value" colspan="7" ref="AEJ01">
                <div class="flexTable">
              <span style="padding-right:10px;">入院前</span> 
              <div v-if="!ifFile">{{ data.AEJ01 }}</div>
              <div v-if="ifFile">  
                <el-input class="width150" v-model="data.AEJ01"></el-input>
                天
                <el-input class="width150" v-model="data.AEJ02"></el-input>
                小时
                <el-input class="width150" v-model="data.AEJ03"></el-input>
                分</div></div>
              </td>
              <td class="table-value" colspan="6" ref="AEJ04">
                <div class="flexTable">
              <span style="padding-right:10px;">入院后：</span> 
              <div v-if="!ifFile">{{ data.AEJ04 }}</div>
              <div v-if="ifFile"> 
                <el-input class="width150" v-model="data.AEJ04"></el-input>
                天
                <el-input class="width150" v-model="data.AEJ05"></el-input>
                小时
                <el-input class="width150" v-model="data.AEJ06"></el-input>
                分</div></div>
              </td>
          
              <td class="table-value" colspan="5" style="border-left: 0" v-if="!ifFile"></td>
              <!-- <td class="table-value" colspan="5" style="border-left: 0" v-if="ifFile"></td> -->
            </tr>
                <tr>
              <td class="table-labelon table-label30" colspan="2">重症监护病房类型 </br>(CCU,NICU,ECU,SICU,PICU,RICU,ICU(综合),其他)</td>
              <td class="table-labelon table-label31" colspan="8">进重症监护室时间(年_月_日_时_分)</td>
              <td class="table-labelon table-label32" colspan="2">出重症监护室时间(年_月_日_时_分)</td>
              <td class="table-labelon table-label33" colspan="4">合计(_时_分)</td>
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon" colspan="2">
                <p class="FA6400">{{ item.FRIST_ASSISTANT_NAME }}</p>
              </td>
              <td class="table-valueon FA6400" colspan="8">{{ data.ABF01C }}</td>
              <td class="table-valueon" colspan="2">{{ data.ABC03C }}</td>
              <td class="table-valueon" colspan="4">{{ data.ABC01N }}</td>
            </tr>
              <tr>
              <td class="table-labelon table-label30" colspan="3">输血品种</td>
              <td class="table-labelon table-label31" colspan="7">输血量</td>
              <td class="table-labelon table-label32" colspan="6">输血计量单位</td>
            </tr>
             <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon" colspan="3">
                <p class="FA6400">{{ item.FRIST_ASSISTANT_NAME }}</p>
              </td>
              <td class="table-valueon FA6400" colspan="7">{{ data.ABF01C }}</td>
              <td class="table-valueon" colspan="6">{{ data.ABC03C }}</td>
            </tr>
                        <tr>
              <td class="table-value" colspan="5" ref="TJHLTS">
              <div class="flexTable">
              <span style="padding-right:10px;">特级护理天数:</span> 
              <div v-if="!ifFile">{{ data.TJHLTS }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEG02C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>

              <td class="table-value" colspan="5" ref="YJHLTS">
              <div class="flexTable">
              <span style="padding-right:10px;">一级护理天数:</span> 
              <div v-if="!ifFile">{{ data.YJHLTS }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEG02C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
              <td class="table-value" colspan="2" ref="RJHLTS">
              <div class="flexTable">
              <span style="padding-right:10px;">二级护理天数:</span> 
              <div v-if="!ifFile">{{ data.RJHLTS }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEG02C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
              <td class="table-value" colspan="5" ref="SJHLTS">
              <div class="flexTable">
              <span style="padding-right:10px;">三级护理天数:</span> 
              <div v-if="!ifFile">{{ data.SJHLTS }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEG02C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
              <tr>
                <td class="table-value" colspan="24" ref="AEM01C">
              <div class="flexTable">
              <span style="padding-right:10px;">离院方式:</span> 
              <div v-if="!ifFile">{{ data.AEM01C }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AEM01C" placeholder="请输入内容"></el-input></div></div>
              </td>
            </tr>
             <tr>
              <td class="table-value" colspan="24" ref="AEM03C">
              <div class="flexTable">
              <span style="padding-right:10px;">是否有出院31天内再住院计划:</span> 
              <div v-if="!ifFile">{{ data.AEM03C }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div></div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="10" ref="AEE03">
              <div class="flexTable">
              <span style="padding-right:10px;">主诊医师姓名:</span> 
              <div v-if="!ifFile">{{ data.AEE03 }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AEE03" placeholder="请输入内容"></el-input></div>
              </div>
              </td>
              <td class="table-value" colspan="10" ref="ZZYSDM">
              <div class="flexTable">
              <span style="padding-right:10px;">主诊医师代码:</span> 
              <div v-if="!ifFile">{{ data.ZZYSDM }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="10" ref="AEE10">
              <div class="flexTable">
              <span style="padding-right:10px;">责任护士姓名:</span> 
              <div v-if="!ifFile">{{ data.AEE10 }}</div>
              <div v-if="ifFile"> <el-input v-model="data.AEE10" placeholder="请输入内容"></el-input></div></div>
              </td>
              <td class="table-value" colspan="10" ref="ZRHSDM">
              <div class="flexTable">
              <span style="padding-right:10px;">责任护士代码:</span> 
              <div v-if="!ifFile">{{ data.ZRHSDM }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
          </table>
        </div>

        <div class="TableHead">四.医疗收费信息</div>
        <!--  -->
          <!--  -->
          <div class="table">
          <table>
            <tr>
              <td class="table-value" colspan="1">
              <div v-if="!ifFile" class="flexTable"> <span class="flexBlockText" style="padding-right:10px;width:150px;">
                业务流水号:</span> {{ data.AEM03C }}</div>
              <div v-if="ifFile" class="flexTable"><span  class="flexBlockText">业务流水号:</span> 
               <!-- <el-input style="width:200px;" v-model="data.AEM03C" placeholder="请输入内容"></el-input> -->
               </div>
              <div v-if="!ifFile" class="flexTable"> <span  class="flexBlockText">票据代码：</span> {{ data.AEM03C }}</div>
              <div v-if="ifFile" class="flexTable">  <span  class="flexBlockText">票据代码:</span>
              <!-- <el-input style="width:200px;" v-model="data.AEM03C" placeholder="请输入内容"></el-input> -->
              </div>
              <div v-if="!ifFile" class="flexTable"> <span  class="flexBlockText">票据号码:</span> {{ data.AEM03C }}</div>
              <div v-if="ifFile" class="flexTable">  <span  class="flexBlockText">票据号码:</span>
              <!-- <el-input style="width:200px;" v-model="data.AEM03C" placeholder="请输入内容"></el-input> -->
              </div>
              </td>
              <td class="table-value" colspan="6">
                <div class="flexTable">
              <span style="padding-right:10px;">结算期间</span> 
              <div v-if="!ifFile">{{ data.JSQJ }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="1" ref="XMMC">
              <div class="flexTable">
              <span style="padding-right:10px;">项目名称:</span> 
             </div>
              </td>
              <td class="table-value" colspan="1" ref="JE">
              <div class="flexTable">
              <span style="padding-right:10px;">金额:</span> 
             </div>
              </td>
              <td class="table-value" colspan="1" ref="JL">
              <div class="flexTable">
              <span style="padding-right:10px;">甲类:</span> 
             </div>
              </td>
              <td class="table-value" colspan="1" ref="YL">
              <div class="flexTable">
              <span style="padding-right:10px;">乙类:</span> 
             </div>
              </td>
              <td class="table-value" colspan="1" ref="ZF">
              <div class="flexTable">
              <span style="padding-right:10px;">自费:</span> 
             </div>
              </td>
              <td class="table-value" colspan="1" ref="D34">
              <div class="flexTable">
              <span style="padding-right:10px;">其他:</span> 
             </div>
              </td>
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">床位费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">诊查费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">检查费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">化验费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>

            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">治疗费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{data.D19X01}}</td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>

            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">手术费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{data.D20X02}}</td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">护理费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{data.D13}}</td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">卫生材料费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">西药费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{data.D23}}</td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">中药饮片费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">中成药费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{data.D24}}</td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">一般诊疗费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">挂号费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">其他费:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" >{{data.D34}}</td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">XX(按病种收费名称+代码):</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr v-for="(item, index) in data.main_operation" :key="index">
              <td class="table-valueon"  colspan="1" >
                <p class="FA6400">金额合计:</p>
              </td>
              <td class="table-valueon FA6400"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td>
              <td class="table-valueon"  colspan="1" ></td> 
            </tr>
            <tr>
              <td class="table-value" colspan="24" ref="YBZFFS">
              <div class="flexTable">
              <span style="padding-right:10px;">医保支付方式:</span> 
              <div v-if="!ifFile">{{ data.YBZFFS }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.YBZFFS" placeholder="请输入内容"></el-input></div> -->
              </div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="3" ref="TCJJ">
               <div style="width:100%;text-align:left;height:40px;line-height:40px;font-weight: bold;">医保统筹基金支付</div>

              <div class="flexTable">
                <div style="margin-right:25px;font-weight: bold;">补充医疗保险支付</div>
                <div>
                  <div class="flexTable">
                    <span style="padding-right:10px;">职工大额补助:</span> 
              <div v-if="!ifFile">{{ data.ZGDeBZ }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                  <div class="flexTable">
                    <span style="padding-right:10px;">居民大病保险:</span> 
              <div v-if="!ifFile">{{ data.JMDBBX }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                  <div class="flexTable">
                    <span style="padding-right:10px;">公务员医疗补助:</span> 
              <div v-if="!ifFile">{{ data.GWYYLBZ }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                </div>
            </div>
              </td>
              <td class="table-value" colspan="5" ref="ADA0101">
              <div class="flexTable">
                <div style="margin-right:25px;font-weight: bold;">个人负担</div>
                <div>
                  <div class="flexTable" style="border-bottom:1px solid #f5f5f5;">
                    <span style="padding-right:10px;">个人自付:</span> 
              <div v-if="!ifFile">{{ data.GRZF }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                  <div class="flexTable">
                    <span style="padding-right:10px;">个人自费:</span> 
              <div v-if="!ifFile">{{ data.ADA0101 }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                </div>
            </div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="3" ref="SYBX">
               <div style="width:100%;text-align:left;height:40px;line-height:40px;font-weight: bold;">医保统筹基金支付</div>

              <div class="flexTable">
                <div style="margin-right:25px;font-weight: bold;">医疗救助支付</div>
                <div>
                  <div class="flexTable">
                    <span style="padding-right:10px;">企业补充:</span> 
              <div v-if="!ifFile">{{ data.QYBC }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                  <div class="flexTable">
                    <span style="padding-right:10px;">商业保险:</span> 
              <div v-if="!ifFile">{{ data.SYBX }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                  <div class="flexTable">
                    <span style="padding-right:10px;">....:</span> 
              <div v-if="!ifFile">{{ data.WZ }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                </div>
            </div>
              </td>
              <td class="table-value" colspan="5" ref="GRZF">
              <div class="flexTable">
                <div style="margin-right:25px;font-weight: bold;">个人支付</div>
                <div>
                  <div class="flexTable" style="border-bottom:1px solid #f5f5f5;">
                    <span style="padding-right:10px;">个人账户支付:</span> 
              <div v-if="!ifFile">{{ data.GRZHZF }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                  <div class="flexTable">
                    <span style="padding-right:10px;">个人现金支付:</span> 
              <div v-if="!ifFile">{{ data.GRXJZF }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> -->
                  </div>
                </div>
            </div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="2" ref="BM">
              <div class="flexTable">
              <span style="padding-right:10px;">定点医疗机构填报部门:</span> 
              <div v-if="!ifFile">{{ data.BM }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div>--></div> 
              </td>
              <td class="table-value" colspan="2" ref="JG">
              <div class="flexTable">
              <span style="padding-right:10px;">医保经办机构:</span> 
              <div v-if="!ifFile">{{ data.JG }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> --></div>
              </td>
              <td class="table-value" colspan="5" ref="DM">
              <div class="flexTable">
              <span style="padding-right:10px;">代码:</span> 
              <div v-if="!ifFile">{{ data.DM }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> --></div>
              </td>
            </tr>
            <tr>
              <td class="table-value" colspan="2" ref="TYBR">
              <div class="flexTable">
              <span style="padding-right:10px;">定点医疗机构填报人:</span> 
              <div v-if="!ifFile">{{ data.TYBR }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> --></div>
              </td>
              <td class="table-value" colspan="2" ref="JBR">
              <div class="flexTable">
              <span style="padding-right:10px;">医保机构经办人:</span> 
              <div v-if="!ifFile">{{ data.JBR }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> --></div>
              </td>
              <td class="table-value" colspan="5" ref="DM">
              <div class="flexTable">
              <span style="padding-right:10px;">代码:</span> 
              <div v-if="!ifFile">{{ data.AEM03C }}</div>
              <!-- <div v-if="ifFile"> <el-input v-model="data.AEM03C" placeholder="请输入内容"></el-input></div> --></div>
              </td>
            </tr>
          </table>
        </div>
        <!--  -->
      </div>
      <div class="cont-reight">
        <div class="cont-reight-top">
          <div class="cont-reight-btn">结算单</div>
        </div>
        <!-- 评分 -->
        <div class="storeBox">
          <div class="text">{{ score }}</div>
          <div class="spa">
            <div class="spaview" v-if="score > 90">优</div>
            <div class="spaview" v-else-if="score >= 70 && score == 90">良</div>
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
        <div v-if="item.category ==0 || item.category == 1" class="cont-reight-bottom-title">
            <div class="cont-reight-bottom-title-zi">{{item.category==0?'A类':'B类'}}</div>
            -{{ item.down }}
          </div>
          <div v-else class="cont-reight-bottom-title-null">
            <div class="cont-reight-bottom-title-null-zi">{{item.category==2?'C类':'D类'}}</div>
            -{{ item.down }}
          </div>
          <div class="cont-reight-bottom-conter">
            <p>原病案：疾病编码：{{ item.error_field }}</p>
            <p>提示：{{ item.desc }}</p>
            <p>{{ item.error_name }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
// import OtherComponent from '@/components/OtherComponent'
export default {
  components: {
    // OtherComponent
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {},
  data() {
    return {
      data: '',
      errorList: [],
      valData: '',
      score: 0,
      source:1,
      ifFile: false,
    };
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
    // this.valData = this.storageGet('getData');
    this.valData = this.storageGet('getData');
    console.log(this.valData);
    this.funQuery();
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
    funNext() {
      this.data.source=1;
      this.$axios.post('/medicalRecordEdit', this.data).then(res => {
        console.log(res);
        this.$message('修改成功');
        this.funQuery();
        this.ifFile = false;
      });
    },
    funEdit() {
      this.ifFile = true;
    },
    getback() {
      this.$router.go(-1);
    },
    /**
     * 跳转对应病历首页
     */
    getBlankIndex(item) {
      let eleClass = document.querySelectorAll('.choose-twinkle');
      console.log(eleClass);
      // const tb = this.$refs.tables.value;
      console.log(item.error_field);
      // this.$refs[item.error_field]
      console.log(this.$refs[item.error_field]);
      for (let item = 0; item < eleClass.length; item++) {
        console.log(eleClass[item]);
        eleClass[item].className = 'table-label';
      }
      // return;
      // document
      // this.$refs[item.error_field].classList.value + ' choose-twinkle';
      this.$refs[item.error_field].className = 'choose-twinkle';
      this.$refs[item.error_field].scrollIntoView({ block: 'start', behavior: 'smooth' });
      //   .getElementById("agentTitle")
      //   .scrollIntoView({ block: "start", behavior: "smooth" });
      // 跳转到指定位置并且平滑滚动
      // this.$el.querySelector('.table-labelon1').scrollIntoView({ behavior: 'smooth' });
      // this.$el.querySelector('.table-labelon1').style.color = 'red';
    },

    funQuery() {
      let pramse = {
        id: this.valData,
      };
      this.$axios.post('/medical_record', pramse).then(res => {
        console.log(res);
        this.data = res.data;
        this.errorList = res.data.error;
        this.score = res.data.score;
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
.HeadBoxWidth{
  width: 300px;
  text-align: left;
}
.flexBlockText{
  width: 120px;
  text-align: left;
  display: inline-block;
  padding-right: 10px;;
}
.flexTable{
    display: flex;
    align-items: center;
  }
  
.header {
  height: 60px;
  margin: 5px 20px;
  text-align: right;
}
.cont_container {
  //   margin-top: 60px;
  display: flex;
  justify-content: center;
  //   flex-direction: column;
  //   align-items: center;
}
.TableHead{
  height: 40px;
  text-align: center;
  border: 1px solid #ccc;
  line-height: 40px;
  background: cornflowerblue;
  width:100%;
  font-weight: bold;
}
.cont-left {
  flex: 1;
  min-height: 650px;
  margin: 0 15px;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  padding: 10px;
}
.cont-reight {
  width: 420px;
  min-height: 650px;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  margin: 0 15px 0 0;
}
.cont-title {
  display: flex;
  justify-content: space-between;
  width: 90%;
  margin: 0 auto;
  text-align: left;
  padding:15px 0px;
  span {
    padding: 5px;
  }
}
.cont-title-description {
  margin: 20px;
  font-size: 24px;
  font-weight: bold;
  width: 90%;
  margin: 0 auto;

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
    padding-left:15px;
    text-align: left;
  }
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
  // border-radius: 8px;
  font-size: 14px;
  font-weight: bold;
  color: #333333;
  text-align: center;
  line-height: 43px;
}
.cont-reight-bottom {
  width: 100%;
  margin: 24px 7px;
  display: flex;
}

.cont-reight-bottom-title {
  width: 60px;
  height: 70px;
  background: #ffdfdf;
  border-right: 3px solid #ff0000;
  text-align: center;
  line-height: 88px;
  font-size: 24px;
  font-weight: bold;
  color: #da1515;
  position: relative;
}

.cont-reight-bottom-title-zi {
  // height: 10px;
  position: absolute;
  top: 23px;
  left:15px;
  font-size:18px ;
  color: #da1515;
  line-height: 0px;

}
.cont-reight-bottom-title-null {
  width: 60px;
  height: 70px;
  background: #FA6400;
  border-right: 3px solid #FA6400;
  text-align: center;
  line-height: 60px;
  font-size: 24px;
  font-weight: bold;
  color: #FF5700;
  position: relative;
}
.cont-reight-bottom-title-null-zi{
  position: absolute;
  top: 23px;
  left:15px;
  font-size:18px ;
  color: #FF5700;
  line-height: 0px;
}
.cont-reight-bottom-conter {
  margin-left: 8px;
  height: 70px;
  display: flex;
  justify-content: space-around;
  flex-direction: column;
  p {
    font-size: 8px;
    color: #333333;
  }
}

//
.storeBox {
  width: 300px;
  height: 80px;
  line-height: 80px;
  font-size: 30px;
  text-align: center;
  align-items: center;
  position: relative;
  margin: 30px auto;
  border: 1px solid #ccc;
  .text {
    font-size: 30px;
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
.choose-twinkle {
  // background: red;
  font-size: 20px;
  color: red;
  font-weight: 600;
  background: yellow;
}
</style>
