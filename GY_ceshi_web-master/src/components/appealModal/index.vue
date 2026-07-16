<template>
  <el-dialog
    :visible.sync="dialogVisible"
    class="custom-dialog"
    :modal="false"
    :title="getDialogTitle()"
    :width="dialogWidth"
  >
    <div class="medical-record" style="margin-top: 30px">
      <el-descriptions>
        <el-descriptions-item label="病案号">{{ baseInfo.AAA28 }}</el-descriptions-item>
        <el-descriptions-item label="床号">{{ baseInfo.CH }}</el-descriptions-item>
        <el-descriptions-item label="患者姓名">{{ baseInfo.BRXM }}</el-descriptions-item>
      </el-descriptions>
    </div>
    <div class="reason" style="margin-top: 20px">
      <el-form
        :disabled="dialogType == 'appeal_yes' || dialogType == 'appeal_no'"
        :model="appealForm"
        :rules="appealRules"
        ref="appealFormRef"
        label-width="80px"
      >
        <el-form-item :label="`${getDialogTitle()}医师`" prop="doctor">
          <el-select
            v-if="$route.query.from === 'review'"
            style="width:100%"
            :disabled="dialogType === 'appeal_ing'"
            v-model="appealForm.doctor"
            filterable
            clearable
            :placeholder="`请选择${getDialogTitle()}医师姓名及工号`"
          >
            <el-option
              v-for="item of staffList"
              :key="item.code"
              :label="`${item.name}`"
              :value="`${item.code}`"
              filterable
            />
          </el-select>

          <!-- <el-input v-else :disabled="dialogType === 'appeal_ing'" v-model="appealForm.doctor" :placeholder="`请输入${getDialogTitle()}医师姓名及工号`"></el-input> -->
          <el-input
            v-else
            :disabled="dialogType === 'appeal_ing'"
            v-model="appealForm.doctor"
            :placeholder="`申诉人姓名及科室`"
          ></el-input>
        </el-form-item>
        <el-form-item :label="`${getDialogTitle()}原因`" prop="reason">
          <el-input
            :disabled="dialogType === 'appeal_ing'"
            type="textarea"
            v-model="appealForm.reason"
            :placeholder="`请输入${getDialogTitle()}原因`"
            :rows="2"
          ></el-input>
        </el-form-item>
      </el-form>
    </div>
    <div class="cont-reight-bottom-conter" style="color: #606266 !important">
      <p>
        <span class="bold" style="color: #606266 !important">字段：</span>
        {{ dialogFormsLabel.zd_field_name }}
      </p>
      <p style="margin-top: 10px">
        <span class="bold" style="color: #606266 !important">提示：</span>
        {{ dialogFormsLabel.ts_desc }}
      </p>
    </div>
    <template #footer v-if="dialogType != 'appeal_yes' && dialogType != 'appeal_no'">
      <div
        class="dialog-footer-container"
        :class="{ 'justify-end': ( qualityType != 3) || (( qualityType == 3) && dialogType != 'appeal_ing') }"
      >
        <div
          class="btn-left"
          v-if="pageType !='single' && ((qualityType === 1 || qualityType === 2) && dialogType === 'appeal_ing')"
        >
          <div class="appeal_in_yes" @click="submitAppealHandle('appeal_in_yes', 1)">通过</div>
          <div class="appeal_in_no" @click="submitAppealHandle('appeal_in_no', 1)">驳回</div>
        </div>
        <div class="btn-left" v-if="pageType === 'single'"></div>

        <el-button
          v-if="dialogType != 'appeal_ing'"
          class="btn-right"
          type="primary"
          :style="{
          backgroundColor: `${dialogType == 'appeal_in_no' ? '#ef1f3a' : dialogType == 'appeal_in_no' ? '#1b64b0' : ''}`,
        }"
          @click="submitAppeal()"
        >{{ getDialogTitle() }}</el-button>
      </div>
    </template>
  </el-dialog>
