<template>
  <el-form style="width: 100%" ref="filterListFormRef" :model="formData" class="demo-form-inline" label-suffix=":"
    label-width="74px">
    <el-row :gutter="24">
      <el-col :span="5">
        <el-form-item label="计划名称" prop="title">
          <el-input v-model="formData.title" placeholder="请输入"></el-input>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="当前进度" prop="progress">
          <el-select style="width:100%" v-model="formData.progress" filterable clearable placeholder="请选择">
            <el-option label="已完成" value="1" />
            <el-option label="未完成" value="2" />         
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="6">
        <el-form-item label="发布人" prop="add_user">
          <el-select style="width:100%" v-model="formData.add_user" filterable clearable placeholder="请选择">
            <el-option v-for="item of publishPeopleList" :key="item.id" :label="item.realname" :value="item.id" />
          </el-select>
        </el-form-item>
      </el-col>
     
      <el-col :span="7">
        <el-form-item label="发布时间">
          <div style="width: 100%;display: flex;gap: 5px;">
            <el-form-item prop="start_time">
              <el-date-picker style="width: 100%" v-model="formData.start_time" type="date" placeholder="开始日期"
                :picker-options="AAC01PickerOptions" value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
            <el-form-item prop="end_time">
              <el-date-picker style="width: 100%" v-model="formData.end_time" type="date" placeholder="结束日期"
                value-format="yyyyMMdd" format="yyyy年MM月dd日">
              </el-date-picker>
            </el-form-item>
          </div>
        </el-form-item>
      </el-col>
     
    </el-row>
    <el-row :gutter="24">
      <el-col :span="7" :offset="17">
        <el-form-item label="" prop="" style="float: right;">
          <el-button type="primary" @click="onSubmit">查询</el-button>
          <el-button @click="onReset">重置</el-button>
        </el-form-item>
      </el-col>
    </el-row>
  </el-form>
</template>
<script>
import moment from 'moment/moment';

export default {
  emits: ['search', 'reset'],
  data() {
    const that = this
    return {
      formData: {
        start_time: '',
        end_time: '',
        title: '',
        add_user: '',
        progress: ''
      },
      publishPeopleList: [],
      AAC01PickerOptions: {
        disabledDate: (time) => {
          if (that.formData.end_time != "") {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [{
          text: '今天',
          onClick(picker) {
            picker.$emit('pick', moment().format('YYYYMMDD'));
            that.formData.end_time = moment().format('YYYYMMDD')
          }
        }, {
          text: '近7天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
            that.formData.end_time = moment().format('YYYYMMDD')
          }
        }, {
          text: '近30天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
            that.formData.end_time = moment().format('YYYYMMDD')
          }
        }, {
          text: '一季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.end_time = moment().startOf('year').add(3,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3,'M').format('YYYYMMDD'));
            that.formData.end_time = moment().startOf('year').add(6,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6,'M').format('YYYYMMDD'));
            that.formData.end_time = moment().startOf('year').add(9,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9,'M').format('YYYYMMDD'));
            that.formData.end_time = moment().startOf('year').add(12,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.end_time = moment().add(-2,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.end_time = moment().add(-1,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.end_time = moment().endOf('year').format('YYYYMMDD')
          }
        }]
      },
      AAB01PickerOptions: {
        disabledDate: (time) => {
          if (that.formData.AAB01_END != "") {
            return time.getTime() > Date.now();
          }
        },
        shortcuts: [{
          text: '今天',
          onClick(picker) {
            picker.$emit('pick', moment().format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近7天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(7, 'days').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '近30天',
          onClick(picker) {
            picker.$emit('pick', moment().subtract(30, 'days').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().format('YYYYMMDD')
          }
        }, {
          text: '一季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(3,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '二季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(3,'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(6,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '三季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(6,'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(9,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        },  {
          text: '四季度',
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').add(9,'M').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().startOf('year').add(12,'M').subtract(1, 'days').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-2,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-2,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().add(-2,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().add(-1,'Y').format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().add(-1,'Y').startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().add(-1,'Y').endOf('year').format('YYYYMMDD')
          }
        }, {
          text: moment().format("YYYY"),
          onClick(picker) {
            picker.$emit('pick', moment().startOf('year').format('YYYYMMDD'));
            that.formData.AAB01_END = moment().endOf('year').format('YYYYMMDD')
          }
        }]
      },
    }
  },
  created() {
    this.getDeportmentList()
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
      // this.$axios.get('/user/depDropDown').then(res => {
      //   const { data } = res
      //   this.publishPeopleList = data;
      // }).catch(error => {
      //   console.log(error)
      // })
      this.$axios.post('/user/userList',{ page :1,len:10000}).then(res => {
        this.publishPeopleList = res.data.list
      }).catch(error => {
        console.log(error)
      })
    }
  }
}
</script>

<style lang="scss" scoped></style>
