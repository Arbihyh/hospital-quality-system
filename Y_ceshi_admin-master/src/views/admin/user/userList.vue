<template>
  <div class="app-container">
    <el-dialog
      title="提示"
      :visible.sync="dialogVisible"
      :close-on-click-modal="false"
      width="30%"
    >
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

          <el-select
            v-else
            v-model="alertForm.group_id"
          >
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
    <!--    <div class="filter-container">-->
    <!--      <el-form inline>-->
    <!--        <el-form-item>-->
    <!--          <el-button type="primary" @click="clearForm();dialogVisible=true;">添加用户</el-button>-->
    <!--        </el-form-item>-->
    <!--        <el-form-item label="名称：">-->
    <!--          <el-input v-model="form.name" placeholder="名称" />-->
    <!--        </el-form-item>-->
    <!--        <el-form-item>-->
    <!--          <el-button type="primary" @click="list">查询</el-button>-->
    <!--          <el-button type="primary" @click="getAll">查看全部</el-button>-->
    <!--        </el-form-item>-->
    <!--      </el-form>-->
    <!--    </div>-->
    <el-form v-show="showSearch" ref="queryForm" :model="queryParams" size="small" :inline="true" label-width="68px">
      <el-form-item label="系统模块" prop="title">
        <el-input
          v-model="queryParams.title"
          placeholder="请输入系统模块"
          clearable
          style="width: 240px;"
          @keyup.enter.native="handleQuery"
        />
      </el-form-item>
      <el-form-item label="操作人员" prop="operName">
        <el-input
          v-model="queryParams.operName"
          placeholder="请输入操作人员"
          clearable
          style="width: 240px;"
          @keyup.enter.native="handleQuery"
        />
      </el-form-item>
      <el-form-item label="类型" prop="businessType">
        <el-select
          v-model="queryParams.businessType"
          placeholder="操作类型"
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
      <!--      <el-form-item label="操作时间">-->
      <!--        <el-date-picker-->
      <!--          v-model="dateRange"-->
      <!--          style="width: 240px"-->
      <!--          value-format="yyyy-MM-dd"-->
      <!--          type="daterange"-->
      <!--          range-separator="-"-->
      <!--          start-placeholder="开始日期"-->
      <!--          end-placeholder="结束日期"-->
      <!--        />-->
      <!--      </el-form-item>-->
      <el-form-item>
        <el-button type="primary" icon="el-icon-search" @click="handleQuery">搜索</el-button>
        <el-button icon="el-icon-refresh" @click="resetQuery">重置</el-button>
      </el-form-item>
    </el-form>

    <el-row :gutter="10" class="mb8">
      <el-col :span="17" style="margin-bottom: -15px;">
        <el-col :span="1.5">
          <el-tooltip class="item" effect="dark" content="刷新" placement="top">
            <el-button size="small" icon="el-icon-refresh" @click="refresh()" />
          </el-tooltip>
        </el-col>
        <el-col :span="1.5">
          <el-button
            v-hasPermi="['monitor:operlog:remove']"
            type="primary"
            plain
            icon="el-icon-plus"
            size="small"
          >新增</el-button>
        </el-col>
        <el-col :span="1.5">
          <el-button
            v-hasPermi="['monitor:operlog:remove']"
            type="success"
            plain
            icon="el-icon-edit"
            size="small"
          >修改</el-button>
        </el-col>
        <el-col :span="1.5">
          <el-button
            v-hasPermi="['monitor:operlog:remove']"
            type="danger"
            plain
            icon="el-icon-delete"
            size="small"
          >删除</el-button>
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
          <el-form-item label="操作人员" prop="operName">
            <el-input
              v-model="queryParams.operName"
              size="small"
              placeholder="请输入操作人员"
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
    <el-table :data="pageList">
      <el-table-column type="index" label="ID" />
      <el-table-column prop="account" label="登陆名" />
      <el-table-column prop="name" label="名称" />
      <el-table-column prop="login_at" label="登陆时间" />
      <el-table-column prop="login_ip" label="登陆IP" />
      <el-table-column prop="group_name" label="账号组" />
      <el-table-column prop="desc" label="描述" />
      <el-table-column label="操作">
        <template slot-scope="scope">
          <el-button type="primary" icon="el-icon-edit" circle size="mini" @click="editAlert(scope.row)" />
          <el-button
            v-if="scope.row.status === 1"
            slot="reference"
            type="danger"
            icon="el-icon-delete"
            circle
            size="mini"
            @click="delUserAlert(scope.row)"
          />
        </template>
      </el-table-column>
    </el-table>
    <pagination
      :total="count"
      :page.sync="queryParams.pageNum"
      :limit.sync="queryParams.pageSize"
      @pagination="list"
    />
    <el-dialog
      title="提示"
      :visible.sync="visible"
      width="30%"
      center
    >
      <span>确定删除 <b style="color: red">"{{ delForm.name }}"</b> 吗？？？？</span>
      <span slot="footer" class="dialog-footer">
        <el-button @click="visible = false">取 消</el-button>
        <el-button type="primary" @click="delUser">确 定</el-button>
      </span>
    </el-dialog>
    <!--    <el-pagination-->
    <!--      background-->
    <!--      :current-page.sync="form.page"-->
    <!--      :page-size="form.length"-->
    <!--      layout="total, prev, pager, next"-->
    <!--      :total="count"-->
    <!--      @current-change="list"-->
    <!--    />-->
  </div>
</template>

<script>
import { addUser, userGroup, userList, delUser, editUser, userText } from '@/api/user'

export default {
  data() {
    return {
      passwordType: 'password',
      dialogVisible: false,
      visible: false,
      dialogType: 1, // 0修改 1添加
      pageList: [],
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
      groupList: [],
      // 显示搜索条件
      showSearch: false,
      search: true,
      // 查询参数
      queryParams: {
        pageNum: 1,
        pageSize: 10,
        title: undefined,
        operName: undefined,
        businessType: undefined,
        status: undefined
      }
    }
  },
  created() {
    const groupId = this.$route.query.groupId
    if (groupId) {
      this.alertForm.group_id = groupId
      this.form.group_id = groupId
    }
    this.list()
    this.getUserGroup()
    // this.text()
  },
  methods: {
    // 搜索
    toggleSearch() {
      this.showSearch = !this.showSearch
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.pageNum = 1
      // this.getList()
    },
    list() {
      userList(this.form).then(res => {
        console.log(res)
        this.pageList = res.p.list
        this.count = res.p.count
      })
    },
    handleClose(done) {
      this.$confirm('确认关闭？')
        .then(_ => {
          done()
        })
        .catch(_ => {})
    },
    addSubmit() {
      addUser(this.alertForm).then(res => {
        this.$message.success(res.m)
        this.list()
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
      editUser(this.alertForm).then(res => {
        this.$message.success(res.m)
        this.dialogVisible = false
        this.list()
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
    getUserGroup() {
      userGroup().then(res => {
        this.groupList = res.p
      })
    },
    delUserAlert(data) {
      this.delForm.id = data.id
      this.delForm.name = data.name
      this.visible = true
    },
    delUser() {
      delUser({ id: this.delForm.id }).then(res => {
        this.$message.success(res.m)
        this.visible = false
        this.list()
      })
    },
    text() {
      userText({ url: null }).then(res => {
      })
    },
    getAll() {
      this.form.group_id = ''
      this.list()
    }
  }
}
</script>

<style scoped>

</style>
