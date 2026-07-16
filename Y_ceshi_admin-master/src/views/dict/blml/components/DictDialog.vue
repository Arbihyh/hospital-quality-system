<template>
  <div>
    <el-dialog
      v-el-drag-dialog
      title="数据字典"
      :visible.sync="data.bSwitch"
      width="700px"
    >
      <el-form :inline="true" :model="form" class="demo-form-inline">
        <el-form-item label="">
          <el-input v-model="form.field_name" clearable placeholder="名称" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="getData">查询</el-button>
        </el-form-item>
        <el-form-item style="float: right;">
          <el-button type="primary" @click="onCreate">新增</el-button>
        </el-form-item>
      </el-form>
      <el-table
        :data="tableData"
        max-height="400"
        style="width: 100%;"
      >
        <el-table-column
          type="index"
          label="序号"
          align="center"
          width="80"
        />
        <el-table-column
          prop="field"
          label="代码"
        />
        <el-table-column
          prop="field_name"
          label="名称"
        />
        <el-table-column
          prop="remark"
          label="备注"
        />
        <el-table-column
          prop=""
          label="操作"
          align="right"
          fixed="right"
        >
          <template slot-scope="scope">
            <el-button type="text" @click="onEdit(scope.row)">修改</el-button>
            <el-button type="text" style="color: #F56C6C;" @click="onDel(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>
    <!-- 新增 -->
    <CreateDictDialog v-if="createData.bSwitch" :data="createData" @refresh="handleRefresh" />
    <!-- 编辑 -->
    <EditDictDialogVue v-if="editData.bSwitch" :data="editData" @refresh="handleRefresh" />
  </div>
</template>

<script>
import CreateDictDialog from './CreateDictDialog.vue'
import { del_dict, get_field_detail } from '@/api/dict'
import EditDictDialogVue from './EditDictDialog.vue'

export default {
  components: {
    CreateDictDialog,
    EditDictDialogVue
  },
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          row: {}
        }
      }
    }
  },
  data() {
    return {
      createData: {
        bSwitch: false,
        row: {}
      },
      editData: {
        bSwitch: false,
        row: {}
      },
      form: {
        field_name: ''
      },
      tableData: []
    }
  },
  created() {
    this.getData()
  },
  methods: {
    getData() {
      const { id, parent_field } = this.data.row
      const { field_name } = this.form
      get_field_detail({ id, parent_field, field_name }).then(res => {
        const { p } = res
        this.tableData = Array.isArray(p) ? p : []
      })
    },
    onCreate() {
      this.createData.row = {}
      this.createData.row.parent_fildname = this.data.row.parent_fildname
      this.createData.row.parent_field = this.data.row.field_name
      console.log('dictDialog', this.createData.row)
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
          this.getData()
        })
      })
    },
    handleRefresh() {
      this.getData()
    }
  }
}
</script>

<style lang="scss" scoped>

</style>
