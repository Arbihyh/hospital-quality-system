<template>
  <div class="app-container">
    <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="140px" class="demo-ruleForm">
      <template>
        <div style="font-size: 16px; color: #1b64b0; line-height: 40px; margin-bottom: 16px;">
          病历质控AI助手
        </div>
      </template>
      <el-form-item label="应用名称" prop="rule_id">
        <el-select v-model="ruleForm.rule_id" filterable clearable style="width: 300px;" placeholder="请选择">
          <el-option v-for="item of apps" :key="item.id" :label="item.notice" :value="item.id" />
        </el-select>
      </el-form-item>
      <template>
        <div style="font-size: 16px; color: #1b64b0; line-height: 40px; margin-bottom: 16px;">
          AI配置
        </div>
      </template>
      <el-form-item label="输入" prop="data_type">
        <el-cascader
          v-model="ruleForm.data_type"
          :options="inputs"
          filterable
          collapse-tags
          :props="{ expandTrigger: 'hover', label: 'name', children: 'child', value: 'key', multiple: true, checkStrictly: false, emitPath: false }"
          placeholder="请选择"
          style="width: 340px;"
        />
      </el-form-item>
      <el-form-item label="提示词" prop="content">
        <el-input v-model="ruleForm.content" type="textarea" :autosize="{ minRows: 8, maxRows: 12 }" placeholder="请输入" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">提交</el-button>
        <el-button @click="onCancle">取消</el-button>
        <el-button type="primary" plain style="float: right;" @click="onTest">测试</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script>
import { getRule, getInputSelect, addHelper } from '@/api/helper'
export default {
  data() {
    return {
      ruleForm: {
        rule_id: '',
        data_type: [],
        content: ''
      },
      rules: {
        rule_id: [
          { required: true, message: '请选择', trigger: 'change' }
        ],
        data_type: [
          { required: true, message: '请选择', trigger: 'change' }
        ],
        content: [
          { required: true, message: '请输入', trigger: 'blur' }
        ]
      },
      apps: [],
      inputs: []
    }
  },
  created() {
    this.getRule()
    this.getInputSelect()
  },
  mounted() {
    const helper = localStorage.getItem('helper')
    if (helper) {
      setTimeout(() => {
        this.ruleForm = JSON.parse(localStorage.getItem('helper'))
        console.log(this.ruleForm)
      }, 1500)
    }
  },
  methods: {
    onTest() {
      const url = 'http://172.16.9.43:8001'
      window.open(url, '_blank')
    },
    getRule() {
      getRule().then((res) => {
        const { p } = res
        this.apps = p
      })
    },
    getInputSelect() {
      getInputSelect().then((res) => {
        const { p } = res
        this.inputs = p
      })
    },
    onSubmit() {
      this.$refs['ruleForm'].validate((valid) => {
        if (valid) {
          const { id, data_type, content, rule_id } = this.ruleForm
          const params = {
            data_type,
            content,
            rule_id
          }
          if (id) {
            params.id = id
          }
          addHelper(params).then((res) => {
            this.$message.success(res.m || '操作成功')
            this.$router.push({ path: '/helper/index' })
          })
        } else {
          return false
        }
      })
    },
    onCancle() {
      this.$router.push({ path: '/helper/index' })
    }
  }
}
</script>

<style lang="scss" scoped>
.demo-ruleForm {
  width: 50%;
}
</style>
