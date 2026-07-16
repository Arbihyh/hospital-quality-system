<template>
  <div class="part-box">

    <div class="btn-box" style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <span class="page-msg">
          共找到 <span class="num">{{ page.total }}</span> 条结果
          <span class="page" v-if="page.total">{{ page.page }}/{{ Math.ceil(page.total / page.limit) }}</span>
          <span class="page_limit_box">
            显示
            <el-select v-model="page.limit" size="mini" @change="handleLimitChange" style="width: 100px;">
              <el-option label="10条/页" :value="10"></el-option>
              <el-option label="20条/页" :value="20"></el-option>
              <el-option label="30条/页" :value="30"></el-option>
              <el-option label="50条/页" :value="50"></el-option>
            </el-select>
          </span>
        </span>
      </div>

      <div>
        <el-button type="primary" icon="el-icon-download" @click="onExport" class="export-btn">导出数据</el-button>
        <el-button icon="el-icon-refresh" @click="onReset"
          style="position: absolute; top: -60px; right: 0px;">重置条件</el-button>
        <el-popover placement="bottom-end" title="" trigger="click" popper-class="table_code_popper">
          <el-checkbox v-model="checkAll" :indeterminate="isIndeterminate"
            @change="handleCheckAllChange">全选</el-checkbox>
          <el-checkbox-group v-model="showCodes" @change="handleChange">
            <el-checkbox label="field">缺陷字段</el-checkbox>
            <el-checkbox label="desc">缺陷描述</el-checkbox>
            <el-checkbox label="AAA28">住院号码</el-checkbox>
            <el-checkbox label="AAA01">姓名</el-checkbox>
            <el-checkbox label="time">出院时间</el-checkbox>
            <el-checkbox label="AAC11N">出院科室</el-checkbox>
            <el-checkbox label="AEE08">编码员</el-checkbox>
            <el-checkbox label="AEE04">住院医师</el-checkbox>
            <el-checkbox label="ICD10_NAME">主要诊断名称</el-checkbox>
            <el-checkbox label="ICD10_ID1">主要诊断编码</el-checkbox>
            <el-checkbox label="ICD9_NAME">主要手术名称</el-checkbox>
            <el-checkbox label="ICD9_ID1">主要手术编码</el-checkbox>
            <el-checkbox label="level">缺陷分级</el-checkbox>
            <el-checkbox label="type">缺陷归类</el-checkbox>
          </el-checkbox-group>
          <el-button slot="reference" icon="el-icon-setting" style="margin-left: 10px;"></el-button>
        </el-popover>
      </div>

    </div>
    <el-table v-loading="loading" :data="data" @sort-change="handleSortChange" style="width: 100%">
      <el-table-column type="index" label="序号" align="center" width="50">
      </el-table-column>
      <el-table-column v-if="codes.includes('field')" prop="field" label="缺陷字段" width="160" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('desc')" prop="desc" label="缺陷描述" width="160" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('AAA28')" prop="AAA28" label="住院号码" width="100" align="center">
        <template slot-scope="scope">
          <span class="link" @click="toPage(scope.row)">{{ scope.row.AAA28 }}</span>
        </template>
      </el-table-column>
      <el-table-column v-if="codes.includes('AAA01')" prop="AAA01" label="姓名" width="100" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('time')" prop="AAC01" label="出院时间" sortable width="160" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('AAC11N')" prop="AAC11N" label="出院科室" width="160" align="center">
      </el-table-column>
      <!-- <el-table-column
        v-if="codes.includes('AEE08')"
        prop=""
        label="编码员"
        width="100"
        align="center">
        <template slot-scope="scope">
          <span>{{ type_name ? scope.row.coder_name : scope.row.AEE08 }}</span>
        </template>
      </el-table-column> -->
      <el-table-column v-if="codes.includes('AEE04')" prop="" label="住院医师" width="100" align="center">
        <template slot-scope="scope">
          <span>{{ type_name ? scope.row.ZYYS : scope.row.AEE04 }}</span>
        </template>
      </el-table-column>
      <el-table-column v-if="codes.includes('ICD10_NAME')" prop="ICD10_NAME" label="主要诊断名称" width="160" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('ICD10_ID1')" prop="ICD10_ID1" label="主要诊断编码" width="160" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('ICD9_NAME')" prop="ICD9_NAME" label="主要手术名称" width="200" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('ICD9_ID1')" prop="ICD9_ID1" label="主要手术编码" width="120" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('level')" prop="level" label="缺陷分级" width="100" align="center">
      </el-table-column>
      <el-table-column v-if="codes.includes('type')" prop="type" label="缺陷归类" width="120" align="center">
      </el-table-column>
      <!-- <el-table-column label="质控审核人员" width="120" align="center">
        <template>
          <span>林明霞</span>
        </template>
      </el-table-column> -->
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
    loading: {
      type: Boolean,
      default() {
        return false
      }
    },
    codes: {
      type: Array,
      default() {
        return []
      }
    },
    type_name: {  // 'lc' 临床
      type: String,
      default() {
        return ''
      }
    },
    hospital_name: {
      type: String,
      default() {
        return ''
      }
    },
    page: {
      type: Object,
      default() {
        return {
          total: 0,
          page: 1,
          limit: 10,
        }
      }
    }
  },
  data() {
    const defaultCodes = [
      'field',
      'desc',
      'AAA28',
      'AAA01',
      'time',
      'AAC11N',
      'AEE08',
      'AEE04',
      'type',
      'level',
      'ICD10_NAME',
      'ICD10_ID1',
      'ICD9_NAME',
      'ICD9_ID1'
    ]
    return {
      defaultCodes,
      showCodes: [],
      checkAll: true,
      isIndeterminate: false
    }
  },
  created() {
    this.showCodes = JSON.parse(JSON.stringify(this.codes))
  },
  methods: {
    onExport() {
      this.$emit('export')
    },
    onReset() {
      this.$emit('reset')
    },
    handleCheckAllChange(val) {
      this.showCodes = val ? this.defaultCodes : []
      this.isIndeterminate = false
      this.$emit('codesChange', this.showCodes)
    },
    // 展示字段发生变化
    handleChange(val) {
      const checkedCount = val.length
      this.checkAll = checkedCount === this.defaultCodes.length
      this.$emit('codesChange', val)
    },
    toPage(row) {
      // MED_REC_ID  非临床住院号
      // ZYH  临床住院号
      const { MED_REC_ID, ZYH } = row
      this.storageSet('getData', MED_REC_ID ? MED_REC_ID : ZYH);
      const toPath = '/details'
      this.$router.push({ path: toPath, query: { type_name: this.type_name, error_field: row.auth } });
    },

    handleSortChange(column) {
      const { prop, order } = column;
      let sort = []
      let str = '';
      if (order === 'descending') {
        str = 'desc';
      } else if (order === 'ascending') {
        str = 'asc';
      } else {
        str = null;
      }
      if (str) {
        sort = [prop, str]
      } else {
        sort = []
      }
      this.$emit('sort', sort)
    },
    handleLimitChange(val) {
      this.$emit('limit_change', val)
    }
  }
}
</script>

<style lang="scss" scoped>
.btn-box {
  text-align: right;
  margin-bottom: 15px;

  .page-msg {
    float: left;
    height: 40px;
    line-height: 40px;
    font-size: 14px;

    .num {
      color: #F56C6C;
    }

    .page {
      margin-left: 40px;
    }

    .page_limit_box {
      margin-left: 40px;
    }
  }
}

.link {
  cursor: pointer;
  color: #409EFF;
}
</style>
