<template>
  <el-dialog :visible.sync="dialogVisible" class="custom-dialog" :modal="false" :title="getDialogTitle()" width="740px" @close="onCancel">
    <el-form :model="formData" :rules="rules" ref="formDataRef" label-width="120px" :disabled="action == 'DETAIL'">
      <el-form-item label="计划名称" prop="title" required>
        <el-input style="width: 87%; display: flex; gap: 5px" v-model="formData.title" placeholder="请输入"></el-input>
      </el-form-item>

      <el-form-item label="单元分配" required>
        <div style="width: 100%; display: flex; gap: 5px">
          <el-form-item prop="unit">
            <el-radio-group v-model="formData.unit">
              <el-radio :label="1">科室</el-radio>
              <el-radio :label="2">病区</el-radio>
              <!-- <el-radio :label="3">诊疗组</el-radio> -->
            </el-radio-group>
          </el-form-item>
          <el-form-item prop="nums" label="随机分配">
            <el-input style="width: 50%" v-model="formData.nums" type="number" min="1" step="1" placeholder="">
              <template slot="append">份病历</template>
            </el-input>
          </el-form-item>
        </div>
      </el-form-item>
      <el-form-item label="出院时间">
        <div style="width: 100%; display: flex; gap: 5px">
          <el-form-item prop="AAC01_start_time">
            <el-date-picker
              style="width: 100%"
              v-model="formData.AAC01_start_time"
              type="date"
              placeholder="请选择开始时间"
              :picker-options="cysjPickerOptions"
              value-format="yyyyMMdd"
              format="yyyy年MM月dd日"
            ></el-date-picker>
          </el-form-item>
          <el-form-item prop="AAC01_end_time">
            <el-date-picker
              style="width: 100%"
              v-model="formData.AAC01_end_time"
              type="date"
              placeholder="请选择结束时间"
              value-format="yyyyMMdd"
              format="yyyy年MM月dd日"
            ></el-date-picker>
          </el-form-item>
        </div>
      </el-form-item>
      <el-form-item label="计划时间">
        <div style="width: 100%; display: flex; gap: 5px">
          <el-form-item prop="start_time">
            <el-date-picker
              style="width: 100%"
              v-model="formData.start_time"
              type="date"
              placeholder="请选择开始时间"
              :picker-options="jhsjPickerOptions"
              value-format="yyyyMMdd"
              format="yyyy年MM月dd日"
            ></el-date-picker>
          </el-form-item>
          <el-form-item prop="end_time">
            <el-date-picker
              style="width: 100%"
              v-model="formData.end_time"
              type="date"
              placeholder="请选择结束时间"
              value-format="yyyyMMdd"
              format="yyyy年MM月dd日"
            ></el-date-picker>
          </el-form-item>
        </div>
      </el-form-item>
    </el-form>

    <template #footer >
      <el-button v-if="action != 'DETAIL'" type="primary" @click="onSubmit">发 布</el-button>
    </template>
  </el-dialog>
