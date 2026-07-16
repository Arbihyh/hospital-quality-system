<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="医院名称" v-if="this.data.hospital_name">
        <el-select v-model="data.hospital_name" filterable class="selects" placeholder="医院名称">
          <el-option v-for="(item, index) in HospitalList" :label="item" :value="item" :key="index"></el-option>
        </el-select>
      </el-form-item>

      <el-form-item label="出院时间">
        <el-date-picker
          v-model="data.start_time"
          type="month"
          format="yyyy 年 MM 月"
          value-format="yyyyMM"
          placeholder="开始时间"
        />
      </el-form-item>
      <el-form-item label="">
        <el-date-picker
          v-model="data.end_time"
          type="month"
          format="yyyy 年 MM 月"
          value-format="yyyyMM"
          placeholder="结束时间"
        />
      </el-form-item>
      <el-form-item label="缺陷归类">
        <el-select v-model="data.type" filterable clearable placeholder="请选择">
          <el-option v-for="item of selectData.type" :key="item" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="缺陷分级">
        <el-select v-model="data.level" filterable clearable placeholder="请选择">
          <el-option v-for="item of selectData.level" :key="item" :label="item" :value="item" />
        </el-select>
      </el-form-item>
      <el-form-item label="缺陷描述">
        <el-input v-model="data.desc" clearable placeholder="请输入"></el-input>
      </el-form-item>
      <el-form-item label="缺陷字段">
        <el-input v-model="data.field" clearable placeholder="请输入"></el-input>
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
          hospital_name:'',
          start_time: '',
          end_time: '',
          level: '',
          type: '',
          desc: '',
          field: ''
        }
      }
    },
    type_name:{  // 'lc' 临床
      type: String,
      default() {
        return ''
      }
    },
  },
  data() {
    return {
      selectData:  {},
      HospitalList:[],
    }
  },
  created() {
    this.getSelectData();
    if(this.data.hospital_name){
      // 获取医院名称
      this.getHospitalList();
    }
  },
  methods: {
    
    getSelectData() {
      this.$axios.post('/home_quality/getErrorSerachWhere').then(res => {
        this.selectData = res.data
      });
    },
    // 获取医院名称
    getHospitalList() {
      let url = '';
      if(this.type_name == 'lc'){
        url = '/home_sz_quality/getHospitalList'
      }else{
        url = '/home_quality/getHospitalList'
      }
      this.$axios.post(url).then(res => {
        this.HospitalList = res.data;
      });
    },
    onSubmit() {
      this.$emit('search')
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
