<template>
  <div class="pages">
    <div class="top-content">
      <el-row :gutter="10">
        <el-form :label-position="labelPosition" ref="queryForm" :model="queryParams" size="small" label-width="70px">
          <el-row class="">
            <el-col class="" :span="6">
              <el-form-item label="工号">
                <el-input v-model="queryParams.search_gh" size="small" placeholder="请输入工号" clearable style="width: 240px" @keyup.enter.native="handleQuery" />
              </el-form-item>
            </el-col>
            <el-col class="" :span="6">
              <el-form-item label="姓名" prop="">
                <el-input v-model="queryParams.search_xm" size="small" placeholder="请输入姓名" clearable style="width: 240px" @keyup.enter.native="handleQuery" />
              </el-form-item>
            </el-col>
            <el-col class="" :span="6">
              <el-form-item label="科室" prop="">
                <el-select style="width: 240px" v-model="queryParams.search_ks" multiple filterable clearable placeholder="请选择科室">
                  <el-option v-for="(item, index) in deportments" :label="item.name" :value="item.dep_id" :key="index"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row class="">
            <!--          <el-col class="" :span="6">-->
            <!--          <el-form-item label="角色" prop="">-->
            <!--            <el-select style="width: 240px;" v-model="queryParams.search_js"  filterable clearable placeholder="请选择角色">-->
            <!--                <el-option v-for="(item, index) in groupList" :label="item.name" :value="item.id"-->
            <!--                           :key="index"></el-option>-->
            <!--              </el-select>-->
            <!--          </el-form-item>-->
            <!--        </el-col>-->
            <!-- <el-col class="" :span="6">
          <el-form-item label="权限" prop="">

            <el-select style="width: 240px;" v-model="queryParams.search_qx" multiple filterable clearable placeholder="请选择权限">
                <el-option v-for="(item, index) in roleList" :label="item.name" :value="item.id"
                           :key="index"></el-option>
              </el-select>
          </el-form-item>
        </el-col> -->
            <el-col class="" :span="18">
              <el-form-item label="更新时间">
                <el-date-picker style="width: 240px" v-model="queryParams.start_time" type="date" :picker-options="pickerOptions1" placeholder="开始日期"></el-date-picker>
                <el-date-picker
                  style="width: 240px; margin-left: 5px"
                  v-model="queryParams.end_time"
                  type="date"
                  :picker-options="pickerOptions2"
                  placeholder="结束日期"
                ></el-date-picker>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </el-row>
      <el-row class="">
        <div class="btn-class">
          <el-button size="small" type="primary" @click="handleQuery">查询</el-button>
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
          @click="
            clearForm();
            dialogType = 1;
            dialogVisible = true;
            dialogStatus = 'create';
          "
        >
          新增
        </el-button>
        <!-- <el-button
            type="primary"
            plain
            icon="el-icon-upload2"
            size="small"
          >上传</el-button> -->
        <!-- <el-button
            type="primary"
            plain
            icon="el-icon-setting"
            size="small"
          >设置</el-button> -->
        <!-- <el-button
            type="primary"
            plain
            icon="el-icon-download"
            size="small"
          >下载</el-button> -->
      </div>
      <el-table v-loading="listLoading" :data="pageList">
        <el-table-column type="index" prop="" :align="textCenter" label="序号" header-align="center">
          <template slot-scope="scope">
            {{ scope.$index + 1 }}
          </template>
        </el-table-column>
        <el-table-column prop="name" label="工号" :align="textCenter" header-align="center" />
        <el-table-column prop="realname" label="姓名" :align="textCenter" header-align="center" />
        <el-table-column prop="dep_name" label="科室名称" :align="textCenter" show-overflow-tooltip header-align="center" />
        <el-table-column prop="group_name" label="角色名称" :align="textCenter" header-align="center" />
        <!-- <el-table-column prop="role_name" label="权限" :align="textCenter" header-align="center"/> -->
        <el-table-column prop="phone" label="手机号" :align="textCenter" header-align="center" />
        <el-table-column prop="desc" label="备注" header-align="center" :align="textCenter" />
        <el-table-column prop="updated_at" label="更新时间" :align="textCenter" header-align="center" />
        <el-table-column label="操作" width="180" :align="textCenter" header-align="center">
          <template slot-scope="scope">
            <!--                v-if="checkPermission(['user/delUser'])"
                 v-if="checkPermission(['user/editUser'])"  circle icon="el-icon-refresh" @click="handleRefresh(scope.row)"
