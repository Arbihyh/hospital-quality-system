<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="质控分类">
        <el-select v-model="data.category" clearable placeholder="质控分类">
          <el-option v-for="item of categorys" :key="item" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="质控项目">
        <el-input v-model="data.title" clearable placeholder="质控项目" />
      </el-form-item>
      <el-form-item label="科室">
        <el-input v-model="data.department" clearable placeholder="科室" />
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="data.status" clearable placeholder="状态">
          <el-option label="正常" :value="1" />
          <el-option label="停用" :value="0" />
          <el-option label="已删除" :value="3" />
        </el-select>
      </el-form-item>
      <el-form-item label="扣分">
        <el-input v-model="data.score" clearable placeholder="扣分" />
      </el-form-item>
      <el-form-item label="质控类型">
        <el-select v-model="data.type" clearable placeholder="质控类型">
          <el-option v-for="item of types" :key="item" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="错误描述">
        <el-input v-model="data.notice" clearable placeholder="错误描述" />
      </el-form-item>
      <el-form-item label="规则类型">
        <el-select v-model="data.is_ai" clearable placeholder="规则类型">
          <el-option label="全部" value="" />
          <el-option label="规则" :value="1" />
          <el-option label="人工" :value="2" />
          <el-option label="模型" :value="3" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" @click="onSubmit">查询</el-button>
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
          category: '',
          title: '',
          notice: '',
          status: '',
          is_ai: '',
          score: '',
          type: '',
          department: ''
        }
      }
    },
    categorys: {
      type: Array,
      default() {
        return []
      }
    },
    types: {
      type: Array,
      default() {
        return []
      }
    }
  },
  methods: {
    onSubmit() {
      this.$emit('search')
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
