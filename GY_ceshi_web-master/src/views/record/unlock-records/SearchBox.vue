<template>
  <div class="search-box">
    <el-form
      style="width: 100%"
      ref="filterListFormRef"
      :model="formData"
      class="demo-form-inline"
      label-suffix=":"
      label-width="74px"
    >
      <el-row :gutter="24">
        <el-col :span="6">
          <el-form-item label="病案号" prop="medical_record_no">
            <el-input v-model="formData.medical_record_no" placeholder="请输入"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="出院科室" prop="discharge_department">
            <el-select
              style="width: 100%"
              v-model="formData.discharge_department"
              filterable
              clearable
              multiple
              collapse-tags
              placeholder="请选择"
            >
              <el-option
                v-for="item of departments"
                :key="item.dep_id"
                :label="item.name"
                :value="item.dep_id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="审核状态" prop="audit_status">
            <el-select
              style="width: 100%"
              v-model="formData.audit_status"
              filterable
              clearable
              placeholder="请选择"
            >
              <el-option label="审核中" :value="0" />
              <el-option label="通过" :value="1" />
              <el-option label="驳回" :value="2" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="是否在院" prop="is_in_hospital">
            <el-select
              style="width: 100%"
              v-model="formData.is_in_hospital"
              filterable
              clearable
              placeholder="请选择"
            >
              <el-option label="是" :value="1" />
              <el-option label="否" :value="0" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row>
        <el-col :span="8">
          <el-form-item label="解锁时间">
            <div style="display: flex; gap: 10px;">
              <el-date-picker
                v-model="formData.unlock_start_time"
                type="date"
                :picker-options="unlockPickerOptions"
                placeholder="开始日期"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
              <el-date-picker
                v-model="formData.unlock_end_time"
                type="date"
                :picker-options="[]"
                placeholder="结束日期"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
            </div>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="出院日期">
            <div style="display: flex; gap: 10px;">
              <el-date-picker
                v-model="formData.discharge_start_time"
                type="date"
                :picker-options="dischargePickerOptions"
                placeholder="开始日期"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
              <el-date-picker
                v-model="formData.discharge_end_time"
                type="date"
                :picker-options="[]"
                placeholder="结束日期"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
            </div>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="入院日期">
            <div style="display: flex; gap: 10px;">
              <el-date-picker
                v-model="formData.admission_start_time"
                type="date"
                :picker-options="admissionPickerOptions"
                placeholder="开始日期"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
              <el-date-picker
                v-model="formData.admission_end_time"
                type="date"
                :picker-options="[]"
                placeholder="结束日期"
                value-format="yyyyMMdd"
                format="yyyy年MM月dd日"
              />
            </div>
          </el-form-item>
        </el-col>
      </el-row>

      <el-row>
        <el-col :span="24">
          <el-form-item label prop style="float: right">
            <el-button type="primary" @click="onSubmit">查询</el-button>
            <el-button @click="onReset">重置</el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
  </div>
</template>
<script>
import moment from 'moment/moment';

export default {
  emits: ['search', 'reset'],
  props: {
    qualityType: {
      type: String,
      default: '1',
    },
  },
  data() {
    const that = this;
    return {
      formData: {
        medical_record_no: '',
        discharge_department: '',
        audit_status: 0,
        is_in_hospital: '',
        unlock_start_time: '',
        unlock_end_time: '',
        admission_start_time: '',
        admission_end_time: '',
        discharge_start_time: '',
        discharge_end_time: '',
      },
      departments: [],
      unlockPickerOptions: {
        disabledDate: time => {
          if (that.formData.unlock_end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.unlock_end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      admissionPickerOptions: {
        disabledDate: time => {
          if (that.formData.admission_end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.formData.admission_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.admission_end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      dischargePickerOptions: {
        disabledDate: time => {
          if (that.formData.discharge_end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.discharge_end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
    };
  },
  created() {
    this.getDeportmentList();
  },
  methods: {
    onSubmit() {
      this.$emit('search');
    },
    onReset() {
      this.$refs.filterListFormRef.resetFields();

      this.$emit('reset');
    },
    getDeportmentList() {
      this.$axios
        .get('/user/depDropDown')
        .then(res => {
          const { data } = res;
          this.departments = data;
        })
        .catch(error => {
          console.log(error);
        });
    },
  },
};
</script>

<style lang="scss" scoped></style>