--->
            <div>
              <el-button type="danger" icon="el-icon-delete" circle size="mini" @click="handleDelUser(scope.row)" />
              <el-button type="primary" icon="el-icon-edit" size="mini" circle @click="editAlert(scope.row)" />
              <el-button type="warning" icon="el-icon-key" size="mini" circle @click="onChangePassword(scope.row)" />
            </div>
          </template>
        </el-table-column>
      </el-table>
      <pagination :auto-scroll="false" :total="listCount" :page="queryParams.page" :limit="queryParams.len" @pagination="handlePagination" />
    </div>
    <el-dialog v-el-drag-dialog :title="textMap[dialogStatus]" :visible.sync="dialogVisible" :close-on-click-modal="false" width="40%">
      <el-form ref="alertForm" :model="alertForm" label-position="right" label-width="100px" :rules="rules" style="height: 400px; overflow-y: auto">
        <el-form-item label="工号" prop="name">
          <el-input v-model="alertForm.name" placeholder="工号" />
        </el-form-item>
        <el-form-item v-if="dialogStatus === 'create'" label="密码" prop="pwd">
          <el-input v-model="alertForm.pwd" :type="passwordType" placeholder="密码">
            <i
              slot="suffix"
              :class="'el-input__icon ' + (passwordType === 'password' ? 'el-icon-open' : 'el-icon-turn-off')"
              style="cursor: pointer"
              @click="passwordType = passwordType === 'password' ? 'text' : 'password'"
            />
          </el-input>
        </el-form-item>
        <el-form-item label="姓名" prop="realname">
          <el-input v-model="alertForm.realname" placeholder="姓名" />
        </el-form-item>
        <el-form-item label="科室审核">
          <el-radio-group v-model="alertForm.department_review">
            <el-radio :label="1">质控医师</el-radio>
            <el-radio :label="2">上级医师</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="科室" prop="dep_name">
          <el-select change="changeDep" v-model="alertForm.dep_id" multiple filterable clearable collapse-tags placeholder="请选择">
            <el-option label="全选" value="全选" @click.native="selectAll"></el-option>
            <el-option v-for="item of deportments" :key="item.dep_id" :label="item.name" :value="item.dep_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="病区" prop="w_name">
          <el-select change="changeDep" v-model="alertForm.w_id" multiple filterable clearable collapse-tags placeholder="请选择">
            <el-option label="全选" value="全选" @click.native="selectAllw"></el-option>
            <el-option v-for="item of deportments" :key="item.dep_id" :label="item.name" :value="item.dep_id" />
          </el-select>
        </el-form-item>
        <el-form-item label="医师" prop="w_name">
          <el-select change="changeDep" v-model="alertForm.s_id" multiple filterable clearable collapse-tags placeholder="请选择">
            <el-option v-for="item of staff" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <!-- <el-form-item label="权限" prop="role_name">
          <el-select v-model="alertForm.role_name"  filterable clearable placeholder="请选择">
            <el-option v-for="item of roleList" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item> -->
        <el-form-item label="角色" prop="group_name">
          <el-select v-if="parseInt(alertForm.id) === 1" v-model="alertForm.group_id" disabled>
            <el-option v-for="(v, k) in groupList" :key="k" :label="v.name" :value="parseInt(v.id)" />
          </el-select>
          <el-select v-else v-model="alertForm.group_id" change="changeGroup">
            <el-option v-for="item of groupList" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="自动审核人">
          <el-radio-group v-model="alertForm.is_toexamine">
            <el-radio :label="0">否</el-radio>
            <el-radio :label="1">是</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="手机号">
          <el-input v-model="alertForm.phone" placeholder="手机号" />
        </el-form-item>

        <el-form-item label="备注">
          <el-input v-model="alertForm.desc" type="textarea" placeholder="描述" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button v-if="dialogType === 1" type="primary" @click="addSubmit">确 定</el-button>
        <el-button v-else type="primary" @click="editSubmit">确 定</el-button>
      </span>
    </el-dialog>
    <!-- 修改密码 -->
    <PasswordDialogVue v-if="pwdData.bSwitch" :data="pwdData" />
  </div>
