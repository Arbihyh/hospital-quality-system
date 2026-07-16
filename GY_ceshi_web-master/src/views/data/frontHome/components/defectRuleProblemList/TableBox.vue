<template>
  <div class="table-box">
    <div class="btn-box">
      <el-radio-group v-model="search.is_bas" @input="handleIsbasChage" style="float: left; line-height: 40px;">
        <el-radio :label="0">全部</el-radio>
        <el-radio :label="1">病案室</el-radio>
      </el-radio-group>
      <el-button type="primary" icon="el-icon-upload" class="export-btn" @click="onExport">下载</el-button>
      <el-popover
        placement="bottom-end"
        title=""
        trigger="click"
        popper-class="table_code_popper"
      >
        <el-checkbox v-model="checkAll" :indeterminate="isIndeterminate" @change="handleCheckAllChange">全选</el-checkbox>
        <el-checkbox-group v-model="codes" @change="handleChange">
          <el-checkbox label="desc">缺陷问题</el-checkbox>
          <el-checkbox label="AAA28">病案号</el-checkbox>
          <el-checkbox label="AAC01">出院时间</el-checkbox>
          <el-checkbox label="zzd">主诊断</el-checkbox>
          <el-checkbox label="zss">主手术</el-checkbox>
          <el-checkbox label="AAA01">患者姓名</el-checkbox>
          <el-checkbox label="AAC11N">出院科室</el-checkbox>
          <el-checkbox label="AEE08">编码员</el-checkbox>
          <el-checkbox label="field">缺陷字段</el-checkbox>
          <el-checkbox label="level">是否强制</el-checkbox>
          <el-checkbox label="is_edit">是否编辑</el-checkbox>
        </el-checkbox-group>
        <el-button slot="reference" type="primary" plain icon="el-icon-setting" style="margin-left: 10px;"></el-button>
      </el-popover>
    </div>
    <el-table
      :data="data"
      style="width: 100%">
      <el-table-column
        type="index"
        label="序号"
        width="50"
        align="center">
        <template slot-scope="scope">
          <span>{{ scope.$index + 1 + (paginationData.page - 1) * paginationData.page_size }}</span>
        </template>
      </el-table-column>
      <el-table-column
        v-if="codes.includes('desc')"
        prop="desc"
        label="缺陷问题"
        show-overflow-tooltip
        width="160"
        align="center">
      </el-table-column>
      <el-table-column
        v-if="codes.includes('AAA28')"
        prop="AAA28"
        label="病案号"
        show-overflow-tooltip
        width="160"
        align="center">
        <template slot-scope="scope">
          <span class="link22" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</span>
        </template>
      </el-table-column>
      <el-table-column
        v-if="codes.includes('AAC01')"
        prop="AAC01"
        label="出院时间"
        show-overflow-tooltip
        width="160"
        align="center">
      </el-table-column>
      <el-table-column
        v-if="codes.includes('zzd')"
        prop="zzd"
        label="主诊断"
        show-overflow-tooltip
        width="160"
        align="center">
        <template slot-scope="scope">
          <span v-if="scope.row.ICD10_NAME">{{ scope.row.ICD10_NAME }}</span>
          <span v-if="scope.row.ICD10_ID1">({{ scope.row.ICD10_ID1 }})</span>
        </template>
      </el-table-column>
      <el-table-column
        v-if="codes.includes('zss')"
        prop="zss"
        label="主手术"
        show-overflow-tooltip
        width="160"
        align="center">
        <template slot-scope="scope">
          <span v-if="scope.row.ICD9_NAME">{{ scope.row.ICD9_NAME }}</span>
          <span v-if="scope.row.ICD9_ID1">({{ scope.row.ICD9_ID1 }})</span>
        </template>
      </el-table-column>
      <el-table-column
        v-if="codes.includes('AAA01')"
        prop="AAA01"
        label="患者姓名"
        show-overflow-tooltip
        width="160"
        align="center">
      </el-table-column>
      <el-table-column
        v-if="codes.includes('AAC11N')"
        prop="AAC11N"
        label="出院科室"
        show-overflow-tooltip
        width="160"
        align="center">
      </el-table-column>
      <el-table-column
        v-if="codes.includes('AEE08')"
        prop="AEE08"
        label="编码员"
        show-overflow-tooltip
        width="160"
        align="center">
      </el-table-column>
      <el-table-column
        v-if="codes.includes('type')"
        prop="type"
        label="缺陷分类"
        show-overflow-tooltip
        width="160"
        align="center">
      </el-table-column>
      <el-table-column
        v-if="codes.includes('field')"
        prop="field"
        label="缺陷字段"
        show-overflow-tooltip
        width="160"
        align="center">
      </el-table-column>
      <el-table-column
        v-if="codes.includes('level')"
        prop="level"
        label="是否强制"
        show-overflow-tooltip
        width="160"
        align="center">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.level === '强制'" type="danger" size="mini">强制</el-tag>
          <el-tag v-else type="success" size="mini">建议</el-tag>
        </template>
      </el-table-column>
      <el-table-column
        v-if="codes.includes('is_edit')"
        prop="is_edit"
        label="是否编辑"
        show-overflow-tooltip
        width="160"
        align="center">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.is_edit === 1" type="warning" size="mini">是</el-tag>
          <el-tag v-if="scope.row.is_edit === 2" type="info" size="mini">否</el-tag>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
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
      search: {
        type: Object,
        default() {
          return {
            AAA28: '',
            AAC11C: '',
            AAC01: [],
            AEE04_CODE: '',
            AEE08_CODE: '',
            ICD9_ID1: '',
            ICD9_NAME: '',
            ICD10_ID1: '',
            ICD10_NAME: '',
            is_bas: 1
          };
        },
      },
    },
    data() {
      const defaultCodes = [
        'desc',
        'AAA28',
        'AAC01',
        'zzd',
        'zss',
        'AAA01',
        'AAC11N',
        'AEE08',
        'field',
        'level',
        'is_edit'
      ]
      return {
        checkAll: true,
        isIndeterminate: false,
        defaultCodes,
        codes: [
          'desc',
          'AAA28',
          'AAC01',
          'zzd',
          'zss',
          'AAA01',
          'AAC11N',
          'AEE08',
          'field',
          'level',
          'is_edit'
        ],
      }
    },
    methods: {
      handleIsbasChage() {
        this.$emit('basChange')
      },
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
      onExport() {
        this.$emit('export')
      },
      toPage(row) {
        // MED_REC_ID  非临床住院号
        // ZYH  临床住院号
        const { MED_REC_ID, ZYH } = row
        this.storageSet('getData', MED_REC_ID ? MED_REC_ID : ZYH);
        const toPath = '/details'
        this.$router.push({ path: toPath });
      },
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

.link22 {
  cursor: pointer;
  color: #409EFF;
}
</style>