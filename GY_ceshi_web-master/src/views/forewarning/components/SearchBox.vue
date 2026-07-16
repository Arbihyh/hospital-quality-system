<template>
  <div class="bg-card" style="margin-bottom: 16px">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-row :gutter="0">
        <el-col :span="8">
          <el-form-item label="质控日期" style="margin-bottom: 0">
            <el-date-picker v-model="data.quality_start_time" type="date" placeholder="质控开始日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" style="width: 170px" />
          </el-form-item>
          <el-form-item label="" style="margin-bottom: 0">
            <el-date-picker v-model="data.quality_end_time" type="date" placeholder="质控结束日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" style="width: 170px" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="出院日期" style="margin-bottom: 0">
            <el-date-picker v-model="data.aac01_start_time" type="date" placeholder="出院开始日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" style="width: 170px" />
          </el-form-item>
          <el-form-item label="" style="margin-bottom: 0">
            <el-date-picker v-model="data.aac01_end_time" type="date" placeholder="出院结束日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" style="width: 170px" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="出院科室">
            <el-select v-model="data.department" clearable filterable :disabled="!!depId" placeholder="请选择" style="width: 350px">
              <el-option v-for="(item, index) in departmentList" :label="item.dep_name" :value="item.dep_name" :key="index"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="入院日期">
            <el-date-picker v-model="data.aab01_start_time" type="date" placeholder="入院开始日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" style="width: 170px" />
          </el-form-item>
          <el-form-item label="">
            <el-date-picker v-model="data.aab01_end_time" type="date" placeholder="入院结束日期" value-format="yyyyMMdd" format="yyyy年MM月dd日" style="width: 170px" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="预警问题">
            <el-input v-model="data.content" placeholder="预警问题" style="width: 350px"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="住院号码">
            <el-input v-model="data.AAA28" placeholder="住院号码" style="width: 350px"></el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="是否删除">
            <el-select v-model="data.is_delete" filterable placeholder="是否删除" style="width: 350px">
              <el-option v-for="(item, index) in deleteOption" :label="item.name" :value="item.id" :key="index"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="是否整改">
            <el-select v-model="data.status" filterable placeholder="是否整改" style="width: 350px">
              <el-option v-for="(item, index) in statusList" :label="item.name" :value="item.id" :key="index"></el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8" style="text-align: right">
          <el-form-item label="" style="margin-bottom: 0">
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
          quality_start_time: '',
          quality_end_time: '',
          aac01_start_time: '',
          aac01_end_time: '',
          aab01_start_time: '',
          aab01_end_time: '',
          department: '',
          content: '',
          AAA28: '',
          id_delete: 1
        };
      },
    },
  },
  data() {
    return {
      departmentList: [],
      depId: 0,
      deleteOption: [
        {
          id: 1,
          name: '正常'
        },
        {
          id: 0,
          name: '删除'
        },
        {
          id: -1,
          name: '全部'
        }
      ],
      statusList: [
        {
          id: '',
          name: '全部'
        },
        {
          id: 1,
          name: '已整改'
        },
        {
          id: 2,
          name: '未整改'
        },
        {
          id: 3,
          name: '按时整改'
        }
      ]
    };
  },
  created() {
    this.getDepList()
  },
  methods: {
    getUserDepName() {
      this.$axios2.get('/get_admin_department').then(res => {
        this.depId = typeof res.data === "object" || res.data === null || res.data === "" ? 0 : res.data
        const deps = this.departmentList.filter(item => item.id == this.depId)
        if (deps && deps.length) {
          this.data.department = deps[0].name
        }
      });
    },
    getDepList() {
      this.$axios.post('/case-quality/warning/get_admin_department').then(res => {
        this.departmentList = res.data || [];
        // this.getUserDepName()
      });
    },
    onSubmit() {
      this.$emit('search')
    },
    onReset() {
      this.$emit('reset')
    }
  },
};
</script>

<style lang="scss" scoped>
.el-col {
  text-align: center;
}
</style>