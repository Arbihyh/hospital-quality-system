<template>
  <el-dialog :title="titleStr" :visible.sync="data.bSwitch" width="30%">
    <el-form
      ref="ruleForm"
      :model="ruleForm"
      :rules="rules"
      label-width="100px"
      class="demo-ruleForm"
    >
      <el-form-item label="质控项目" prop="title">
        <el-input v-model="ruleForm.title" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="错误描述" prop="notice">
        <el-input v-model="ruleForm.notice" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="质控分类" prop="category">
        <el-select
          v-model="ruleForm.category"
          clearable
          placeholder="请选择"
          style="width: 100%"
        >
          <el-option
            v-for="item of categorys"
            :key="item"
            :label="item"
            :value="item"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="扣分" prop="score">
        <el-input v-model="ruleForm.score" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="质控类型" prop="type">
        <el-select
          v-model="ruleForm.type"
          clearable
          placeholder="请选择"
          style="width: 100%"
        >
          <el-option
            v-for="item of types"
            :key="item"
            :label="item"
            :value="item"
          />
        </el-select>
      </el-form-item>
      <!-- <el-form-item label="科室" prop="department"> -->
      <!-- <el-input v-model="ruleForm.department" placeholder="请输入" /> -->
      <!-- <el-select
          v-model="ruleForm.department"
          clearable
          multiple
          filterable
          placeholder="请选择"
          style="width: 100%"
        >
          <el-option
            v-for="item of deptList"
            :key="item.dep_id"
            :label="item.dep_name"
            :value="item.dep_id"
          />
        </el-select>
      </el-form-item> -->
      <el-form-item label="单项否决" prop="one_no">
        <el-select
          v-model="ruleForm.one_no"
          clearable
          placeholder="请选择"
          style="width: 100%"
        >
          <el-option
            v-for="item of oneNos"
            :key="item.name"
            :label="item.name"
            :value="item.value"
          />
        </el-select>
      </el-form-item>
      <!-- <el-form-item label="质控节点" prop="node"> -->
      <!-- <el-input v-model="ruleForm.node" placeholder="请输入" /> -->
      <!-- <el-select
          v-model="ruleForm.node"
          placeholder="质控节点"
          style="width: 100%"
          multiple
          filterable
          clearable
        >
          <el-option
            v-for="item in node_options"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item> -->
    </el-form>
    <span slot="footer" class="dialog-footer">
      <el-button @click="data.bSwitch = false">取 消</el-button>
      <el-button
        type="primary"
        @click="submitForm('ruleForm')"
      >确 定</el-button>
    </span>
  </el-dialog>
</template>

<script>
import { createCaseRuleList, getDepartmentList } from '@/api/admin'
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          row: {}
        }
      }
    },
    categorys: {
      type: Array,
      default() {
        return []
      }
    },
    types: {
      type: Array,
      default() {
        return []
      }
    }
  },
  data() {
    return {
      ruleForm: {
        title: '',
        notice: '',
        category: '',
        score: '',
        type: '',
        department: '',
        one_no: '',
        node: []
      },
      node_options: [
        { id: 0, name: '终末' },
        { id: 1, name: '运行' }
      ],
      deptList: [],
      rules: {
        title: [{ required: true, message: '请输入', trigger: 'blur' }],
        notice: [{ required: true, message: '请输入', trigger: 'blur' }],
        category: [{ required: true, message: '请输入', trigger: 'blur' }],
        score: [{ required: true, message: '请输入', trigger: 'blur' }],
        type: [{ required: true, message: '请输入', trigger: 'blur' }],
        department: [{ required: true, message: '请选择', trigger: 'blur' }],
        one_no: [{ required: true, message: '请输入', trigger: 'blur' }],
        node: [{ required: true, message: '请选择', trigger: 'blur' }]
      },
      oneNos: [
        { name: '否', value: 0 },
        { name: '是', value: 1 }
      ]
    }
  },
  computed: {
    titleStr() {
      return this.data.row.id ? '编辑' : '新增'
    }
  },
  created() {
    this.getDeptList()
    if (this.data.row.id) {
      const {
        title,
        notice,
        category,
        id,
        score,
        type,
        department,
        one_no,
        node
      } = this.data.row
      this.ruleForm.title = title
      this.ruleForm.notice = notice
      this.ruleForm.category = category
      this.ruleForm.id = id
      this.ruleForm.score = score
      this.ruleForm.type = type
      this.ruleForm.department = department
      this.ruleForm.node = node
      this.ruleForm.one_no = one_no
    }
  },
  methods: {
    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          createCaseRuleList(this.ruleForm).then((res) => {
            this.data.bSwitch = false
            this.$emit('refresh')
            this.$message.success(res.m || '操作成功')
          })
        } else {
          return false
        }
      })
    },
    getDeptList() {
      getDepartmentList().then((res) => {
        this.deptList = res.p
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.demo-ruleForm {
  width: 80%;
}
</style>
