<template>
  <div>
    <el-dialog
      :title="titleStr"
      :visible.sync="data.bSwitch"
      width="600px"
      top="5vh"
    >
      <el-form ref="ruleForm" :model="ruleForm" :rules="rules" label-width="120px" class="demo-ruleForm">
        <el-form-item label="科室名称">
          <el-select v-model="ruleForm.KSMC" filterable clearable placeholder="请选择" style="width: 100%;">
            <el-option v-for="item of deportments" :key="item.id" :label="item.name" :value="item.name" />
          </el-select>
        </el-form-item>
        <el-form-item label="疾病名称" prop="JBMC">
          <el-input v-model="ruleForm.JBMC" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="疾病别名" prop="BM">
          <el-input v-model="ruleForm.BM" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="疾病编码">
          <el-input v-model="ruleForm.JBBM" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="鉴别诊断">
          <el-input v-model="ruleForm.JBZD" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="症状">
          <el-input v-model="ruleForm.ZZ" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="体征">
          <el-input v-model="ruleForm.TZ" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="药品">
          <el-input v-model="ruleForm.YP" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="治疗">
          <el-input v-model="ruleForm.ZL" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="检查">
          <el-input v-model="ruleForm.JC" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="检验">
          <el-input v-model="ruleForm.JJ" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="并发症">
          <el-input v-model="ruleForm.BFZ" placeholder="请输入" />
        </el-form-item>
        <el-form-item label="参考文献">
          <el-input v-model="ruleForm.CKWX" placeholder="请输入" />
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
import { getDeportmentList } from '@/api/admin'
import { illnessAdd, illnessInfo, illnessUpdate } from '@/api/knowledge'
export default {
  props: {
    data: {
      type: Object,
      default() {
        return {
          bSwitch: false,
          id: ''
        }
      }
    }
  },
  data() {
    return {
      ruleForm: {
        KSMC: '',
        JBMC: '',
        BM: '',
        JBBM: '',
        JBZD: '',
        ZZ: '',
        TZ: '',
        YP: '',
        ZL: '',
        JC: '',
        JJ: '',
        BFZ: '',
        CKWX: ''
      },
      rules: {
        JBMC: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
        BM: [
          { required: true, message: '请输入', trigger: 'blur' }
        ]
      },
      deportments: []
    }
  },
  computed: {
    titleStr() {
      return this.data.id ? '编辑' : '新增'
    }
  },
  created() {
    if (this.data.id) {
      this.getInfo()
    }
    this.getDeportmentList()
  },
  methods: {
    // 详情
    getInfo() {
      illnessInfo({ id: this.data.id }).then(res => {
        const { p } = res
        this.ruleForm = p.data
      }).catch(error => {
        console.log(error)
      })
    },
    // 部门
    getDeportmentList() {
      getDeportmentList().then(res => {
        const { p } = res
        if (Object.keys(p.list).length) {
          for (const key in p.list) {
            this.deportments.push({
              id: key,
              name: p.list[key]
            })
          }
        }
      }).catch(error => {
        console.log(error)
      })
    },
    // 提交
    onSubmit() {
      this.$refs['ruleForm'].validate((valid) => {
        if (valid) {
          if (this.data.id) {
            this.editSubmit()
          } else {
            this.createSubmit()
          }
        } else {
          return false
        }
      })
    },
    createSubmit() {
      illnessAdd(this.ruleForm).then(res => {
        const { m } = res
        this.$message.success(m || '成功')
        this.data.bSwitch = false
        this.$emit('refresh')
      }).catch(error => {
        console.log(error)
      })
    },
    editSubmit() {
      illnessUpdate(this.ruleForm).then(res => {
        const { m } = res
        this.$message.success(m || '成功')
        this.data.bSwitch = false
        this.$emit('refresh')
      }).catch(error => {
        console.log(error)
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.demo-ruleForm {
  width: 85%;
}
</style>
