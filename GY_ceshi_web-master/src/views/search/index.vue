<template>
  <div class="dashboard-container" :class="{ 'nocopy': $route.meta.nocopy }">
    <div class="block">
      <div class="barBtn" v-if="$route.query.from !== 'kyts'">
        <el-radio-group v-model="choice" class="bnts" size="medium" @change="handleRadioChange">
          <!-- <el-radio-button :label="0">普通检索</el-radio-button> -->
          <!-- <el-radio-button :label="1">高级检索</el-radio-button>
          <el-radio-button :label="2">专业检索</el-radio-button> -->
        </el-radio-group>
      </div>
      <div class="bnh" />
      <div class="barBtn-title">住院病历查询</div>
      <!-- 普通检索 -->
      <div v-if="choice == 0" class="bnh">
        <el-form :inline="true" class="demo-form-inline">
          <el-form-item label="">
            <el-input v-model="formData0.input" placeholder="输入关键字搜索" class="input-with-select width500"></el-input>
          </el-form-item>
          <el-form-item>
            <el-date-picker v-model="formData1.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd"
              placeholder="出院时间开始" :picker-options="pickerOptions" :disabled="lock" />
            <span class="pind10" />
            <el-date-picker v-model="formData1.endTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd"
              placeholder="出院时间结束" :picker-options="pickerOptions" :disabled="lock" />
          </el-form-item>
        </el-form>
        <div style="margin: 15px">
          <el-button type="primary" class="long-btn" @click="searchBtn(0)">检索</el-button>
        </div>
      </div>
      <!-- 高级检索 -->
      <div v-if="choice == 1" class="barBtn">
        <el-form ref="form" :model="formData1" label-width="100px">
          <el-form-item v-for="(item, index) in formData1.seniorList" :key="index">
            <!-- 下拉框开始 -->
            <el-select v-if="index != 0" v-model="item.select_type" :class="index != 0 ? 'marginLeft' : ''"
              class="width100" filterable placeholder="" :disabled="item.lock">
              <!-- fieldList -->
              <el-option label="且" :value="0" />
              <el-option label="或" :value="1" />
              <el-option label="不包含" :value="2" />
            </el-select>
            <span v-if="index != 0" class="pind10" />
            <el-select v-model="item.key" class="width150" filterable placeholder="请选择" @change="funSelect(index)"
              :disabled="item.lock">
              <!-- fieldList -->
              <el-option v-for="(item, index) in fieldList" :key="index" :label="item.name" :value="item.bllb"
                :disabled="item.lock" />
            </el-select>
            <!-- 下拉框结束 -->

            <span class="pind10" />
            <!-- 中间选择输入框开始 -->
            <span v-if="item.key == 'AAC11N'">
              <el-select v-model="item.value" class="width150" filterable placeholder="请选择" :disabled="item.lock">
                <el-option v-for="(itemo, indexo) in departmentList" :key="indexo" :label="itemo.name"
                  :value="itemo.name" />
              </el-select>
            </span>
            <span v-else>
              <el-input v-model="item.value" class="width150" :disabled="item.lock" placeholder="请输入" />
            </span>
            <!-- 中间选择输入框结束 -->

            <span class="pind10" />
            <!-- 条件下拉开始 -->
            <el-select v-model="item.type" class="width90" :disabled="item.lock" placeholder="">
              <el-option label="精确" value="1" />
              <el-option label="模糊" value="0" />
            </el-select>
            <!-- 条件下拉结束 -->

            <span class="pind10" />
            <!-- 增减重置选项按钮开始 -->
            <span>
              <!-- <el-button v-if="index != 0 && !item.lock" :disabled="formData1.seniorList.length == 1" type="primary" icon="el-icon-minus" @click="funDel(index)" />
              <el-button v-if="index == formData1.seniorList.length - 1" type="primary" icon="el-icon-plus" @click="funAdd" /> -->
              <el-button :disabled="formData1.seniorList.length == 1" type="primary" icon="el-icon-minus"
                @click="funDel(index)" />
              <el-button type="primary" icon="el-icon-plus" @click="funAdd" />
              <el-button v-if="index === formData1.seniorList.length - 1 && searchNum"
                @click="onLockResult">结果中检索</el-button>
            </span>
            <!-- 增减重置选项按钮结束 -->
          </el-form-item>
          <el-form-item label="出院时间">
            <el-date-picker v-model="formData1.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd"
              placeholder="开始日期" :picker-options="pickerOptions" :disabled="lock" />
            <span class="pind10" />
            <el-date-picker v-model="formData1.endTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd"
              placeholder="结束日期" :picker-options="pickerOptions" :disabled="lock" />
          </el-form-item>

          <el-form-item label="住院天数">
            <el-input-number v-model="formData1.AAC04_start" :min="1" :step="1" :controls="false" placeholder="起始天数"
              style="width: 220px" :disabled="lock"></el-input-number>
            <span class="pind10" />
            <el-input-number v-model="formData1.AAC04_end" :min="1" :step="1" :controls="false" placeholder="终止天数"
              style="width: 220px" :disabled="lock"></el-input-number>
          </el-form-item>

          <el-form-item label="患者年龄">
            <el-input-number v-model="formData1.AAA04_start" :min="1" :step="1" :controls="false"
              :placeholder="formData1.ageType1 ? '起始年龄' : '起始天数'" style="width: 220px"
              :disabled="lock"></el-input-number>
            <el-select v-model="formData1.ageType1" :disabled="lock" placeholder="请选择" style="width: 80px">
              <el-option label="年龄" :value="1"></el-option>
              <el-option label="天数" :value="0"></el-option>
            </el-select>
            <span class="pind10" />
            ——
            <span class="pind10" />
            <el-input-number v-model="formData1.AAA04_end" :min="1" :step="1" :controls="false"
              :placeholder="formData1.ageType2 ? '终止年龄' : '终止天数'" style="width: 220px"
              :disabled="lock"></el-input-number>
            <el-select v-model="formData1.ageType2" :disabled="lock" placeholder="请选择" style="width: 80px">
              <el-option label="年龄" :value="1"></el-option>
              <el-option label="天数" :value="0"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="住院号码">
            <el-input v-model="formData1.AAA28" placeholder="请输入" style="width: 220px"></el-input>
          </el-form-item>
        </el-form>
      </div>
      <div v-if="choice == 1" class="fBtn" style="position: relative">
        <el-button class="btn11" @click="reset">重置条件</el-button>
        <el-button type="primary" class="long-btn" @click="searchBtn(1)">检索</el-button>
      </div>
      <!-- 专业检索 -->
      <ProfessionSearchVue ref="professionSearch" v-if="choice == 2" @search="handleProfessionSearchFn"
        @export="handelExport" @reset="handleReset" />
    </div>
    <div class="tableBox">
      <div class="flextab" style="margin: 0; margin-bottom: 30px; display: block">
        <div class="flextabtitle-box">
          <div class="h-title">
            <span class="blue" />
            <span class="text">检索结果</span>
            <span v-if="paginationData.total != 0" class="titleName">
              找到
              <span class="title-left-span">{{ paginationData.total }}</span>
              个例条相关结果
            </span>
          </div>
        </div>
      </div>
      <el-radio-group v-model="onClass">
        <el-radio-button label="0">列表</el-radio-button>
        <el-radio-button label="1">详情</el-radio-button>
        <!-- <el-link type="primary" :href="'http://172.16.2.81:8000/#/popularLiterature?search='+formData0.input" target="_blank" style="margin-left: 16px;">科研探索</el-link> -->
      </el-radio-group>
      <div class="title-right" v-if="!isWhitelist">
        <el-button v-if="choice == 0" type="primary" icon="el-icon-download" @click="normalDownload('住院病历-普通检索')"
          style="margin-left: 20px;" class="export-btn">导出数据</el-button>
        <el-button v-if="choice == 1" type="primary" icon="el-icon-download" @click="highDownload('住院病历-高级检索')"
          style="margin-left: 20px;" class="export-btn">导出数据</el-button>
        <el-button v-if="choice == 2" type="primary" icon="el-icon-download" @click="onExport"
          style="margin-left: 20px;" class="export-btn">导出数据</el-button>
      </div>
      <div v-if="onClass == '0'" class="conter" style="border: none">
        <el-table ref="multipleTable" :data="tableData" tooltip-effect="dark" style="width: 100%"
          @selection-change="handleSelectionChange" @sort-change="handleSortChange">
          <el-table-column type="index" :index="indexAdd" label="序号" width="70px" />
          <el-table-column prop="AAA28" label="住院号码">
            <template slot-scope="scope">
              <span class="blue" @click="funGoto(scope.row.MED_REC_ID ? scope.row.MED_REC_ID : scope.row.ZYH)">
                <div style="cursor: pointer;" v-html="scope.row.AAA28"></div>
                <!-- <template>
                  <div >
                    {{ scope.row.AAA28 }}
                  </div>
                </template> -->
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="AAA04" label="年龄" />
          <el-table-column prop="AAC11N" label="出院科室" />
          <el-table-column prop="AAB01" label="入院时间" sortable />
          <el-table-column prop="AAC01" label="出院时间" sortable />
        </el-table>
        <!-- 分页控制 -->
      </div>
      <div v-for="(item, index) in tableDataDetails" :key="index">
        <div v-if="onClass == '1'" class="conter" style="padding: 20px;">
          <!-- 普通检索、高级检索 -->
          <div v-if="choice != 2">
            <div v-for="(i, j) in item.EMR_BL_BL01" :key="j">
              <div class="conter-title">
                <span>{{ paginationData.pageSize * (paginationData.currentPage - 1) + (index + 1) }}.{{ j + 1 }}</span>
                病历名称：
                <span v-for="(k, q) in fieldList" :key="q" class="blue">{{ i.BLLB == k.bllb ? k.name : '' }}</span>
              </div>
              <div class="conter-case">
                <div v-html="i.HJNR" />
              </div>
            </div>
            <!-- 结账单 -->
            <div v-if="item.FeeDetailed && item.FeeDetailed.length" style="margin-bottom: 20px">
              <span class="conter-title">{{ paginationData.pageSize * (paginationData.currentPage - 1) + (index + 1)
              }}.{{
                  item.EMR_BL_BL01.length + 1 }}</span>
              <el-table :data="item.FeeDetailed" stripe style="width: 100%">
                <el-table-column prop="FYMC" label="费用名称">
                  <template slot-scope="scope">
                    <span v-html="scope.row.FYMC[0]"></span>
                  </template>
                </el-table-column>
                <el-table-column prop="JFRQ" label="计费日期"></el-table-column>
                <el-table-column prop="FYSL" label="费用数量"></el-table-column>
              </el-table>
            </div>
            <!-- 医嘱本 -->
            <div v-if="item.YZB && item.YZB.length" style="margin-bottom: 20px">
              <span class="conter-title" v-if="!item.FeeDetailed.length">
                {{ paginationData.pageSize * (paginationData.currentPage - 1) + (index + 1) }}.{{
                  item.EMR_BL_BL01.length + 1
                }}
              </span>
              <span class="conter-title" v-else>{{ paginationData.pageSize * (paginationData.currentPage - 1) + (index +
                1)
              }}.{{ item.EMR_BL_BL01.length + 2 }}</span>
              <el-table :data="item.YZB" stripe style="width: 100%">
                <el-table-column prop="YZMC" label="医嘱名称">
                  <template slot-scope="scope">
                    <!-- 高级搜索 -->
                    <span v-if="choice" v-html="scope.row.YZMC"></span>
                    <!-- 普通搜索 -->
                    <span v-else v-html="scope.row.YZMC[0]"></span>
                  </template>
                </el-table-column>
                <el-table-column prop="BRKS" label="开嘱科室"></el-table-column>
                <el-table-column prop="KZSJ" label="开嘱时间"></el-table-column>
                <el-table-column prop="ZJE" label="医嘱期限"></el-table-column>
              </el-table>
            </div>

            <div class="conter-num" style="margin-top: 10px">
              病案号：
              <span class="yeleou underline-link" @click="funGoto(item.MED_REC_ID)">{{ item.AAA28 }}</span>
            </div>
            <div class="conter-time">出院时间：{{ item.AAC01 }}</div>
          </div>
          <!-- 专业检索 -->
          <div v-if="choice == 2">
            <!-- 患者信息 -->
            <div v-if="item.patient_info">
              <el-descriptions title="患者信息" :column="7" class="patient-info-inline">
                <el-descriptions-item label="住院号码" v-if="item.patient_info.AAA28">
                  <a v-html="item.patient_info.AAA28"
                    @click="funGoto(item.patient_info.MED_REC_ID ? item.patient_info.MED_REC_ID : item.patient_info.ZYH)"
                    class="yeleou underline-link"></a>
                </el-descriptions-item>
                <el-descriptions-item label="出院时间" v-if="item.patient_info.AAC01" >
                  <span v-html="item.patient_info.AAC01" :title="item.patient_info.AAC01" ></span>
                </el-descriptions-item>
                <el-descriptions-item label="姓名" v-if="item.patient_info.AAA01">
                  <span v-html="item.patient_info.AAA01"></span>
                </el-descriptions-item>
                <el-descriptions-item label="性别" v-if="item.patient_info.AAA02C">
                  <span v-html="item.patient_info.AAA02C"></span>
                </el-descriptions-item>
                <el-descriptions-item label="入院时间" v-if="item.patient_info.AAB01">
                  <span v-html="item.patient_info.AAB01" :title="item.patient_info.AAB01"></span>
                </el-descriptions-item>
                <el-descriptions-item label="住院天数" v-if="item.patient_info.AAC04">
                  <span v-html="item.patient_info.AAC04"></span>
                </el-descriptions-item>
                <el-descriptions-item label="年龄" v-if="item.patient_info.AAA04 || item.patient_info.AAA40">
                  <span v-html="item.patient_info.AAA04 ? item.patient_info.AAA04 : item.patient_info.AAA40"></span>
                </el-descriptions-item>
                <el-descriptions-item label="出院科室" v-if="item.patient_info.AAC11N">
                  <span v-html="item.patient_info.AAC11N" :title="item.patient_info.AAC11N"></span>
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
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.ryjl.RYJL_FZJC)" :key="aIndex">
                    <span v-if="aIndex" class="inline_block">;</span>
                    <span v-html="aItem" class="inline_block"></span>
                  </span>
                </el-descriptions-item>
                <el-descriptions-item label="初步诊断" v-if="item.ryjl.RYJL_CBZD">
                  <!-- <span v-for="(aItem, aIndex) of safeParseJSON(item.ryjl.RYJL_CBZD)" :key="aIndex">
                    <span v-if="aIndex" class="inline_block">;</span>
                    <span v-html="aItem" class="inline_block"></span>
                  </span> -->
                  <span v-html="item.ryjl.RYJL_CBZD"></span>
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
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.bcjl_scbc.BCJL_SCBC_CBZD_OTHER)" :key="aIndex">
                    <span v-if="aIndex" class="inline_block">;</span>
                    <span v-html="aItem" class="inline_block"></span>
                  </span>
                </el-descriptions-item>
                <el-descriptions-item label="诊断依据" v-if="item.bcjl_scbc.BCJL_SCBC_ZDYJ">
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.bcjl_scbc.BCJL_SCBC_ZDYJ)" :key="aIndex">
                    <span v-if="aIndex" class="inline_block">;</span>
                    <span v-html="aItem" class="inline_block"></span>
                  </span>
                </el-descriptions-item>
                <el-descriptions-item label="鉴别诊断" v-if="item.bcjl_scbc.BCJL_SCBC_JBZD">
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.bcjl_scbc.BCJL_SCBC_JBZD)" :key="aIndex">
                    <span v-if="aIndex" class="inline_block">;</span>
                    <span v-html="aItem" class="inline_block"></span>
                  </span>
                </el-descriptions-item>
                <el-descriptions-item label="鉴别诊断名称" v-if="item.bcjl_scbc.BCJL_SCBC_JBZDMC">
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.bcjl_scbc.BCJL_SCBC_JBZDMC)" :key="aIndex">
                    <span v-if="aIndex" class="inline_block">;</span>
                    <span v-html="aItem" class="inline_block"></span>
                  </span>
                </el-descriptions-item>
                <el-descriptions-item label="诊疗记录" v-if="item.bcjl_scbc.BCJL_SCBC_ZLJH">
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.bcjl_scbc.BCJL_SCBC_ZLJH)" :key="aIndex">
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
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.cyjl.CBZD)" :key="aIndex">
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
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.cyjl.CYZD)" :key="aIndex">
                    <span v-if="aIndex" class="inline_block">;</span>
                    <span v-html="aItem" class="inline_block"></span>
                  </span>
                </el-descriptions-item>
                <el-descriptions-item label="第一出院诊断" v-if="item.cyjl.CYZD_FIRST">
                  <span v-html="item.cyjl.CYZD_FIRST"></span>
                </el-descriptions-item>
                <el-descriptions-item label="出院医嘱" v-if="item.cyjl.CYYZ">
                  <span v-for="(aItem, aIndex) of safeParseJSON(item.cyjl.CYYZ)" :key="aIndex">
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
              <el-descriptions :title="`手术记录${sIndex + 1}`" :column="1">
                <el-descriptions-item label="整体" v-if="sItem.HJNR">
                  <span v-html="sItem.HJNR"></span>
                </el-descriptions-item>
                <el-descriptions-item label="术前诊断" v-if="sItem.SQZD">
                  <span v-html="sItem.SQZD"></span>
                </el-descriptions-item>
                <el-descriptions-item label="术中诊断" v-if="sItem.SZZD">
                  <span v-for="(aItem, aIndex) of safeParseJSON(sItem.SZZD)" :key="aIndex">
                    <span v-if="aIndex" class="inline_block">;</span>
                    <span v-html="aItem" class="inline_block"></span>
                  </span>
                </el-descriptions-item>
                <el-descriptions-item label="第一术中诊断" v-if="sItem.SZZD_ONE">
                  <span v-html="sItem.SZZD_ONE"></span>
                </el-descriptions-item>
                <el-descriptions-item label="手术名称" v-if="sItem.SSMC">
                  <span v-for="(aItem, aIndex) of safeParseJSON(sItem.SSMC)" :key="aIndex">
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
              <el-descriptions :title="`输血病程记录${sIndex + 1}`" :column="1">
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
              <el-descriptions :title="`其它诊断${sIndex + 1}`" :column="1">
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
              <el-descriptions :title="`其它诊断${sIndex + 1}`" :column="1">
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
              <el-descriptions :title="`24小时出入院记录${sIndex + 1}`" :column="1">
                <el-descriptions-item label="整体" v-if="sItem.HJNR">
                  <span v-html="sItem.HJNR"></span>
                </el-descriptions-item>
              </el-descriptions>
            </div>
            <!-- 首次病程 -->
            <div v-for="(sItem, sIndex) of item.bllb294" :key="sIndex">
              <!-- <el-descriptions :title="`病程记录${sIndex + 1}`" :column="1">
                <el-descriptions-item label="整体" v-if="sItem.HJNR">
                  <span v-html="sItem.HJNR"></span>
                </el-descriptions-item>
              </el-descriptions> -->
              <el-descriptions :title="sItem.BLMC" :column="1">
                <el-descriptions-item v-if="sItem.HJNR">
                  <span v-html="sItem.HJNR"></span>
                </el-descriptions-item>
              </el-descriptions>
            </div>
          </div>
        </div>
      </div>
      <el-pagination v-if="tableData && tableData.length !== 0" :total="paginationData.total" background
        class="table-pagination" style="margin: 15px 0px" :page-size="paginationData.pageSize"
        :current-page.sync="paginationData.currentPage" layout="total, sizes, prev, pager, next, jumper"
        @size-change="SizeChangeEvent" @current-change="pageHasChanged" />
    </div>
  </div>
