<template>
  <el-dialog
    :title="titleStr"
    :visible.sync="data.bSwitch"
    width="40%"
  >
    <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="140px" class="demo-ruleForm">
      <el-form-item label="青苗表名" prop="table_name">
        <el-select v-model="ruleForm.qingmiao_table_name" filterable clearable placeholder="请选择" style="width: 100%;">
          <el-option v-for="(item, index) of options.table_name" :key="'qingmiao_table_name' + index" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="菁苗字段名称" prop="qingmiao_field_name">
        <el-select v-model="ruleForm.qingmiao_field_name" filterable clearable placeholder="请选择" style="width: 100%;">
          <el-option v-for="(item, index) of options.EMR_YZB.field_name" :key="'qingmiao_field_name' + index" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="菁苗字段" prop="qingmiao_field">
        <el-select v-model="ruleForm.qingmiao_field" filterable clearable placeholder="菁苗字段" style="width: 100%;">
          <el-option v-for="(item, index) of options.EMR_YZB.field" :key="'qingmiao_field' + index" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="医院名称" prop="hospital_name">
        <el-select v-model="ruleForm.hospital_name" filterable clearable placeholder="医院名称" style="width: 87%;">
          <el-option v-for="(item, index) of hospitals" :key="'hospital_name' + index" :label="item.name" :value="item.name" />
        </el-select>
        <el-button type="primary" plain icon="el-icon-plus" style="margin-left: 10px;" @click="open" />
      </el-form-item>
      <el-form-item label="医院字段" prop="hospital_field">
        <el-input v-model="ruleForm.hospital_field" clearable placeholder="请输入" />
      </el-form-item>
      <el-form-item label="医院一级目录">
        <el-input v-model="ruleForm.hospital_one" clearable placeholder="请输入" />
      </el-form-item>
      <el-form-item label="医院二级目录">
        <el-input v-model="ruleForm.hospital_two" clearable placeholder="请输入" />
      </el-form-item>
      <el-form-item label="医院三级目录">
        <el-input v-model="ruleForm.hospital_three" clearable placeholder="请输入" />
      </el-form-item>
    </el-form>
    <span slot="footer" class="dialog-footer">
      <el-button @click="data.bSwitch = false">取 消</el-button>
      <el-button type="primary" @click="submitForm('ruleForm')">确 定</el-button>
    </span>
  </el-dialog>
</template>

<script>
import { data_source_add, data_source_edit, hospitalList, hospitalAdd } from '@/api/rule/data'

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
    },
    options: {
      type: Object,
      default() {
        return {
          table_name: [],
          EMR_YZB: {
            field_name: [],
            field: []
          },
          hospital_name: [],
          hospital_field: []
        }
      }
    }
  },
  data() {
    return {
      hospitals: [],
      ruleForm: {
        qingmiao_table_name: '',
        qingmiao_field_name: '',
        qingmiao_field: '',
        hospital_name: '',
        hospital_field: '',
        hospital_one: '',
        hospital_two: '',
        hospital_three: ''
      },
      rules: {
        qingmiao_table_name: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        qingmiao_field_name: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        qingmiao_field: [
          { required: true, message: '请选择', trigger: 'blur' }
        ],
        hospital_field: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        hospital_name: [
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
    this.getHospitalList()
    if (this.data.row.id) {
      const {
        qingmiao_table_name,
        qingmiao_field_name,
        qingmiao_field,
        hospital_name,
        hospital_field,
        hospital_one,
        hospital_two,
        hospital_three,
        id
      } = this.data.row
      this.ruleForm.qingmiao_table_name = qingmiao_table_name
      this.ruleForm.qingmiao_field_name = qingmiao_field_name
      this.ruleForm.qingmiao_field = qingmiao_field
      this.ruleForm.hospital_name = hospital_name
      this.ruleForm.hospital_field = hospital_field
      this.ruleForm.hospital_one = hospital_one
      this.ruleForm.hospital_two = hospital_two
      this.ruleForm.hospital_three = hospital_three
      this.ruleForm.id = id
    }
  },
  methods: {
    // 添加医院
    open() {
      this.$prompt('请输入医院名称', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        inputPattern: /^[\S]+$/,
        inputErrorMessage: '请输入'
      }).then(({ value }) => {
        hospitalAdd({ name: value }).then(res => {
          this.$message.success(res.m || '操作成功')
          this.getHospitalList()
        })
      })
    },
    // 获取医院列表
    getHospitalList() {
      hospitalList().then(res => {
        this.hospitals = Array.isArray(res.p) ? res.p : []
      })
    },
    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          if (this.ruleForm.id) {
            data_source_edit(this.ruleForm).then(res => {
              this.data.bSwitch = false
              this.$emit('refresh')
              this.$message.success(res.m || '操作成功')
            })
          } else {
            data_source_add(this.ruleForm).then(res => {
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
