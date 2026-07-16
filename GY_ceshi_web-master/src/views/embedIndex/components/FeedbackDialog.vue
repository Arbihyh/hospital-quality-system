<template>
  <div>
    <el-dialog title="问题反馈" v-el-drag-dialog :visible.sync="data.bSwitch" width="30%">
      <el-form :model="ruleForm" :rules="rules" ref="ruleForm" label-width="120px" class="demo-ruleForm" label-suffix="：">
        <el-form-item label="反馈类型" prop="type_id">
          <el-select v-model="ruleForm.type_id" filterable placeholder="请选择" style="width: 100%;">
            <el-option label="病案首页质控" :value="1"></el-option>
            <el-option label="住院病历质控" :value="2"></el-option>
            <el-option label="病案首页查询" :value="3"></el-option>
            <el-option label="住院病历查询" :value="4"></el-option>
            <el-option label="住院医嘱查询" :value="5"></el-option>
            <el-option label="门诊病历查询" :value="6"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="反馈人" prop="user_name">
          <el-input v-model="ruleForm.user_name" disabled placeholder="请输入"></el-input>
        </el-form-item>
        <el-form-item label="反馈部门" prop="dep_id">
          <el-select v-model="ruleForm.dep_id" clearable filterable placeholder="请选择" style="width: 100%;">
            <el-option v-for="(item, index) in departmentList" :label="item.name" :value="item.id" :key="index"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="反馈内容" prop="content">
          <el-input type="textarea" v-model="ruleForm.content" :autosize="{ minRows: 5 }" placeholder="请输入"></el-input>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="data.bSwitch = false">取 消</el-button>
        <el-button type="primary" @click="onSubmit">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false
        }
      }
    }
  },
  data() {
    return {
      ruleForm: {
        type_id: '',
        dep_id: '',
        user_name: localStorage.getItem('realname'),
        content: ''
      },
      rules: {
        type_id: [
          { required: true, message: '请选择反馈类型', trigger: 'blur' },
        ],
        dep_id: [
          { required: true, message: '请选择反馈部门', trigger: 'blur' },
        ],
        user_name: [
          { required: true, message: '请输入反馈人', trigger: 'change' },
        ],
        content: [
          { required: true, message: '请输入反馈内容', trigger: 'change' },
        ],
      },
      departmentList: []
    };
  },
  created() {
    this.selectInfo()
  },
  methods: {
    // 获取部门
    selectInfo() {
      this.$axios.post('/selectInfo').then(res => {
        this.departmentList = res.data.department.slice(1, res.data.department.length);
      });
    },
    // 提交
    onSubmit() {
      this.$refs['ruleForm'].validate((valid) => {
        if (valid) {
          this.$axios.post('/add_feedback', this.ruleForm).then(res => {
            this.$message.success('已提交')
            this.data.bSwitch = false
          })
        } else {
          return false;
        }
      });
    }
  },
};
</script>

<style lang="scss" scoped>
.demo-ruleForm {
  width: 83%;
}
</style>