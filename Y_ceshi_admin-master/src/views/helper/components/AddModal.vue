<template>
  <div>
    <el-dialog
      width="950px"
      :title="`${actionType == 'ADD' ? '新增' : '编辑'} Prompt 任务`"
      :visible.sync="dialogFormVisible"
      @close="handleCancel"
    >
      <el-tabs v-model="formData.type" @tab-click="handleClickTab">
        <el-tab-pane
          :disabled="actionType !== 'ADD' && formData.type !== '1'"
          label="病历质控"
          name="1"
        />
        <el-tab-pane
          :disabled="actionType !== 'ADD' && formData.type !== '2'"
          label="病历生成"
          name="2"
        />
      </el-tabs>
      <el-form
        ref="formDataRef"
        :model="formData"
        :rules="rules"
        label-width="140px"
      >
        <el-form-item
          label="任务名称"
          prop="rule_id"
          :rules="
            formData.type == '2'
              ? [{ required: true, message: '请选择', trigger: 'blur' }]
              : [{ required: true, message: '请输入', trigger: 'blur' }]
          "
        >
          <el-cascader
            v-if="formData.type == 2"
            v-model="formData.rule_id"
            style="width: 100%"
            :options="scTaskNameList"
            :props="{
              value: 'id',
              label: 'name',
              children: 'child',
            }"
            placeholder="请选择"
          />

          <el-input
            v-else
            v-model="formData.rule_id"
            placeholder="请输入"
            size="small"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="模型入参" prop="data_type">
          <el-input
            v-model="formData.data_type"
            :placeholder="'请选择'"
            size="small"
            readonly
            style="width: 100%"
            @focus="openModelParamsDialog()"
          />
        </el-form-item>
        <el-form-item label="病历类型" prop="mblb">
          <el-select
            v-model="formData.mblb"
            filterable
            placeholder="请选择"
            style="width: 100%"
          >
            <el-option
              v-for="item in mblbList"
              :key="item.id"
              :label="item.field_name"
              :value="item.mblb"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="提示工程" prop="content">
          <el-input
            v-model="formData.content"
            type="textarea"
            :autosize="{ minRows: 8, maxRows: 12 }"
            placeholder="请输入"
          />
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button type="primary" @click="handleSubmit">发布</el-button>
        <!-- <el-button @click="handleCancel">取消</el-button> -->
        <el-button type="default" @click="handleTest">测试</el-button>
      </div>
    </el-dialog>
    <ModelParamsModal ref="ModelParamsModalRef" @onSelect="handleSelect" />
  </div>
</template>

<script>
import { addHelper, getRule, getTaskNameData, selectMblb } from '@/api/helper'
import ModelParamsModal from './ModelParamsModal.vue'
export default {
  components: {
    ModelParamsModal
  },
  emits: ['onSuccess'],
  data() {
    return {
      dialogFormVisible: false,
      scTaskNameList: [],
      mblbList: [],
      zkTaskNameList: [],
      formData: {
        id: '',
        type: '1',
        rule_id: '',
        data_type: '',
        app_name: '',
        mblb: '',
        content: ''
      },
      rules: {
        // rule_id: [{ required: true, message: "请选择", trigger: "blur" }],
        // app_name: [{ required: true, message: "请输入", trigger: "blur" }],
        data_type: [{ required: true, message: '请选择', trigger: 'xx' }],
        mblb: [{ required: true, message: '请选择', trigger: 'xx' }],
        content: [{ required: true, message: '请输入', trigger: 'blur' }]
      },
      actionType: 'ADD',
      selectModelDataList: []
    }
  },
  created() {
    this.getSCTaskNameList()
    this.getZKTaskNameList()
    this.selectMblbM()
  },
  methods: {
    openModal(params, action) {
      this.dialogFormVisible = true
      this.$nextTick(() => {
        for (const key in this.formData) {
          this.formData[key] = params[key]
        }
        if (action !== 'ADD') {
          this.formData.type = String(this.formData.type)
          this.formData.rule_id = JSON.parse(this.formData.rule_id)
          this.$refs.ModelParamsModalRef.getSelectList(this.formData.data_type)
          if (this.formData.type !== '2') {
            this.formData.rule_id = this.formData.app_name
          }
        }
      })
      this.actionType = action
    },
    getSCTaskNameList() {
      getTaskNameData().then((res) => {
        const { p } = res
        this.scTaskNameList = p || []
      })
    },
    selectMblbM() {
      selectMblb().then((res) => {
        const { p } = res
        this.mblbList = p || []
      })
    },
    getZKTaskNameList() {
      getRule().then((res) => {
        const { p } = res
        this.zkTaskNameList = p || []
      })
    },
    handleClickTab(tab) {
      this.formData.type = tab.name
      // this.formData.rule_id = "";
      if (tab.name === '2') {
        this.formData.rule_id = []
      } else {
        this.formData.rule_id = ''
      }
    },
    handleTest() {
      const url = 'http://172.16.9.43:8001'
      window.open(url, '_blank')
    },
    openModelParamsDialog() {
      this.$refs.ModelParamsModalRef.openModal(this.selectModelDataList)
    },
    handleSubmit() {
      this.$refs['formDataRef'].validate((valid) => {
        if (valid) {
          const dataType = []
          this.selectModelDataList.forEach((element) => {
            const obj = {}
            obj.table = element.field
            obj.field = element.child
              ? element.child.map((item) => item.field)
              : []
            dataType.push(obj)
          })
          const params = {
            ...this.formData,
            data_type: dataType
          }
          addHelper(params).then((res) => {
            this.$message.success(res.m || '发布成功')
            this.handleCancel()
            this.$emit('onSuccess')
          })
        } else {
          return false
        }
      })
    },
    handleCancel() {
      this.dialogFormVisible = false
      this.selectModelDataList = []
      this.$refs['formDataRef'].resetFields()
    },
    handleSelect(val) {
      this.selectModelDataList = val
      const values = []
      val.forEach((element) => {
        if (element.child) {
          values.push(...element.child)
        }
      })
      this.formData.data_type = values
        .map((element) => element.field_name)
        .join(',')
    }
  }
}
</script>

<style lang="scss" scoped></style>
