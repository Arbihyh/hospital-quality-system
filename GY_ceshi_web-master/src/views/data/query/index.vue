<!-- 病案首页查询 -->
<template>
  <div class="dashboard-container" :class="{ nocopy: $route.meta.nocopy }">
    <div class="block">
      <div class="barBtn">
        <el-radio-group v-model="choice" class="bnts" size="medium" @change="handleChoiceChange">
          <!-- <el-radio-button :label="0">普通检索</el-radio-button> -->
          <!-- <el-radio-button :label="1">高级检索</el-radio-button> -->
        </el-radio-group>
        <!-- <el-button style="position: absolute; right: 50px" @click="commonQuery" v-if="choice === 1">常用查询条件</el-button> -->
      </div>
      <div class="bnh">
        <!-- <el-input v-if="choice == 0" style="width: 303px" placeholder="全站搜索病案号" suffix-icon="el-icon-search" v-model="inputOn"></el-input> -->
      </div>
      <div class="barBtn-title">病案首页查询</div>
      <div class="inputs" v-if="choice == 0">
        <el-row :gutter="24" class="rowsa">
          <el-col :span="8">
            <div class="grid-content bg-purple">
              <el-input class="inpus" v-model="formData0.recordNum" placeholder="病案号"></el-input>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.Department" filterable class="selects" placeholder="出院科室">
                <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <!-- <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.problem" class="selects" placeholder="问题属性">
                <el-option v-for="(item, index) in levelList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col> -->
          <el-col :span="8">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.payment" filterable class="selects" placeholder="医疗付款方式">
                <el-option v-for="(item, index) in payList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <!-- <el-col :span="5">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.medicalRecord" class="selects" placeholder="全部病案">
                <el-option label="已质控" value="1"></el-option>
                <el-option label="未质控" value="0"></el-option>
              </el-select>
            </div>
          </el-col> -->
        </el-row>
        <el-row :gutter="24" class="rowsa">
          <!-- <el-col :span="4">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.state" class="selects" placeholder="编辑状态">
                <el-option v-for="(item, index) in statusList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col> -->
          <el-col :span="8">
            <div class="grid-content bg-purple">
              <el-date-picker
                class="selects"
                v-model="formData0.startTime"
                type="date"
                format="yyyy 年 MM 月 dd 日"
                value-format="yyyyMMdd"
                placeholder="开始日期"
              ></el-date-picker>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="grid-content bg-purple">
              <el-date-picker class="selects" v-model="formData0.endTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
            </div>
          </el-col>
          <el-col :span="8">
            <div class="grid-content bg-purple">
              <el-select v-model="formData0.Coder" filterable class="selects" placeholder="编码员">
                <el-option v-for="(item, index) in coderList" :label="item.label" :value="item.id" :key="index"></el-option>
              </el-select>
            </div>
          </el-col>
          <el-col :span="5">
            <div class="grid-content bg-purple"></div>
          </el-col>
        </el-row>
      </div>
      <div class="barBtn" v-else>
        <div>
          <el-form ref="form" :model="formData1" label-width="100px" style="float: left">
            <el-form-item v-for="(item, index) in formData1.seniorList" :key="index">
              <!-- 下拉框开始 -->
              <el-select
                v-model="item.select_type"
                :class="index != 0 ? 'marginLeft' : ''"
                class="width100"
                filterable
                placeholder=""
                :disabled="item.lock"
                @change="handleSelectChange(item.select_type, index)"
              >
                <!-- fieldList -->
                <el-option label="且" :value="0" />
                <el-option label="或" :value="1" />
                <el-option label="不包含" :value="2" />
              </el-select>
              <!-- 下拉框开始 -->
              <el-select class="width150" filterable v-model="item.key" @change="getOneCleck(item, index)" placeholder="请选择">
                <!-- fieldList -->
                <el-option v-for="(item, index) in fieldList" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
              <!-- 下拉框结束 -->

              <span class="pind10"></span>
              <!-- 中间选择输入框开始 -->
              <span v-if="keyList.includes(item.key)">
                <el-select class="width150" filterable v-model="item.value" multiple collapse-tags placeholder="请选择">
                  <el-option v-for="(itemo, indexo) in item.selectList" :key="indexo" :label="itemo.label" :value="itemo.id"></el-option>
                </el-select>
              </span>
              <span
                v-else-if="
                  [
                    'ABC01N',
                    'ICD10_NAME_first',
                    'ABA01N',
                    'ICD10_NAME',
                    'ABC01C',
                    'ICD10_ID1_first',
                    'ICD10_ID1',
                    'ICD9_NAME',
                    'MO_ICD9_NAME',
                    'SO_ICD9_NAME',
                    'ICD9_ID1',
                    'MO_ICD9_ID1',
                    'SO_ICD9_ID1',
                  ].includes(item.key)
                "
              >
                <big-data-remote-select
                  :ref="`bigDataRemoteSelectRef_${index}`"
                  v-model="item.value"
                  :api-url="isContainICU(item.key)"
                  placeholder="请选择"
                ></big-data-remote-select>
              </span>
              <span v-else>
                <el-input class="width150" v-model="item.value" placeholder="请输入"></el-input>
              </span>
              <!-- 中间选择输入框结束 -->

              <span class="pind10"></span>
              <!-- 条件下拉开始 -->
              <el-select class="width90" v-model="item.type" placeholder="">
                <el-option label="精确" value="1"></el-option>
                <el-option label="模糊" value="0"></el-option>
              </el-select>
              <!-- 条件下拉结束 -->

              <span class="pind10"></span>
              <!-- 增减重置选项按钮开始 -->
              <span style="position: relative">
                <el-button type="primary" icon="el-icon-minus" :disabled="formData1.seniorList.length === 1" @click="funDel(index)"></el-button>
                <el-button type="primary" icon="el-icon-plus" @click="funAdd"></el-button>
              </span>
              <!-- 增减重置选项按钮结束 -->
            </el-form-item>

            <el-form-item label="患者年龄">
              <div class="zkSelect">
                <el-input class="width300" v-model="formData1.ageday" :min="28" :max="365" type="number" placeholder="<28天" @blur="funBlur">
                  <template slot="append">
                    <el-select v-model="formData1.age_start_type" @change="ageChange" placeholder="请选择">
                      <el-option v-for="item in Dayoptions" :key="item.value" :label="item.label" :value="item.value"></el-option>
                    </el-select>
                  </template>
                </el-input>
              </div>
              <span class="pind" style="color: #ccc">——</span>
              <div class="zkSelect">
                <el-input class="width300" v-model="formData1.ageyear" :min="1" :max="150" type="number" placeholder="1-150岁" @blur="funBlur">
                  <template slot="append">
                    <el-select v-model="formData1.age_end_type" placeholder="请选择">
                      <el-option v-for="item in Dayoptions" :key="item.value" :label="item.label" :value="item.value"></el-option>
                    </el-select>
                  </template>
                </el-input>
              </div>
            </el-form-item>
            <el-form-item label="住院天数">
              <div class="zkSelect">
                <el-input class="width300" v-model="formData1.hospitalizationon" :min="28" :max="365" type="number" @blur="funBluron">
                  <template slot="append">天</template>
                </el-input>
              </div>
              <span class="pind" style="color: #ccc">——</span>
              <div class="zkSelect">
                <el-input class="width300" v-model="formData1.hospitalizationin" :min="1" :max="150" type="number" @blur="funBluron">
                  <template slot="append">天</template>
                </el-input>
              </div>
            </el-form-item>
            <el-form-item label="出院日期">
              <el-date-picker v-model="formData1.startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
              <span class="pind10"></span>
              <el-date-picker v-model="formData1.endTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
            </el-form-item>

            <el-form-item label="入院日期">
              <el-date-picker v-model="formData1.AAB01_startTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="开始日期"></el-date-picker>
              <span class="pind10"></span>
              <el-date-picker v-model="formData1.AAB01_endTime" type="date" format="yyyy 年 MM 月 dd 日" value-format="yyyyMMdd" placeholder="结束日期"></el-date-picker>
            </el-form-item>

            <el-form-item label="范围搜索">
              <!-- 下拉框开始 -->
              <el-select class="width150" filterable v-model="formData1.rangeName" @change="rangeChange" placeholder="请选择范围类型">
                <!-- fieldList -->
                <el-option v-for="(item, index) in rangeArray" :label="item.name" :value="item.id" :key="index"></el-option>
              </el-select>
              <!-- 下拉框开始 -->
              <div class="zkSelect">
                <el-input class="width300" v-model="formData1.bmStart" placeholder="起始范围"></el-input>
              </div>
              <span class="pind" style="color: #ccc">——</span>
              <div class="zkSelect">
                <el-input class="width300" v-model="formData1.bmEnd" placeholder="结束范围"></el-input>
              </div>
            </el-form-item>
          </el-form>

          <el-button style="float: right" @click="commonQuery" v-if="choice === 1">常用查询条件</el-button>
        </div>
      </div>
      <div class="fBtn" style="position: relative">
        <el-button style="position: absolute; right: 150px" @click="searchCollectClick" v-if="choice == 1">收藏当前搜索</el-button>
        <el-button style="position: absolute; right: 30px" @click="reset">重置条件</el-button>
        <el-button type="primary" class="long-btn" @click="funQuery(1, 'btn')">检索</el-button>
      </div>
    </div>
    <div class="tableBox">
      <div class="flextab" style="margin: 0; margin-bottom: 15px">
        <div class="flextabtitle-box" style="display: flex; justify-content: space-between; align-items: center">
          <!-- <Title :title="'病案列表'" /> -->
          <div class="h-title">
            <span class="blue"></span>
            <span class="text">病案列表</span>
          </div>
          <div class="flextab-item">
            <div>
              平均住院日：
              <span class="tag-box">
                <span class="s-1">{{ ARG_STAY }}</span>
                <span class="s-2">天</span>
              </span>
            </div>
            <div>
              平均费用：

              <span class="tag-box">
                <span class="s-1">{{ ARG_F_D }}</span>
                <span class="s-2">元</span>
              </span>
            </div>
            <div>
              例数：
              <span class="tag-box">
                <span class="s-1">{{ paginationData.total ? paginationData.total : 0 }}</span>
                <span class="s-2">例</span>
              </span>
            </div>
            <div>
              死亡例数：

              <span class="tag-box">
                <span class="s-1">{{ AEM01C ? AEM01C : 0 }}</span>
                <span class="s-2">例</span>
              </span>
            </div>
            <div>
              总费用：

              <span class="tag-box">
                <span class="s-1">{{ SUM_ARG_F_D ? SUM_ARG_F_D : 0 }}</span>
                <span class="s-2">元</span>
              </span>
            </div>
            <div style="position: relative">
              总住院日：

              <span class="tag-box">
                <span class="s-1">{{ SUM_ARG_STAY ? SUM_ARG_STAY : 0 }}</span>
                <span class="s-2">天</span>
              </span>
            </div>
          </div>

          <div style="margin-left: 150px">
            <el-button type="primary" class="export-btn export-btn" @click="getExportField((filterTypeName = 'table'))">表格筛选</el-button>
            <el-button type="primary" class="export-btn export-btn" @click="getExportField((filterTypeName = 'export'))">数据导出</el-button>
          </div>
        </div>
      </div>
      <div style="text-align: right; margin-bottom: 20px">
        <!-- <el-button type="primary" class="export-btn export-btn"
          @click="getExportField(filterTypeName = 'table')">表格筛选</el-button>
        <el-button type="primary" class="export-btn export-btn"
          @click="getExportField(filterTypeName = 'export')">数据导出</el-button> -->
        <!--
        <el-button type="primary" v-if="!isWhitelist" icon="el-icon-download" class="export-btn export-btn" @click="funExport('质控列表', '/qualityList')">导出数据</el-button>
        -->
      </div>
      <el-table :data="tableData" style="width: 100%" v-if="tableOptions.length > 0">
        <template v-for="(item, index) in tableOptions">
          <el-table-column v-if="item.prop == 'id'" type="index" label="序号" width="80" align="center" :key="index"></el-table-column>
          <el-table-column v-else-if="item.prop == 'AAA28'" :prop="item.prop" :label="item.name" :width="item.filed_width" align="center">
            <template slot-scope="scope">
              <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">{{ scope.row.AAA28 }}</span>
            </template>
          </el-table-column>
          <el-table-column v-else :prop="item.prop" :label="item.name" :width="item.filed_width" align="center"></el-table-column>
        </template>

        <!--
        <template slot-scope="scope" v-if="item.prop == 'AAA28'">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">{{ scope.row.AAA28 }}</span>
          </template>
        <el-table-column type="index" label="序号" width="80" align="center"></el-table-column>
        <el-table-column prop="AAA28" label="病案号" width="100" align="center">
          <template slot-scope="scope">
            <span class="blue" @click="funGoto(scope.row.MED_REC_ID)">{{ scope.row.AAA28 }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="AAA01" label="患者姓名" width="100" align="center"></el-table-column>
        <el-table-column prop="AAC01" label="出院时间" width="160" align="center"></el-table-column>
        <el-table-column prop="AAA02C" label="性别" width="80" align="center"></el-table-column>
        <el-table-column prop="AAA04" label="年龄" width="80" align="center"></el-table-column>
        -->
        <!-- <template v-for="(item, ind) in formData1.seniorList">
          <el-table-column :key="ind" v-if="tabKeyList.includes(item.key)" :label="funkdef(item.key)" :prop="item.key" width="200" show-overflow-tooltip align="center"></el-table-column>
        </template> -->
        <!--
       <el-table-column prop="ABC01N" label="主诊断名称" width="200" show-overflow-tooltip align="center"></el-table-column>
       <el-table-column prop="ABC01C" label="主诊断编码" width="200" show-overflow-tooltip align="center"></el-table-column>
       <el-table-column prop="ICD9_NAME" label="主手术名称" width="200" show-overflow-tooltip align="center"></el-table-column>
       <el-table-column prop="ICD9_ID1" label="主手术编码" width="200" show-overflow-tooltip align="center"></el-table-column>
       -->
        <!-- <el-table-column v-if="columnShow" :prop="clumText.id" :label="clumText.name" width="200" show-overflow-tooltip align="center"></el-table-column> -->
        <!--
       <el-table-column prop="AAA29" label="住院次数" width="80" align="center"></el-table-column>
       <el-table-column prop="AAC11N" label="出院科室" width="200" show-overflow-tooltip align="center"></el-table-column>
       <el-table-column prop="ADA01" label="住院总费用" width="120" align="center"></el-table-column>
       <el-table-column prop="F_D" label="药品总费用" width="120" align="center"></el-table-column>
       <el-table-column prop="J" label="材料总费用" width="120" align="center"></el-table-column>
       <el-table-column prop="AAC04" label="实际住院(天)" width="120" align="center"></el-table-column>
       <el-table-column prop="AEM01C" label="离院方式" width="200" show-overflow-tooltip align="center"></el-table-column>
       <el-table-column prop="AAB06C" label="入院途径" width="200" show-overflow-tooltip align="center"></el-table-column>
       <el-table-column prop="SSPB" label="手术判别" width="200" show-overflow-tooltip align="center"></el-table-column>
       <el-table-column prop="AAB11N" label="入院科室" width="200" show-overflow-tooltip align="center"></el-table-column>
       <el-table-column prop="AAB01" label="入院时间" width="160" align="center"></el-table-column>
       -->
      </el-table>
      <!-- 分页控制 -->
      <el-pagination
        v-if="tableData && tableData.length !== 0"
        @size-change="SizeChangeEvent"
        @current-change="pageHasChanged"
        :total="paginationData.total"
        background
        class="table-pagination"
        style="margin: 15px 0px"
        :page-size="paginationData.pageSize"
        :current-page.sync="paginationData.currentPage"
        layout="total, sizes, prev, pager, next, jumper"
      ></el-pagination>
      <!-- <mPagination style="margin:15px 0px;"
         v-if="tableData && tableData.length !== 0"
          :data="paginationData"
          @SizeChangeEvent="SizeChangeEvent"
           @pageChangeEvent="pageHasChanged"></mPagination> -->
    </div>
    <!--搜索收藏弹窗-->
    <el-dialog title="收藏当前搜索条件" :visible.sync="dialogVisible" width="50%" center custom-class="custom-dialog">
      <el-form ref="searchCollectFrom" :model="searchCollectFrom" label-width="100px" :rules="formRules">
        <el-form-item label="收藏名称" :required="true" prop="name">
          <el-input placeholder="请输入收藏名称" v-model="searchCollectFrom.name"></el-input>
        </el-form-item>
        <el-form-item label="公用收藏" :required="true" prop="is_public">
          <el-radio-group v-model="searchCollectFrom.is_public">
            <el-radio :label="1">是</el-radio>
            <el-radio :label="0">否</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="公用科室" :required="true" v-if="searchCollectFrom.is_public === 1" prop="dep_ids">
          <!-- 下拉框开始 -->
          <el-select class="width150" filterable multiple v-model="searchCollectFrom.dep_ids" placeholder="请选择范围类型">
            <el-option v-for="(item, index) in this.departmentArray" :label="item.dep_name" :value="item.dep_id" :key="index"></el-option>
          </el-select>
          <!-- 下拉框开始 -->
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="searchCollectFromSave">保存</el-button>
      </span>
    </el-dialog>
    <!--常用查询条件弹窗-->
    <el-dialog title="常用查询条件" :visible.sync="commonQueryDialog" width="800px" center custom-class="custom-dialog">
      <el-input placeholder="请输入收藏名称" prefix-icon="el-icon-search" v-model="searchCollectSearch" @input="getSearchCollect"></el-input>
      <el-form ref="searchCollectFrom" :model="searchCollectFrom" label-width="100px">
        <el-tabs v-model="is_public" @tab-click="getSearchCollect" style="margin-top: 10px">
          <el-tab-pane label="账号收藏" name="0">
            <el-radio-group v-model="radio">
              <div style="display: flex; flex-wrap: wrap; justify-content: left; align-items: center">
                <el-radio :label="item.id" v-for="(item, index) in searchCollectArray" style="margin-top: 10px; width: 150px">
                  <span v-html="item.name"></span>
                  <el-button type="text" style="color: red; padding-left: 10px" @click="deleteSearchCollect(item.id)">删除</el-button>
                </el-radio>
              </div>
            </el-radio-group>
          </el-tab-pane>
          <el-tab-pane label="公共收藏" name="1">
            <el-radio-group v-model="radio">
              <div style="display: flex; flex-wrap: wrap; justify-content: left; align-items: center">
                <el-radio :label="item.id" v-for="(item, index) in searchCollectArray" style="margin-top: 10px; width: 150px">
                  <span v-html="item.name"></span>
                  <el-button type="text" style="color: red; padding-left: 10px" @click="deleteSearchCollect(item.id)">删除</el-button>
                </el-radio>
              </div>
            </el-radio-group>
          </el-tab-pane>
        </el-tabs>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="commonQueryDialog = false">取消</el-button>
        <el-button type="primary" @click="quote()">引用</el-button>
      </span>
    </el-dialog>
    <!--导出筛选-->
    <el-dialog :title="filterTypeName == 'table' ? '表单内容筛选' : '导出内容筛选'" :visible.sync="exportFilter" width="1000px" center custom-class="custom-dialog">
      <el-transfer
        ref="myTransfer"
        filterable
        :titles="['未选字段', '已选字段']"
        filter-placeholder="请输入关键字查询"
        v-model="checkList"
        :data="cityOptions"
        :props="{
          key: 'id',
          label: 'name',
          disabled: 'disabled',
        }"
      ></el-transfer>
      <span slot="footer" class="dialog-footer">
        <el-button @click="exportFilter = false">取消</el-button>
        <el-button type="primary" @click="exportExcel" v-if="filterTypeName == 'export'">导出</el-button>
        <el-button type="primary" @click="tableChange" v-if="filterTypeName == 'table'">确认</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { downloadFile } from '@/httpFile';
