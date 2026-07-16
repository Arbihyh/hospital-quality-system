<template>
  <div class="app-container">
    <el-row :gutter="10" class="mb8">
      <el-col :span="13" style="margin-bottom: -15px;">
        <el-col :span="1.5">
          <el-tooltip class="item" effect="dark" content="刷新" placement="top">
            <el-button size="small" icon="el-icon-refresh" @click="handleRefresh" />
          </el-tooltip>
        </el-col>
        <el-col :span="1.5">
          <el-button
            v-if="checkPermission(['admin/admin/addAdmin'])"
            type="primary"
            plain
            icon="el-icon-plus"
            size="small"
            @click="clearForm();dialogType=1;dialogVisible=true;dialogStatus = 'create'"
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
          <el-form-item label="" prop="realname">
            <el-input
              v-model="queryParams.realname"
              size="small"
              placeholder="请输入姓名"
              clearable
              style="width: 240px;"
              @keyup.enter.native="handleQuery"
            />
          </el-form-item>
          <el-form-item label="" prop="account">
            <el-input
              v-model="queryParams.account"
              size="small"
              placeholder="请输入工号"
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
    <el-table v-loading="listLoading" :data="pageList" class="my-table">
      <el-table-column type="index" label="序号" />
      <el-table-column prop="account" label="工号" />
      <el-table-column prop="group_name" label="部门" />
      <el-table-column prop="KSMC" label="科室" />
      <el-table-column prop="realname" label="姓名" />
      <el-table-column prop="phone" label="手机号" />
      <el-table-column prop="login_at" label="最后登陆" />
      <el-table-column prop="desc" label="描述" />
      <el-table-column label="操作" width="120">
        <template slot-scope="scope">
          <div>
            <el-popover
              placement="right"
              trigger="hover"
              popper-class="opera-popper"
            >
              <div>
                <el-button
                  v-if="checkPermission(['admin/admin/editAdmin'])"
                  type="primary"
                  icon="el-icon-edit"
                  size="mini"
                  circle
                  @click="editAlert(scope.row)"
                />
                <el-button
                  v-if="scope.row.status === 1 && checkPermission(['admin/admin/delAdmin'])"
                  slot="reference"
                  type="danger"
                  icon="el-icon-delete"
                  size="mini"
                  circle
                  @click="handleDelAdmin(scope.row)"
                />
              </div>
              <i slot="reference" class="el-icon-more my-vertical-more" style="display: inline-block" />
            </el-popover>
          </div>
        </template>
      </el-table-column>
    </el-table>
    <pagination
      :auto-scroll="false"
      :total="listCount"
      :page="queryParams.page"
      :limit="queryParams.limit"
      @pagination="handlePagination"
    />

    <el-dialog :title="textMap[dialogStatus]" :visible.sync="dialogVisible" :close-on-click-modal="false" width="30%">
      <el-form ref="form" :model="alertForm" label-position="right" label-width="70px">
        <el-form-item label="工号">
          <el-input v-model="alertForm.account" placeholder="工号" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input v-model="alertForm.pwd" :type="passwordType" placeholder="密码">
            <i
              slot="suffix"
              :class="'el-input__icon '+(passwordType === 'password' ? 'el-icon-open' : 'el-icon-turn-off')"
              style="cursor:pointer;"
              @click="passwordType = (passwordType === 'password' ? 'text' : 'password')"
            />
          </el-input>
        </el-form-item>
        <el-form-item label="部门">
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
        <el-form-item label="科室">
          <el-select v-model="alertForm.KSDM" filterable clearable>
            <el-option
              v-for="(v,k) in deportmentList"
              :key="k"
              :label="v.name"
              :value="parseInt(v.id)"
            />
          </el-select>

        </el-form-item>

        <el-form-item label="姓名">
          <el-input v-model="alertForm.realname" placeholder="姓名" />
        </el-form-item>
        <el-form-item label="手机号">
          <el-input v-model="alertForm.phone" placeholder="手机号" />
        </el-form-item>
        <el-form-item label="描述">
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
import { addAdmin, adminGroup, adminList, delAdmin, editAdmin, getDeportmentList } from '@/api/admin'

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
        limit: 10,
        account: undefined,
        realname: undefined,
        phone: undefined
      },
      dialogStatus: '',
      textMap: {
        update: '编辑管理员',
        create: '创建管理员'
      },
      // 杂乱标记
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
        phone: null,
        pwd: null,
        group_id: null,
        KSDM: null,
        desc: null
      },
      delForm: {
        id: null,
        name: null
      },
      count: 0,
      groupList: [],
      deportmentList: []
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
    this.getDeportmentList()
    // this.text()
  },
  methods: {
    getDeportmentList() {
      getDeportmentList().then(res => {
        const { p } = res
        if (Object.keys(p.list).length) {
          for (const key in p.list) {
            this.deportmentList.push({
              id: key,
              name: p.list[key]
            })
          }
        }
      }).catch(error => {
        console.log(error)
      })
    },

    // 搜索
    toggleSearch() {
      this.showSearch = !this.showSearch
    },
    handleRefresh() {
      this.getList()
    },
    handleResetQuery() {
      this.queryParams = {
        page: 1,
        limit: 10,
        account: undefined,
        realname: undefined,
        phone: undefined
      }
      this.getList()
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.page = 1
      this.getList()
    },
    handlePagination(param) {
      this.queryParams.page = param.page
      this.queryParams.limit = param.limit
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
    // 标记
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
      this.alertForm.phone = data.phone
      this.alertForm.realname = data.realname
      this.alertForm.group_id = parseInt(data.group_id)
      this.alertForm.KSDM = parseInt(data.KSDM)
      this.alertForm.desc = data.desc
      this.dialogStatus = 'update'
      this.dialogVisible = true
      this.dialogType = 0
    },
    editSubmit() {
      editAdmin(this.alertForm).then(res => {
        this.$message.success(res.m || '操作成功')
        this.dialogVisible = false
        this.getList()
      })
    },
    clearForm() {
      this.alertForm = {
        id: null,
        name: null,
        account: null,
        phone: null,
        pwd: null,
        group_id: null,
        KSDM: null,
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
    handleDelAdmin(row) {
      this.$confirm('此操作将永久删除该记录, 是否继续?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        delAdmin({ id: row.id }).then((res) => {
          this.$message.success(res.m || '操作成功')
          this.getList()
        })
      })
    }
  }
}
</script>

<style lang="scss">
</style>
