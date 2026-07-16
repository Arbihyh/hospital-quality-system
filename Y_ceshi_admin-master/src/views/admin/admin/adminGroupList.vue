<template>
  <div class="app-container">
    <el-dialog
      title="提示"
      :visible.sync="dialogVisible"
      :close-on-click-modal="false"
      width="30%"
    >
      <el-form label-position="left" label-width="60px">
        <el-form-item label="组名">
          <el-input v-model="form.name" label="组名：" placeholder="权限组名" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.desc" type="textarea" label="描述：" placeholder="权限组描述" />
        </el-form-item>
        <el-form-item label="权限">
          <div>
            <el-tree
              ref="rbacTree"
              :data="rbacList"
              :show-checkbox="form.role !== 'all'"
              accordion
              node-key="id"
              :default-checked-keys="checkedList"
              :props="defaultProps"
            />
          </div>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible=false">取 消</el-button>
        <el-button v-if="act === 1" type="primary" @click="addSubmit">确 定</el-button>
        <el-button v-else type="primary" @click="editSubmit">确 定</el-button>
      </span>
    </el-dialog>
    <el-row :gutter="10" class="mb8">
      <el-col :span="13" style="margin-bottom: -15px;">
        <el-col :span="1.5">
          <el-tooltip class="item" effect="dark" content="刷新" placement="top">
            <el-button size="small" icon="el-icon-refresh" @click="handleRefresh" />
          </el-tooltip>
        </el-col>
        <el-col :span="1.5">
          <el-button
            type="primary"
            plain
            icon="el-icon-plus"
            size="small"
            @click="dialogVisible=true;getRbac();"
          >新增</el-button>
        </el-col>
      </el-col>
      <el-col :span="11" style="margin-bottom: -15px;">
        <el-form
          ref="queryForm"
          :model="queryParams"
          size="small"
          :inline="true"
          label-width="68px"
          style="text-align:right;"
        >
          <el-form-item label="名称" prop="name">
            <el-input
              v-model="queryParams.name"
              size="small"
              placeholder="请输入账号组名称"
              clearable
              style="width: 240px;"
              @keyup.enter.native="handleQuery"
            />
          </el-form-item>
          <el-form-item style="margin-right:0">
            <el-button size="small" type="primary" icon="el-icon-search" @click="handleQuery">搜索</el-button>
            <el-button size="small" icon="el-icon-refresh" @click="handleResetQuery">重置</el-button>
          </el-form-item>
        </el-form>
      </el-col>
    </el-row>
    <el-table v-loading="listLoading" :data="pageList">
      <el-table-column type="index" label="序号" />
      <el-table-column prop="name" label="账号组" />
      <el-table-column prop="admin_name" label="所有者" />
      <el-table-column prop="desc" label="描述" />
      <el-table-column label="操作">
        <template slot-scope="scope">
          <!--          <el-button-->
          <!--            type="success"-->
          <!--            icon="el-icon-tickets"-->
          <!--            circle-->
          <!--            size="mini"-->
          <!--            @click="link('/admin/adminList',{ groupId: scope.row.id })"-->
          <!--          />-->
          <el-button type="primary" icon="el-icon-edit" circle size="mini" @click="editAlert(scope.row)" />
          <el-button
            v-if="scope.row.status === 1"
            type="danger"
            icon="el-icon-delete"
            circle
            size="mini"
            @click="delAdminGroupAlert(scope.row)"
          />
        </template>
      </el-table-column>
    </el-table>
    <el-dialog
      title="提示"
      :visible.sync="visible"
      width="30%"
      center
    >
      <span>确定删除 <b style="color: red">"{{ delForm.name }}"</b> 吗？？？？</span>
      <span slot="footer" class="dialog-footer">
        <el-button @click="visible = false">取 消</el-button>
        <el-button type="primary" @click="delAdminGroup">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { addAdminGroup, adminGroupList, delAdminGroup, editAdminGroup, rbacList } from '@/api/admin'

export default {
  data() {
    return {
      listCount: 0,
      pageList: [],
      listLoading: true,
      // 显示搜索条件
      showSearch: false,
      search: true,
      // 查询参数
      queryParams: {
        pageNum: 1,
        pageSize: 10,
        name: undefined
      },
      visible: false,
      dialogVisible: false,
      defaultProps: {
        children: 'children',
        label: 'name'
      },
      rbacList: [],
      checkedList: [],
      delForm: {
        id: null,
        name: null
      },
      form: {
        name: null,
        desc: null,
        role: null
      },
      act: 1 // 0修改 1添加
    }
  },
  created() {
    this.list()
  },
  methods: {
    handleRefresh() {
      this.handleQuery()
    },
    handleResetQuery() {
      this.queryParams = {
        name: undefined
      }
      this.list()
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.page = 1
      this.list()
    },
    list() {
      this.listLoading = true
      adminGroupList(this.queryParams).then(res => {
        this.pageList = res.p
        this.listLoading = false
      }).catch(error => {
        console.log(error)
      })
    },
    // list() {
    //   adminGroupList().then(res => {
    //     this.pageList = res.p
    //   })
    // },
    getRbac() {
      rbacList().then(resArr => {
        this.rbacList = resArr.p
      })
    },
    resetForm() {
      this.form = {
        name: null,
        desc: null,
        role: null
      }
    },
    addSubmit() {
      this.form['role'] = this.getChecked()
      addAdminGroup(this.form).then(res => {
        this.$message.success(res.m || 'ok')
        this.list()
        this.resetForm()
        this.dialogVisible = false
      })
    },
    getChecked() {
      let checked = []
      const data = this.$refs.rbacTree.getCheckedKeys()
      checked = data.filter(res => {
        return res !== undefined
      })
      return checked
    },
    editAlert(data) {
      this.act = 0
      this.id = data.id
      this.form.name = data.name
      this.form.desc = data.desc
      this.form.role = data.role
      if (data.role !== 'all') {
        this.checkedList = data.role
      }
      this.getRbac()
      this.dialogVisible = true
    },
    editSubmit() {
      this.form.id = this.id
      this.form['role'] = this.getChecked()
      editAdminGroup(this.form).then(res => {
        this.$message.success(res.m || 'ok')
        this.list()
        this.dialogVisible = false
      })
    },
    delAdminGroupAlert(data) {
      this.delForm.id = data.id
      this.delForm.name = data.name
      this.visible = true
    },
    delAdminGroup() {
      delAdminGroup({ id: this.delForm.id }).then(res => {
        this.$message.success(res.m)
        this.visible = false
        this.list()
      })
    },
    link(url, data) {
      this.$router.push({
        path: url,
        query: data
      })
    }
  }
}
</script>

<style scoped>

</style>
