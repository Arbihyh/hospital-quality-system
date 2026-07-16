<template>
  <div class="app-container">
    <el-dialog
      :title="textMap[dialogStatus]"
      :visible.sync="dialogVisible"
      :close-on-click-modal="false"
      width="30%"
    >
      <el-form label-position="left" label-width="60px">
        <el-form-item label="部门">
          <el-input v-model="form.name" placeholder="部门名称" />
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
              check-on-click-node
              :props="defaultProps"
            />
          </div>
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.desc" type="textarea" placeholder="描述" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible=false">取 消</el-button>
        <el-button v-if="act === 1" type="primary" @click="addSubmit">确 定</el-button>
        <el-button v-else type="primary" @click="editSubmit">确 定</el-button>
      </span>
    </el-dialog>
    <el-row :gutter="10" class="mb8">
      <el-col :span="13">
        <el-col :span="1.5">
          <el-tooltip class="item" effect="dark" content="刷新" placement="top">
            <el-button size="small" icon="el-icon-refresh" @click="handleRefresh" />
          </el-tooltip>
        </el-col>
        <el-col :span="1.5">
          <el-button
            v-if="checkPermission(['admin/user/addUserGroup'])"
            type="primary"
            plain
            icon="el-icon-plus"
            size="small"
            @click="handleAdd"
          >新增</el-button>
        </el-col>
      </el-col>
    </el-row>
    <el-table v-loading="listLoading" :data="pageList">
      <el-table-column type="index" label="序号" />
      <el-table-column prop="name" label="部门" />
      <!--      <el-table-column prop="user_name" label="所有者" />-->
      <el-table-column prop="desc" label="描述" />
      <el-table-column label="操作">
        <template slot-scope="scope">
          <div>
            <el-popover
              placement="right"
              trigger="hover"
              popper-class="opera-popper"
            >
              <div>
                <el-button
                  v-if="checkPermission(['admin/user/editUserGroup'])"
                  type="primary"
                  icon="el-icon-edit"
                  size="mini"
                  circle
                  @click="editAlert(scope.row)"
                />
                <el-button
                  v-if="scope.row.status === 1 && checkPermission(['admin/user/delUserGroup'])"
                  type="danger"
                  icon="el-icon-delete"
                  circle
                  size="mini"
                  @click="delUserGroupAlert(scope.row)"
                />
              </div>
              <i slot="reference" class="el-icon-more my-vertical-more" style="display: inline-block" />
            </el-popover>
          </div>
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
        <el-button type="primary" @click="delUserGroup">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { addUserGroup, userGroupList, delUserGroup, editUserGroup, rbacList } from '@/api/user'

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
      dialogStatus: '',
      textMap: {
        update: '编辑管理组',
        create: '创建管理组'
      },
      visible: false,
      dialogVisible: false,
      defaultProps: {
        children: 'children',
        label: 'title'
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
      userGroupList(this.queryParams).then(res => {
        this.pageList = res.p
        this.listLoading = false
      }).catch(error => {
        console.log(error)
      })
    },
    // list() {
    //   userGroupList().then(res => {
    //     this.pageList = res.p
    //   })
    // },
    getRbac() {
      rbacList().then(resArr => {
        this.rbacList = resArr.p
      })
    },
    resetForm() {
      this.act = 1
      this.form = {
        name: null,
        desc: null,
        role: null
      }
    },
    handleAdd() {
      this.resetForm()
      this.dialogVisible = true
      this.getRbac()
    },
    addSubmit() {
      this.dialogStatus = 'create'
      this.form['role'] = this.getChecked()
      addUserGroup(this.form).then(res => {
        this.$message.success(res.m || 'ok')
        this.list()
        this.resetForm()
        this.dialogVisible = false
      })
    },
    getChecked() {
      const parentIds = this.$refs.rbacTree.getHalfCheckedKeys()
      const childs = this.$refs.rbacTree.getCheckedKeys()
      childs.filter(res => {
        return res !== undefined
      })
      return parentIds.concat(childs)
    },
    editAlert(data) {
      this.resetForm()
      this.dialogStatus = 'update'
      this.act = 0
      this.id = data.id
      this.form.name = data.name
      this.form.desc = data.desc
      this.form.role = data.role
      rbacList().then(resArr => {
        this.rbacList = resArr.p
        if (data.role !== 'all') {
          this.$nextTick(() => {
            this.$refs.rbacTree.setCheckedKeys([])
            data['role'].forEach((item) => {
              const node = this.$refs.rbacTree.getNode(item)
              if (node.isLeaf != null) {
                this.$refs.rbacTree.setChecked(node, true)
              }
            })
          })
        }
      })
      this.dialogVisible = true
    },
    editSubmit() {
      this.form.id = this.id
      this.form['role'] = this.getChecked()
      editUserGroup(this.form).then(res => {
        this.$message.success(res.m || 'ok')
        this.list()
        this.dialogVisible = false
      })
    },
    delUserGroupAlert(data) {
      this.delForm.id = data.id
      this.delForm.name = data.name
      this.visible = true
    },
    delUserGroup() {
      delUserGroup({ id: this.delForm.id }).then(res => {
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
