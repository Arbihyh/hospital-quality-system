<template>
  <div>
    <div class="btn-box">
      <el-select v-model="ruleType" placeholder="请选择规则类型" style="width: 150px; margin-right: 10px;">
        <el-option label="病历规则" :value="1" />
        <el-option label="首页规则" :value="2" />
        <el-option label="门诊规则" :value="3" />
      </el-select>
      <el-button type="primary" icon="el-icon-plus" style="margin-right: 10px;" @click="onCreate">新增</el-button>
      <el-popover
        placement="bottom-end"
        title=""
        trigger="click"
        popper-class="table_code_popper"
      >
        <el-checkbox v-model="checkAll" :indeterminate="isIndeterminate" @change="handleCheckAllChange">全选</el-checkbox>
        <el-checkbox-group v-model="codes" @change="handleChange">
          <el-checkbox label="description">规则描述</el-checkbox>
          <el-checkbox label="department">质控科室</el-checkbox>
          <el-checkbox label="is_not">单项否决</el-checkbox>
          <el-checkbox label="status">质控状态</el-checkbox>
          <el-checkbox label="type">质控类型</el-checkbox>
          <el-checkbox label="changjing">质控场景</el-checkbox>
          <el-checkbox label="error_level">风险等级</el-checkbox>
          <el-checkbox label="score">扣分</el-checkbox>
          <el-checkbox label="rule">质控规则</el-checkbox>
        </el-checkbox-group>
        <el-button slot="reference" type="primary" icon="el-icon-setting">设置</el-button>
      </el-popover>
    </div>
    <el-table
      v-loading="loading"
      :data="data"
      border
      :max-height="630"
      style="width: 100%"
    >
      <el-table-column
        type="index"
        label="序号"
        width="80"
        fixed="left"
        align="center"
      />
      <el-table-column
        prop="case_type"
        :label="(ruleType === 1 || ruleType === 3) ? '病历类型' : '首页字段'"
        fixed="left"
        align="center"
        width="120"
      />
      <el-table-column
        prop="object"
        :label="(ruleType === 1 || ruleType === 3) ? '质控项目' : '缺陷类别'"
        align="center"
        width="240"
      />
      <el-table-column
        v-if="codes.includes('description')"
        prop="description"
        label="规则描述"
        min-width="240"
        align="center"
      />
      <el-table-column
        v-if="codes.includes('department')"
        prop="department"
        :label="(ruleType === 1 || ruleType === 3) ? '质控科室' : '缺陷类型'"
        min-width="120"
        align="center"
      >
        <template slot-scope="scope">
          {{ formatDepartment(scope.row.department) }}
        </template>
      </el-table-column>
      <el-table-column
        v-if="codes.includes('is_not')"
        prop="is_not"
        label="单项否决"
        align="center"
        width="120"
      >
        <template slot-scope="scope">
          <el-tag v-if="scope.row.is_not" type="primary">是</el-tag>
          <el-tag v-else type="info">否</el-tag>
        </template>
      </el-table-column>
      <el-table-column
        v-if="codes.includes('status')"
        prop="status"
        label="质控状态"
        align="center"
        width="120"
      >
        <template slot-scope="scope">
          <el-switch
            v-model="scope.row.status"
            active-color="#13ce66"
            :active-value="1"
            :inactive-value="2"
            @change="handleStatusChange(scope.row)"
          />
        </template>
        <!-- <template slot-scope="scope">
          <el-tag v-if="scope.row.status === 1" type="success">开启中</el-tag>
          <el-tag v-if="scope.row.status === 2" type="danger">禁用中</el-tag>
        </template> -->
      </el-table-column>
      <el-table-column
        v-if="codes.includes('type')"
        prop="type"
        label="质控类型"
        align="center"
        width="120"
      />
      <el-table-column
        v-if="codes.includes('changjing')"
        prop="changjing"
        label="质控场景"
        align="center"
        width="120"
      />
      <el-table-column
        v-if="codes.includes('error_level')"
        prop="error_level"
        label="风险等级"
        align="center"
        width="120"
      >
        <template slot-scope="scope">
          <el-tag v-if="scope.row.error_level === 1" type="danger">必改</el-tag>
          <el-tag v-if="scope.row.error_level === 2" type="primary">建议</el-tag>
          <!-- <el-tag v-if="scope.row.error_level === 3" type="danger">高风险</el-tag> -->
        </template>
      </el-table-column>
      <el-table-column
        v-if="codes.includes('score')"
        prop="score"
        label="扣分"
        align="center"
        width="120"
      />
      <el-table-column
        v-if="codes.includes('rule')"
        prop=""
        label="质控规则"
        align="center"
        width="120"
      >
        <template slot-scope="scope">
          <el-button type="primary" size="mini" @click="onRuleDetail(scope.row)">查看详情</el-button>
        </template>
      </el-table-column>
      <el-table-column
        prop=""
        label="操作"
        align="center"
        fixed="right"
        width="140"
      >
        <template slot-scope="scope">
          <el-button type="text" @click="onEdit(scope.row)">修改</el-button>
          <el-button type="text" style="color: #F56C6C;" @click="onDel(scope.row)">删除</el-button>
          <el-button type="text" @click="onTest">测试</el-button>
        </template>
      </el-table-column>
    </el-table>
    <!-- 新增、编辑 病历规则 -->
    <CreateDialog v-if="createData.bSwitch" :rule-type="ruleType" :data="createData" :departments="departments" :objects="objects" @refresh="handleRefresh" />
    <!-- 新增、编辑 首页规则 -->
    <HomePageRuleDialog v-if="homePageRuleData.bSwitch" :rule-type="ruleType" :data="homePageRuleData" :departments="departments" :objects="objects" @refresh="handleRefresh" />
    <!-- 规则详情 -->
    <RuleDialog v-if="ruleData.bSwitch" :data="ruleData" :departments="departments" :objects="objects" />
  </div>