import Title from '@/components/Title';
import { mapGetters } from 'vuex';
import mPagination from '@/components/m-pagination';
// import { json } from 'stream/consumers';
import { medicalRecordExport, zz } from '@/api/excel';
import BigDataRemoteSelect from '@/components/BigDataRemoteSelect';

export default {
  name: 'Dashboard',
  props: {
    isWhitelist: {
      type: Boolean,
      default() {
        return false;
      },
    },
  },
  components: {
    Title,
    mPagination,
    BigDataRemoteSelect,
  },
  computed: {
    ...mapGetters(['name']),
  },
  data() {
    return {
      searchTimer: null,
      filterSearchKeyword: '',
      tableOptions: [],
      filterTypeName: 'table',
      isExportDefault: true, //导出默认选择状态
      isExportAll: false, //导出全选状态
      checkList: [], //导出选中list
      cityOptions: [], //导出筛选的数据
      exportFilter: false, //导出弹窗状态
      formRules: {
        name: [{ required: true, message: '收藏名称不能为空', trigger: 'blur' }],
        is_public: [{ required: true, message: '请选择是否公开', trigger: 'change' }],
        dep_ids: [{ required: true, message: '请至少选择一个科室', trigger: 'change' }],
      }, //收藏规则
      searchCollectSearch: '', //搜索收藏搜索
      activeName: 'first',
      is_public: 0,
      searchCollectArray: [], //搜索收藏
      commonQueryDialog: false, //常用查询条件dialog
      searchCollectFrom: {
        is_public: 0,
        department: [],
      },
      selectDepartment: [], //选中的科室
      departmentArray: {}, //所有科室
      radio: 6,
      dialogVisible: false,
      selectRange: '', //范围下拉选中
      rangeArray: [
        { id: 1, name: '主要诊断编码', value: 'zyzdbm' },
        { id: 2, name: '其他诊断编码', value: 'qtzdbm' },
        { id: 3, name: '主要手术编码', value: 'zyssbm' },
        { id: 4, name: '其他手术编码', value: 'qtssbm' },
        { id: 5, name: '手术编码', value: 'ssbm' },
        { id: 6, name: '诊断编码', value: 'zdbm' },
      ],
      choice: 1,
      clumText: {},
      columnShow: false,
      labelList: ['ICD10_NAME'],
      formData0: {
        recordNum: '', //病案号
        Department: '', //出院科室
        // problem: 'all', //问题属性
        payment: '', //医疗付款方式
        // state: '', //编辑状态
        // rangeDate: [], //时间
        endTime: '',
        startTime: '',
        Coder: '', //住院医师
        // medicalRecord: '', //全部病案
      },
      Dayoptions: [
        {
          value: 1,
          label: '天',
        },
        {
          value: 2,
          label: '岁',
        },
      ],
      formData1: {
        ageday: '',
        age_start_type: 2,
        age_end_type: 2,
        ageyear: '',
        // rangeDate: [],
        endTime: '',
        startTime: '',
        seniorList: [
          {
            select_type: 0,
            key: '',
            value: '',
            type: '1',
            selectList: [],
          },
        ],
        hospitalizationon: '',
        hospitalizationin: '',
        rangeName: '',
        bmStart: '',
        bmEnd: '',
      },
      inputOn: '', //全站搜索病案号
      value: '',
      value1: '',
      selectList: [],
      labelText: '',
      keyList: [
        'OPE_LEVEL',
        'SSPB',
        'ABC03C',
        'RYQK',
        'AAA02C',
        'RJSS',
        'AEM01C',
        'AAC11N',
        'LNSSQ',
        'LNSSH',
        'AEL01',
        'AEE10',
        'AEE03',
        'AEE04',
        'AEE01',
        'AEE02',
        'ZZYISXM',
        'MO_OPE_LEVEL',
        'SO_OPE_LEVEL',
        'SO_OPE_TYPE',
        'MO_OPE_TYPE',
        // 'AAD01C',
        'AAC02C',
        'AAB02C',
        // 'ABC01N',
        // 'ICD10_NAME_first',
        // 'ABA01N',
        // 'ICD10_NAME',
      ],
      tabKeyList: [
        'ICD10_ID1_first',
        'ICD10_NAME_first',
        'ICD10_ID1',
        'ICD10_NAME',
        'ICD9_ID1',
        'ICD9_NAME',
        'ABC03C',
        'RYQK',
        'OPE_LEVEL',
        'ABA01N',
        'ABA01C',
        'AEL01',
        'RJSS',
        'LNSSQ',
        'LNSSH',
      ], // 表头key动态展示
      tableData: [],
      payList: [], //支付方式
      departmentList: [], //出院科室
      levelList: [], //问题属性
      coderList: [], //编码元
      statusList: [], //编辑状态
      fieldList: [], //主要诊断名字
      department: [], //科室
      diagnosisAllData: [],
      diagnosisCodeAllData: [],
      diagnosisList: [], //诊断名称
      diagnosisCodeList: [], //诊断编码
      keywordDiagnosisName: '',
      keywordDiagnosisCode: '',
      // 分页数据
      paginationData: {
        total: 10,
        currentPage: 1,
        pageSize: 10,
      },
      ARG_F_D: '',
      ARG_STAY: '',
      AEM01C: '',
      SUM_ARG_STAY: '',
      SUM_ARG_F_D: '',
      is_tm_path: ['/hospital-caseViews', '/embedIndex-caseViews', '/reviewIndex-caseViews', '/whitelist-caseViews', '/whitelist-search'],
      doctors: [],
    };
  },
  mounted() {},
  created() {
    this.funQuery(1);
    this.selectInfo();
    this.selectBmyStaff();
    this.getDoctors();
    this.$axios.post('/getExportField').then(res => {
      this.cityOptions = res.data.fieldTable;
      this.checkList = res.data.checkList;
      this.tableChange();
    });
  },
  beforeDestroy() {
    clearTimeout(this.searchTimer);
  },
  methods: {
    isContainICU(key) {
      //诊断名称
      if (['ABC01N', 'ICD10_NAME_first', 'ABA01N', 'ICD10_NAME'].includes(key)) {
        return '/icd10DiagnosisList';
      }
      //诊断编码
      if (['ABC01C', 'ICD10_ID1_first', 'ICD10_ID1'].includes(key)) {
        return '/icd10DiagnosisCodeList';
      }

      //手术名称
      if (['ICD9_NAME', 'MO_ICD9_NAME', 'SO_ICD9_NAME'].includes(key)) {
        return '/icd09OperationNameList';
      }

      //手术编码
      if (['ICD9_ID1', 'MO_ICD9_ID1', 'SO_ICD9_ID1'].includes(key)) {
        return '/icd09OperationCodeList';
      }
    },
    //筛选表格
    tableChange() {
      //把选中的数据转换成key=>value
      let checkArray = [];
      this.checkList.forEach(item => {
        checkArray[item] = item;
      });
      //处理表格显示的数据
      this.tableOptions = [];
      this.cityOptions.forEach((item, index) => {
        if (checkArray[item.id]) {
          if (item.table_name == 'patient_info') {
            this.tableOptions.push({ prop: item.filed_name, name: item.name, filed_width: item.filed_width });
          } else {
            this.tableOptions.push({ prop: item.table_name + '[' + item.array_id + '].' + item.filed_name, name: item.name, filed_width: item.filed_width });
          }
        }
      });
      this.exportFilter = false;
    },

    scrollToTable() {
      const container = document.querySelector('.dashboard-container');
      const tableElement = document.querySelector('.tableBox');
      if (container && tableElement) {
        const tableTop = tableElement.offsetTop;
        container.scrollTo({
          top: tableTop - 50,
          behavior: 'smooth',
        });
      }
    },
    //获取搜索条件
    getSearch() {
      //查询条件
      var pramse = {};
      let min = this.formData1.hospitalizationon;
      let max = this.formData1.hospitalizationin;
      if (this.choice == 0) {
        pramse = {
          // level: this.formData0.problem || null, //问题属性
          AAA28: this.formData0.recordNum || null, //病案号
          AAC11C: this.formData0.Department || null, //出院科室
          AAA26C: this.formData0.payment || null, //付款方式
          // status: this.formData0.state || null, //编辑状态
          AAC01_start_date: this.formData0.startTime || '',
          AAC01_end_date: this.formData0.endTime || '',
          coder_id: this.formData0.Coder || null, //编码员ID
          // ORG_STATE: this.formData0.medicalRecord || null, //全部病案
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
          is_export: 1,
        };
      } else {
        pramse = {
          AAC04: `${min ? min : 0}-${max ? max : 0}`,
          AAC0401: `${min ? min : 0}`,
          AAC0402: `${max ? max : 0}`,
          AAA04: this.formData1.ageyear || null, //年龄
          AAA40: this.formData1.ageday || null, //不足一周岁年龄
          age_start_type: this.formData1.age_start_type || null,
          age_end_type: this.formData1.age_end_type || null,
          AAC01_start_date: this.formData1.startTime || '',
          AAC01_end_date: this.formData1.endTime || '',

          // 入院时间
          AAB01_start_time: this.formData1.AAB01_startTime || '',
          AAB01_end_time: this.formData1.AAB01_endTime || '',

          rangeName: this.formData1.rangeName || null, //范围类型
          bmStart: this.formData1.bmStart || null, //编码起始范围
          bmEnd: this.formData1.bmEnd || null, //编码结束范围
          field: this.formData1.seniorList || null, //字段条件
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
          is_export: 1,
        };
      }
      return pramse;
    },
    //导出数据
    exportExcel() {
      let pramse = this.getSearch();
      pramse['filedIds'] = this.checkList;
      zz(pramse).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `病案列表.csv`;
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
      this.exportFilter = false;
      this.isExportDefault = true;
      this.isExportAll = false;
    },
    //导出字段默认选择
    exportCheckChange() {
      this.isExportAll = false;
      this.checkList = [];
      if (this.isExportDefault === true) {
        this.cityOptions.forEach(item => {
          if (item.is_check === 1) this.checkList.push(item.id);
        });
      } else {
        this.cityOptions.forEach(item => {
          if (item.disabled === 1) this.checkList.push(item.id);
        });
      }
    },
    //导出字段全选
    exportCheckAllChange() {
      this.isExportDefault = false;
      this.checkList = [];
      if (this.isExportAll === true) {
        this.cityOptions.forEach(item => {
          this.checkList.push(item.id);
        });
      } else {
        this.cityOptions.forEach(item => {
          if (item.disabled === 1) this.checkList.push(item.id);
        });
      }
    },

    //获取导出字段
    getExportField() {
      this.$axios.post('/getExportField').then(res => {
        this.cityOptions = res.data.fieldTable;
        this.checkList = this.checkList == '' ? res.data.checkList : this.checkList;
        this.exportFilter = true;
        this.isExportDefault = true;
      });
    },
    //收藏搜索引用
    quote() {
      this.$axios.post('/getCollect', { collect_id: this.radio }).then(res => {
        this.formData1 = this.$options.data().formData1; //重置formData1
        res.data.forEach(item => {
          switch (item.name) {
            case 'seniorList':
              item.search_value = JSON.parse(item.search_value);
              this.formData1[item.name] = item.search_value;
              break;
            case 'rangeName':
              this.rangeChange(item.search_value);
              break;
            case 'age_start_type':
            case 'age_end_type':
              this.ageChange(item.search_value);
              break;
            default:
              this.formData1[item.name] = item.search_value;
          }
        });
        this.commonQueryDialog = false;
      });
    },
    //年龄选中类型选择效果
    ageChange(value) {
      let data = this.Dayoptions.find(item => item.value == value);
      console.log(data);
      this.formData1.age_start_type = data.label;
    },
    //搜索收藏删除
    deleteSearchCollect(id) {
      this.$confirm('此操作将永久删除该收藏, 是否继续?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      })
        .then(() => {
          this.$axios.post('/deleteSearchCollect', { id: id }).then(res => {
            if (res.code == 100) this.$message.error(res.msg);
            if (res.code == 200) {
              this.getSearchCollect();
              this.$message({
                type: 'success',
                message: res.msg,
              });
            }
          });
        })
        .catch(() => {
          this.$message({
            type: 'info',
            message: '已取消删除',
          });
        });
    },
    //获取收藏数据
    getSearchCollect() {
      let search = {};
      search['is_public'] = this.is_public;
      search['searchCollectSearch'] = this.searchCollectSearch;
      this.$axios.post('/getSearchCollect', search).then(res => {
        this.searchCollectArray = res.data;
        console.log(this.searchCollectArray);
      });
    },
    //常用查询条件
    commonQuery() {
      this.getSearchCollect();
      this.commonQueryDialog = true;
    },
    //收藏成功
    success() {
      this.dialogVisible = false;
      console.log('123');
      this.$message.success('收藏成功');
    },
    //搜索收藏保存
    searchCollectFromSave() {
      this.$refs.searchCollectFrom.validate(valid => {
        if (!valid) this.$message.error('请填写必填项');
      });
      this.searchCollectFrom.searchArray = this.formData1;
      this.$axios.post('/searchCollectSave', this.searchCollectFrom).then(res => {
        if (res.code == 200) {
          this.searchCollectFrom = { is_public: 0, department: [] };
          this.success(res.msg);
        }
      });
    },
    //获取科室
    getDeportmentList() {
      this.$axios.post('/getDeportmentList').then(res => {
        this.departmentArray = res.data;
      });
    },
    //收藏
    searchCollectClick() {
      this.getDeportmentList();
      this.dialogVisible = true;
    },
    //范围选择
    rangeChange(value) {
      let currentRange = this.rangeArray[value - 1];
      this.selectRange = currentRange['name'];
      this.$set(this.formData1, 'rangeName', currentRange['id']);
    },
    handleSelectChange(val, index) {
      console.log(val, index);
      if (index === 1) {
        this.$set(this.formData1.seniorList[0], 'select_type', this.formData1.seniorList[1].select_type);
      }
    },
    // choice变化搜索条件重置
    handleChoiceChange() {
      this.reset();
    },
    funkdef(key) {
      for (let item in this.fieldList) {
        if (this.fieldList[item].id == key) {
          return this.fieldList[item].name;
        }
      }
    },
    // 获取医生选线
    getDoctors() {
      this.$axios.post('/selectStaff').then(res => {
        this.doctors = res.data;
      });
    },
    /**
     * 根据下拉框选择出现对应数据
     * @param {val} 选中当前
     */
    getOneCleck(val, index) {
      const targetKeyList = [
        'ABC01N',
        'ICD10_NAME_first',
        'ABA01N',
        'ICD10_NAME',
        'ABC01C',
        'ICD10_ID1_first',
        'ICD10_ID1',
        'ICD9_NAME',
        'MO_ICD9_NAME',
        'SO_ICD9_NAME',
        'ICD9_ID1',
        'MO_ICD9_ID1',
        'SO_ICD9_ID1',
      ];

      if (targetKeyList.includes(val.key)) {
        this.$nextTick(() => {
          const refName = `bigDataRemoteSelectRef_${index}`;
          const targetSelect = this.$refs[refName];
           const selectInstance = Array.isArray(targetSelect) ? targetSelect[0] : targetSelect;
          selectInstance.currentKeyword = '';
          selectInstance.initData();
        });
      }

      this.$nextTick();
      this.$set(this.formData1.seniorList[index], 'value', '');
      var that = this;
      this.labelText = val.key;
      var text = this.fieldList.filter(item => val.key == item.id);
      console.log('text', text);
      that.$nextTick(function () {
        that.clumText = {
          name: text[0].name,
          id: text[0].id,
        };
      });
      if (val.key == 'OPE_LEVEL') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '一级手术',
            id: 1,
          },
          {
            label: '二级手术',
            id: 2,
          },
          {
            label: '三级手术',
            id: 3,
          },
          {
            label: '四级手术',
            id: 4,
          },
        ];
      } else if (val.key == 'SSPB') {
        val.selectList = [
          {
            label: '全部',
            id: 5,
          },

          {
            label: '介入治疗',
            id: 4,
          },
          {
            label: '手术',
            id: 1,
          },
          {
            label: '诊断操作',
            id: 2,
          },
          {
            label: '治疗操作',
            id: 3,
          },
          {
            label: '空',
            id: 0,
          },
        ];
      } else if (val.key == 'AAA02C') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '男',
            id: 1,
          },
          {
            label: '女',
            id: 2,
          },
          {
            label: '未知的性别',
            id: 3,
          },
          {
            label: '未说明的性别',
            id: 4,
          },
        ];
      } else if (val.key == 'RJSS') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '是',
            id: 1,
          },
          {
            label: '否',
            id: 2,
          },
        ];
      } else if (val.key == 'ABC03C') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '有',
            id: 1,
          },
          {
            label: '临床未确定',
            id: 2,
          },
          {
            label: '情况不明',
            id: 3,
          },
          {
            label: '无',
            id: 4,
          },
        ];
      } else if (val.key == 'RYQK') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '有',
            id: 1,
          },
          {
            label: '临床未确定',
            id: 2,
          },
          {
            label: '情况不明',
            id: 3,
          },
          {
            label: '无',
            id: 4,
          },
        ];
      } else if (val.key == 'AEM01C') {
        val.selectList = [
          {
            label: '全部',
            id: 0,
          },
          {
            label: '医嘱离院',
            id: 1,
          },
          {
            label: '医嘱转院',
            id: 2,
          },
          {
            label: '医嘱转社区卫生服务机构/乡镇卫生院',
            id: 3,
          },
          {
            label: '非医嘱离院',
            id: 4,
          },
          {
            label: '死亡',
            id: 5,
          },
          {
            label: '其他',
            id: 6,
          },
        ];
      } else if (val.key == 'AAC11N') {
        let selectArr = [];
        for (let item in this.departmentList) {
          this.departmentList[item];
          selectArr.push({
            label: this.departmentList[item].name,
            id: this.departmentList[item].id,
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'ABC01N') {
        let selectArr = [];
        for (let item in this.diagnosisList) {
          this.diagnosisList[item];
          selectArr.push({
            label: this.diagnosisList[item],
            id: this.diagnosisList[item],
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'ICD10_NAME_first') {
        let selectArr = [];
        for (let item in this.diagnosisList) {
          this.diagnosisList[item];
          selectArr.push({
            label: this.diagnosisList[item],
            id: this.diagnosisList[item],
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'ABA01N') {
        let selectArr = [];
        for (let item in this.diagnosisList) {
          this.diagnosisList[item];
          selectArr.push({
            label: this.diagnosisList[item],
            id: this.diagnosisList[item],
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'ICD10_NAME') {
        let selectArr = [];
        for (let item in this.diagnosisList) {
          this.diagnosisList[item];
          selectArr.push({
            label: this.diagnosisList[item],
            id: this.diagnosisList[item],
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'AAD01C') {
        let selectArr = [];
        for (let item of text[0].value) {
          selectArr.push({
            label: item.dep_name,
            id: item.dep_id,
          });
        }
        val.selectList = selectArr;
      } //'AAC02C','AAB02C'
      else if (val.key == 'AAC02C') {
        let selectArr = [];
        for (let item of text[0].value) {
          selectArr.push({
            label: item.dep_name,
            id: item.dep_id,
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'AAB02C') {
        let selectArr = [];
        for (let item of text[0].value) {
          selectArr.push({
            label: item.dep_name,
            id: item.dep_id,
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'LNSSQ' || val.key == 'LNSSH') {
        val.selectList = [
          {
            label: '全部',
            id: '0',
          },
          {
            label: '有',
            id: '1',
          },
          {
            label: '无',
            id: '2',
          },
        ];
      } else if (val.key == 'AEL01') {
        val.selectList = [
          {
            label: '全部',
            id: '0',
          },
          {
            label: '有',
            id: '1',
          },
          {
            label: '无',
            id: '2',
          },
        ];
      } else if (['AEE10', 'AEE03', 'AEE04', 'AEE01', 'AEE02', 'ZZYISXM'].includes(val.key)) {
        val.selectList = [
          {
            label: '全部',
            id: '0',
          },
          ...this.doctors,
        ];
      } else if (val.key == 'MO_OPE_LEVEL') {
        let selectArr = [];
        for (let item of text[0].value) {
          selectArr.push({
            label: item.value,
            id: item.key,
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'MO_OPE_TYPE') {
        let selectArr = [];
        for (let item of text[0].value) {
          selectArr.push({
            label: item.value,
            id: item.key,
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'SO_OPE_LEVEL') {
        let selectArr = [];
        for (let item of text[0].value) {
          selectArr.push({
            label: item.value,
            id: item.key,
          });
        }
        val.selectList = selectArr;
      } else if (val.key == 'SO_OPE_TYPE') {
        let selectArr = [];
        for (let item of text[0].value) {
          selectArr.push({
            label: item.value,
            id: item.key,
          });
        }
        val.selectList = selectArr;
      }
      this.$nextTick();
    },
    funRead() {
      //重置
      Object.assign(this.$data.formData1, this.$options.data().formData1);
    },
    funExport(fileName, httpUrl) {
      //查询
      var pramse = {};
      let min = this.formData1.hospitalizationon;
      let max = this.formData1.hospitalizationin;
      if (this.choice == 0) {
        // hospitalizationon: '',
        // hospitalizationin: '',
        pramse = {
          // level: this.formData0.problem || null, //问题属性
          AAA28: this.formData0.recordNum || null, //病案号
          AAC11C: this.formData0.Department || null, //出院科室
          AAA26C: this.formData0.payment || null, //付款方式
          // status: this.formData0.state || null, //编辑状态
          AAC01_start_date: this.formData0.startTime || '',
          AAC01_end_date: this.formData0.endTime || '',
          coder_id: this.formData0.Coder || null, //编码员ID
          // ORG_STATE: this.formData0.medicalRecord || null, //全部病案
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
          is_export: 1,
        };
      } else {
        pramse = {
          AAC04: `${min ? min : 0}-${max ? max : 0}`,
          AAC0401: `${min ? min : 0}`,
          AAC0402: `${max ? max : 0}`,
          AAA04: this.formData1.ageyear || null, //年龄
          AAA40: this.formData1.ageday || null, //不足一周岁年龄
          age_start_type: this.formData1.age_start_type || null,
          age_end_type: this.formData1.age_end_type || null,
          AAC01_start_date: this.formData1.startTime || '',
          AAC01_end_date: this.formData1.endTime || '',

          // 入院时间
          AAB01_start_time: this.formData1.AAB01_startTime || '',
          AAB01_end_time: this.formData1.AAB01_endTime || '',

          rangeName: this.formData1.rangeName || null, //范围类型
          bmStart: this.formData1.bmStart || null, //编码起始范围
          bmEnd: this.formData1.bmEnd || null, //编码结束范围
          field: this.formData1.seniorList || null, //字段条件
          page: this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
          is_export: 1,
        };
      }

      medicalRecordExport(pramse).then(res => {
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `病案列表.csv`;
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
    funExeclPost(fileName, pramse, httpUrl, format) {
      //导出
      let httpUrls = '/api' + httpUrl;
      downloadFile(httpUrls, pramse, format, fileName).then(res => {
        console.error('111', res);
      });
    },

    // 导出
    onExport() {
      medicalRecordExport(params).then(res => {
        console.log('123');
        const content = res.data; // 后台返回二进制数据
        const blob = new Blob([content]);
        const fileName = `门诊病例.csv`;
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
    funGoto(val) {
      this.storageSet('getData', val);
      const { path } = this.$route;
      let toPath;
      if (path === '/hospital-search') {
        toPath = '/hospital-details';
      } else if (path === '/whitelist-search') {
        toPath = '/whitelist-details';
      } else {
        toPath = '/details';
      }
      // status = 1 代表不能复制文本
      // this.$router.push({ path: toPath });
      this.$router.push({ name: 'MedicalRecordNew', query: { zyh: val, type: 'search' } });
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

      // this.formData1.hospitalizationon || '0' + '-' + this.formData1.hospitalizationin || '0',
    },
    funDel(i) {
      let index = i;
      if (index == 0) {
        if (this.formData1.seniorList.length >= 2) {
          this.formData1.seniorList.pop();
        }
        return;
      }
      let list = this.formData1.seniorList;
      list.splice(index, 1);
      this.formData1.seniorList = list;
    },
    funAdd() {
      this.formData1.seniorList.push({
        select_type: 0,
        key: '',
        value: '',
        type: '0',
        selectList: [],
      });
    },
    pageHasChanged() {
      this.funQuery();
    },
    SizeChangeEvent(val) {
      this.paginationData.pageSize = val;
      this.funQuery();
    },
    selectInfo() {
      // let pramse = {};
      this.$axios.post('/selectInfo').then(res => {
        this.payList = res.data.pay;
        //支付方式 pay
        this.departmentList = res.data.department;
        //出院科室 department
        this.levelList = res.data.level;
        //问题属性 level
        // this.coderList = res.data.coder;
        //编码元  coder
        this.statusList = res.data.status;
        this.fieldList = res.data.field;
        // this.department = res.data.department
      });
    },
    selectBmyStaff() {
      this.$axios.get('/selectBmyStaff').then(res => {
        console.error('this.selectBmyStaff', res);
        this.coderList = res.data;
      });
    },
    // 点击检索按钮
    funQuery(num, type = 'normal') {
      // console.error('this.choice111', this.choice);
      let min = this.formData1.hospitalizationon;
      let max = this.formData1.hospitalizationin;
      if (this.labelList.includes(this.labelText)) {
        this.columnShow = true;
      } else {
        this.columnShow = false;
      }
      //查询
      if (this.choice == 0) {
        let pramse = {
          // level: this.formData0.problem || null, //问题属性
          AAA28: this.formData0.recordNum || null, //病案号
          AAC11C: this.formData0.Department || null, //出院科室
          AAA26C: this.formData0.payment || null, //付款方式
          // status: this.formData0.state || null, //编辑状态
          AAC01_start_date: this.formData0.startTime || '',
          AAC01_end_date: this.formData0.endTime || '',
          coder_id: this.formData0.Coder || null, //编码员ID
          // ORG_STATE: this.formData0.medicalRecord || null, //全部病案
          page: num == 1 ? num : this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
        };
        if (this.$route.query.code) {
          pramse.code = this.$route.query.code;
        }
        sessionStorage.setItem('Zkpramse', JSON.stringify(pramse));
        sessionStorage.setItem('ZkChoice', this.choice);
        this.getinfo(pramse, type);
      } else {
        let pramse = {
          AAC04: `${min ? min : 0}-${max ? max : 0}`,
          AAC0401: `${min ? min : 0}`,
          AAC0402: `${max ? max : 0}`,
          AAA04: this.formData1.ageyear || null, //年龄
          AAA40: this.formData1.ageday || null, //不足一周岁年龄
          age_start_type: this.formData1.age_start_type || null,
          age_end_type: this.formData1.age_end_type || null,
          AAC01_start_date: this.formData1.startTime || '',
          AAC01_end_date: this.formData1.endTime || '',

          // 入院时间
          AAB01_start_time: this.formData1.AAB01_startTime || '',
          AAB01_end_time: this.formData1.AAB01_endTime || '',

          field: this.formData1.seniorList || null, //字段条件
          rangeName: this.formData1.rangeName || null, //范围类型
          bmStart: this.formData1.bmStart, //编码起始范围
          bmEnd: this.formData1.bmEnd, //编码结束范围
          page: num == 1 ? num : this.paginationData.currentPage, //页码
          limit: this.paginationData.pageSize, //条数
        };
        if (this.$route.query.code) {
          pramse.code = this.$route.query.code;
        }
        sessionStorage.setItem('Zkpramse', JSON.stringify(pramse));
        sessionStorage.setItem('ZkChoice', this.choice);
        if (num == 1) {
          this.paginationData.currentPage = 1;
        }
        this.getinfo(pramse, type);
      }
    },
    getinfo(p, type) {
      if (this.is_tm_path.includes(this.$route.path)) {
        p.is_tm = 1;
      }
      this.$axios.post('/qualityList', p).then(res => {
        this.paginationData.total = res.data.count;
        this.tableData = res.data.list;
        this.ARG_F_D = res.data.ARG_F_D;
        this.ARG_STAY = res.data.ARG_STAY;
        this.AEM01C = res.data.AEM01C;
        this.SUM_ARG_STAY = res.data.SUM_ARG_STAY;
        this.SUM_ARG_F_D = res.data.SUM_ARG_F_D;

        // 添加滚动到表格的代码

        if (this.tableData.length > 0 && type == 'btn') {
          this.$nextTick(() => {
            this.scrollToTable();
          });
        }
      });
    },
    reset() {
      // 重置数据
      if (this.choice == 0) {
        Object.assign(this.$data.formData0, this.$options.data().formData0);
      } else {
        Object.assign(this.$data.formData1, this.$options.data().formData1);
        this.selectRange = '';
      }
      this.formData1.AAB01_endTime = null;
      this.formData1.AAB01_startTime = null;
      this.funQuery(1);
    },
  },
};
</script>
<style scoped>
::v-deep .red-text {
  color: red;
}

::v-deep .el-transfer-panel {
  width: 350px;
}

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
</style>
<style lang="scss" scoped>
.dashboard-container {
  &::-webkit-scrollbar {
    width: 16px !important;
    height: 16px !important;
  }
}

.custom-dialog {
  border-radius: 10px !important;
}

.inputs {
  // width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.tableBox {
  background: #fff;
  padding: 19px;
  border-radius: 5px;
  font-size: 12px;
}

.block {
  background: #fff;
  border-radius: 5px;
  padding: 20px 30px;
  margin-bottom: 20px;

  .fBtn {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .bnh {
    margin-bottom: 20px;
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

.action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;

  .action-bar-left {
    // 左侧按钮默认居左
  }

  .action-bar-right {
  }
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

.width150 {
  width: 200px;
}

.width300 {
  width: 295px;
}

.width500 {
  width: 645px;
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

.flextabtitle-box {
  display: flex;
  align-items: center;
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

.width100 {
  width: 100px;
}

.tag-box {
  padding: 8px 16px;
  box-sizing: border-box;
  display: inline-block;
  text-align: center;
  border: 1px solid #e2e2e2;
  border-radius: 4px;
}

.long-btn.el-button--primary {
  background: #185da6;
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
