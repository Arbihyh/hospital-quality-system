<template>
  <div class="app-container">
    <el-row :gutter="10" class="mb8" style="margin-right: 0;">
      <el-col :span="5">
        <el-col :span="1.5" style="margin-left: 0;padding-left: 0;">
          <el-tooltip class="item" effect="dark" content="刷新" placement="top">
            <el-button size="small" icon="el-icon-refresh" @click="handleRefresh" />
          </el-tooltip>
        </el-col>
        <el-col :span="1.5">
          <el-button
            v-if="checkPermission(['admin/rule/addErrorRule'])"
            type="primary"
            plain
            icon="el-icon-plus"
            size="small"
            @click="handleAdd"
          >新增</el-button>
        </el-col>
      </el-col>
      <el-col :span="19" style="margin-bottom: -15px;padding-right: 0;">
        <el-form
          ref="queryForm"
          :model="queryParams"
          size="small"
          :inline="true"
          class="head-query-form"
          label-width="68px"
          style="text-align:right;"
        >
          <el-form-item label="" prop="auth">
            <el-input
              v-model="queryParams.fee_name"
              size="small"
              placeholder="请输入项目名称"
              clearable
              style="width: 220px;"
              @keyup.enter.native="handleQuery"
              @clear="handleQuery"
            />
          </el-form-item>
          <el-form-item label="" prop="field">
            <el-input
              v-model="queryParams.operation_name"
              size="small"
              placeholder="请输入手术名称"
              clearable
              style="width: 220px;"
              @keyup.enter.native="handleQuery"
              @clear="handleQuery"
            />
          </el-form-item>
          <el-form-item label="" prop="desc">
            <el-input
              v-model="queryParams.code"
              size="small"
              placeholder="请输入手术代码"
              clearable
              style="width: 220px;"
              @keyup.enter.native="handleQuery"
              @clear="handleQuery"
            />
          </el-form-item>
          <el-form-item label="" prop="status">
            <el-select
              v-model="queryParams.status"
              placeholder="规侧状态"
              clearable
              style="width: 110px"
              @clear="handleQuery"
            >
              <el-option
                v-for="item in statusArr"
                :key="item.id"
                :label="item.name"
                :value="item.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item style="margin-right: 0;">
            <el-button type="primary" icon="el-icon-search" @click="handleQuery">搜索</el-button>
            <el-button icon="el-icon-refresh" @click="handleResetQuery">重置</el-button>
          </el-form-item>
        </el-form>
      </el-col>
    </el-row>
    <el-table v-loading="listLoading" :data="pageList" style="width: 100%" max-height="500">
      <el-table-column type="index" label="序号" width="80" />
      <el-table-column label="项目名称" width="260">
        <template slot-scope="scope">
          {{ scope.row.fee_name }}
        </template>
      </el-table-column>
      <el-table-column label="手术名称" width="260">
        <template slot-scope="scope">
          {{ scope.row.operation_name }}
        </template>
      </el-table-column>
      <el-table-column label="手术代码" width="180">
        <template slot-scope="scope">
          {{ scope.row.code }}
        </template>
      </el-table-column>
      <el-table-column label="计费单位" width="140">
        <template slot-scope="scope">
          {{ scope.row.fee_unit }}
        </template>
      </el-table-column>
      <el-table-column label="价格" width="140">
        <template slot-scope="scope">
          {{ scope.row.price }}
        </template>
      </el-table-column>
      <el-table-column key="status" label="状态" width="130">
        <template slot-scope="scope">
          <el-switch
            v-model="scope.row.status"
            active-color="#13ce66"
            :active-value="0"
            :inactive-value="1"
            @change="handleStatusChange(scope.row)"
          />
        </template>
      </el-table-column>
      <el-table-column header-align="center" align="center" label="操作" width="80">
        <template slot-scope="scope">
          <div>
            <el-popover
              placement="right"
              trigger="hover"
              popper-class="opera-popper"
            >
              <div>
                <el-button
                  v-if="checkPermission(['admin/admin/editErrorRule'])"
                  type="primary"
                  icon="el-icon-edit"
                  size="mini"
                  circle
                  @click="handleUpdate(scope.row)"
                />
                <el-button
                  v-if="checkPermission(['admin/admin/delErrorRule'])"
                  type="danger"
                  icon="el-icon-delete"
                  size="mini"
                  circle
                  @click="handleDelete(scope.row, scope.$index)"
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
      :limit="queryParams.length"
      @pagination="handlePagination"
    />
    <!-- 添加或修改对话框 -->
    <el-dialog :title="title" :visible.sync="open" width="600px" append-to-body>
      <el-form ref="form" :model="form" :rules="rules" label-width="80px">
        <el-form-item label="项目名称" prop="fee_name">
          <el-input v-model="form.fee_name" placeholder="请输入项目名称" />
        </el-form-item>
        <el-form-item label="手术名称" prop="operation_name">
          <el-input v-model="form.operation_name" placeholder="请输入手术名称" />
        </el-form-item>
        <el-form-item label="手术代码" prop="code">
          <el-input v-model="form.code" placeholder="请输入手术代码" />
        </el-form-item>
        <el-form-item label="计费单位" prop="code">
          <el-input v-model="form.fee_unit" placeholder="请输入计费单位" />
        </el-form-item>
        <el-form-item label="价格" prop="code">
          <el-input v-model="form.price" placeholder="请输入价格" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio
              v-for="item in statusArr"
              :key="item.id"
              :label="item.id"
            >{{ item.name }}</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button type="primary" @click="submitForm">确 定</el-button>
        <el-button @click="cancel">取 消</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { fetchList, add, update, updateStatus, del } from '@/api/rule/charges'
