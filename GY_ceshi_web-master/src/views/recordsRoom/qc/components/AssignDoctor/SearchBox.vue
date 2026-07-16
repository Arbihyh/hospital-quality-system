<template>
  <el-form style="width: 100%" ref="filterListFormRef" :model="formData" class="demo-form-inline" label-suffix=":"
    label-width="120px">
    <el-row :gutter="24">
      <el-col :span="6">
        <el-form-item label="导入审阅医师" prop="drsyys">
          <el-select style="width:100%" v-model="formData.drsyys" filterable clearable placeholder="请选择">
            <el-option v-for="item of peopleList" :key="item.dep_id" :label="item.name" :value="item.dep_id" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="随机分配" prop="sjfp">
          <el-radio-group v-model="formData.sjfp">
            <el-radio :label="1">是</el-radio>
            <el-radio :label="0">否</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="导入审阅科室" prop="drsyks">
          <el-select style="width:100%" v-model="formData.drsyks" filterable clearable placeholder="请选择">
            <el-option v-for="item of peopleList" :key="item.dep_id" :label="item.name" :value="item.dep_id" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="6" v-if="action !== 'DETAIL'">
        <el-form-item label="" prop="">
          <el-row type="flex" justify="end">
            <el-button icon="el-icon-plus" @click="$emit('add')">新增</el-button>
            <!-- <el-button icon="el-icon-delete" @click="onReset">批量删除</el-button>
            <el-button icon="el-icon-edit-outline"type="primary" @click="onReset" plain>批量编辑</el-button> -->
          </el-row>
        </el-form-item>
      </el-col>
    </el-row>
    <!-- <el-row :gutter="24">
      <el-col :span="7" :offset="17">
        <el-form-item label="" prop="" style="float: right;">
          <el-button type="primary" @click="onSubmit">查询</el-button>
          <el-button @click="onReset">重置</el-button>
        </el-form-item>
      </el-col>
    </el-row> -->
  </el-form>
</template>
<script>
import moment from 'moment/moment';

export default {
  props: ['action'],
  emits: ['search', 'reset', 'add'],
  data() {
    const that = this
    return {
      formData: {
        drsyys: '',
        sjfp: 1,
        drsyks: '',
      },
      peopleList: [],
    }
  },
  created() {
    this.getDeportmentList()
  },
  watch: {
    formData: {
      handler() {
        this.$emit('search')
      },
      deep: true // 深度监听对象内部属性的变化
    }
  },
  methods: {
    onSubmit() {
      this.$emit('search')
    },
    onReset() {
      this.$refs.filterListFormRef.resetFields();
      this.$emit('reset')
    },
    getDeportmentList() {
      this.$axios.get('/user/depDropDown').then(res => {
        const { data } = res
        this.peopleList = data;
      }).catch(error => {
        console.log(error)
      })
    }
  }
}
</script>

<style lang="scss" scoped></style>
