<template>
  <div class="part-box" style="margin-bottom: 20px;">
    <el-form :model="data" class="demo-form-inline" label-width="80px">
      <el-row :gutter="20">
        <el-col :span="6">
          <el-form-item label="一级目录">
            <el-select v-model="data.name" filterable clearable placeholder="请选择" style="width: 100%;">
              <el-option v-for="(item, index) of options.name" :key="'name'+index" :label="item" :value="item"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="分子">
            <el-select v-model="data.fenzi" filterable clearable placeholder="请选择" style="width: 100%;">
              <el-option v-for="(item, index) of options.fenzi" :key="'fenzi'+index" :label="item" :value="item"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="分母">
            <el-select v-model="data.fenmu" filterable clearable placeholder="请选择" style="width: 100%;">
              <el-option v-for="(item, index) of options.fenmu" :key="'fenmu'+index" :label="item" :value="item"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6" style="text-align: right;">
          <el-form-item>
            <el-button type="primary" @click="onSubmit">查询</el-button>
            <el-button @click="onReset">重置</el-button>
          </el-form-item>
        </el-col>
      </el-row>
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
          name: '',
          fenzi: '',
          fenmu: ''
        };
      },
    },
  },
  data() {
    return {
      options: {
        name: [],
        fenzi: [],
        fenmu: []
      }
    };
  },
  created() {
    this.getOptions()
  },
  methods: {
    getOptions() {
      this.$axios2.get('/catalog_options').then(res => {
        this.options = res.data;
      });
    },
    onReset() {
      this.$emit('reset');
    },
    onSubmit() {
      this.$emit('search');
    },
  },
};
</script>

<style lang="scss" scoped>
.demo-form-inline {
}
</style>
