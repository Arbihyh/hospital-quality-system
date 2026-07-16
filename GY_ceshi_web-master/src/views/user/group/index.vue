<template>
  <div class="pages">
    <el-dialog
     v-el-drag-dialog
      :title="textMap[dialogStatus]"
      :visible.sync="dialogVisible"
      :close-on-click-modal="false"
      width="45%"
    >
      <el-form label-position="left" :model="form" label-width="80px"  :rules="rules" ref="ruleForm">
        <el-form-item label="角色名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入角色名称" />
        </el-form-item>
        <el-form-item label="权限" prop="role">
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
        <el-form-item label="说明">
          <el-input v-model="form.desc" type="textarea" placeholder="说明" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible=false">取 消</el-button>
        <el-button v-if="act === 1" type="primary" @click="addSubmit">确 定</el-button>
        <el-button v-else type="primary" @click="editSubmit">确 定</el-button>
      </span>
    </el-dialog>
    <div class="top-content">
    <el-row :gutter="10">
      <el-form
        :label-position="labelPosition"
          ref="queryForm"
          :model="queryParams"
          size="small"
          label-width="75px"
          
        >
         <el-row class="">
          <!-- <el-col class="" :span="6">
          <el-form-item label="权限" prop="">
           
            <el-select  v-model="queryParams.search_role" multiple  filterable clearable placeholder="权限">
                <el-option v-for="(item, index) in roleList" :label="item.name" :value="item.id"
                           :key="index"></el-option>
              </el-select>
          </el-form-item>
        </el-col> -->
          <el-col class="" :span="8">
          <el-form-item label="角色" prop="">
            <el-input
              v-model="queryParams.search_name"
              placeholder="请输入角色"
              style='width: 240px;'
        
            />
            <!-- <el-select  v-model="queryParams.search_name" multiple filterable clearable placeholder="角色">
                <el-option v-for="(item, index) in groupList" :label="item.name" :value="item.id"
                           :key="index"></el-option>
              </el-select> -->
          </el-form-item>
        </el-col>  
        
        <el-col class="" :span="16">
          <el-form-item label="更新时间">
            <el-date-picker v-model="queryParams.start_time" type="date" :picker-options="pickerOptions1" placeholder="开始日期" style="width: 240px;"></el-date-picker>
            <el-date-picker v-model="queryParams.end_time" type="date" :picker-options="pickerOptions2" placeholder="结束日期" style="width: 240px;margin-left:5px;"></el-date-picker>
          </el-form-item>
        </el-col>
        </el-row>
        </el-form>
    </el-row>
    <el-row class="">
         <div class="btn-class">
            <el-button size="small" type="primary"  @click="handleQuery">查询</el-button>
            <el-button size="small" icon="el-icon-refresh" @click="handleResetQuery">重置</el-button>
          </div>
        </el-row>
   
    </div>
  <div class="contentBox">
    <div class="add-btn">
      <el-button
            type="primary"
            plain
            icon="el-icon-plus"
            size="small"
             @click="handleAdd"
          >新增</el-button>
         <!-- <el-button
            type="primary"
            plain
            icon="el-icon-download"
            size="small"
           
          >下载</el-button>-->
    </div>
    <el-table v-loading="listLoading" :data="pageList" >
      <el-table-column type="index" prop="" :align="textCenter" label="序号" width="140" header-align="center" >
      <template slot-scope="scope">
        {{ scope.$index + 1 }}
      </template>
      </el-table-column>
      <el-table-column prop="name" label="角色名称" :align="textCenter" width="200" header-align="center" />
      <el-table-column prop="role_text" label="菜单栏" header-align="center" />
      <el-table-column prop="updated_at" label="更新时间" :align="textCenter" width="180" header-align="center"/>
      <el-table-column label="操作" width="150" :align="textCenter" header-align="center" >
      <!--                 v-if="scope.row.status === 1 && checkPermission(['user/delUserGroup'])"
     v-if="checkPermission(['user/editUserGroup'])"-->
        <template slot-scope="scope">
              <div>
                <el-button
                  type="danger"
                  icon="el-icon-delete"
                  circle
                  size="mini"
                  @click="delUserGroupAlert(scope.row)"
                />
                <el-button
                  type="primary"
                  icon="el-icon-edit"
                  size="mini"
                  circle
                  @click="editAlert(scope.row)"
                />
                
              </div>
        </template>
      </el-table-column>
    </el-table> 
    <pagination
      :auto-scroll="false"
      :total="listCount"
      :page="queryParams.page"
      :limit="queryParams.len"
      @pagination="handlePagination"
    />
  </div>
    <el-dialog
      title="提示"
      :visible.sync="visible"
      width="30%"
      center
    >
      <span>确定删除 <b style="color: red">"{{ delForm.name }}"</b> 吗?</span>
      <span slot="footer" class="dialog-footer">
        <el-button @click="visible = false">取 消</el-button>
        <el-button type="primary" @click="delUserGroup">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
 import {  userGroupList,addUserGroup,editUserGroup,delUserGroup,rbacList,userGroup,roleDropDown } from '@/api/user'