</template>

<script>
import CreateDialog from './CreateDialog.vue'
import RuleDialog from './RuleDialog.vue'
import HomePageRuleDialog from './HomePageRuleDialog.vue'
import { del_rule, edit_rule_status } from '@/api/rule/config'

export default {
  components: {
    CreateDialog,
    RuleDialog,
    HomePageRuleDialog
  },
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
    objects: {
      type: Array,
      default() {
        return []
      }
    },
    departments: {
      type: Array,
      default() {
        return []
      }
    }
  },
  data() {
    return {
      ruleType: 1,
      codes: [
        'changjing',
        'department',
        'type',
        'is_not',
        'score',
        'error_level',
        'status',
        'description',
        'rule'
      ],
      defaultCodes: [],
      checkAll: true,
      isIndeterminate: false,
      createData: {
        bSwitch: false,
        row: {}
      },
      homePageRuleData: {
        bSwitch: false,
        row: {}
      },
      ruleData: {
        bSwitch: false,
        row: {}
      },
      statusArr: [
        { 'id': 1, 'name': '启用' },
        { 'id': 2, 'name': '停用' }
      ]
    }
  },
  watch: {
    ruleType(newVal) {
      this.$emit('refresh', { rule_type: newVal })
    }
  },
  created() {
    this.defaultCodes = JSON.parse(JSON.stringify(this.codes))
  },
  methods: {
    onTest() {
      this.$message.info('功能待开放...')
    },
    onCreate() {
      if (this.ruleType === 1 || this.ruleType === 3) {
        // 病历规则
        this.createData.row = { rule_type: this.ruleType }
        this.createData.bSwitch = true
      } else if (this.ruleType === 2) {
        // 首页规则
        this.homePageRuleData.row = { rule_type: this.ruleType }
        this.homePageRuleData.bSwitch = true
      }
    },
    onEdit(row) {
      if (this.ruleType === 1 || this.ruleType === 3) {
        // 病历规则
        this.createData.row = row
        this.createData.bSwitch = true
        this.createData.actionType = 'EDIT'
      } else if (this.ruleType === 2) {
        // 首页规则
        this.homePageRuleData.row = row
        this.homePageRuleData.bSwitch = true
      }
    },
    handleRefresh() {
      this.$emit('refresh', { rule_type: this.ruleType })
    },
    // 删除规则
    onDel(row) {
      this.$confirm('是否确认删除该数据?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        del_rule({ id: row.id }).then((res) => {
          this.$message.success(res.m || '操作成功')
          this.$emit('refresh')
        })
      })
    },
    // 查看规则
    onRuleDetail(row) {
      if (this.ruleType === 1 || this.ruleType === 3) {
        // 病历规则
        this.createData.row = row
        this.createData.bSwitch = true
        this.createData.actionType = 'DETAIL'
      } else {
        this.ruleData.row = row
        this.ruleData.bSwitch = true
      }
    },
    handleCheckAllChange(val) {
      this.codes = val ? this.defaultCodes : []
      this.isIndeterminate = false
    },
    // 展示字段发生变化
    handleChange(val) {
      const checkedCount = val.length
      this.checkAll = checkedCount === this.defaultCodes.length
    },
    handleStatusChange(row) {
      const statusIndex = this.statusArr.findIndex((value) => parseInt(value.id) === parseInt(row.status))
      this.$confirm('确认要更改为 <strong>' + this.statusArr[statusIndex].name + '</strong> 状态吗？', '提示', {
        dangerouslyUseHTMLString: true,
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        edit_rule_status({ id: row.id, status: row.status }).then((res) => {
          this.$message.success(res.m || '操作成功')
        }).catch(function() {
          row.status = row.status === 1 ? 2 : 1
        })
      }).catch(function() {
        row.status = row.status === 1 ? 2 : 1
      })
    },
    formatDepartment(department) {
      if (!department || (Array.isArray(department) && department.length === 0)) {
        return '通用'
      }
      // 如果需要显示科室名称而不是编码，可以通过 departments 数据查找对应的名称
      return Array.isArray(department) ? department.join('、') : department
    }
  }
}
</script>

<style lang="scss" scoped>
.btn-box {
  text-align: right;
  margin-bottom: 15px;
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
