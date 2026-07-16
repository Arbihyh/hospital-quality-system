<template>
  <div class="part-box" style="margin-bottom: 20px;">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <!-- <el-form-item label="医院名称" v-if="this.data.hospital_name">
        <el-select v-model="data.hospital_name" filterable class="selects" placeholder="医院名称">
          <el-option v-for="(item, index) in HospitalList" :label="item" :value="item" :key="index"></el-option>
        </el-select>
      </el-form-item> -->
      <el-row :gutter="24">
        <el-col :span="8">
          <el-form-item label="质控时间">
            <el-date-picker
              v-model="data.zk_start_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="质控开始时间"
              :picker-options="pickerOptions"
              style="margin-right: 8px; width: 200px;"
            />
            <el-date-picker
              v-model="data.zk_end_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="质控结束时间"
              :picker-options="pickerOptions"
              style="width: 200px;"
            />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="出院时间">
            <el-date-picker
              v-model="data.start_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="出院开始时间"
              :picker-options="pickerOptions"
              style="width: 200px; margin-right: 8px"
            />
            <el-date-picker
              v-model="data.end_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="出院结束时间"
              :picker-options="pickerOptions"
              style="width: 200px"
            />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="入院时间">
            <el-date-picker
              v-model="data.ry_start_time"
              type="date"
              format="yyyy年MM月dd日"
              value-format="yyyyMMdd"
              placeholder="入院开始时间"
              :picker-options="pickerOptions"
              style="margin-right: 8px; width: 200px;"
            />
            <el-date-picker v-model="data.ly_end_time" type="date" format="yyyy年MM月dd日" value-format="yyyyMMdd" placeholder="入院结束时间" :picker-options="pickerOptions"
              style="width: 200px" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="出院科室">
            <el-select v-model="data.cykb" filterable clearable placeholder="请选择" style="width: 410px; max-width: 100%;">
              <el-option v-for="(item, index) in departmentList" :label="item" :value="item" :key="index"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="缺陷描述">
            <el-input v-model="data.desc" clearable placeholder="请输入" style="width: 410px; max-width: 100%;"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="缺陷分级">
            <el-select v-model="data.level" filterable clearable placeholder="请选择" style="width: 410px; max-width: 100%;">
              <el-option v-for="item of selectData.level" :key="item" :label="item" :value="item" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="缺陷归类">
            <el-select v-model="data.type" filterable clearable placeholder="请选择" style="width: 410px; max-width: 100%;">
              <el-option v-for="item of selectData.type" :key="item" :label="item" :value="item" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="缺陷字段">
            <el-input v-model="data.field" clearable placeholder="请输入" style="width: 410px; max-width: 100%;"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="住院号码">
            <el-input v-model="data.zyhm" clearable placeholder="请输入" style="width: 410px; max-width: 100%;"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8" style="width: 512px; float: right; margin-right: 24px;">
          <el-form-item style="float: right;">
            <el-button type="primary" @click="onSubmit">查询</el-button>
            <el-button @click="onReset">重置</el-button>
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
        return {
          hospital_name: '',
          start_time: '',
          end_time: '',
          level: '',
          type: '',
          desc: '',
          field: '',
          zk_start_time: '',
          zk_end_time: '',
          ry_start_time: '',
          ly_end_time: '',
          cykb: '',
        };
      },
    },
    type_name: {
      // 'lc' 临床
      type: String,
      default() {
        return '';
      },
    },
  },
  data() {
    return {
      selectData: {},
      HospitalList: [],
      pickerOptions: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
      },
      departmentList: [],
    };
  },
  created() {
    this.getSelectData();
    this.getDepList();
    if (this.data.hospital_name) {
      // 获取医院名称
      this.getHospitalList();
    }
  },
  methods: {
    onReset() {
      this.$emit('reset');
    },
    getDepList() {
      this.$axios.post('/home_sz_quality/getKsList').then(res => {
        this.departmentList = res.data;
      });
    },
    getSelectData() {
      this.$axios.post('/home_quality/getErrorSerachWhere').then(res => {
        this.selectData = res.data;
      });
    },
    // 获取医院名称
    getHospitalList() {
      let url = '';
      if (this.type_name == 'lc') {
        url = '/home_sz_quality/getHospitalList';
      } else {
        url = '/home_quality/getHospitalList';
      }
      this.$axios.post(url).then(res => {
        this.HospitalList = res.data;
      });
    },
    onSubmit() {
      this.$emit('search');
    },
  },
};
</script>

<style lang="scss" scoped>
.demo-form-inline {
  text-align: center;
}
::v-deep .el-col-8 {
  // width: auto;
  // min-width: 410px !important;
}
</style>
