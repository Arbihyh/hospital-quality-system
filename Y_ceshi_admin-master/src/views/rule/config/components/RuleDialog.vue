<template>
  <el-dialog v-el-drag-dialog title="规则详情" :visible.sync="data.bSwitch" width="1200px" top="5vh">
    <el-form
      ref="ruleForm"
      :model="ruleForm"
      :rules="rules"
      label-width="80px"
      class="demo-ruleForm"
    >
      <el-row :gutter="16">
        <el-col :span="24">
          <el-form-item label="质控规则">
            <el-col :span="5">
              <el-form-item label="规则模板" prop="rule_type">
                <el-select
                  v-model="ruleForm.rule_type"
                  filterable
                  placeholder="请选择"
                  disabled
                  style="width: 100%;"
                >
                  <el-option label="普通规则" value="普通规则" />
                  <el-option label="门诊规则" value="门诊规则" />
                  <!-- <el-option label="时效性规则" value="时效性规则" />
                  <el-option label="完整性规则" value="完整性规则" />
                  <el-option label="知识库规则" value="知识库规则" />
                  <el-option label="大模型规则" value="大模型规则" />-->
                </el-select>
              </el-form-item>
            </el-col>
            <!-- 前置条件 -->
            <el-col v-if="ruleForm.qztj.length" :span="24">
              <el-card
                v-for="(item, index) of ruleForm.qztj"
                :key="'qztj'+index"
                class="box-card"
                shadow="never"
              >
                <div slot="header" class="clearfix box-card_header span">
                  <span class="text">前置条件{{ index + 1 }}</span>
                  <span class="ml40">
                    <span>流向</span>
                    <el-radio v-model="item.condition_type" :label="1" disabled class="ml40">过滤</el-radio>
                    <el-radio v-model="item.condition_type" :label="2" disabled>免审</el-radio>
                  </span>
                  <!-- <i class="el-icon-delete" style="float: right; margin-top: 13px; cursor: pointer;" @click="onDeleteQZTJ(index)" /> -->
                </div>
                <div>
                  <el-row
                    v-for="(sItem, sIndex) of item.condition_content"
                    :key="'tj'+sIndex"
                    :gutter="12"
                    class="mb12"
                  >
                    <el-col :span="2">
                      <div class="text-right">条件{{ sIndex + 1 }}</div>
                    </el-col>
                    <el-col :span="8">
                      <el-cascader
                        v-model="sItem.param1"
                        :options="objects"
                        :props="{
                          expandTrigger: 'hover',
                          value: 'field',
                          label: 'field_name',
                          children: 'child'
                        }"
                        clearable
                        filterable
                        disabled
                        :show-all-levels="false"
                        placeholder="请选择"
                        style="width: 100%;"
                      />
                    </el-col>
                    <el-col :span="4">
                      <el-select
                        v-model="sItem.condition"
                        filterable
                        clearable
                        disabled
                        placeholder="请选择"
                        style="width: 100%;"
                      >
                        <el-option label="包含" value="包含" />
                        <el-option label="不包含" value="不包含" />
                        <el-option label="大于" value="大于" />
                        <el-option label="小于" value="小于" />
                        <el-option label="等于" value="等于" />
                      </el-select>
                    </el-col>
                    <el-col :span="6">
                      <el-input
                        v-model="sItem.param2"
                        clearable
                        disabled
                        placeholder="请输入"
                        style="width: 100%;"
                      />
                    </el-col>
                  </el-row>
                  <div class="span">
                    <span>条件之间的逻辑</span>
                    <el-radio
                      v-model="item.condition_relation"
                      :label="1"
                      disabled
                      class="ml40"
                    >且（满足所有条件）</el-radio>
                    <el-radio v-model="item.condition_relation" :label="2" disabled>或（满足任意一条件）</el-radio>
                  </div>
                </div>
              </el-card>
            </el-col>
            <!-- 质控逻辑 -->
            <el-col :span="24">
              <el-divider />
              <el-card
                v-for="(item, index) of ruleForm.rule"
                :key="'rule'+index"
                class="box-card"
                shadow="never"
              >
                <div slot="header" class="clearfix box-card_header span">
                  <span class="text">质控逻辑</span>
                  <span class="ml40">
                    <span>流向</span>
                    <el-radio v-model="item.detail_status" :label="1" class="ml40">正确</el-radio>
                    <el-radio v-model="item.detail_status" :label="2">错误</el-radio>
                  </span>
                </div>
                <div>
                  <el-row
                    v-for="(sItem, sIndex) of item.condition_content"
                    :key="'tj'+sIndex"
                    :gutter="12"
                    class="mb12"
                  >
                    <el-col :span="2">
                      <div class="text-right">逻辑{{ sIndex + 1 }}</div>
                    </el-col>
                    <el-col :span="8">
                      <el-cascader
                        v-model="sItem.param1"
                        :options="objects"
                        :props="{
                          expandTrigger: 'hover',
                          value: 'field',
                          label: 'field_name',
                          children: 'child'
                        }"
                        clearable
                        filterable
                        disabled
                        :show-all-levels="false"
                        placeholder="请选择"
                        style="width: 100%;"
                      />
                    </el-col>
                    <el-col :span="4">
                      <el-select
                        v-model="sItem.condition"
                        filterable
                        clearable
                        disabled
                        placeholder="请选择"
                        style="width: 100%;"
                      >
                        <el-option label="包含" value="包含" />
                        <el-option label="不包含" value="不包含" />
                        <el-option label="大于" value="大于" />
                        <el-option label="小于" value="小于" />
                        <el-option label="等于" value="等于" />
                      </el-select>
                    </el-col>
                    <el-col :span="6">
                      <el-autocomplete
                        v-model="sItem.param2"
                        class="inline-input"
                        value-key="name"
                        disabled
                        :fetch-suggestions="querySearch"
                        placeholder="请输入"
                        :trigger-on-focus="false"
                        @select="handleSelect"
                      />
                    </el-col>
                  </el-row>
                  <div class="span">
                    <span>规则之间的逻辑</span>
                    <el-radio
                      v-model="item.condition_relation"
                      :label="1"
                      disabled
                      class="ml40"
                    >且（满足所有条件）</el-radio>
                    <el-radio v-model="item.condition_relation" :label="2" disabled>或（满足任意一条件）</el-radio>
                  </div>
                </div>
              </el-card>
            </el-col>
          </el-form-item>
        </el-col>
      </el-row>

      <div v-if="data.isParamsEx">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item prop="disease" label="病种">
              <el-select
                v-model="ruleForm.disease"
                :disabled="data.isDisable"
                clearable
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
          <el-col :span="12">
            <el-form-item label="质控依据" prop="quality_basis">
              <el-input v-model="ruleForm.quality_basis" placeholder="请输入质控依据" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="依据来源" prop="basis_source">
              <el-select
                v-model="ruleForm.basis_source"
                clearable
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
            :rows="2"
            placeholder="请输入触发条件"
          />
        </el-form-item>

        <el-form-item label="判断口径" prop="judgment_caliber">
          <el-input
            v-model="ruleForm.judgment_caliber"
            type="textarea"
            :rows="2"
            placeholder="请输入判断口径"
          />
        </el-form-item>
      </div>
    </el-form>
  </el-dialog>