</template>

<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
import { bassNormalSearchDownload, bassHighSearchDownload, professionSearchExport } from '@/api/excel';
import ProfessionSearchVue from './components/ProfessionSearch.vue';

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
  },
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      pickerOptions: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
      },
      checked: false,
      choice: 2,
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
        total: 0,
        currentPage: 1,
        pageSize: 10,
      },
      fieldList: [],
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
    // this.funQuery(0);
    this.handleProfessionSearch(this.professionSearchData)
    this.getfieldList();
  },
  methods: {

    safeParseJSON(str) {
      try {
        const result = JSON.parse(str);
        return Array.isArray(result) ? result : [];
      } catch (e) {
        console.error('JSON 解析失败:', e, '原始字符串:', str);
        return [];
      }
    },
    getfieldList() {
      let that = this;
      that.$axios3.post('/searchSelect', {}).then(res => {
        that.fieldList = res.data;
      });

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
      console.log("funGoto", path);
      let toPath;
      if (path === '/hospital-search') {
        toPath = '/hospital-caseViews';
      } else if (path === '/whitelist-search') {
        toPath = '/whitelist-caseViews';
      }
      else {
        toPath = '/caseViews?pageType=search';
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
        if (this.formData1.seniorList.length >= 2) {
          this.formData1.seniorList.pop();
        }
        return;
      }
      const list = this.formData1.seniorList;
      list.splice(index, 1);
      this.formData1.seniorList = list;
    },

    scrollToTable() {
      const container = document.querySelector('.dashboard-container');
      const tableElement = document.querySelector('.tableBox');
      if (container && tableElement) {
        const tableTop = tableElement.offsetTop;
        container.scrollTo({
          top: tableTop - 50,
          behavior: 'smooth'
        });
      }
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
      console.log("funQuery", this.choice);
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

          // 添加滚动到表格的代码
          // if (this.tableData.length > 0) {
          //   this.$nextTick(() => {
          //     this.scrollToTable();
          //   });
          // }

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
      this.handleProfessionSearch(params, 'search')
    },
    handleProfessionSearch(params, type = 'normal') {
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

        // 添加滚动到表格的代码
        if (this.tableData.length > 0 && type == 'search') {
          this.$nextTick(() => {
            this.scrollToTable();
          });
        }
      });
    },
    // 判断对象是否为空
    isObject(form) {
      let a = 0
      for (let key in form) {
        if (!!form[key]) {
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
    }
  },
};
</script>
<style lang="scss" scoped>
@import "~@/styles/index.model.scss";

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

