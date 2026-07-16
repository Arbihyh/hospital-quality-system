<template>
  <div>
    <el-dialog v-el-drag-dialog append-to-body title="新增" :visible.sync="data.bSwitch" width="500px">
      <el-form :model="ruleForm" :rules="rules" ref="ruleForm" label-width="100px" class="demo-ruleForm">
        <el-form-item label="名称" prop="name">
          <el-input v-model="ruleForm.name" placeholder="请输入"></el-input>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="data.bSwitch = false">取 消</el-button>
        <el-button type="primary" @click="submitForm">确 定</el-button>
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
          bSwitch: false,
          pid: '',
          type: '',
          row: {}
        };
      },
    },
  },
  data() {
    return {
      ruleForm: {
        name: '',
      },
      rules: {
        name: [
          { required: true, message: '请输入', trigger: 'blur' }
        ],
      },
    };
  },
  created() {
    if (this.data.row.id) {
      this.ruleForm.name = this.data.row.name
    }
  },
  methods: {
    submitForm() {
      this.$refs['ruleForm'].validate((valid) => {
        if (valid) {
          if (this.data.row && this.data.row.id) {
            const params = {
              id: this.data.row.id,
              name: this.ruleForm.name
            }
            this.$axios2.post('/catalog_edit', params).then(res => {
              this.$emit('refresh')
              this.data.bSwitch = false
            });
          } else {
            this.onSubmit()
          }
        }
      });
    },
    onSubmit() {
      const params = {
        pid: this.data.pid,
        name: this.ruleForm.name
      }
      this.$axios2.post('/catalog_add', params).then(res => {
        this.$emit('refresh', this.data.type)
        this.$message.success('成功')
        this.data.bSwitch = false
      });
    }
  }
};
</script>

<style lang="scss" scoped>
.plus-btn {
  margin-left: 20px;
  &:hover {
    opacity: 0.6;
    cursor: pointer;
  }
}
.demo-ruleForm {
  width: 85%;
}
</style>