</template>

<script>
import { add_rule } from '@/api/rule/config'
import { get_all_word_map } from '@/api/dict'
import { get_rule_detail } from '@/api/rule/config'

export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          row: {},
          isParamsEx: false
        }
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
    objects: {
      type: Array,
      default() {
        return []
      }
    },
    departments: {
      type: Array,
      default() {
        return []
      }
    }
  },
  data() {
    return {
      qcData: [],
      ruleForm: {
        case_type: '',
        changjing: [],
        department: [],
        object: [],
        type: '',
        is_not: '',
        description: '',
        score: undefined,
        error_level: '',
        status: '',
        rule_type: '普通规则',
        disease: '', // 病种
        trigger_condition: '', // 触发条件
        judgment_caliber: '', // 判断口径
        quality_basis: '', // 质控依据
        basis_source: '', // 依据来源
        data_source: '', // 数据来源
        rule: [
          // 质控逻辑只有一条
          {
            is_pre_condition: 0,
            condition_type: 1,
            condition_relation: 2,
            detail_status: 1,
            condition_content: [
              {
                param1: '',
                param2: '',
                condition: '包含'
              }
            ]
          }
        ],
        qztj: [
          // 前置条件默认只有一条
          {
            is_pre_condition: 1,
            condition_type: 1,
            condition_relation: 2,
            detail_status: 1,
            condition_content: [
              {
                param1: '',
                param2: '',
                condition: '包含'
              }
            ]
          }
        ]
      },
      rules: {
        description: [{ required: true, message: '请输入', trigger: 'blur' }],
        score: [{ required: true, message: '请输入', trigger: 'blur' }],
        case_type: [{ required: true, message: '请选择', trigger: 'blur' }],
        changjing: [{ required: true, message: '请选择', trigger: 'blur' }],
        department: [{ required: true, message: '请选择', trigger: 'blur' }],
        object: [{ required: true, message: '请选择', trigger: 'blur' }],
        type: [{ required: true, message: '请选择', trigger: 'blur' }],
        is_not: [{ required: true, message: '请选择', trigger: 'blur' }],
        error_level: [{ required: true, message: '请选择', trigger: 'blur' }],
        status: [{ required: true, message: '请选择', trigger: 'blur' }],
        rule_type: [{ required: true, message: '请选择', trigger: 'blur' }]
      }
    }
  },
  created() {
    console.log(this.object)
    this.getQcData()
    if (this.data.row.id) {
      this.getDetail(this.data.row.id)
    }
  },
  methods: {
    // 获取详情
    getDetail(id) {
      console.log('获取详情')
      get_rule_detail({ id }).then((res) => {
        const { p } = res
        const {
          case_type,
          changjing,
          department,
          object,
          type,
          is_not,
          description,
          score,
          error_level,
          status,
          disease,
          trigger_condition,
          judgment_caliber,
          quality_basis,
          basis_source,
          data_source
        } = p.rule
        this.ruleForm.case_type = case_type
        this.ruleForm.changjing = changjing.split(',')
        this.ruleForm.department = this.toNumberArray(department)
        this.ruleForm.is_not = is_not
        this.ruleForm.status = status
        this.ruleForm.type = type
        this.ruleForm.error_level = error_level
        this.ruleForm.score = score

        this.ruleForm.disease = disease
        this.ruleForm.trigger_condition = trigger_condition
        this.ruleForm.judgment_caliber = judgment_caliber
        this.ruleForm.quality_basis = quality_basis
        this.ruleForm.basis_source = basis_source
        this.ruleForm.data_source = data_source

        this.ruleForm.description = description
        this.ruleForm.object = object.split(',')
        this.ruleForm.rule = this.getQcRule(p.rule_list)
        this.ruleForm.qztj = this.getPreRule(p.rule_list)
        console.log('=============', this.ruleForm)
      })
    },
    // 找到前置条件
    getPreRule(list) {
      return list.filter((item) => item.is_pre_condition)
    },
    // 找到质控逻辑
    getQcRule(list) {
      const rule = list.filter((item) => !item.is_pre_condition)
      return rule
    },
    // string 数组 装 number 数组
    toNumberArray(slist) {
      const alist = []
      slist.map((item) => {
        alist.push(Number(item))
      })
      return alist
    },
    // 搜索质控字典
    getQcData() {
      get_all_word_map({ status: 1 }).then((res) => {
        const { p } = res
        this.qcData = Array.isArray(p) ? p : []
      })
    },
    querySearch(queryString, cb) {
      var qcData = this.qcData
      var results = queryString
        ? qcData.filter(this.createFilter(queryString))
        : qcData
      // 调用 callback 返回建议列表的数据
      cb(results)
    },
    createFilter(queryString) {
      return (restaurant) => {
        return (
          restaurant.name.toLowerCase().indexOf(queryString.toLowerCase()) === 0
        )
      }
    },
    handleSelect(item) {
      console.log(item)
    },
    // 添加前置条件大框
    onAddQZTJ() {
      this.ruleForm.qztj.push({
        is_pre_condition: 1,
        condition_type: 1,
        condition_relation: 2,
        detail_status: 1,
        condition_content: [
          {
            param1: '',
            param2: '',
            condition: '包含'
          }
        ]
      })
    },
    // 删除前置条件大框
    onDeleteQZTJ(index) {
      this.ruleForm.qztj.splice(index, 1)
    },
    // 新增前置条件大框中_条件
    onAddTJ(index, sIndex) {
      this.ruleForm.qztj[index].condition_content.push({
        param1: '',
        param2: '',
        condition: '包含'
      })
    },
    // 删除前置条件大框中_条件
    onDeleteTJ(index, sIndex) {
      this.ruleForm.qztj[index].condition_content.splice(sIndex, 1)
    },
    // 新增质控逻辑大框中_逻辑
    onAddGZ(index, sIndex) {
      this.ruleForm.rule[index].condition_content.push({
        param1: '',
        param2: '',
        condition: '包含'
      })
    },
    // 删除质控逻辑大框中_逻辑
    onDeleteGZ(index, sIndex) {
      this.ruleForm.rule[index].condition_content.splice(sIndex, 1)
    },
    hasEmptyValues(obj) {
      return Object.values(obj).some((value) => !value)
    },
    // 验证前置条件
    judgeQZTJ() {
      const { qztj } = this.ruleForm
      let result = true
      const e_index = []
      if (qztj.length) {
        for (let i = 0; i < qztj.length; i++) {
          qztj[i].condition_content.map((item) => {
            if (this.hasEmptyValues(item)) {
              e_index.push(i + 1)
            }
          })
        }
        if (e_index.length) {
          this.$message.error(`请完善前置条件${e_index.join()}`)
          result = false
        }
      }
      return result
    },
    // 验证质控逻辑
    judgeRule() {
      const { rule } = this.ruleForm
      let result = true
      // 质控逻辑只有一条
      rule[0].condition_content.map((item) => {
        if (this.hasEmptyValues(item)) {
          this.$message.error(`请完善质控逻辑`)
          result = false
        }
      })
      return result
    },
    submitForm(formName) {
      console.log(this.ruleForm.object)
      this.$refs[formName].validate(async(valid) => {
        const {
          case_type,
          changjing,
          department,
          object,
          type,
          is_not,
          description,
          score,
          error_level,
          status,
          rule_type,
          rule,
          qztj
        } = this.ruleForm
        if (valid) {
          if (this.judgeQZTJ() && this.judgeRule()) {
            const params = {
              case_type,
              changjing,
              department,
              object,
              type,
              is_not,
              description,
              score,
              error_level,
              status,
              rule_type,
              rule: [...rule, ...qztj]
            }
            if (this.data.row.id) {
              params.id = this.data.row.id
            }
            add_rule(params).then((res) => {
              this.data.bSwitch = false
              this.$emit('refresh')
              this.$message.success(res.m || '操作成功')
            })
          }
        } else {
          return false
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
::v-deep .el-col-15 {
  .el-form-item__label {
    width: 160px !important;
  }
}
.box-card {
  margin-bottom: 16px;
}
::v-deep .el-card__header {
  padding: 0;
  .box-card_header {
    background: #f7f7f8;
    padding: 0 20px;
    line-height: 40px;
  }
}
::v-deep .el-card__body {
  padding: 20px 12px;
}
.text-right {
  text-align: right;
}
.ml40 {
  margin-left: 40px;
}
.mb12 {
  margin-bottom: 12px;
}
.span {
  padding-top: 40px;
  .text {
    color: #909399;
  }
}
</style>
