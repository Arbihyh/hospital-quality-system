<template>
    <div class="dashboard-container" :class="{'nocopy': $route.meta.nocopy}">
      <div class="block">
        <!-- <div class="barBtn" v-if="$route.query.from !== 'kyts'">
          <el-radio-group v-model="choice" class="bnts" size="medium" @change="handleRadioChange">
            <el-radio-button :label="0">普通检索</el-radio-button>
            <el-radio-button :label="1">高级检索</el-radio-button>
            <el-radio-button :label="2">专业检索</el-radio-button>
          </el-radio-group>
        </div> -->
        <span style="font-size: 16px;font-weight: 700;">检索信息:</span>
        <div class="bnh" />
        <!-- 专业检索 -->
        <ProfessionSearchVue ref="professionSearch"  @search="handleProfessionSearchFn" @export="handelExport" @reset="handleReset" />
      </div>
      <div class="tableBox">
        <div class="flextab" style="margin: 0; margin-bottom: 30px; display: block">
          <div class="flextabtitle-box">
            <div class="h-title">
              <span class="blue" />
              <span class="text">检索结果</span>
              <!-- <span v-if="paginationData.total != 0" class="titleName">
                找到
                <span class="title-left-span">{{ paginationData.total }}</span>
                个例条相关结果
              </span> -->
            </div>
          </div>
        </div>
        <div >
         <div class="" style="display:flex;justify-content: flex-start;">
          <span v-if="paginationData.total != 0" class="titleName">
                共找到
                <span class="title-left-span">{{ paginationData.total }}</span>
                <!-- 个例条相关结果 -->
                 份病历
            </span>
          </div>
        <!-- <el-button
        class="add-btn"
              type="primary"
              plain
              icon="el-icon-setting"
              size="small"
            >配置</el-button>  class="table-pagination"-->
        <!-- <el-pagination
          v-if="tableData && tableData.length !== 0"
          :page-sizes="[10, 20, 30, 50,100]"
        /> -->
        <!-- <el-pagination
          v-if="tableData && tableData.length !== 0"
          background
          class="table-pagination"
          style="margin: 15px 0px"
          :page-size="paginationData.pageSize"
          :current-page.sync="paginationData.currentPage"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="SizeChangeEvent"
          @current-change="pageHasChanged"
        /> -->
        <!-- <el-pagination
          v-if="tableData && tableData.length !== 0"
          background
          class="table-pagination"
          style="margin: 15px 0px"
          :page-size="paginationData.pageSize"
          :current-page.sync="paginationData.currentPage"
          layout="prev, pager, next, jumper"
          @size-change="SizeChangeEvent"
          @current-change="pageHasChanged"
        />  style="margin: 15px 0px"-->
        <el-pagination
         class="table-pagination"
          layout="prev, pager, next"
          :total='paginationData.total'>
        </el-pagination>
        <el-pagination
         class="table-pagination"
           layout="sizes"
          :total='paginationData.total'>
        </el-pagination>
        <el-radio-group class="btn-class"  v-model="onClass">
         <el-radio-button size="mini" label="11"  @click.native="onChangePassword">配置</el-radio-button> 
          <el-radio-button size="mini" label="0">列表</el-radio-button>
          <el-radio-button size="mini" label="1">详情</el-radio-button>
          <el-radio-button size="mini" label="12" v-if="!isWhitelist" @click="onExport">下载</el-radio-button>
          <!-- <el-link type="primary" :href="'http://172.16.2.81:8000/#/popularLiterature?search='+formData0.input" target="_blank" style="margin-left: 16px;">科研探索</el-link> -->
        </el-radio-group>
        <!-- <div class="title-right" v-if="!isWhitelist">
          <el-button class="topClass" type="primary"  icon="el-icon-download" @click="onExport" style="margin-left: 20px;background-color:#185da6;" size="small" >下载</el-button>
        </div> -->
        
      </div>
     
        <div v-if="onClass == '0'" class="conter" style="border: none">
          <!--tableDataresData-->
          <el-table ref="multipleTable" :data="tableData" tooltip-effect="dark" style="width: 100%" @selection-change="handleSelectionChange" @sort-change="handleSortChange">
            <el-table-column type="index" :index="indexAdd" label="序号" width="70px" />
            <template v-for = "(col,index) in tableTitle">
            <el-table-column  :prop="col.prop" :label="col.label"></el-table-column>
            <el-table-column slot-scope="scope"></el-table-column>
          </template>
            <!-- <el-table-column prop="AAA28" label="住院号码">
              <template slot-scope="scope">
                <span class="blue" @click="funGoto(scope.row.MED_REC_ID ? scope.row.MED_REC_ID : scope.row.ZYH)">
                  <template>
                    <div>
                      {{ scope.row.AAA28 }}
                    </div>
                  </template>
                </span>
              </template>
            </el-table-column> -->
            <!-- <el-table-column prop="AAC01" label="出院时间" sortable />
            <el-table-column prop="AAC11N" label="出院科室" />
            <el-table-column prop="ICD10_NAME" label="主要诊断名称" width="200" :align="textCenter" header-align="center"/>
            <el-table-column prop="ICD9_NAME" label="主要手术名称" :align="textCenter" header-align="center"/> 
            <el-table-column prop="AAC04" label="住院天数" :align="textCenter" header-align="center" />
            <el-table-column prop="ADA01" label="住院总费用" :align="textCenter" header-align="center"/>
         -->
          </el-table>
          <!-- 分页控制 -->
        </div>
         <!--hq-->
         <!-- <div v-if="onClass == '1'"   class="conter" style="padding: 20px;">
          <el-row class="" v-for="(item, index) in res.detail" :key="index">
            <el-row class="">
              <el-col :span="24" style="display:inline-block;font-weight: 700;">
              <span style="display:inline-block;margin-right:60px;">住院号码：<a href="#"  @click="funGoto(item.patient_info.MED_REC_ID ? item.patient_info.MED_REC_ID : item.patient_info.ZYH)" class="blue">{{ item.patient_info.AAA28 }}</a></span>
              <span>出院时间：{{ item.patient_info.AAC01 }}</span>
            </el-col>
            </el-row>
            <el-row class="" style="margin-top:10px;">
             <h6>1.1 手术记录</h6>
            <el-table ref="multipleTable" :data="tableData" tooltip-effect="dark" style="width: 100%;margin-top:10px;" @selection-change="handleSelectionChange" @sort-change="handleSortChange">
            <el-table-column type="index" :index="indexAdd" label="序号" width="70px" />
            <el-table-column prop="AAC01" label="手术开始时间" />
            <el-table-column prop="AAC11N" label="手术结束时间" />
            <el-table-column prop="ICD9_NAME" label="手术名称" width="200" :align="textCenter" header-align="center"/>
            <el-table-column prop="OPE_MAN_NAME" label="术者" :align="textCenter" header-align="center"/> 
            <el-table-column prop="FRIST_ASSISTANT_NAME" label="一助" :align="textCenter" header-align="center"/>
            <el-table-column prop="SECOND_ASSISTANT_NAME    " label="二助" :align="textCenter" header-align="center" />
          </el-table> 
          <div v-if="item.ryjl" style="margin-top:10px;">
                <el-descriptions title="1.2入院记录" :column="1">
                  <el-descriptions-item label="" v-if="item.ryjl.RYJL_HJNR">
                    <span v-html="item.ryjl.RYJL_HJNR"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div> 
            </el-row>
          </el-row>
          </div> hq-end-->
        <div v-for="(item, index) in tableDataDetails" :key="index">
          <div v-if="onClass == '1'" class="conter" style="padding: 20px;">
           
            <!--hqend-->
            <!-- 专业检索 -->
            <div v-if="choice == 2">
              <!-- 患者信息 -->
              <div v-if="item.patient_info">
                <el-descriptions title="患者信息" :column="1">
                  <el-descriptions-item label="住院号码" v-if="item.patient_info.AAA28">
                    <span v-html="item.patient_info.AAA28" @click="funGoto(item.patient_info.MED_REC_ID ? item.patient_info.MED_REC_ID : item.patient_info.ZYH)" class="yeleou"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="出院科室" v-if="item.patient_info.AAC11N">
                    <span v-html="item.patient_info.AAC11N"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="出院时间" v-if="item.patient_info.AAC01">
                    <span v-html="item.patient_info.AAC01"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="姓名" v-if="item.patient_info.AAA01">
                    <span v-html="item.patient_info.AAA01"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="性别" v-if="item.patient_info.AAA02C">
                    <span v-html="item.patient_info.AAA02C"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="入院时间" v-if="item.patient_info.AAB01">
                    <span v-html="item.patient_info.AAB01"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="住院天数" v-if="item.patient_info.AAC04">
                    <span v-html="item.patient_info.AAC04"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="年龄" v-if="item.patient_info.AAA04 || item.patient_info.AAA40">
                    <span v-html="item.patient_info.AAA04 ? item.patient_info.AAA04 : item.patient_info.AAA40 "></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 入院记录 -->
              <div v-if="item.ryjl">
                <el-descriptions title="入院记录" :column="1">
                  <el-descriptions-item label="整体" v-if="item.ryjl.RYJL_HJNR">
                    <span v-html="item.ryjl.RYJL_HJNR"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="主诉" v-if="item.ryjl.RYJL_ZHS">
                    <span v-html="item.ryjl.RYJL_ZHS"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="现病史" v-if="item.ryjl.RYJL_XBS">
                    <span v-html="item.ryjl.RYJL_XBS"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="既往史" v-if="item.ryjl.RYJL_JWS">
                    <span v-html="item.ryjl.RYJL_JWS"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="个人史" v-if="item.ryjl.RYJL_GRS">
                    <span v-html="item.ryjl.RYJL_GRS"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="月经及婚育史+婚育史" v-if="item.ryjl.RYJL_YJJHYS">
                    <span v-html="item.ryjl.RYJL_YJJHYS"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="家族史" v-if="item.ryjl.RYJL_JZS">
                    <span v-html="item.ryjl.RYJL_JZS"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="体格检查" v-if="item.ryjl.RYJL_TGJC">
                    <span v-html="item.ryjl.RYJL_TGJC"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="专科检查" v-if="item.ryjl.RYJL_ZHUANKE">
                    <span v-html="item.ryjl.RYJL_ZHUANKE"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="辅助检查" v-if="item.ryjl.RYJL_FZJC">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.ryjl.RYJL_FZJC)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="初步诊断" v-if="item.ryjl.RYJL_CBZD">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.ryjl.RYJL_CBZD)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="第一初步诊断" v-if="item.ryjl.RYJL_CBZB">
                    <span v-html="item.ryjl.RYJL_CBZB"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 病程记录 -->
              <div v-if="item.bcjl_scbc">
                <el-descriptions title="病程记录" :column="1">
                  <el-descriptions-item label="整体" v-if="item.bcjl_scbc.BCJL_SCBC_HJNR">
                    <span v-html="item.bcjl_scbc.BCJL_SCBC_HJNR"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="病历特点" v-if="item.bcjl_scbc.BCJL_SCBC_BLTD">
                    <span v-html="item.bcjl_scbc.BCJL_SCBC_BLTD"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="第一初步诊断" v-if="item.bcjl_scbc.BCJL_SCBC_CBZD_ONE">
                    <span v-html="item.bcjl_scbc.BCJL_SCBC_CBZD_ONE"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="其他初步诊断" v-if="item.bcjl_scbc.BCJL_SCBC_CBZD_OTHER">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.bcjl_scbc.BCJL_SCBC_CBZD_OTHER)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="诊断依据" v-if="item.bcjl_scbc.BCJL_SCBC_ZDYJ">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.bcjl_scbc.BCJL_SCBC_ZDYJ)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="鉴别诊断" v-if="item.bcjl_scbc.BCJL_SCBC_JBZD">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.bcjl_scbc.BCJL_SCBC_JBZD)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="鉴别诊断名称" v-if="item.bcjl_scbc.BCJL_SCBC_JBZDMC">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.bcjl_scbc.BCJL_SCBC_JBZDMC)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="诊疗记录" v-if="item.bcjl_scbc.BCJL_SCBC_ZLJH">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.bcjl_scbc.BCJL_SCBC_ZLJH)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 出院记录 -->
              <div v-if="item.cyjl">
                <el-descriptions title="出院记录" :column="1">
                  <el-descriptions-item label="整体" v-if="item.cyjl.HJNR">
                    <span v-html="item.cyjl.HJNR"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="入院情况" v-if="item.cyjl.RYQK">
                    <span v-html="item.cyjl.RYQK"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="初步诊断" v-if="item.cyjl.CBZD">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.cyjl.CBZD)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="第一初步诊断" v-if="item.cyjl.CBZD_FIRST">
                    <span v-html="item.cyjl.CBZD_FIRST"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="诊疗经过" v-if="item.cyjl.ZLJG">
                    <span v-html="item.cyjl.ZLJG"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="出院情况" v-if="item.cyjl.CYQK">
                    <span v-html="item.cyjl.CYQK"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="出院诊断" v-if="item.cyjl.CYZD">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.cyjl.CYZD)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="第一出院诊断" v-if="item.cyjl.CYZD_FIRST">
                    <span v-html="item.cyjl.CYZD_FIRST"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="出院医嘱" v-if="item.cyjl.CYYZ">
                    <span v-for="(aItem, aIndex) of JSON.parse(item.cyjl.CYYZ)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 主要诊断 -->
              <div v-if="item.zyzd">
                <el-descriptions title="主要诊断" :column="1">
                  <el-descriptions-item label="主要诊断名称" v-if="item.zyzd.MD_ICD10_NAME">
                    <span v-html="item.zyzd.MD_ICD10_NAME"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="主要诊断编码" v-if="item.zyzd.MD_ICD10_ID1">
                    <span v-html="item.zyzd.MD_ICD10_ID1"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 主要手术 -->
              <div v-if="item.zyss">
                <el-descriptions title="主要手术" :column="1">
                  <el-descriptions-item label="主要手术名称" v-if="item.zyss.MO_ICD9_NAME">
                    <span v-html="item.zyss.MO_ICD9_NAME"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="主要诊断编码" v-if="item.zyss.MO_ICD9_ID1">
                    <span v-html="item.zyss.MO_ICD9_ID1"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 手术记录 -->
              <div v-for="(sItem, sIndex) of item.ssjl" :key="sIndex">
                <el-descriptions :title="`手术记录${sIndex+1}`" :column="1">
                  <el-descriptions-item label="整体" v-if="sItem.HJNR">
                    <span v-html="sItem.HJNR"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="术前诊断" v-if="sItem.SQZD">
                    <span v-html="sItem.SQZD"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="术中诊断" v-if="sItem.SZZD">
                    <span v-for="(aItem, aIndex) of JSON.parse(sItem.SZZD)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="第一术中诊断" v-if="sItem.SZZD_ONE">
                    <span v-html="sItem.SZZD_ONE"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="手术名称" v-if="sItem.SSMC">
                    <span v-for="(aItem, aIndex) of JSON.parse(sItem.SSMC)" :key="aIndex">
                      <span v-if="aIndex" class="inline_block">;</span>
                      <span v-html="aItem" class="inline_block"></span>
                    </span>
                  </el-descriptions-item>
                  <el-descriptions-item label="第一手术名称" v-if="sItem.SSMC_ONE">
                    <span v-html="sItem.SSMC_ONE"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="手术指导" v-if="sItem.SSZD">
                    <span v-html="sItem.SSZD"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="手术者" v-if="sItem.SSZ">
                    <span v-html="sItem.SSZ"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="助手" v-if="sItem.ZS">
                    <span v-html="sItem.ZS"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="床号" v-if="sItem.CH">
                    <span v-html="sItem.CH"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="手术日期" v-if="sItem.SSRQ">
                    <span v-html="sItem.SSRQ"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 病程记录-输血病程记录 -->
              <div v-for="(sItem, sIndex) of item.bcjl_sxbc" :key="sIndex">
                <el-descriptions :title="`输血病程记录${sIndex+1}`" :column="1">
                  <el-descriptions-item label="整体" v-if="sItem.HJNR">
                    <span v-html="sItem.HJNR"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="核对者" v-if="sItem.HDZ">
                    <span v-html="sItem.HDZ"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="检测结果" v-if="sItem.JCJG">
                    <span v-html="sItem.JCJG"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="检查指标" v-if="sItem.JCZB">
                    <span v-html="sItem.JCZB"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="输注者" v-if="sItem.SZZ">
                    <span v-html="sItem.SZZ"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 其它诊断 -->
              <div v-for="(sItem, sIndex) of item.qtzd" :key="sIndex">
                <el-descriptions :title="`其它诊断${sIndex+1}`" :column="1">
                  <el-descriptions-item label="其它诊断编码" v-if="sItem.ICD10_ID1">
                    <span v-html="sItem.ICD10_ID1"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="其它诊断名称" v-if="sItem.ICD10_NAME">
                    <span v-html="sItem.ICD10_NAME"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 其它手术 -->
              <div v-for="(sItem, sIndex) of item.qtss" :key="sIndex">
                <el-descriptions :title="`其它诊断${sIndex+1}`" :column="1">
                  <el-descriptions-item label="其它手术编码" v-if="sItem.ICD9_ID1">
                    <span v-html="sItem.ICD9_ID1"></span>
                  </el-descriptions-item>
                  <el-descriptions-item label="其它手术名称" v-if="sItem.ICD9_NAME">
                    <span v-html="sItem.ICD9_NAME"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 24小时出入院记录 -->
              <div v-for="(sItem, sIndex) of item.bllb18" :key="sIndex">
                <el-descriptions :title="`24小时出入院记录${sIndex+1}`" :column="1">
                  <el-descriptions-item label="整体" v-if="sItem.HJNR">
                    <span v-html="sItem.HJNR"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
              <!-- 首次病程 -->
              <div v-for="(sItem, sIndex) of item.bllb294" :key="sIndex">
                <el-descriptions :title="`病程记录${sIndex+1}`" :column="1">
                  <el-descriptions-item label="整体" v-if="sItem.HJNR">
                    <span v-html="sItem.HJNR"></span>
                  </el-descriptions-item>
                </el-descriptions>
              </div>
            </div>
          </div>
        </div>
        <!-- <el-pagination
          v-if="tableData && tableData.length !== 0"
          :total="paginationData.total"
          background
          class="table-pagination"
          style="margin: 15px 0px"
          :page-size="paginationData.pageSize"
          :current-page.sync="paginationData.currentPage"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="SizeChangeEvent"
          @current-change="pageHasChanged"
        /> -->
      </div>
      <!-- 修改密码 -->
      <configDirDialog v-if="pwdData.bSwitch" :data="pwdData" @submitForm="submitForm" ref="configRef"/>
    </div>
  </template>
  
  <script>
  import Title from '@/components/Title';
  import { mapGetters } from 'vuex';
  import mPagination from '@/components/m-pagination';
  import { bassNormalSearchDownload, bassHighSearchDownload, professionSearchExport } from '@/api/excel';
  import ProfessionSearchVue from './components/ProfessionSearch.vue';
  import configDirDialog from './components/configDirDialog.vue'
  export default {
    props: {
      isWhitelist: {
        type: Boolean,
        default() {
          return false
        }
      }
    },
    components: {
      Title,
      mPagination,
      ProfessionSearchVue,
      configDirDialog
    },
    computed: {
      ...mapGetters(['name']),
    },
    data() {
      return {
        tableTitle:[],
        total:10,
        pwdData: {
          bSwitch: false,
        },
        textCenter:"center",
        pickerOptions: {
          disabledDate(time) {
            return time.getTime() > Date.now();
          },
        },
        checked: false,
        choice: 0,
        formData0: {
          input: '',
          input1: '',
        },
        formData1: {
          ageday: '',
          age_start_type: 2,
          age_end_type: 2,
          ageyear: '',
          endTime: undefined,
          startTime: undefined,
          seniorList: [
            {
              select_type: 0,
              key: '',
              value: '',
              type: '0',
              lock: false,
            },
          ],
          seniorList1: [],
          hospitalizationon: '',
          hospitalizationin: '',
          AAC04_start: undefined,
          AAC04_end: undefined,
          AAA04_start: undefined,
          AAA04_end: undefined,
          ageType1: 1,
          ageType2: 1,
          AAA28: '',
        },
        tableData: [],
        tableDataDetails: [],
        paginationData: {
          total: 11,
          currentPage: 1,
          pageSize: 10,
        },
        fieldList: [
          { name: '出院记录', id: '1' },
          { name: '入院记录', id: '292' },
          { name: '病程记录', id: '294' },
          { name: '手术记录', id: '303' },
          { name: '病历讨论记录', id: '43' },
          { name: '授权同意类', id: '329' },
          { name: '评估评分表类', id: '79' },
          { name: '死亡记录类', id: '288' },
          { name: '24小时内记录类', id: '18' },
          { name: '医患沟通类', id: '34' },
          { name: '医疗常用表格', id: '87' },
          { name: '医嘱单', id: '49' },
          { name: '出院科室', id: 'AAC11N' },
          { name: '费用明细', id: 'FYMC' },
          { name: '报告单', id: '2000002' },
          { name: '主要诊断名称', id: 'ICD10_NAME' },
          { name: '主要诊断编码', id: 'ICD10_ID1' },
        ],
        keyList: [],
        Dayoptions: [],
        multipleSelection: [],
        onClass: 0,
        setListTwe: [],
        setList: [],
        departmentList: [],
        lock: false,
        searchNum: 0,
        professionSearchData: {},
        // 排序
        sort: [],
        is_tm_path: ['/hospital-caseViews', '/embedIndex-caseViews', '/reviewIndex-caseViews', '/whitelist-caseViews', '/whitelist-search']
      };
    },
    created() {
      const { from } = this.$route.query
      if (from === 'kyts') {
        this.choice = 2
      }
    },
    mounted() {
      if (this.storageGet('inputOn')) {
        this.formData0.input = JSON.parse(this.storageGet('inputOn'));
        this.storageRemove('inputOn');
      }
      if (this.storageGet('formData1')) {
        this.formData1 = JSON.parse(this.storageGet('formData1'));
        this.storageRemove('formData1');
      }
      if (this.storageGet('choice')) {
        this.choice = Number(this.storageGet('choice'));
        this.storageRemove('choice');
      }
      if (this.storageGet('setList')) {
        this.setList = JSON.parse(this.storageGet('setList'));
      }
      this.selectInfo();
      this.funQuery(0);
    },
    methods: {
      submitForm(key,val){
        let arr=[]
       key.forEach(item => {
        this.$refs.configRef.home.forEach(itema=>{
          if(item==itema.key){
            let obj={
              prop:item,
              label:itema.name
            }
           arr.push(obj)
          }

        })
        this.$refs.configRef.main_diagnosis.forEach(itemb=>{
          if(item==itemb.key){
            let obj={
              prop:item,
              label:itemb.name
            }
            arr.push(obj)
              }

              })
        this.$refs.configRef.other_diagnosis.forEach(itemc=>{
          if(item==itemc.key){
            let obj={
              prop:item,
              label:itemc.name
            }
            arr.push(obj)
              }

           })
        this.$refs.configRef.main_operation.forEach(itemd=>{
          if(item==itemd.key){
            let obj={
              prop:item,
              label:itemd.name
            }
            arr.push(obj)
              }

            })
         this.$refs.configRef.other_operation.forEach(iteme=>{
          if(item==iteme.key){
            let obj={
              prop:item,
              label:iteme.name
            }
            arr.push(obj)
              }

          })
       this.$refs.configRef.SSJL.forEach(itemf=>{
        if(item==itemf.key){
            let obj={
              prop:item,
              label:itemf.name
            }
            arr.push(obj)
              }

          })
       this.$refs.configRef.FYMX.forEach(itemg=>{
        if(item==itemg.key){
            let obj={
              prop:item,
              label:itemg.name
            }
            arr.push(obj)
              }
         })
        
      });
      this.tableTitle=arr
     

      },
      onChangePassword(row) {
       // this.pwdData.row = row
        this.pwdData.bSwitch = true
      },
      // table 字段排序
      handleSortChange(column) {
        const { prop, order } = column;
        let str = '';
        if (order === 'descending') {
          str = 'desc';
        } else if (order === 'ascending') {
          str = 'asc';
        } else {
          str = null;
        }
        if (str) {
          this.$set(this.sort, 0, prop)
          this.$set(this.sort, 1, str)
        } else {
          this.sort = []
        }
        this.tableData = [];
        
        if (this.choice == 2) {
          this.handleProfessionSearch(this.professionSearchData)
        } else {
          this.funQuery(this.searchNum);
        }
      },
      // 普通下载
      normalDownload(name) {
        const params = {
          keyword: this.formData0.input,
          AAC01_start: this.formData1.startTime,
          AAC01_end: this.formData1.endTime,
        };
        bassNormalSearchDownload(params).then(res => {
          const content = res.data; // 后台返回二进制数据
          const blob = new Blob([content]);
          const fileName = `${name}.csv`;
          if ('download' in document.createElement('a')) {
            // 非IE下载
            const elink = document.createElement('a');
            elink.download = fileName;
            elink.style.display = 'none';
            elink.href = URL.createObjectURL(blob);
            document.body.appendChild(elink);
            elink.click();
            URL.revokeObjectURL(elink.href); // 释放URL 对象
            document.body.removeChild(elink);
          } else {
            // IE10+下载
            navigator.msSaveBlob(blob, fileName);
          }
        });
      },
      // 高级搜索下载
      highDownload(name) {
        const pramse = {};
        pramse.field = this.formData1.seniorList;
        pramse.AAC01_start = this.formData1.startTime;
        pramse.AAC01_end = this.formData1.endTime;
        pramse.AAA28 = this.formData1.AAA28;
        pramse.AAC04_start = this.formData1.AAC04_start;
        pramse.AAC04_end = this.formData1.AAC04_end;
        const { ageType1, ageType2 } = this.formData1;
        // ageType1 = 1 年龄
        // ageType1 = 0 天数
        if (ageType1) {
          pramse.AAA04_start = this.formData1.AAA04_start;
        } else {
          pramse.AAA40_start = this.formData1.AAA04_start;
        }
        if (ageType2) {
          pramse.AAA04_end = this.formData1.AAA04_end;
        } else {
          pramse.AAA40_end = this.formData1.AAA04_end;
        }
        bassHighSearchDownload(pramse).then(res => {
          const content = res.data; // 后台返回二进制数据
          const blob = new Blob([content]);
          const fileName = `${name}.csv`;
          if ('download' in document.createElement('a')) {
            // 非IE下载
            const elink = document.createElement('a');
            elink.download = fileName;
            elink.style.display = 'none';
            elink.href = URL.createObjectURL(blob);
            document.body.appendChild(elink);
            elink.click();
            URL.revokeObjectURL(elink.href); // 释放URL 对象
            document.body.removeChild(elink);
          } else {
            // IE10+下载
            navigator.msSaveBlob(blob, fileName);
          }
        });
      },
      // 结果中查询
      onLockResult() {
        this.lock = true;
        this.formData1.seniorList.map(item => {
          if (!!item.value) {
            item.lock = true;
          } else {
            item.lock = false;
          }
        });
        this.funAdd();
      },
      indexAdd(index) {
        return index + 1 + (this.paginationData.currentPage - 1) * this.paginationData.pageSize;
      },
      funSelect(index) {
        // 建坤：切换时，取消input 重置
        // this.formData1.seniorList[index].value = "";
      },
      selectInfo() {
        this.$axios.post('/selectInfo').then(res => {
          // 支付方式 pay
          this.departmentList = res.data.department;
        });
      },
      funGoto(val) {
        this.storageSet('inputOn', JSON.stringify(this.formData0.input));
        this.storageSet('formData1', JSON.stringify(this.formData1));
        this.storageSet('choice', JSON.stringify(this.choice));
        this.storageSet('getData', val);
        const { path } = this.$route;
        let toPath;
        if (path === '/hospital-search') {
          toPath = '/hospital-caseViews';
        } else if (path === '/whitelist-search') {
          toPath = '/whitelist-caseViews';
        } else {
          toPath = '/caseViews';
        }
        // status = 1 代表不能复制文本
        this.$router.push({ path: toPath });
      },
      funCeal() {
        this.$refs.multipleTable.clearSelection();
      },
      funSetformData2() {
        this.formData1.seniorList = this.formData1.seniorList1;
      },
      funSetList() {
        this.formData0.input = String(this.formData0.input1);
      },
      funOnclass(val) {
        this.onClass = val;
      },
      handleSelectionChange(val) {
        this.multipleSelection = val;
      },
      SizeChangeEvent(val) {
        this.paginationData.pageSize = val;
        const num = this.lock ? 1 : 0;
        if (this.choice == 2) {
          this.handleProfessionSearch(this.professionSearchData)
        } else {
          this.funQuery(num);
        }
      },
      pageHasChanged(val) {
        this.paginationData.currentPage = val
        const num = this.lock ? 1 : 0;
        if (this.choice == 2) {
          this.handleProfessionSearch(this.professionSearchData)
        } else {
          this.funQuery(num);
        }
      },
      funBlur() {
        if (this.formData1.ageday > 356) {
          this.formData1.ageday = 356;
        }
        if (this.formData1.ageyear > 150) {
          this.formData1.ageyear = 150;
        }
      },
      funBluron() {
        if (this.formData1.hospitalizationon && this.formData1.hospitalizationin) {
          if (this.formData1.hospitalizationon > this.formData1.hospitalizationin) {
            this.$message('起止住院天数输入不正确');
          }
        }
      },
      funDel(i) {
        const index = i;
        if (index == 0) {
          return;
        }
        const list = this.formData1.seniorList;
        list.splice(index, 1);
        this.formData1.seniorList = list;
      },
      funAdd() {
        this.formData1.seniorList.push({
          key: '',
          select_type: 0,
          value: '',
          type: '0',
          lock: false,
        });
      },
      funQuery(num) {
        const pramse = {
          limit: this.paginationData.pageSize,
          page: this.paginationData.currentPage // 是当前页数 默认是0 。普通检索的参数是
        };
        if (this.$route.query.code) {
          pramse.code = this.$route.query.code
        }
        if (this.sort.length) {
          pramse.sort = this.sort
        }
        let queryUrl = 'normalSearch';
        if (this.choice == '0') {
          queryUrl = 'normalSearch';
          // 普通检索
          pramse.keyword = this.formData0.input; // 关键字
          pramse.AAC01_start = this.formData1.startTime;
          pramse.AAC01_end = this.formData1.endTime;
          
          if (this.is_tm_path.includes(this.$route.path)) {
            pramse.is_tm = 1;
          }
          this.$axios3.post(queryUrl, pramse).then(res => {
            this.tableData = res.data.data.list || [];
            this.tableDataDetails = res.data.data.detail || [];
            this.paginationData.total = res.data.data.total;
            if (this.choice == '0' && this.formData0.input) {
              this.setList.push(String(this.formData0.input));
              const listSetto = Array.from(new Set(this.setList));
              this.setList = listSetto;
              this.storageSet('setList', JSON.stringify(this.setList));
            }
          });
        } else {
          queryUrl = 'search';
          // 高级检索
          pramse.field = this.formData1.seniorList;
          pramse.AAC01_start = this.formData1.startTime;
          pramse.AAC01_end = this.formData1.endTime;
          pramse.AAA28 = this.formData1.AAA28;
          pramse.AAC04_start = this.formData1.AAC04_start;
          pramse.AAC04_end = this.formData1.AAC04_end;
          this.searchNum = num;
          const { ageType1, ageType2 } = this.formData1;
          // ageType1 = 1 年龄
          // ageType1 = 0 天数
          if (ageType1) {
            pramse.AAA04_start = this.formData1.AAA04_start;
          } else {
            pramse.AAA40_start = this.formData1.AAA04_start;
          }
          if (ageType2) {
            pramse.AAA04_end = this.formData1.AAA04_end;
          } else {
            pramse.AAA40_end = this.formData1.AAA04_end;
          }
          if (this.is_tm_path.includes(this.$route.path)) {
            pramse.is_tm = 1;
          }
          this.$axios3.post(queryUrl, pramse).then(res => {
            this.tableData = res.data.data.list || [];
            this.tableDataDetails = res.data.data.detail || [];
            this.paginationData.total = res.data.data.total;
            if (this.choice == '0' && this.formData0.input) {
              this.setList.push(String(this.formData0.input));
              const listSetto = Array.from(new Set(this.setList));
              this.setList = listSetto;
              this.storageSet('setList', JSON.stringify(this.setList));
            }
          });
        }
      },
      searchBtn(num) {
        this.paginationData.currentPage = 1;
        this.funQuery(num);
      },
      funBluron() {
        if (this.formData1.hospitalizationon && this.formData1.hospitalizationin) {
          if (this.formData1.hospitalizationon > this.formData1.hospitalizationin) {
            this.$message('起止住院天数输入不正确');
          }
        }
      },
      reset() {
        // 重置数据
        if (this.choice == 0) {
          Object.assign(this.$data.formData0, this.$options.data().formData0);
        } else {
          this.lock = false;
          this.searchNum = 0;
          Object.assign(this.$data.formData1, this.$options.data().formData1);
        }
      },
      // 专业检索
      handleProfessionSearchFn(params) {
        this.paginationData.currentPage = 1
        this.handleProfessionSearch(params)
      },
      handleProfessionSearch(params) {
        this.professionSearchData = params
        const { pageSize, currentPage } = this.paginationData
        const queryData = {
          ...params,
          page_size: pageSize,
          page: currentPage
        }
        if (this.$route.query.code) {
          queryData.code = this.$route.query.code
        }
        if (this.sort.length) {
          queryData.sort = this.sort
        }
        
        if (this.is_tm_path.includes(this.$route.path)) {
          queryData.is_tm = 1;
        }
        this.$axios3.post('/bl/serach', queryData).then(res => {
          this.tableData = res.data.list || [];
          this.tableDataDetails = res.data.detail || [];
          this.paginationData.total = res.data.total;
        });
      },
      // 判断对象是否为空
      isObject(form){
        let a = 0
        for(let key in form){
          if(!!form[key]){
            a++
          }
        }
        if (a) {
          return false
        } else {
          return true
        } 
      },
      // 导出
      onExport() {
        this.$refs.professionSearch.onExport()
      },
      handelExport(params) {
        professionSearchExport(params).then(res => {
          const content = res.data; // 后台返回二进制数据
          const blob = new Blob([content]);
          const fileName = `住院病历-专业检索.csv`;
          if ('download' in document.createElement('a')) {
            // 非IE下载
            const elink = document.createElement('a');
            elink.download = fileName;
            elink.style.display = 'none';
            elink.href = URL.createObjectURL(blob);
            document.body.appendChild(elink);
            elink.click();
            URL.revokeObjectURL(elink.href); // 释放URL 对象
            document.body.removeChild(elink);
          } else {
            // IE10+下载
            navigator.msSaveBlob(blob, fileName);
          }
        });
      },
      // 重置
      handleReset() {
        this.paginationData.currentPage = 1
        this.handleProfessionSearch({})
      },
      // 检索类型变化
      handleRadioChange(val) {
        if (val == 2) {
          this.paginationData.currentPage = 1
          this.handleProfessionSearch({})
        }
      },
      // 分页
    handleSizeChange(val) {
     
    },
    //分页
    handleCurrentChange(val) {
      
    }
    },
  };
  </script>
  <style scoped>
  ::v-deep.el-pagination.is-background .btn-next,
  ::v-deep.el-pagination.is-background .btn-prev,
  ::v-deep.el-pagination.is-background .el-pager li {
    margin: 0 5px;
    background-color: #fff;
    color: #606266;
    min-width: 30px;
    border-radius: 2px;
    border: 1px solid #dfe3f3;
    line-height: 27px;
  }
  ::v-deep.el-pagination.is-background .el-pager li:not(.disabled).active {
    background: #7e8bab;
  }
  ::v-deep.el-table .el-table__row td {
    color: #7e8bab;
    border-bottom: 1px solid #f4f4f4;
  }
  ::v-deep.el-table .el-table__header tr th:first-child {
    border-radius: 10px 0px 0px 10px;
  }
  ::v-deep.el-table .el-table__header tr th:last-child {
    border-radius: 0px 10px 10px 0px;
  }
  ::v-deep.el-table .el-table__header tr th {
    background: #f1f6ff;
    color: #13171e;
    border-bottom: 0px;
  }
  </style>
  <style lang="scss" scoped>
  .tableBox {
    background: #fff;
    padding: 19px;
    border-radius: 5px;
    font-size: 12px;
  }
  .block {
    background: #fff;
    border-radius: 5px;
    padding: 20px;
    margin-bottom: 20px;
    .fBtn {
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      .btn11 {
        position: absolute;
        right: 0;
      }
    }
    .bnh {
      margin: 0 auto;
      margin-bottom: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .barBtn {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .selects {
      width: 100%;
    }
    .rowsa {
      margin-bottom: 20px;
    }
  }
  .tableBox {
    background: #fff;
    padding: 19px;
    border-radius: 5px;
  }
  .dashboard {
    &-container {
      margin: 30px;
    }
    &-text {
      font-size: 30px;
      line-height: 46px;
    }
  }
  .pind {
    padding: 0 20px;
  }
  .pind10 {
    padding: 0 5px;
  }
  .width100 {
    width: 100px;
  }
  .width150 {
    width: 200px;
  }
  .width300 {
    width: 295px;
  }
  .width500 {
    width: 280px;
  }
  .width90 {
    width: 90px;
  }
  .blue {
    color: #185da6;
  }
  .h-title {
    display: flex;
    .blue {
      display: block;
      width: 6px;
      height: 17px;
      background: linear-gradient(180deg, #185da6 0%, #3195ff 100%);
      border-radius: 3px;
    }
    .text {
      font-size: 16px;
      font-weight: 600;
      color: #13171e;
      margin: 0 0 0 14px;
    }
  }
  .flextab-item {
    display: flex;
    align-items: center;
    margin-left: 20px;
  }
  .flextab-item > div {
    font-size: 15px;
    margin-right: 15px;
  }
  .flextab-item > div span.s-1 {
    color: #185da6;
  }
  .flextab-item > div span.s-2 {
    font-weight: bold;
  }
  .inputOn {
    width: 200px;
    margin: 10px 10px;
  }
  .search-title {
    padding: 20px 10px;
    // width: 900px;
    display: flex;
    justify-content: space-between;
  }
  .search-title-span {
    font-size: 20px;
    font-weight: 600;
    color: #13171e;
  }
  .conter {
    position: relative;
    margin: 20px 0;
    border: 1px solid skyblue;
    border-radius: 5px;
  }
  .conter-title {
    font-size: 16px;
    font-weight: 600;
    color: #13171e;
  }
  .blue {
    color: #185da6;
    font-size: 16px;
    font-weight: 600;
  }
  .conter-case {
    margin: 20px 0;
    font-size: 15px;
    text-indent: 30px;
    line-height: 30px;
  }
  .conter-case1 {
    margin: 0 auto;
    margin: 50px 0;
  }
  .yeleou {
    font-size: 16px;
    color: rgb(233, 157, 66);
    cursor: pointer;
    font-weight: 600;
  }
  .conter-num {
    font-size: 15px;
    font-weight: 600;
  }
  .conter-time {
    font-size: 15px;
    padding-top: 10px;
    font-weight: 600;
  }
  .onQuery {
    padding: 0 20px;
    color: rgb(233, 157, 66);
  }
  .cont-title {
    width: 100%;
    padding: 20px;
    display: flex;
    justify-content: space-between;
  }
  .title-left {
    display: flex;
  }
  .title-right {
    display: flex;
    float: right;
    & > div {
      width: 150px;
      border: 1px solid #d5e4ff;
      text-align: center;
      padding: 10px 0;
    }
  }
  .title-right-data {
    background: #3195ff;
    color: #fff;
  }
  .title-left-p {
    line-height: 38px;
    padding: 0 20px;
    font-size: 16px;
    color: #13171e;
  }
  .title-left-span {
    color: #3195ff;
    font-size: 18px;
    padding: 0 5px;
    // font-weight: 600;
  }
  .title-left-btn {
    margin: 0 0 0 40px;
  }
  .title-left-checked {
    margin-top: 10px;
  }
  .conter-checked {
    position: absolute;
    top: 21px;
    left: 30px;
  }
  .bule {
    background: #3195ff !important;
    color: #fff !important;
  }
  .titleName {
    margin-left: 30px;
    vertical-align: top;
  }
  .marginLeft {
    margin-left: -110px;
  }
  
  ::v-deep .el-input-number .el-input__inner {
    text-align: left;
  }
  .inline_block {
    display: inline-block;
  }
  ::v-deep .el-descriptions-item__content {
    display: inline-block;
  }
  .add-btn{
    position: relative;
    float: right;
    right: 240px;
    text-align: right;
        
      }
      .btn-class{
      display: flex;
      justify-content: flex-end;
      margin-right:90px;
      // background-color: #185da6;
    }
    .topClass{
      position: relative;
      bottom: 40px;
    }
::v-deep .el-radio-button__orig-radio:checked + .el-radio-button__inner {
  // box-shadow: #dcdfe6 -1px 0px 0px 0px !important;
  color:  #00C797;
    // background-color: #185da6 !important;
    // border-color: #185da6 !important;
}
.el-pagination {
  float: right;
}
.cus-total {
  float: left;
  font-size: 13px;
  color: #606266;
  font-family: Helvetica Neue, Helvetica, PingFang SC, Hiragino Sans GB, Microsoft YaHei, Arial, sans-serif;
  line-height: 28px;
}
.pagination-container {
  display:flex;
  justify-content: center;
  
  background: #fff;
  padding: 0 16px;
  overflow: hidden;
}
.pagination-container.hidden {
  display: none;
}
::v-deep .el-radio-button__inner:hover {
  color: #00C797;
}
::v-deep .el-radio-button__orig-radio:checked+.el-radio-button__inner {
  color: #00C797 !important;
  background-color: #fff !important;
  border-color:#00C797 !important;
   
}
::v-deep .el-pagination {
    /* display: flex; */
    float: left !important;
    margin-left: 25% !important;
}

::v-deep .el-pagination__sizes {
    margin: 0 10px 0 100px;
    font-weight: 400;
    color: #606266;
    // padding:10px 0 10px 0;
}
.custom-pagination .el-pagination__button {
  height: 50px !important;
  line-height: 28px;
  padding: 0 8px;
}
  </style>
  