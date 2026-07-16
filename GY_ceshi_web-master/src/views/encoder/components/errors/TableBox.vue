<template>
  <div class="table-box">
    <div class="btn-box">
      <el-button type="primary" icon="el-icon-upload" class="export-btn" @click="onExport">下载</el-button>
      <el-popover placement="bottom-end" title="" trigger="click" popper-class="table_code_popper">
        <el-checkbox v-model="checkAll" :indeterminate="isIndeterminate" @change="handleCheckAllChange">全选</el-checkbox>
        <el-checkbox-group v-model="codes" @change="handleChange">
          <el-checkbox label="field_name">缺陷字段</el-checkbox>
          <el-checkbox label="desc">缺陷描述</el-checkbox>
          <el-checkbox label="AAA28">住院号码</el-checkbox>
          <el-checkbox label="AAA01">姓名</el-checkbox>
          <el-checkbox label="AAC01">出院时间</el-checkbox>
          <el-checkbox label="YQ_CODE">出院院区</el-checkbox>
          <el-checkbox label="AAC02C">出院科室</el-checkbox>
          <el-checkbox label="AAC11C">出院病房</el-checkbox>
          <el-checkbox label="AEE08">编码员</el-checkbox>
          <el-checkbox label="AEE04">住院医师</el-checkbox>
          <el-checkbox label="ICD10_NAME">主要诊断名称</el-checkbox>
          <el-checkbox label="ICD10_ID1">主要诊断编码</el-checkbox>
          <el-checkbox label="type">缺陷归类</el-checkbox>
        </el-checkbox-group>
        <el-button slot="reference" type="primary" plain icon="el-icon-setting" style="margin-left: 10px"></el-button>
      </el-popover>
    </div>
    <el-table :data="data" style="width: 100%">
      <el-table-column type="index" label="序号" width="50" align="center">
        <template slot-scope="scope">
          <span>{{ scope.$index + 1 + (paginationData.page - 1) * paginationData.page_size }}</span>
        </template>
      </el-table-column>
      <el-table-column v-if="codes.includes('field_name')" prop="field_name" label="缺陷字段" show-overflow-tooltip width="160" align="center"></el-table-column>
      <el-table-column v-if="codes.includes('AAA28')" prop="" label="住院号码" show-overflow-tooltip width="120" align="center">
        <template slot-scope="scope">
          <span class="link2" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</span>
        </template>
      </el-table-column>
      <el-table-column v-if="codes.includes('AAA01')" prop="AAA01" label="姓名" width="120" align="center"></el-table-column>
      <el-table-column v-if="codes.includes('AAC01')" prop="AAC01" label="出院时间" width="160" align="center"></el-table-column>
      <el-table-column prop="YQ_CODE" label="出院院区" width="160" show-overflow-tooltip align="center"></el-table-column>
      <el-table-column prop="AAC11N" label="出院科室" width="160" show-overflow-tooltip align="center"></el-table-column>
      <el-table-column prop="AAC11C" label="出院病房" width="160" show-overflow-tooltip align="center"></el-table-column>
      <el-table-column v-if="codes.includes('AEE08')" prop="AEE08" label="编码员" width="120" align="center"></el-table-column>
      <el-table-column v-if="codes.includes('AEE04')" prop="AEE04" label="住院医师" width="120" align="center"></el-table-column>
      <el-table-column v-if="codes.includes('ICD10_NAME')" prop="ICD10_NAME" label="主要诊断名称" width="160" show-overflow-tooltip align="center"></el-table-column>
      <el-table-column v-if="codes.includes('ICD10_ID1')" prop="ICD10_ID1" label="主要诊断编码" width="160" show-overflow-tooltip align="center"></el-table-column>
      <el-table-column v-if="codes.includes('ICD9_NAME')" prop="ICD9_NAME" label="主要手术名称" width="160" show-overflow-tooltip align="center"></el-table-column>
      <el-table-column v-if="codes.includes('ICD9_ID1')" prop="ICD9_ID1" label="主要手术编码" width="160" show-overflow-tooltip align="center"></el-table-column>
    </el-table>
  </div>
</template>

<script>
import { number } from 'echarts';

export default {
  props: {
    data: {
      type: Array,
      default() {
        return []
      }
    },
    paginationData: {
      type: Object,
      default: {
        page: 1,
        page_size: 10
      }
    },

  },
  data() {
    const defaultCodes = [
      'field_name',
      'AAA28',
      'AAA01',
      'AAC01',
      'AAC11N',
      'AEE08',
      'AEE04',
      'ICD10_NAME',
      'ICD10_ID1',
      'ICD9_NAME',
      'ICD9_ID1'
    ]
    return {
      checkAll: true,
      isIndeterminate: false,
      defaultCodes,
      codes: [
        'field_name',
        'AAA28',
        'AAA01',
        'AAC01',
        'AAC11N',
        'AEE08',
        'AEE04',
        'ICD10_NAME',
        'ICD10_ID1',
        'ICD9_NAME',
        'ICD9_ID1'
      ],
    }
  },

  methods: {
    handleCheckAllChange(val) {
      this.codes = val ? this.defaultCodes : []
      this.isIndeterminate = false
    },
    // 展示字段发生变化
    handleChange(val) {
      const checkedCount = val.length
      this.checkAll = checkedCount === this.defaultCodes.length
      this.codes = val
    },
    toPage(row) {
      this.$router.push({ name: 'MedicalRecordNew', query: { zyh: row.ZYH,'rule_id':this.$route.query.rule_id}})
    },
    onExport() {
      this.$emit('export')
    }
  }
}
</script>

<style lang="scss" scoped>
.table-box {
  margin-bottom: 20px;
  .btn-box {
    text-align: right;
    margin-bottom: 20px;
  }
}
</style>
<style lang="scss">
.table_code_popper {
  .el-checkbox {
    display: block;
    line-height: 26px;
  }
}
</style>
