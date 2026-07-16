<template>
  <el-form
    ref="filterListFormRef"
    style="width: 100%"
    :model="formData"
    class="demo-form-inline"
    label-suffix=":"
    label-width="100px"
  >
    <el-row :gutter="24">
      <el-col :span="6">
        <el-form-item label="任务类型" prop="type">
          <el-select
            v-model="formData.type"
            style="width: 100%"
            filterable
            clearable
            multiple
            placeholder="请选择"
          >
            <el-option label="病历质控" :value="1" />
            <el-option label="病历生成" :value="2" />
            <el-option label="评审指标" :value="3" />
          </el-select>
        </el-form-item>
      </el-col>
      <!-- <el-col :span="6">
        <el-form-item label="病历类目" prop="bllm">
          <el-select style="width: 100%;" v-model="formData.bllm" filterable clearable placeholder="审核状态">
            <el-option label="未审核" :value="0" />
            <el-option label="审核中" :value="1" />
            <el-option label="已通过" :value="2" />
            <el-option label="未通过" :value="3" />
          </el-select>
        </el-form-item>
      </el-col> -->
      <el-col :span="6">
        <el-form-item label="任务名称" prop="title">
          <el-input
            v-model="formData.title"
            style="width: 100%"
            placeholder="请输入"
          />
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="任务状态" prop="status">
          <el-select
            v-model="formData.status"
            style="width: 100%"
            filterable
            clearable
            placeholder="请选择"
          >
            <el-option label="全部" value="" />
            <el-option label="开启" :value="1" />
            <el-option label="关闭" :value="0" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="" prop="" style="float: right">
          <el-button type="primary" @click="onSubmit">查询</el-button>
          <el-button @click="onReset">重置</el-button>
        </el-form-item>
      </el-col>
    </el-row>
  </el-form>
</template>

<script>
export default {
  emits: ['onSearch', 'onReset'],
  props: {},
  data() {
    return {
      formData: {
        type: '',
        title: '',
        status: 1
      }
    }
  },
  created() {
    // 获取病历类目和任务名称
  },
  methods: {
    onSubmit() {
      this.$emit('onSearch')
    },
    onReset() {
      this.$refs.filterListFormRef.resetFields()
      this.formData = {
        type: '',
        title: '',
        status: 1
      }
      this.$emit('onReset')
    }
  }
}
</script>

<style lang="scss" scoped></style>