import { scrollTo } from '@/utils/scroll-to'

export default {
  data() {
    return {
      listCount: 0,
      pageList: [],
      listLoading: false,
      search: true,
      queryParams: {
        page: 1,
        length: 15,
        id: undefined,
        fee_name: undefined,
        code: undefined,
        operation_name: undefined,
        fee_unit: undefined,
        price: undefined,
        status: undefined
      },
      title: '',
      open: false,
      statusArr: [
        { 'id': 0, 'name': '启用' },
        { 'id': 1, 'name': '停用' }
      ],
      form: {},
      rules: {
        // fee_name: [
        //   { required: true, message: '项目名称不能为空', trigger: 'blur' }
        // ],
        // operation_name: [
        //   { required: true, message: '手术名称不能为空', trigger: 'blur' }
        // ],
        // code: [
        //   { required: true, message: '手术代码不能为空', trigger: 'blur' }
        // ]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    handleRefresh() {
      this.getList()
    },
    handleResetQuery() {
      this.queryParams = {
        page: 1,
        length: 15,
        fee_name: undefined,
        code: undefined,
        operation_name: undefined,
        fee_unit: undefined,
        price: undefined,
        status: undefined
      }
      this.getList()
    },
    handleQuery() {
      this.queryParams.page = 1
      this.getList()
    },
    handlePagination(param) {
      this.queryParams.page = param.page
      this.queryParams.length = param.limit
      this.getList()
    },
    getList() {
      this.listLoading = true
      fetchList(this.queryParams).then(res => {
        this.pageList = res.p.list
        this.listCount = res.p.count
        this.listLoading = false
        scrollTo(300)
      }).catch(error => {
        console.log(error)
      })
    },
    cancel() {
      this.open = false
      this.reset()
    },
    reset() {
      this.form = {
        id: undefined,
        fee_name: undefined,
        code: undefined,
        operation_name: undefined,
        fee_unit: undefined,
        price: undefined,
        status: 0
      }
      this.resetForm('form')
    },
    handleAdd() {
      this.reset()
      this.open = true
      this.title = '添加收费项目'
    },
    handleUpdate(row) {
      this.reset()
      const temp = Object.assign({}, row)
      this.form = temp
      this.form.status = parseInt(temp.status)
      this.open = true
      this.title = '修改收费项目'
    },
    submitForm: function() {
      this.$refs['form'].validate(valid => {
        if (valid) {
          if (this.form.id !== undefined) {
            update(this.form).then(res => {
              this.$message.success(res.m || '操作成功')
              this.open = false
              this.getList()
            })
          } else {
            add(this.form).then(res => {
              this.$message.success(res.m || '操作成功')
              this.open = false
              this.getList()
            })
          }
        }
      })
    },
    // 用户状态修改
    handleStatusChange(row) {
      const statusIndex = this.statusArr.findIndex((value) => parseInt(value.id) === parseInt(row.status))
      this.$confirm('确认要更改为 <strong>' + this.statusArr[statusIndex].name + '</strong> 状态吗？', '提示', {
        dangerouslyUseHTMLString: true,
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        updateStatus({ id: row.id, status: row.status }).then((res) => {
          this.$message.success(res.m || '操作成功')
        }).catch(function() {
          row.status = row.status === 0 ? 1 : 0
        })
      }).catch(function() {
        row.status = row.status === 0 ? 1 : 0
      })
    },
    handleDelete(row, index) {
      const indexNum = index + 1
      this.$confirm('是否确认删除第 <strong>' + indexNum + '</strong> 行的数据项？', '提示', {
        dangerouslyUseHTMLString: true,
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        del({ id: row.id }).then((res) => {
          this.$message.success(res.m || '操作成功')
          this.getList()
        })
      }).catch(function() {})
    }
  }
}
</script>

<style lang="scss" scoped>
::v-deep .head-query-form{
  .el-form-item{
    .el-form-item__label{
      font-weight: 400 !important;
    }
  }
}
</style>