</template>
<script>
import PasswordDialogVue from './components/PasswordDialog.vue';
import Pagination from '@/components/Pagination';
import { addUser, userList, editUser, delUser, userGroup, roleDropDown, depDropDown } from '@/api/user';
import Title from '@/components/Title';
export default {
  components: {
    Title,
    Pagination,
    PasswordDialogVue,
  },
  data() {
    let validatePass = (rule, value, callback) => {
      const pwdValue = this.alertForm.pwd;
      if (pwdValue === '') {
        callback(new Error('请输入密码'));
      } else {
        if (this.alertForm.pwd !== '') {
          if (pwdValue.length >= 8 && /[A-Z]/.test(pwdValue) && /[a-z]/.test(pwdValue) && /\d/.test(pwdValue)) {
            callback();
          } else {
            callback(new Error('密码必须长度至少8位，包括数字，大写字母，小写字母'));
          }
        }
      }
    };
    return {
      textCenter: 'center',
      rules: {
        name: [{ required: true, message: '请输入工号', trigger: 'blur' }],
        pwd: [
          { validator: validatePass, trigger: 'blur' },
          { required: true, message: '请输入密码', trigger: 'blur' },
        ],
        realname: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
        dep_name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
        // role_name: [
        //   { required: true, message: '请输入权限', trigger: 'change' }
        // ],
        group_name: [{ required: true, message: '请输入角色', trigger: 'blur' }],
      },
      labelPosition: 'left',
      listCount: 0,
      listLoading: true,
      showSearch: false,
      search: true,
      queryParams: {
        page: 1,
        len: 10,
        // name: undefined,
        // realname: undefined,
        search_gh: '', //工号
        search_xm: '', //姓名
        search_ks: '', //科室
        search_js: '', //角色
        // search_qx:"",//权限
        start_time: '', //更新时间开始
        end_time: '', //更新时间结束
      },
      rangeDate: [],
      dialogStatus: '',
      textMap: {
        update: '编辑用户',
        create: '创建用户',
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
      // 杂乱标记
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
        length: 16,
      },
      alertForm: {
        id: null,
        name: null,
        // account: null,
        pwd: null,
        group_id: null, //角色
        group_name: '',
        phone: null,
        realname: null,
        desc: null,
        dep_id: [], //科室
        dep_name: '',
        w_id: [],
        s_id: '',
        department_review: '',
        is_toexamine: 0,
        // role:'',
        //role_name:''//权限
      },
      delForm: {
        id: null,
        name: null,
      },
      count: 0,
      // roleList:[],
      groupList: [],
      deportments: [],
      staff: [],
      pwdData: {
        bSwitch: false,
        row: {},
      },
    };
  },
  created() {
    // const groupId = this.$route.query.groupId
    // if (groupId) {
    //   this.alertForm.group_id = groupId
    //   this.form.group_id = groupId
    // }

    this.getList();
    this.getDeportmentList();
    this.getStaffList();
    // this.getRoleDropDown()
  },
  methods: {
    selectAll() {
      if (this.alertForm.dep_id.length <= this.deportments.length) {
        this.alertForm.dep_id = [];
        this.deportments.map(item => {
          this.alertForm.dep_id.push(item.dep_id);
        });
      } else {
        this.alertForm.dep_id = [];
      }
    },
    selectAllw() {
      if (this.alertForm.w_id.length <= this.deportments.length) {
        this.alertForm.w_id = [];
        this.deportments.map(item => {
          this.alertForm.w_id.push(item.dep_id);
        });
      } else {
        this.alertForm.w_id = [];
      }
    },
    getDeportmentList() {
      // this.$axios.get('/user/depDropDown').then(res => {
      //   const { data } = res
      //   this.deportments=data;
      // }).catch(error => {
      //   console.log(error)
      // })
      this.$axios
        .post('/user/allDepartmentList')
        .then(res => {
          const { data } = res;
          this.deportments = data;
        })
        .catch(error => {
          console.log(error);
        });
    },
    getStaffList() {
      this.$axios
        .get('/user/getDoctorList')
        .then(res => {
          const { data } = res;
          this.staff = data;
        })
        .catch(error => {
          console.log(error);
        });
    },
    // 搜索
    toggleSearch() {
      this.showSearch = !this.showSearch;
    },
    handleRefresh() {
      this.getList();
    },
    handleResetQuery() {
      this.queryParams = {
        page: 1,
        len: 10,
        // name: undefined,
        // realname: undefined,
        search_gh: '', //工号
        search_xm: '', //姓名
        search_ks: '', //科室
        search_js: '', //角色
        //  search_qx:"",//权限
        start_time: '', //更新时间开始
        end_time: '', //更新时间结束
      };
      // this.rangeDate=[]
      this.getList();
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.page = 1;
      this.getList();
    },
    handlePagination(param) {
      this.queryParams.page = param.page;
      this.queryParams.len = param.limit;
      this.getList();
    },
    getList() {
      this.listLoading = true;
      this.$axios
        .post('/user/userList', this.queryParams)
        .then(res => {
          this.pageList = res.data.list;
          this.listCount = res.data.count;
          this.listLoading = false;
        })
        .catch(error => {
          console.log(error);
          this.listLoading = false;
        });
    },
    // 标记
    addSubmit() {
      // this.$refs['alertForm'].validate((valid) => {
      //   if (valid) {
      this.$axios.post('/user/addUser', this.alertForm).then(res => {
        this.$message.success(res.m);
        this.getList();
        this.dialogVisible = false;
      });
      // }else{
      //   return false
      // }
      //})
    },
    editAlert(data) {
      this.getUserGroup();
      this.alertForm.id = data.id;
      // this.alertForm.account = data.account
      // this.alertForm.role = data.role
      // this.alertForm.role_name=data.role_name
      this.alertForm.name = data.name;
      this.alertForm.group_id = parseInt(data.group_id);
      this.alertForm.group_name = data.group_name;
      this.alertForm.dep_id = data.dep_id ?? '';
      this.alertForm.w_id = data.w_id ?? '';
      this.alertForm.s_id = data.s_id ? parseInt(data.s_id) : '';
      this.alertForm.desc = data.desc;
      const arrName = data.dep_name.split(',');
      if (arrName.length !== 0) {
        this.alertForm.dep_name = arrName || '';
      }
      this.alertForm.phone = data.phone;
      this.alertForm.realname = data.realname;
      this.alertForm.department_review = data.department_review;
      this.alertForm.is_toexamine = data.is_toexamine;
      this.dialogStatus = 'update';
      this.dialogVisible = true;
      this.dialogType = 0;
    },
    editSubmit() {
      // this.$refs['alertForm'].validate((valid) => {
      //   if (valid) {
      this.$axios.post('/user/editUser', this.alertForm).then(res => {
        this.$message.success(res.msg || '操作成功');
        this.dialogVisible = false;
        this.getList();
      });
      // }else{
      // return false
      //  }
      // })
    },
    changeGroup(val) {
      this.alertForm.group_id = val;
    },
    changeDep(val) {
      this.alertForm.dep_id = val;
    },
    clearForm() {
      this.alertForm = {
        id: null,
        name: null,
        // account: null,
        dep_id: null,
        // role:null,
        pwd: null,
        group_id: null,
        phone: null,
        realname: null,
        desc: null,
        w_id: null,
        s_id: null,
      };
      this.getUserGroup();
    },
    getUserGroup() {
      this.$axios.get('/user/groupDropDown').then(res => {
        this.groupList = res.data;
      });
    },
    // getRoleDropDown(){
    //   this.$axios.get('/user/roleDropDown').then(res=>{
    //     this.roleList=res.data;
    //   })
    // },
    delUserAlert(data) {
      this.delForm.id = data.id;
      this.delForm.name = data.name;
      this.visible = true;
    },
    handleDelUser(row) {
      this.$confirm('此操作将永久删除该记录, 是否继续?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      }).then(() => {
        this.$axios.post('/user/delUser', { id: row.id }).then(res => {
          this.$message.success(res.msg || '操作成功');
          this.getList();
        });
      });
    },
    onChangePassword(row) {
      this.pwdData.row = row;
      this.pwdData.bSwitch = true;
    },
  },
};
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
.btn-class {
  display: flex;
  justify-content: flex-end;
}
.contentBox {
  margin-bottom: 20px;
  background: #fff;
  padding: 25px 15px;
  border-radius: 5px;
}
.add-btn {
  margin: 20px 0;
  text-align: right;
}
/* .el-dialog__header{
      background-color:#0066B0 !important;
    } */
::v-deep .el-dialog__header {
  padding: 10px 20px;
  background: rgb(27, 100, 169);
  color: #fff !important;
}
::v-deep .el-dialog__header .el-dialog__title {
  color: #fff;
}
.el-button--primary {
  color: #fff;
  background-color: #005fa6;
  border-color: #185da6;
}
.el-button:focus,
.el-button:hover {
  color: #409eff !important;
  border-color: #c6e2ff !important;
  background-color: #ecf5ff !important;
}

::v-deep.el-pagination.is-background .el-pager li:not(.disabled).active {
  background: #185da6 !important;
  color: #fff;
}
</style>
