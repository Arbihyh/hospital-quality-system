<template>
  <div>
    <div class="btn-box">
      <el-button type="primary" icon="el-icon-plus" @click="onCreate">新增</el-button>
    </div>
    <el-table
      v-loading="loading"
      :data="data"
      border
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
        prop="parent_field"
        label="数据表"
        align="center"
      />
      <el-table-column
        prop="parent_fildname"
        label="数据表名称"
        align="center"
      />
      <el-table-column
        prop="field"
        label="表字段"
        align="center"
      />
      <el-table-column
        prop="field_name"
        label="字段名称"
        align="center"
      />
      <el-table-column
        prop=""
        label="数据字典"
        width="130"
        align="center"
      >
        <template slot-scope="scope">
          <el-button type="text" style="color: #E6A23C;" @click="onShowDict(scope.row)">查看</el-button>
        </template>
      </el-table-column>
      <el-table-column key="status" label="状态" width="130" align="center">
        <template slot-scope="scope">
          <el-switch
            v-model="scope.row.status"
            active-color="#13ce66"
            :active-value="1"
            :inactive-value="2"
            @change="handleStatusChange(scope.row)"
          />
        </template>
      </el-table-column>
      <el-table-column
        prop="remark"
        label="备注"
        align="center"
      />
      <el-table-column
        prop=""
        label="操作"
        align="center"
        fixed="right"
      >
        <template slot-scope="scope">
          <el-button type="text" @click="onEdit(scope.row)">修改</el-button>
          <el-button type="text" style="color: #F56C6C;" @click="onDel(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <!-- 新增 -->
    <CreateDialog v-if="createData.bSwitch" :data="createData" @refresh="handleRefresh" />
    <!-- 编辑 -->
    <EditFieldDialogVue v-if="editData.bSwitch" :data="editData" @refresh="handleRefresh" />
    <!-- 查看数据字典 -->
    <DictDialog v-if="dictData.bSwitch" :data="dictData" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CreateDialog from './CreateDialog.vue'
import DictDialog from './DictDialog.vue'
import { edit_dict_status, del_dict } from '@/api/dict'
import EditFieldDialogVue from './EditFieldDialog.vue'

export default {
  components: {
    CreateDialog,
    DictDialog,
    EditFieldDialogVue
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
    }
  },
  data() {
    return {
      statusArr: [
        { 'id': 1, 'name': '启用' },
        { 'id': 2, 'name': '停用' }
      ],
      createData: {
        bSwitch: false,
        row: {},
        type: 1
      },
      dictData: {
        bSwitch: false,
        row: {}
      },
      editData: {
        bSwitch: false,
        row: {}
      }
    }
  },
  methods: {
    onCreate() {
      this.createData.row = {}
      this.createData.bSwitch = true
    },
    onEdit(row) {
      this.editData.row = row
      this.editData.bSwitch = true
    },
    onDel(row) {
      this.$confirm('是否确认删除该数据?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        del_dict({ id: row.id }).then((res) => {
          this.$message.success(res.m || '操作成功')
          this.$emit('refresh')
        })
      })
    },
    handleRefresh() {
      this.$emit('refresh')
    },
    handleStatusChange(row) {
      const statusIndex = this.statusArr.findIndex((value) => parseInt(value.id) === parseInt(row.status))
      this.$confirm('确认要更改为 <strong>' + this.statusArr[statusIndex].name + '</strong> 状态吗？', '提示', {
        dangerouslyUseHTMLString: true,
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        edit_dict_status({ id: row.id, status: row.status }).then((res) => {
          this.$message.success(res.m || '操作成功')
        }).catch(function() {
          row.status = row.status === 2 ? 1 : 2
        })
      }).catch(function() {
        row.status = row.status === 2 ? 1 : 2
      })
    },
    onShowDict(row) {
      this.dictData.row = row
      this.dictData.bSwitch = true
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
