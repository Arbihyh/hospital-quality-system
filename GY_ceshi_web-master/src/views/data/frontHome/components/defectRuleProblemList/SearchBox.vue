<template>
  <div class="search-box">
    <el-form :model="data" class="demo-form-inline" label-suffix=":" label-width="100px">
      <el-row :gutter="20">
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="缺陷问题">
            <el-input v-model="data.desc" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="病案号">
            <el-input v-model="data.AAA28" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="出院时间">
            <el-date-picker
              v-model="data.outTime"
              type="daterange"
              start-placeholder="开始时间"
              end-placeholder="结束时间"
              value-format="yyyyMMdd"
              :picker-options="pickerOptions"
              style="width: 100%"
            ></el-date-picker>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="住院科室">
            <el-select v-model="data.dep_id" filterable clearable virtual-scroll placeholder="全部" style="width: 100%">
              <el-option v-for="item in departmentList" :key="'ks' + item.id" :label="item.name" :value="item.id"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="编码员">
            <el-select v-model="data.AEE08" filterable clearable virtual-scroll placeholder="全部" style="width: 100%">
              <el-option v-for="(item, index) of selectData.doctor" :key="'bmy' + index" :label="item.name" :value="item.id">{{ item.name }}({{ item.id }})</el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="缺陷分类">
            <el-select v-model="data.type" filterable clearable virtual-scroll placeholder="全部" style="width: 100%">
              <el-option v-for="(item, index) of selectData.type" :key="'qxfl' + index" :label="item" :value="item"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="缺陷字段">
            <el-select v-model="data.error_field" filterable clearable virtual-scroll placeholder="全部" style="width: 100%">
              <el-option v-for="item of selectData.error_field" :key="'qxzd' + item.auth" :label="item.field" :value="item.auth"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="是否强制">
            <el-select v-model="data.level" filterable clearable placeholder="全部" style="width: 100%">
              <el-option label="强制" :value="0"></el-option>
              <el-option label="建议" :value="1"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="主手术名称">
            <el-input v-model="data.ICD9_NAME" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="主手术编号">
            <el-input v-model="data.ICD9_ID1" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="主诊断名称">
            <el-input v-model="data.ICD10_NAME" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="主诊断编码">
            <el-input v-model="data.ICD10_ID1" clearable placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="质控时间">
            <el-date-picker
              v-model="data.controlTime"
              type="daterange"
              start-placeholder="开始时间"
              end-placeholder="结束时间"
              value-format="yyyyMMdd"
              :picker-options="pickerOptions"
              style="width: 100%"
            ></el-date-picker>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item label="是否修改">
            <el-select v-model="data.is_edit" filterable clearable placeholder="全部" style="width: 100%">
              <el-option label="是" :value="1"></el-option>
              <el-option label="否" :value="2"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :xl="6" :lg="8" :md="8" :sm="8" :xs="12">
          <el-form-item>
            <el-button type="primary" @click="onSubmit" class="export-btn" icon="el-icon-search">查询</el-button>
            <el-button plain @click="onReset" icon="el-icon-refresh">重置</el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
  </div>
</template>

<script>
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {};
      },
    },
  },
  data() {
    return {
      selectData: {
        type: [],
        level: [],
        error_field: []
      },
      pickerOptions: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
      },
      departmentList: [],
    };
  },
  created() {
    this.getDepartment();
    this.getSelectData();
  },
  methods: {
    getDepartment() {
      this.$axios.post('/omr_zk/department_list').then(res => {
        // 不要全部选项
        this.departmentList = res.data;
      });
    },
    getSelectData() {
      this.$axios.post('/home_quality/getErrorSerachWhere').then(res => {
        this.selectData = res.data;
      });
    },
    onSubmit() {
      this.$emit('search');
    },
    onReset() {
      this.$emit('reset');
    },
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-form-item {
  height: 40px;
}
</style>