::v-deep .patient-info-inline {
  display: flex !important; // 强制生效
  align-items: center !important;
  flex-wrap: wrap !important;
  width: 100% !important;
}

// 标题容器：取消100%宽度，和内容同行
::v-deep .patient-info-inline .el-descriptions__header {
  display: flex !important;
  align-items: center !important;
  width: auto !important; // 取消默认100%宽度
  margin: 0 20px 0 0 !important; // 标题和内容间距
  font-weight: bold !important;
  font-size: 16px !important;
  padding: 0 !important; // 清空默认padding
}

// 描述内容区域：占满剩余宽度，和标题同行
::v-deep .patient-info-inline .el-descriptions__body {
  flex: 1 !important; // 占满剩余宽度
  display: flex !important;
  flex-wrap: wrap !important;
  margin: 0 !important; // 取消默认margin-top
  padding: 0 !important;
}

// 每个描述项：保持8列布局，适配同行
::v-deep .patient-info-inline .el-descriptions-item {
  flex: 0 0 calc(100% / 7) !important; // 8列均分
  box-sizing: border-box !important;
  padding: 5px 10px !important; // 调整内边距，避免挤在一起
}

// 修复描述项标签和内容的行内展示
::v-deep .patient-info-inline .el-descriptions-item__label {
  display: inline-block !important;
  margin-right: 5px !important;
}

::v-deep .el-descriptions-item__label.has-colon::after {
  content: '';
}

::v-deep .patient-info-inline .el-descriptions-item__content {
  display: inline-block !important;
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

.flextab-item>div {
  font-size: 15px;
  margin-right: 15px;
}

.flextab-item>div span.s-1 {
  color: #185da6;
}

.flextab-item>div span.s-2 {
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
  color: #185da6;
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

  &>div {
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

.barBtn-title {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: bold;
  margin-top: 10px;
  margin-bottom: 20px;
}
</style>
