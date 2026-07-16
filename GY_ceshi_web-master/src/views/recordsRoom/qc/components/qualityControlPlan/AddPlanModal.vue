<template>
    <el-dialog :visible.sync="dialogVisible" class="custom-dialog" :modal="false" :title="getDialogTitle()" width="85%" @close="onCancel">
        <el-row type="flex" align="middle" style="width: 100%;margin-bottom: 10px" v-if="action != 'DETAIL'">
          <el-steps :active="activeSteps" finish-status="success" align-center style="width: inherit">
            <el-step title="基础配置" description=""></el-step>
            <el-step title="授权病历" description=""></el-step>
            <el-step title="分配医师" description=""></el-step>
          </el-steps>
          <el-button type="primary" @click="onSubmit">生成计划</el-button>
        </el-row>
        <el-form :model="formData"
            :rules="rules" ref="formDataRef" label-width="120px" :disabled="action == 'DETAIL'">
            <SubTitle title="基础配置"/>
            <el-form-item label="质控计划名称" prop="zkjhmc">
                <el-input style="max-width: 430px" v-model="formData.zkjhmc" placeholder="请输入"></el-input>
            </el-form-item>
            <el-form-item label="质控计划周期" required>
              <div style="width: 100%;display: flex;gap: 5px;">
                <el-form-item prop="zkjhzq_kssj">
                  <el-date-picker style="width: 100%" v-model="formData.zkjhzq_kssj" type="date" placeholder="开始日期"
                    :picker-options="pickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
                  </el-date-picker>
                </el-form-item>
                <el-form-item prop="zkjhzq_jssj">
                  <el-date-picker style="width: 100%" v-model="formData.zkjhzq_jssj" type="date" placeholder="结束日期"
                    value-format="yyyyMMdd" format="yyyy年MM月dd日">
                  </el-date-picker>
                </el-form-item>
              </div>
            </el-form-item>
            <SubTitle title="授权病历"/>
            <el-form-item label="质控病例数量" required>
              <div style="width: 100%;display: flex;gap: 5px;">
                <el-form-item prop="zkblsllx">
                  <el-radio-group v-model="formData.zkblsllx">
                    <el-radio :label="1">全部</el-radio>
                    <el-radio :label="0">自定义</el-radio>
                  </el-radio-group>
                </el-form-item>
                <el-form-item v-if="this.formData.zkblsllx != 1" prop="blslnum" label="病历数量">
                  <el-input style="width: 100%" v-model="formData.blslnum" placeholder="请输入">
                    <template slot="append">份</template>
                  </el-input>
                </el-form-item>
              </div>
            </el-form-item>
        </el-form>
        <SubTitle title="分配医师"/>
          <AssignDoctor :action="action" @onListDataChange="(e) => this.formData.fpysList = e"/>
        <template #footer>
        </template>
    </el-dialog>
</template>
<script>
import moment from 'moment/moment';
import { examineAppeal } from '@/api/qc';
import SubTitle from './subTitle.vue'
import AssignDoctor from './AssignDoctor.vue'
export default {
  emits: ['onUpdate'],
  components: {
    SubTitle,
    AssignDoctor
  },
  data() {
    const that = this;
    return {
        action: 'ADD',
      dialogVisible: false,
      formData: {
        zkjhmc: '',
        zkjhzq_kssj: '',
        zkjhzq_jssj: '',
        zkblsllx: 0,
        blslnum: '',
        fpysList: []
      },
      rules: {
        zkjhmc: [{ required: true, message: '请输入' }],
        zkjhzq_kssj: [{ required: true, message: '请选择' }],
        zkjhzq_jssj: [{ required: true, message: '请选择' }],
        zkblsllx: [{ required: true, message: '请选择' }],
        blslnum: [{ required: true, message: '请输入' }],
      },
      pickerOptions: {
        disabledDate: (time) => {
          if (that.formData.zkjhzq_jssj != "") {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [{
          text: '今天',
          onClick(picker) {
            picker.$emit('pick', moment().format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().format('YYYYMMDD')
          }
        }, {
          text: '近7天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().format('YYYYMMDD')
          }
        }, {
          text: '近30天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().format('YYYYMMDD')
          }
        }, {
          text: '一季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().startOf('year').add(3,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3,'M').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().startOf('year').add(6,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6,'M').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().startOf('year').add(9,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9,'M').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().startOf('year').add(12,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().add(-2,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().add(-1,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.zkjhzq_jssj = moment().endOf('year').format('YYYYMMDD')
          }
        }]
      },
      activeSteps: 0,
    };
  },
  computed: {},
  watch: {},
  created() {},
  mounted() {},
  beforeDestroy() {},
  watch: {
    formData: {
      handler(newVal) {
        const { zkjhmc, zkjhzq_kssj, zkjhzq_jssj, zkblsllx, blslnum, fpysList} = newVal;
        const step1Finished = zkjhmc && zkjhzq_kssj && zkjhzq_jssj 
        const step2Finished = zkblsllx == 1 ? true : blslnum
        const step3Finished = Array.isArray(fpysList) && !!fpysList.length
        console.log('>>>>>>, step1Finished', newVal, step1Finished)
        console.log('>>>>>>, step2Finished', step2Finished)
        console.log('>>>>>>, step3Finished', step3Finished)

        
        if(step1Finished) {
          this.activeSteps = 1
        }
        if(step1Finished && step2Finished) {
          this.activeSteps = 2
        }
        if(step1Finished && step2Finished && step3Finished) {
          this.activeSteps = 3
        }
        if(!step1Finished){
          this.activeSteps = 0
        }
        console.log('>>>>>>>', this.activeSteps)
      },
      deep: true // 深度监听对象内部属性的变化
    }
  },
  methods: {
    getDialogTitle() {
      if(this.action === 'ADD') {
        return '新建质控计划配置'
      }
      if(this.action === 'EDIT') {
        return '编辑质控计划配置'
      }
      if(this.action === 'DETAIL') {
        return '质控计划配置详情'
      }
    },
        
    async openModal(action = 'ADD', params) {
      this.dialogVisible = true;
      this.$nextTick(() => {
        this.action = action;
        if(action == 'EDIT') {
          for(let keys in this.formData) {
              this.formData[keys] = params[keys]
          }
        }
      })
    },
    
    onSubmit() {
      this.$refs.formDataRef.validate((valid) => {
        if (valid) {
            const params = {...this.formData}
            console.log('>>>>>>>>>>>>params', params)
            this.$emit('onUpdate')
            this.onCancel();
            
            return
            // 申诉逻辑
            const pramse = {
              id: this.currentId,
              zyh: this.MEDRECID,
              type: 2,
              quality_type: this.qualityType,
              defect_content: this.formData.reason, // 使用 formData 中的原因
              appeal_docter: this.formData.zkjhmc, // 使用 formData 中的医师
            };
            console.log(pramse, 'pramse');

            examineAppeal(pramse).then(res => {
              if(res.code == 200) {
                this.$message({
                  message: '提交申诉成功',
                  type: 'success',
                });
                // 提交后关闭弹框
                this.dialogVisible = false;
                this.$emit('onUpdate')
              }
            });
          // 申诉审核通过和驳回逻辑
        
        } else {
          console.log('error submit!!');
          return false;
        }
      });
      
    },
    onCancel() {
        this.dialogVisible = false;
        this.$refs.formDataRef && this.$refs.formDataRef.resetFields()
        this.activeSteps = 0
    }
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
