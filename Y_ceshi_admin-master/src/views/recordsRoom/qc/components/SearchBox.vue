<template>
  <div class="app-container">
    <el-form :inline="true" :model="data" class="demo-form-inline">
      <el-form-item label="">
        <el-select v-model="data.level" filterable clearable placeholder="整改级别">
          <el-option v-for="item of levels" :key="item.id" :label="item.name" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.ZKYS" placeholder="质控医师" />
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.BLZT" filterable clearable placeholder="病历状态">
          <el-option v-for="item of BLZTs" :key="item.id" :label="item.name" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.BLDJ" filterable clearable placeholder="病历等级">
          <el-option v-for="item of BLDJs" :key="item.id" :label="item.name" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.AAA28" placeholder="住院号码" />
      </el-form-item>
      <el-form-item label="">
        <el-input v-model="data.AAA01" placeholder="姓名" />
      </el-form-item>
      <el-form-item label="">
        <el-select v-model="data.AAC11N" filterable clearable placeholder="出院科室">
          <el-option v-for="item of deportments" :key="item.id" :label="item.name" :value="item.name" />
        </el-select>
      </el-form-item>
      <el-form-item label="">
        <el-date-picker
          v-model="data.AAB01_START"
          type="date"
          :picker-options="pickerOptions1"
          placeholder="入院开始日期"
        />
      </el-form-item>
      <el-form-item label="">
        <el-date-picker
          v-model="data.AAB01_END"
          type="date"
          :picker-options="pickerOptions2"
          placeholder="入院结束日期"
        />
      </el-form-item>
      <el-form-item label="">
        <el-date-picker
          v-model="data.AAC01_START"
          type="date"
          :picker-options="pickerOptions1"
          placeholder="出院开始日期"
        />
      </el-form-item>
      <el-form-item label="">
        <el-date-picker
          v-model="data.AAC01_END"
          type="date"
          :picker-options="pickerOptions2"
          placeholder="出院结束日期"
        />
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
import { getDeportmentList } from '@/api/admin'
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          BLZT: '',
          level: '',
          ZKYS: '',
          BLDJ: '',
          AAA28: '',
          AAA01: '',
          AAC11N: '',
          AAB01_START: '',
          AAB01_END: '',
          AAC01_START: '',
          AAC01_END: ''
        }
      }
    }
  },
  data() {
    return {
      pickerOptions1: {
        disabledDate: (time) => {
          if (this.data.end_time) {
            return time.getTime() > new Date(this.data.end_time).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      pickerOptions2: {
        disabledDate: (time) => {
          if (this.data.start_time) {
            return time.getTime() < new Date(this.data.start_time).getTime()
          } else {
            return time.getTime() > Date.now()
          }
        }
      },
      deportments: [],
      levels: [
        {
          id: 1,
          name: '强制'
        },
        {
          id: 2,
          name: '建议'
        }
      ],
      BLDJs: [
        {
          id: 1,
          name: '甲'
        },
        {
          id: 2,
          name: '乙'
        },
        {
          id: 3,
          name: '丙'
        }
      ],
      BLZTs: [
        {
          id: 0,
          name: '未改'
        },
        {
          id: 2,
          name: '已改'
        }
      ]
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
      this.$emit('reset')
    },
    getDeportmentList() {
      getDeportmentList().then(res => {
        const { p } = res
        if (Object.keys(p.list).length) {
          for (const key in p.list) {
            this.deportments.push({
              id: key,
              name: p.list[key]
            })
          }
        }
      }).catch(error => {
        console.log(error)
      })
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
