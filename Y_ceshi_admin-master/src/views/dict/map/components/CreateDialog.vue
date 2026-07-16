<template>
  <el-dialog
    :title="titleStr"
    :visible.sync="data.bSwitch"
    width="30%"
  >
    <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="100px" class="demo-ruleForm">
      <el-form-item label="手术2.0代码" prop="ssbm">
        <el-input v-model="ruleForm.ssbm" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="手术2.0名称" prop="ssmc">
        <el-input v-model="ruleForm.ssmc" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="手术3.0代码" prop="ssysbm">
        <el-input v-model="ruleForm.ssysbm" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="手术3.0名称" prop="ssysmc">
        <el-input v-model="ruleForm.ssysmc" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="手术内码" prop="ssnm">
        <el-input v-model="ruleForm.ssnm" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="操作类型" prop="sslb">
        <el-select v-model="ruleForm.sslb" filterable clearable placeholder="请选择">
          <el-option label="介入治疗" value="介入治疗" />
          <el-option label="手术" value="手术" />
          <el-option label="治疗性操作" value="治疗性操作" />
          <el-option label="诊断性操作" value="诊断性操作" />
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
import { ssczysAdd, ssczysSave } from '@/api/dict'
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
        ssysbm: '',
        ssysmc: '',
        sslb: '',
        ssnm: ''
      },
      rules: {
        ssbm: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        ssmc: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        ssysbm: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        ssysmc: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        sslb: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        ssnm: [
          { required: true, message: '请输入', trigger: 'blur' }
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
        SSYSBM,
        SSYSMC,
        SSLB,
        SSNM,
        id } = this.data.row
      this.ruleForm.ssbm = SSBM
      this.ruleForm.ssmc = SSMC
      this.ruleForm.ssysbm = SSYSBM
      this.ruleForm.id = id
      this.ruleForm.ssysmc = SSYSMC
      this.ruleForm.sslb = SSLB
      this.ruleForm.ssnm = SSNM
    }
  },
  methods: {
    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          if (this.ruleForm.id) {
            ssczysSave(this.ruleForm).then(res => {
              this.data.bSwitch = false
              this.$emit('refresh')
              this.$message.success(res.m || '操作成功')
            })
          } else {
            ssczysAdd(this.ruleForm).then(res => {
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