</template>
<script>
import { examineAppeal, getAppealData, getBrry, getStaffListData } from '@/api/qc';
export default {
  emits: ['onUpdate'],
  data() {
    return {
      pageType: 'normal',
      dialogVisible: false,
      staffList: [], // 主治医师
      dialogType: '',
      row: {},
      appealForm: {
        reason: '',
        phone: '',
      },
      // appealRules: {
      //   doctor: [{ required: true, message: '请输入' }],
      //   reason: [{ required: true, message: '请输入' }],
      // },
      currentId: '',
      MEDRECID: '',
      dialogFormsLabel: {
        zd_field_name: '',
        ts_desc: '',
      },
      qualityType: '',
      baseInfo: {},
      dialogWidth: '30%', // 默认宽度
    };
  },
  computed: {
    appealRules() {
      return {
        // doctor: [{ required: true, message: '请输入' }],
        doctor: [
          { required: true, message: '请输入医师姓名，便于后续沟通', trigger: 'blur' },
          {
            validator: (rule, value, callback) => {
              console.log('value', value);
              console.log('rule', rule);
              const reg = /(?=.*[\u4e00-\u9fa5]{2,})/;
              if (value && !reg.test(value) && this.$route.query.from != 'review') {
                callback(new Error('输入内容至少需要两个汉字'));
              } else {
                callback();
              }
            },
            trigger: 'blur',
          },
        ],
        reason: [
          {
            required: !(this.dialogType === 'appeal_in_yes' || this.dialogType === 'appeal_yes'),
            message: `请输入${this.getDialogTitle()}原因`,
          },
        ],
      };
    },
  },
  watch: {},
  created() {},
  mounted() {
    const { ZYH, id } = this.$route.query;
    this.MEDRECID = this.$route.path === '/whitelist-caseControl' || this.$route.path === '/whitelist-qualityResults' ? id : ZYH;
    this.setDialogWidth();
    window.addEventListener('resize', this.setDialogWidth);
    this.getstaffList();
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.setDialogWidth);
  },

  methods: {
    setDialogWidth() {
      const width = window.innerWidth < 768 ? '80%' : '30%'; // 根据屏幕宽度调整宽度
      this.dialogWidth = width;
    },
    getDialogTitle() {
      if (this.dialogType === 'appeal') {
        return '申诉';
      }
      if (this.dialogType === 'appeal_in_yes' || this.dialogType === 'appeal_yes') {
        if (this.pageType != 'single' && (this.qualityType === 1 || this.qualityType === 2)) {
          return '审核';
        }
        return '通过';
      }
      if (this.dialogType === 'appeal_in_no' || this.dialogType === 'appeal_no') {
        return '驳回';
      }
      if (this.dialogType === 'appeal_ing' || this.dialogType === 'appeal_ing') {
        return '申诉';
      }
    },

    getBaseInfo() {
      getBrry({ zyh: this.MEDRECID }).then(res => {
        if (res.code == 200) {
          this.baseInfo = res.data || {};
        }
      });
    },

    openAppealDialog(type, items, quality_type, pageType = 'normal') {
      this.pageType = pageType;
      this.row = items;
      console.log('items', type, items, quality_type);
      this.dialogFormsLabel.zd_field_name = items.error_name || items.field_name || items.error_field;
      this.dialogFormsLabel.ts_desc = quality_type == 2 ? items.notice : items.desc;
      this.dialogType = type;
      this.currentId = type === 'appeal' ? items.rule_id : items.appeal_id;
      this.qualityType = quality_type;
      // 初始化 appealForm 的值
      this.appealForm = {
        doctor: localStorage.getItem('realname'),
        reason: '',
      };
      if (type === 'appeal_yes' || type === 'appeal_no' || type === 'appeal_ing') {
        const params = {
          id: items.rule_id || items.error_rule,
          cate: items.cate,
          ZYH: this.MEDRECID,
        };
        getAppealData(params).then(res => {
          if (type === 'appeal_ing') {
            this.appealForm.doctor = res.data.appeal_docter; // 申诉医师
            this.appealForm.reason = res.data.defect_content; // 申诉原因
          } else {
            this.appealForm.doctor = res.data.case_docter; // 驳回医师
            this.appealForm.reason = res.data.reject_content; // 驳回原因
          }
        });
      }
      this.dialogVisible = true;
      this.getBaseInfo();
    },
    submitAppealHandle(type, quality_type) {
      this.getDialogTitle();
    
      console.log("submitAppealHandle",type, this.qualityType, quality_type,this.pageType);
      this.openAppealDialog(type, this.row, this.qualityType);
    },

    submitAppeal() {
      this.$refs.appealFormRef.validate(valid => {
        if (valid) {
          if (this.dialogType === 'appeal') {
            // 申诉逻辑
            const pramse = {
              id: this.currentId,
              zyh: this.MEDRECID,
              type: 2,
              quality_type: this.qualityType,
              defect_content: this.appealForm.reason, // 使用 appealForm 中的原因
              appeal_docter: this.appealForm.doctor, // 使用 appealForm 中的医师
            };

            examineAppeal(pramse).then(res => {
              if (res.code == 200) {
                this.$message({
                  message: '提交申诉成功',
                  type: 'success',
                });
                // 提交后关闭弹框
                this.dialogVisible = false;
                this.$emit('onUpdate');
              }
            });
          }
          // 申诉审核通过和驳回逻辑
          if (this.dialogType === 'appeal_in_yes' || this.dialogType === 'appeal_in_no') {
            const params = {
              id: this.currentId,
              type: this.qualityType,
              status: this.dialogType === 'appeal_in_yes' ? 1 : 2,
              reject_content: this.appealForm.reason, // 使用 appealForm 中的原因
              case_docter: this.appealForm.doctor, // 使用 appealForm 中的医师
            };
            this.$axios.post('/examineCaseAppeal', params).then(res => {
              if (res.code == 200) {
                this.$message({
                  message: `申诉${this.getDialogTitle()}成功`,
                  type: 'success',
                });
                // 提交后关闭弹框
                this.dialogVisible = false;
                this.$emit('onUpdate');
              } else {
                this.$message({
                  message: res.msg,
                  type: 'error',
                });
              }
            });
          }
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },
    getstaffList() {
      getStaffListData().then(res => {
        const { data = [] } = res;
        this.staffList = data;
      });
    },
    handleIgnore(items, quality_type) {
      const pramse = {
        id: items.rule_id,
        type: 1,
        quality_type: quality_type,
        quality_type,
        zyh: this.MEDRECID,
      };

      examineAppeal(pramse).then(res => {
        this.$message({
          message: '忽略成功',
          type: 'success',
        });
        this.$emit('onUpdate');
      });
    },
  },
  watch: {
    dialogVisible(val) {
      this.$refs.appealFormRef && this.$refs.appealFormRef.resetFields();
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

::v-deep .el-descriptions-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}

::v-deep .el-descriptions-item__label {
  font-weight: bold;
  font-size: 15px;
  white-space: nowrap;
}

::v-deep .el-descriptions-item__content {
  white-space: nowrap;
}

.dialog-footer-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;

  &.justify-end {
    justify-content: flex-end;
  }
}

.btn-left {
  display: flex;
  gap: 10px;
}
</style>