</template>
<script>
import moment from 'moment/moment';
import SubTitle from './subTitle.vue';
import AssignDoctor from './AssignDoctor.vue';
export default {
  emits: ['onUpdate'],
  components: {
    SubTitle,
    AssignDoctor,
  },
  data() {
    const that = this;
    return {
      action: 'ADD',
      dialogVisible: false,
      formData: {
        id: '',
        title: '', //计划时间
        type: '1',
        unit: 1, //单元分配
        nums: '1', //随机分配
        is_random: 1,
        AAC01_start_time: '', //出院开始时间
        AAC01_end_time: '', //出院结束时间
        start_time: '', // 计划开始时间
        end_time: '', // 计划结束时间
      },
      rules: {
        title: [{ required: true, message: '请输入' }],
        unit: [{ required: true, message: '请选择' }],
      },
      cysjPickerOptions: {
        disabledDate: time => {
          if (that.formData.AAC01_end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.AAC01_end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
      jhsjPickerOptions: {
        disabledDate: time => {
          if (that.formData.end_time != '') {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [
          {
            text: '今天',
            onClick(picker) {
              picker.$emit('pick', moment().format('YYYYMMDD'));
              that.formData.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近7天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
              that.formData.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '近30天',
            onClick(picker) {
              picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
              that.formData.end_time = moment().format('YYYYMMDD');
            },
          },
          {
            text: '一季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.end_time = moment().startOf('year').add(3, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '二季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(3, 'M').format('YYYYMMDD'));
              that.formData.end_time = moment().startOf('year').add(6, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '三季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(6, 'M').format('YYYYMMDD'));
              that.formData.end_time = moment().startOf('year').add(9, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: '四季度',
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').add(9, 'M').format('YYYYMMDD'));
              that.formData.end_time = moment().startOf('year').add(12, 'M').subtract(1, 'days').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-2, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-2, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.end_time = moment().add(-2, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().add(-1, 'Y').format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().add(-1, 'Y').startOf('year').format('YYYYMMDD'));
              that.formData.end_time = moment().add(-1, 'Y').endOf('year').format('YYYYMMDD');
            },
          },
          {
            text: moment().format('YYYY'),
            onClick(picker) {
              picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
              that.formData.end_time = moment().endOf('year').format('YYYYMMDD');
            },
          },
        ],
      },
    };
  },
  computed: {},
  watch: {},
  created() {},
  mounted() {},
  beforeDestroy() {},
  watch: {},
  methods: {
    formatTime(timeValue) {
      if (!timeValue || timeValue === '-') {
        return '';
      }
      let timestamp;

      if (typeof timeValue === 'string' && /^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/.test(timeValue)) {
        timestamp = moment(timeValue).unix();
      } else {
        timestamp = typeof timeValue === 'string' ? parseInt(timeValue, 10) : timeValue;
      }
      if (!timestamp || timestamp <= 0) {
        return '';
      }
      return moment(timestamp * 1000).format('YYYYMMDD');
    },
    getDialogTitle() {
      if (this.action === 'ADD') {
        return '质控计划配置';
      }
      if (this.action === 'EDIT') {
        return '质控计划配置';
      }
      if (this.action === 'DETAIL') {
        return '质控计划配置';
      }
    },

    async openModal(action = 'ADD', params) {
      this.dialogVisible = true;
      this.$nextTick(() => {
        this.action = action;
        if (action == 'EDIT' || action == 'DETAIL') {
          this.$refs.formDataRef && this.$refs.formDataRef.resetFields();
          for (let key in this.formData) {
            if (['start_time', 'end_time', 'AAC01_start_time', 'AAC01_end_time'].includes(key)) {
              continue;
            }
            this.formData[key] = params[key] || '';
          }
          this.formData.start_time = params.start_time ? this.formatTime(params.start_time) : '';
          this.formData.end_time = params.end_time ? this.formatTime(params.end_time) : '';
          this.formData.AAC01_start_time = params.AAC01_start_time ? this.formatTime(params.AAC01_start_time) : '';
          this.formData.AAC01_end_time = params.AAC01_end_time ? this.formatTime(params.AAC01_end_time) : '';
        }
      });
    },

    onSubmit() {
      this.$refs.formDataRef.validate(valid => {
        if (valid) {
          const params = { ...this.formData };
          this.$axios
            .post('/case_quality_plan/save', params)
            .then(res => {
              if (res.code == 200) {
                this.$message({
                  message: '配置成功',
                  type: 'success',
                });
                this.dialogVisible = false;
                this.$emit('onUpdate');
              }
            })
            .catch(error => {
              this.$message.error('新增失败');
              this.$emit('onUpdate');
            });
        } 
      });
    },
    onCancel() {
      this.dialogVisible = false;
      this.$refs.formDataRef && this.$refs.formDataRef.resetFields();
    },
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-dialog__header {
  background-color: hsl(205.32deg 43.43% 49.22%);
}

::v-deep .el-dialog__close {
  color: #fff;
  border: 1px solid #fff;
  border-radius: 20px;
}

::v-deep .el-dialog__title {
  color: #fff;
}
</style>
