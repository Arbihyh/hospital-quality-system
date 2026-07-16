<template>
  <el-dialog
    :title="titleStr"
    height="75vh"
    top="10vh"
    :visible.sync="data.bSwitch"
    class="rule-detail-dialog"
    width="50%"
  >
    <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="80px">
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="质控项目" prop="title">
            <el-input v-model="ruleForm.title" :disabled="data.isDisable" placeholder="请输入质控项目" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="状态" prop="status">
            <el-select
              v-model="ruleForm.status"
              :disabled="data.isDisable"
              placeholder="请选择状态"
              style="width: 100%"
            >
              <el-option label="启用" :value="1" />
              <el-option label="停用" :value="2" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="运行节点">
            <el-select
              v-model="ruleForm.node"
              :disabled="data.isDisable"
              clearable
              placeholder="全部"
              style="width: 100%;"
            >
              <el-option
                v-for="item in nodeOptions"
                :key="item.id"
                :label="item.name"
                :value="item.name"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="科室" prop="department">
            <el-select
              v-model="ruleForm.department"
              :disabled="data.isDisable"
              clearable
              filterable
              placeholder="请选择"
              style="width: 100%;"
            >
              <el-option
                v-for="item in deptList"
                :key="item.id"
                :label="item.dep_name"
                :value="item.dep_name"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="质控分类" prop="category">
            <el-select
              v-model="ruleForm.category"
              :disabled="data.isDisable"
              clearable
              placeholder="请选择"
              style="width: 100%"
            >
              <el-option v-for="item of categorys" :key="item" :label="item" :value="item" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="扣分" prop="score">
            <el-input
              v-model="ruleForm.score"
              controls-position="right"
              placeholder="请输入"
              style="width: 100%;"
              oninput="this.value = this.value.replace(/[^\d.]/g, '').replace(/^\./g, '').replace(/\.{2,}/g, '.').replace('.', '$#$').replace(/\./g, '').replace('$#$', '.').replace(/^(\d+)\.(\d*)\.$/, '$1.$2')"
            />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="质控类型" prop="type">
            <el-select
              v-model="ruleForm.type"
              :disabled="data.isDisable"
              clearable
              filterable
              placeholder="请选择"
              style="width: 100%"
            >
              <el-option v-for="item of types" :key="item" :label="item" :value="item" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="单项否决" prop="one_no">
            <el-select
              v-model="ruleForm.one_no"
              :disabled="data.isDisable"
              clearable
              placeholder="请选择"
              style="width: 100%"
            >
              <el-option
                v-for="item of oneNos"
                :key="item.value"
                :label="item.name"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="问题等级" prop="level">
            <el-select
              v-model="ruleForm.level"
              clearable
              :disabled="data.isDisable"
              placeholder="请选择等级"
              style="width: 100%"
            >
              <el-option label="强制" :value="1" />
              <el-option label="建议" :value="2" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item prop="disease" label="病种">
            <el-select
              v-model="ruleForm.disease"
              :disabled="data.isDisable"
              clearable
              filterable
              placeholder="请选择"
              style="width: 100%;"
            >
              <el-option
                v-for="item in diseaseOptions"
                :key="item.id"
                :label="item.name"
                :value="item.name"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="依据来源" prop="basis_source">
            <el-select
              v-model="ruleForm.basis_source"
              :disabled="data.isDisable"
              clearable
              filterable
              placeholder="请选择"
              style="width: 100%;"
            >
              <el-option
                v-for="item in sourceOptions"
                :key="item.id"
                :label="item.name"
                :value="item.name"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="数据来源" prop="data_source">
            <el-select
              v-model="ruleForm.data_source"
              clearable
              filterable
              :disabled="data.isDisable"
              placeholder="请选择"
              style="width: 100%;"
            >
              <el-option
                v-for="item in sourceOptions"
                :key="item.id"
                :label="item.name"
                :value="item.name"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="触发条件" prop="trigger_condition">
        <el-input
          v-model="ruleForm.trigger_condition"
          type="textarea"
          :disabled="data.isDisable"
          :rows="3"
          placeholder="请输入触发条件"
        />
      </el-form-item>

      <el-form-item label="判断口径" prop="judgment_caliber">
        <el-input
          v-model="ruleForm.judgment_caliber"
          type="textarea"
          :disabled="data.isDisable"
          :rows="8"
          placeholder="请输入判断口径"
        />
      </el-form-item>

      <el-form-item label="质控依据" prop="quality_basis">
        <el-input
          v-model="ruleForm.quality_basis"
          :disabled="data.isDisable"
          type="textarea"
          :rows="5"
          placeholder="请输入质控依据"
        />
      </el-form-item>

      <el-form-item label="错误描述" prop="notice">
        <el-input
          v-model="ruleForm.notice"
          :disabled="data.isDisable"
          placeholder="请输入错误描述"
          type="textarea"
          :rows="2"
        />
      </el-form-item>
    </el-form>

    <span slot="footer" class="dialog-footer">
      <el-button @click="data.bSwitch = false">取 消</el-button>
      <el-button type="primary" @click="submitForm('ruleForm')">确 定</el-button>
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
          row: {},
          isDisable: false
        }
      }
    },
    categorys: {
      type: Array,
      default() {
        return []
      }
    },
    diseaseOptions: {
      type: Array,
      default() {
        return []
      }
    },
    sourceOptions: {
      type: Array,
      default() {
        return []
      }
    },
    nodeOptions: {
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
        // 原有
        title: '',
        notice: '',
        category: '',
        score: '',
        type: '',
        department: '',
        one_no: '',
        node: [],

        level: 2, // 等级 1强制 2建议（默认2）
        disease: '', // 病种
        trigger_condition: '', // 触发条件
        judgment_caliber: '', // 判断口径
        quality_basis: '', // 质控依据
        basis_source: '', // 依据来源
        data_source: '', // 数据来源
        status: 1 // 状态 1启用 2停用（默认1）
      },
      node_options: [
        { id: 0, name: '终末' },
        { id: 1, name: '运行' }
      ],
      deptList: [],
      rules: {
        title: [{ required: true, message: '请输入质控项目', trigger: 'blur' }],
        notice: [
          { required: true, message: '请输入错误描述', trigger: 'blur' }
        ],
        category: [
          { required: true, message: '请选择质控分类', trigger: 'change' }
        ],
        score: [{ required: true, message: '请输入扣分', trigger: 'blur' }],
        type: [
          { required: true, message: '请选择质控类型', trigger: 'change' }
        ],
        one_no: [
          { required: true, message: '请选择单项否决', trigger: 'change' }
        ],

        level: [],
        disease: [],
        trigger_condition: [],
        judgment_caliber: [],
        quality_basis: [],
        basis_source: [],
        data_source: [],
        status: []
      },
      oneNos: [
        { name: '否', value: 0 },
        { name: '是', value: 1 }
      ]
    }
  },
  computed: {
    titleStr() {
      return this.data.row.id ? '编辑规则' : '新增规则'
    }
  },
  watch: {
    'data.row': {
      handler(val) {
        if (val && val.id) {
          this.setFormValue(val)
        }
      },
      deep: true,
      immediate: true
    }
  },
  created() {
    this.getDeptList()
  },
  methods: {
    setFormValue(row) {
      const {
        title,
        notice,
        category,
        id,
        score,
        type,
        department,
        one_no,
        node,
        level,
        disease,
        trigger_condition,
        judgment_caliber,
        quality_basis,
        basis_source,
        data_source,
        status
      } = row

      this.ruleForm = {
        ...this.ruleForm,
        title,
        notice,
        category,
        id,
        score,
        type,
        department,
        one_no,
        node,

        // 新增
        level: level ?? 2,
        disease: disease || '',
        trigger_condition: trigger_condition || '',
        judgment_caliber: judgment_caliber || '',
        quality_basis: quality_basis || '',
        basis_source: basis_source || '',
        data_source: data_source || '',
        status: status ?? 1
      }
    },

    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          createCaseRuleList(this.ruleForm).then((res) => {
            this.data.bSwitch = false
            this.$emit('refresh')
            this.$message.success(res.m || '操作成功')
            this.$refs[formName].resetFields()
          })
        }
      })
    },

    // 获取科室
    getDeptList() {
      getDepartmentList().then((res) => {
        this.deptList = res.p || []
      })
    }
  }
}
</script>
<style  scoped>
::v-deep .el-dialog__body {
  max-height: 75vh;
  overflow-y: auto;
  padding: 10px 20px;
}
</style>

<style lang="scss" scoped>
.rule-detail-dialog {
  width: 90%;
  margin: 0 auto;
  padding: 10px 0;
}
.dialog-footer {
  text-align: right;
}
</style>
