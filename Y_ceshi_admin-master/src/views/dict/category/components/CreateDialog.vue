<template>
  <el-dialog
    :title="titleStr"
    :visible.sync="data.bSwitch"
    width="40%"
  >
    <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="140px" class="demo-ruleForm">
      <el-form-item label="手术操作编码" prop="ssbm">
        <el-input v-model="ruleForm.ssbm" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="手术操作名称" prop="ssmc">
        <el-input v-model="ruleForm.ssmc" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="操作类型" prop="sslb">
        <el-select v-model="ruleForm.sslb" filterable clearable placeholder="请选择">
          <el-option label="介入治疗" value="介入治疗" />
          <el-option label="手术" value="手术" />
          <el-option label="治疗性操作" value="治疗性操作" />
          <el-option label="诊断性操作" value="诊断性操作" />
        </el-select>
      </el-form-item>
      <el-form-item label="录入选项" prop="lrxx">
        <el-select v-model="ruleForm.lrxx" filterable clearable placeholder="请选择">
          <el-option label="选择性" value="选择性" />
          <el-option label="必选" value="必选" />
        </el-select>
      </el-form-item>
    </el-form>
    <span slot="footer" class="dialog-footer">
      <el-button @click="data.bSwitch = false">取 消</el-button>
      <el-button type="primary" @click="submitForm('ruleForm')">确 定</el-button>
    </span>
  </el-dialog>
</template>

<script>
import { ssczAdd, ssczSave } from '@/api/dict'
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          row: {}
        }
      }
    }
  },
  data() {
    return {
      ruleForm: {
        ssbm: '',
        ssmc: '',
        sslb: '',
        lrxx: ''
      },
      rules: {
        ssbm: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        ssmc: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        sslb: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        lrxx: [
          { required: true, message: '请选择', trigger: 'blur' }
        ]
      }
    }
  },
  computed: {
    titleStr() {
      return this.data.row.id ? '编辑' : '新增'
    }
  },
  created() {
    if (this.data.row.id) {
      const {
        SSBM,
        SSMC,
        SSLB,
        LRXX,
        id } = this.data.row
      this.ruleForm.ssbm = SSBM
      this.ruleForm.ssmc = SSMC
      this.ruleForm.id = id
      this.ruleForm.sslb = SSLB
      this.ruleForm.lrxx = LRXX
    }
  },
  methods: {
    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          if (this.ruleForm.id) {
            ssczSave(this.ruleForm).then(res => {
              this.data.bSwitch = false
              this.$emit('refresh')
              this.$message.success(res.m || '操作成功')
            })
          } else {
            ssczAdd(this.ruleForm).then(res => {
              this.data.bSwitch = false
              this.$emit('refresh')
              this.$message.success(res.m || '操作成功')
            })
          }
        } else {
          return false
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.demo-ruleForm {
  width: 80%;
}
</style>