import Pagination from '@/components/Pagination'
export default {
  components: {
    Pagination
  },
  data() {
    return {
      textCenter:'center',
      rules: {
        name: [
          { required: true, message: '请输入角色名称', trigger: 'blur' }
        ],
        role: [
          { required: true, message: '请选择菜单权限', trigger: 'change' }
        ]
      },
      roleList:[],
      groupList:[],
      labelPosition: 'left',
      listCount: 0,
      pageList: [],
      // listLoading: true,//hq
      listLoading:false,//hq
      // 显示搜索条件
      showSearch: false,
      search: true,
      // 查询参数
      queryParams: {
        page: 1,
        len: 10,
        search_role:"",//search_role
        search_name:"",//角色名称
        start_time:"",//更新开始时间
        end_time:"",//更新结束时间
       

      },
      pickerOptions1: {
        disabledDate: time => {
          if (this.queryParams.end_time) {
            return time.getTime() > new Date(this.queryParams.end_time).getTime();
          } else {
            return time.getTime() > Date.now();
          }
        },
      },
      pickerOptions2: {
        disabledDate: time => {
          if (this.queryParams.start_time) {
            return time.getTime() < new Date(this.queryParams.start_time).getTime();
          } else {
            return time.getTime() > Date.now();
          }
        },
      },
      rangeDate:[],
      dialogStatus: '',
      dialogType:1,
      textMap: {
        update: '编辑用户组',
        create: '创建用户组'
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
  
    clearForm() {
      this.form = {
        name: null,
        desc:null,
        
       
      }
    },
    handleRefresh() {
      this.handleQuery()
    },
    handleResetQuery() {
      this.queryParams = {
        page: 1,
        len: 10,
        search_role: "",//search_role
        search_name:"",//角色名称
        start_time:"",//更新开始时间
        end_time:"",//更新结束时间
      }
      this.rangeDate=[]
      this.list()
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.page = 1
      this.list()
    },
  async  list() {
      this.listLoading = true
      this.$axios.post('/user/userGroupList',this.queryParams).then(res => {
       this.pageList=res.data.list;
       this.listCount =res.data.count
        this.listLoading = false
      }).catch(error => {
        console.log(error)
        this.listLoading = false
      })
    },
    handlePagination(res) {
      this.queryParams.page = res.page
      this.queryParams.len = res.limit
      this.list()
    },
    //角色
    getUserGroup() {
      this.$axios.get('/user/groupDropDown').then(res => {
        this.groupList = res.data
      })
    },
    getRoleDropDown(){
      this.$axios.get('/user/roleDropDown').then(res => {
         this.roleList = res.data
      })
    },
    getRbac() {
      this.$axios.get('user/menuDropDown').then(resArr => {
        this.rbacList = resArr.data
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
      this.dialogType=1
      this.dialogVisible = true
      this.dialogStatus = 'create'
      this.getRbac()
    },
    addSubmit() {
      // this.$refs['ruleForm'].validate((valid) => {
      //   if (valid) {
      this.dialogStatus = 'create'
      this.form['role'] = this.getChecked()
      this.$axios.post('/user/addUserGroup',this.form).then(res=>{
        this.$message.success(res.msg || '新增成功')
        this.list()
        this.resetForm()
         this.dialogVisible = false
       })
    // }else{
    //   return false
    // }
 // })
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
      this.dialogType=0
      this.dialogStatus = 'update'
      this.act = 0
      this.id = data.id
      this.form.name = data.name
      this.form.desc = data.desc
      this.form.role = data.role
      this.$axios.get('user/menuDropDown').then(res => {
        this.rbacList = res.data
        if (data.role !== 'all') {
          this.$nextTick(() => {
            this.$refs.rbacTree.setCheckedKeys([])
            const arr =data.role.split(",")
            arr.forEach((item) => {
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
      // this.$refs['ruleForm'].validate((valid) => {
      //   if (valid) {
      this.form.id = this.id
      this.form['role'] = this.getChecked()
      this.$axios.post('/user/editUserGroup',this.form).then(res => {
        this.$message.success(res.msg || '修改成功')
        this.list()
        this.dialogVisible = false
                 })
              // }else{
              //   return false
              // }
      // })
    },
    delUserGroupAlert(data) {
      this.delForm.id = data.id
      this.delForm.name = data.name
      this.visible = true
    },
    delUserGroup() {
      this.$axios.post('/user/delUserGroup',{ id: this.delForm.id }).then(res => {
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
.pages {
 margin: 0 auto;
 padding: 0 18px;
 background: #f4f4f4;
 }
 .top-content {
    margin-bottom: 20px;
    background: #fff;
    padding: 25px 15px;
    border-radius: 5px;
  }
  .contentBox {
    margin-bottom: 20px;
    background: #fff;
    padding: 25px 15px;
    border-radius: 5px;
    }
    .contentBox {
    margin-bottom: 20px;
    background: #fff;
    padding: 25px 15px;
    border-radius: 5px;
    }
    .add-btn{
      margin: 20px 0; text-align: right;
    }
    .btn-class{
    display: flex;
    justify-content: flex-end;
  }
  ::v-deep .el-dialog__header{
    padding: 10px 20px;
    background: rgb(27,100,169);
    color: #fff !important;
      
    }
    ::v-deep .el-dialog__header .el-dialog__title{
      color: #fff;
    }
.el-button--primary {
    color: #FFF;
    background-color: #005FA6;
    border-color: #185da6;
}
.el-button:focus, .el-button:hover {
    color: #409EFF !important;
    border-color: #c6e2ff !important;
    background-color: #ecf5ff !important;
}
.el-pager li:not(.disabled).active {
    background-color: #185da6 !important;
    color: #FFF;
}

::v-deep.el-pagination.is-background .el-pager li.number.active {
    background-color: #185da6 !important;
    color: #FFF;
}
li.number.active{
	background-color: #185da6 !important;
}
.el-pager li.active {
    color: #409EFF !important;
    cursor: default;
}
</style>
