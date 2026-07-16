<template>
  <el-dialog :title="titleStr" :visible.sync="data.bSwitch" width="40%">
    <el-form
      ref="ruleForm"
      :model="ruleForm"
      :rules="rules"
      label-width="140px"
      class="demo-ruleForm"
    >
      <el-form-item label="字典名称" prop="name">
        <el-input v-model="ruleForm.name" :disabled="data.row.id" placeholder="请输入" />
      </el-form-item>
      <el-form-item label="映射名称">
        <el-tag
          v-for="tag in ruleForm.keywords"
          :key="tag"
          size="-"
          closable
          effect="plain"
          :disable-transitions="false"
          @close="handleClose(tag)"
        >
          {{ tag }}
        </el-tag>
        <el-input
          v-if="inputVisible"
          ref="saveTagInput"
          v-model="inputValue"
          class="input-new-tag"
          @keyup.enter.native="handleInputConfirm"
          @blur="handleInputConfirm"
        />
        <el-button
          v-else
          type="primary"
          plain
          class="button-new-tag"
          @click="showInput"
        >+ 添加</el-button>
      </el-form-item>
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
import { add_word_map, edit_word_map } from '@/api/dict'
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
    }
  },
  data() {
    return {
      inputVisible: false,
      inputValue: '',
      ruleForm: {
        name: '',
        keywords: []
      },
      rules: {
        name: [{ required: true, message: '请输入', trigger: 'blur' }]
      }
    }
  },
  computed: {
    titleStr() {
      return this.data.row.id ? '编辑' : '新增'
    }
  },
  created() {
    if (this.data.row.id) {
      const { name, keywords, id } = this.data.row
      this.ruleForm.name = name
      this.ruleForm.keywords = keywords
      this.ruleForm.id = id
    }
  },
  methods: {
    handleClose(tag) {
      this.ruleForm.keywords.splice(this.ruleForm.keywords.indexOf(tag), 1)
    },

    showInput() {
      this.inputVisible = true
      this.$nextTick((_) => {
        this.$refs.saveTagInput.$refs.input.focus()
      })
    },

    handleInputConfirm() {
      const inputValue = this.inputValue
      if (inputValue) {
        this.ruleForm.keywords.push(inputValue)
      }
      this.inputVisible = false
      this.inputValue = ''
    },
    submitForm(formName) {
      this.$refs[formName].validate(async(valid) => {
        if (valid) {
          const { id, name, keywords } = this.ruleForm
          const params = {
            name,
            keywords: JSON.stringify(keywords)
          }
          if (this.ruleForm.id) {
            params.id = id
            edit_word_map(params).then((res) => {
              this.data.bSwitch = false
              this.$emit('refresh')
              this.$message.success(res.m || '操作成功')
            })
          } else {
            add_word_map(params).then((res) => {
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
.demo-ruleForm {
  width: 80%;
  .el-tag {
    margin-right: 10px;
    margin-bottom: 10px;
  }
  // .el-tag + .el-tag {
  //   margin-right: 10px;
  // }
  .button-new-tag {
    margin-right: 10px;
    height: 32px;
    line-height: 30px;
    padding-top: 0;
    padding-bottom: 0;
  }
  .input-new-tag {
    width: 90px;
    margin-right: 10px;
    vertical-align: top;
  }
}
</style>
