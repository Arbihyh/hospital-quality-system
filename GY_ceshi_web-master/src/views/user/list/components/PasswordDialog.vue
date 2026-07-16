<template>
  <div>
    <el-dialog
      v-el-drag-dialog
      title="修改密码"
      :visible.sync="data.bSwitch"
      width="30%"
    >
      <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="100px" class="demo-ruleForm">
        <el-form-item label="新密码" prop="password">
          <el-input v-model="ruleForm.password" placeholder="请输入" show-password />
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="data.bSwitch = false">取 消</el-button>
        <el-button type="primary" @click="submitForm">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { editPassword } from '@/api/user'
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
        password: '',
        id: ''
      },
      rules: {
        password: [
          { required: true, message: '请输入', trigger: 'blur' }
        ]
      }
    }
  },
  created() {
    const { id } = this.data.row
    this.ruleForm.id = id
  },
  methods: {
    submitForm() {
      this.$refs['ruleForm'].validate((valid) => {
        if (valid) {
          this.$axios.post('/user/editPassword',this.ruleForm).then(res => {
            this.$message.success(res.m || '操作成功')
            this.data.bSwitch = false
          })
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
.el-button--primary {
    color: #FFF;
    background-color: #005FA6;
    border-color: #185da6;
}
.el-button:focus, .el-button:hover {
    color: #409EFF !important;
    border-color: #c6e2ff !important;
    background-color: #ecf5ff !important;
}
</style>
