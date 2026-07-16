<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-select v-model="data.table" filterable clearable placeholder="请选择数据表">
          <el-option v-for="item of table" :key="item.id" :label="item.field" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.field" clearable placeholder="请输入表字段" />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.field_name" clearable placeholder="请输入字段名称" />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.dict" clearable placeholder="请输入数据字典" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">查询</el-button>
      </el-form-item>
      <el-form-item>
        <el-button @click="onReset">重置</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script>
import { get_field_detail } from '@/api/dict'

export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          table: '',
          field: '',
          field_name: '',
          dict: ''
        }
      }
    }
  },
  data() {
    return {
      table: []
    }
  },
  created() {
    this.getData()
  },
  methods: {
    getData() {
      get_field_detail({ field: 0 }).then(res => {
        const { p } = res
        this.table = p
      })
    },
    onSubmit() {
      this.$emit('search')
    },
    onReset() {
      this.$emit('reset')
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
