<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-select v-model="data.table_name" filterable clearable placeholder="菁苗表名">
          <el-option v-for="(item, index) of options.table_name" :key="'table_name' + index" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.qingmiao_field_name" filterable clearable placeholder="菁苗字段名称">
          <el-option v-for="(item, index) of options.EMR_YZB.field_name" :key="'qingmiao_field_name' + index" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.qingmiao_field" filterable clearable placeholder="菁苗字段">
          <el-option v-for="(item, index) of options.EMR_YZB.field" :key="'qingmiao_field' + index" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.hospital_name" filterable clearable placeholder="医院名称">
          <el-option v-for="(item, index) of options.hospital_name" :key="'hospital_name' + index" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.hospital_field" filterable clearable placeholder="医院字段">
          <el-option v-for="(item, index) of options.hospital_field" :key="'hospital_field' + index" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">查询</el-button>
        <el-button @click="onReset">重置</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script>
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          table_name: '',
          qingmiao_field_name: '',
          qingmiao_field: '',
          hospital_name: '',
          hospital_field: ''
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
  methods: {
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
