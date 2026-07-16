<template>
  <el-dialog
    v-el-drag-dialog
    title="修改字段"
    :visible.sync="data.bSwitch"
    width="30%"
  >
    <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="100px" class="demo-ruleForm" label-suffix="：">
      <el-form-item label="数据表" prop="">
        <el-input v-model="data.row.parent_field" placeholder="请输入" disabled />
      </el-form-item>
      <el-form-item label="表字段" prop="">
        <el-input v-model="data.row.field" placeholder="请输入" disabled />
      </el-form-item>
      <el-form-item label="名称" prop="field_name">
        <el-input v-model="ruleForm.field_name" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="备注" prop="remark">
        <el-input v-model="ruleForm.remark" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="状态" prop="status">
        <el-switch
          v-model="ruleForm.status"
          active-color="#13ce66"
          :active-value="1"
          :inactive-value="2"
        />
      </el-form-item>
    </el-form>
    <span slot="footer" class="dialog-footer">
      <el-button @click="data.bSwitch = false">取 消</el-button>
      <el-button type="primary" @click="submitForm('ruleForm')">确 定</el-button>
    </span>
  </el-dialog>
</template>

<script>
import { edit_field } from '@/api/dict'

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
        field_name: '',
        remark: '',
        status: 1
      },
      rules: {
        field: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        field_name: [
          { required: true, message: '请输入', trigger: 'blur' }
        ]
      }
    }
  },
  created() {
    const { field_name, remark, status, id } = JSON.parse(JSON.stringify(this.data.row))
    this.ruleForm = {
      field_name,
      remark,
      status,
      id
    }
  },
  methods: {
    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          edit_field(this.ruleForm).then(res => {
            this.data.bSwitch = false
            this.$emit('refresh')
            this.$message.success(res.m || '操作成功')
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
</style>
