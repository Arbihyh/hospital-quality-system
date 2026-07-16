<template>
  <div class="app-container">
    <el-form v-show="showSearch" ref="queryForm" :model="queryParams" size="small" :inline="true" label-width="68px">
      <el-form-item label="名称" prop="name">
        <el-input
          v-model="queryParams.name"
          placeholder="请输入管理员名称"
          clearable
          style="width: 240px;"
          @keyup.enter.native="handleQuery"
        />
      </el-form-item>
      <el-form-item label="状态" prop="status">
        <el-select
          v-model="queryParams.status"
          placeholder="操作状态"
          clearable
          style="width: 240px"
        >
          <el-option
            v-for="dict in groupList"
            :key="dict.id"
            :label="dict.name"
            :value="dict.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" icon="el-icon-search" @click="handleQuery">搜索</el-button>
        <el-button icon="el-icon-refresh" @click="handleResetQuery">重置</el-button>
      </el-form-item>
    </el-form>
    <el-row :gutter="10" class="mb8">
      <el-col :span="17" style="margin-bottom: -15px;">
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
            @click="clearForm();dialogVisible=true;"
          >新增</el-button>
        </el-col>
      </el-col>
      <el-col :span="7" style="margin-bottom: -15px;">
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
              placeholder="请输入管理员名称"
              clearable
              style="width: 240px;"
              @keyup.enter.native="handleQuery"
            />
          </el-form-item>
          <el-form-item style="margin-right:0">
            <el-tooltip class="item" effect="dark" :content="showSearch ? '隐藏搜索' : '更多搜索'" placement="top">
              <el-button size="small" icon="el-icon-search" @click="toggleSearch()" />
            </el-tooltip>
          </el-form-item>
        </el-form>
      </el-col>
    </el-row>
    <el-table v-loading="listLoading" :data="pageList">
      <el-table-column type="index" label="ID" />
      <el-table-column prop="account" label="登陆名" />
      <el-table-column prop="name" label="名称" />
      <el-table-column prop="login_at" label="登陆时间" />
      <el-table-column prop="login_ip" label="登陆IP" />
      <el-table-column prop="group_name" label="账号组" />
      <el-table-column prop="desc" label="描述" />
      <el-table-column label="操作">
        <template slot-scope="scope">
          <el-button type="primary" icon="el-icon-edit" size="mini" circle @click="editAlert(scope.row)" />
          <el-button
            v-if="scope.row.status === 1"
            slot="reference"
            type="danger"
            icon="el-icon-delete"
            size="mini"
            circle
            @click="delAdminAlert(scope.row)"
          />
        </template>
      </el-table-column>
    </el-table>
    <pagination
      :total="count"
      :page.sync="queryParams.pageNum"
      :limit.sync="queryParams.pageSize"
      @pagination="getList"
    />

    <el-dialog title="提示" :visible.sync="dialogVisible" :close-on-click-modal="false" width="30%">
      <el-form ref="form" :model="alertForm">
        <el-form-item label="名称：">
          <el-input v-model="alertForm.name" placeholder="名称" />
        </el-form-item>
        <el-form-item label="登陆名：">
          <el-input v-model="alertForm.account" placeholder="登陆名" />
        </el-form-item>
        <el-form-item label="密码：">
          <el-input v-model="alertForm.pwd" :type="passwordType" placeholder="密码">
            <i
              slot="suffix"
              :class="'el-input__icon '+(passwordType === 'password' ? 'el-icon-open' : 'el-icon-turn-off')"
              style="cursor:pointer;"
              @click="passwordType = (passwordType === 'password' ? 'text' : 'password')"
            />
          </el-input>
        </el-form-item>
        <el-form-item label="账号组：">
          <el-select
            v-if="parseInt(alertForm.id) === 1"
            v-model="alertForm.group_id"
            disabled
          >
            <el-option
              v-for="(v,k) in groupList"
              :key="k"
              :label="v.name"
              :value="parseInt(v.id)"
            />
          </el-select>

          <el-select v-else v-model="alertForm.group_id">
            <el-option
              v-for="(v,k) in groupList"
              :key="k"
              :label="v.name"
              :value="parseInt(v.id)"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="描述：">
          <el-input v-model="alertForm.desc" type="textarea" placeholder="描述" />
        </el-form-item>
      </el-form>

      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible=false">取 消</el-button>
        <el-button v-if="dialogType === 1" type="primary" @click="addSubmit">确 定</el-button>
        <el-button v-else type="primary" @click="editSubmit">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { addAdmin, adminGroup, adminList, delAdmin, editAdmin } from '@/api/admin'

export default {
  data() {
    return {
      listCount: 0,
      pageList: [],
      listLoading: false,
      showSearch: false,
      search: true,
      queryParams: {
        page: 1,
        limit: 15,
        name: undefined
      },
      // 以下不是
      passwordType: 'password',
      dialogVisible: false,
      visible: false,
      dialogType: 1, // 0修改 1添加
      // pageList: [],
      loging: false,
      form: {
        group_id: null,
        name: null,
        page: 1,
        length: 16
      },
      alertForm: {
        id: null,
        name: null,
        account: null,
        pwd: null,
        group_id: null,
        desc: null
      },
      delForm: {
        id: null,
        name: null
      },
      count: 0,
      groupList: []
    }
  },
  created() {
    const groupId = this.$route.query.groupId
    if (groupId) {
      this.alertForm.group_id = groupId
      this.form.group_id = groupId
    }

    this.getList()
    this.getAdminGroup()
    // this.text()
  },
  methods: {
    // 搜索
    toggleSearch() {
      this.showSearch = !this.showSearch
    },
    handleRefresh() {
      this.handleQuery()
    },
    handleResetQuery() {
      this.queryParams = {
        page: 1,
        limit: 10,
        name: undefined
      }
      this.getList()
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.page = 1
      this.getList()
    },
    getList() {
      this.listLoading = true
      adminList(this.queryParams).then(res => {
        this.pageList = res.p.list
        this.listCount = res.p.count
        this.listLoading = false
      }).catch(error => {
        console.log(error)
      })
    },
    // 以下不是
    addSubmit() {
      addAdmin(this.alertForm).then(res => {
        this.$message.success(res.m)
        this.getList()
        this.dialogVisible = false
      })
    },
    editAlert(data) {
      this.alertForm.id = data.id
      this.alertForm.account = data.account
      this.alertForm.name = data.name
      this.alertForm.group_id = parseInt(data.group_id)
      this.alertForm.desc = data.desc
      this.dialogVisible = true
      this.dialogType = 0
    },
    editSubmit() {
      editAdmin(this.alertForm).then(res => {
        this.$message.success(res.m)
        this.dialogVisible = false
        this.getList()
      })
    },
    clearForm() {
      this.alertForm = {
        id: null,
        name: null,
        account: null,
        pwd: null,
        group_id: null,
        desc: null
      }
    },
    getAdminGroup() {
      adminGroup().then(res => {
        this.groupList = res.p
      })
    },
    delAdminAlert(data) {
      this.delForm.id = data.id
      this.delForm.name = data.name
      this.visible = true
    },
    delAdmin() {
      delAdmin({ id: this.delForm.id }).then(res => {
        this.$message.success(res.m)
        this.visible = false
        this.getList()
      })
    }
  }
}
</script>

<style scoped>

</style>
