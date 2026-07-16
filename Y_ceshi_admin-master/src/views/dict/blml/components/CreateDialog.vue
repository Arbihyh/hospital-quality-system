<template>
  <el-dialog
    v-el-drag-dialog
    title="新增"
    :visible.sync="data.bSwitch"
    width="30%"
  >
    <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="100px" class="demo-ruleForm">
      <el-form-item label="数据库表" prop="table">
        <el-select v-model="ruleForm.table" filterable placeholder="请选择" style="width: 100%;" @change="handleTableChange">
          <el-option v-for="item of table" :key="item.id" :label="item.field" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item v-if="ruleForm.table" label="表字段" prop="table_field">
        <el-select v-model="ruleForm.table_field" filterable placeholder="请选择" style="width: 100%;">
          <el-option v-for="item of table_field" :key="item.id" :label="item.field_name" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="名称" prop="field_name">
        <el-input v-model="ruleForm.field_name" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="值" prop="field">
        <el-input v-model="ruleForm.field" placeholder="请输入" />
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
import { get_field_detail, add_dict } from '@/api/dict'

export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          row: {},
          type: ''
        }
      }
    }
  },
  data() {
    return {
      table: [],
      table_field: [],
      field: [],
      field_name: [],
      ruleForm: {
        table: '',
        table_field: '',
        field: '',
        field_name: '',
        remark: '',
        status: 1
      },
      rules: {
        table: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        table_field: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        field: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        field_name: [
          { required: true, message: '请输入', trigger: 'blur' }
        ]
      }
    }
  },
  async created() {
    await this.getData(0, null)
  },
  methods: {
    getData(field, field_name) {
      const params = {}
      if (field || field === 0) {
        params.field = field
      }
      if (field_name) {
        params.field_name = field_name
      }
      get_field_detail(params).then(res => {
        const { p } = res
        this.table = Array.isArray(p) ? p : []
      })
    },
    getData2(field, field_name) {
      const params = {}
      if (field || field === 0) {
        params.field = field
      }
      if (field_name) {
        params.field_name = field_name
      }
      get_field_detail(params).then(res => {
        const { p } = res
        this.table_field = Array.isArray(p) ? p : []
      })
    },
    handleTableChange(val) {
      this.getData2(val, null)
    },
    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          add_dict(this.ruleForm).then(res => {
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
