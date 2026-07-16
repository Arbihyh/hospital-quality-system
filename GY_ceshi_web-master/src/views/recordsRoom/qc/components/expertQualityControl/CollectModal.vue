<template>
    <el-dialog :visible.sync="dialogVisible" class="custom-dialog" :modal="false" :title="getDialogTitle()" width="500px" @close="onCancel">
        <el-form :model="formData"
            :rules="rules" ref="formDataRef" label-width="80px">
            <el-form-item label="条件名称" prop="title">
                <el-input v-model="formData.title" placeholder="请输入"></el-input>
            </el-form-item>
            <el-form-item label="是否公共" prop="is_public">
                <el-radio-group v-model="formData.is_public">
                    <el-radio :label="1">是</el-radio>
                    <el-radio :label="2">否</el-radio>
                </el-radio-group>
            </el-form-item>
            <el-form-item label="默认条件" prop="is_default">
                <el-radio-group v-model="formData.is_default">
                    <el-radio :label="1">是</el-radio>
                    <el-radio :label="0">否</el-radio>
                </el-radio-group>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button type="primary" @click="onSubmit()">保存 </el-button>
            <el-button @click="onCancel()">取消 </el-button>
        </template>
    </el-dialog>
</template>
<script>
import { collectSearchSave } from '@/api/qc';
export default {
  emits: ['onUpdate'],
  data() {
    return {
        action: 'ADD',
      dialogVisible: false,
      formData: {
        id: '',
        title: '',
        is_public: 2,
        is_default: 0,
      },
      rules: {
        title: [{ required: true, message: '请输入' }],
        is_public: [{ required: true, message: '请选择' }],
        is_default: [{ required: true, message: '请选择' }],
      },
    };
  },
  computed: {},
  watch: {},
  created() {},
  mounted() {},
  beforeDestroy() {},

  methods: {
    getDialogTitle() {
      if(this.action === 'ADD') {
        return '收藏条件'
      }
      if(this.action === 'EDIT') {
        return '编辑条件'
      }
    },
        
    async openModal(action = 'ADD', params) {
        this.action = action;
        if(action == 'EDIT') {
            this.$nextTick(() => {
                for(let keys in this.formData) {
                    this.formData[keys] = params[keys]
                }
            })
        }
      this.dialogVisible = true;
    },
    
    onSubmit() {
      this.$refs.formDataRef.validate((valid) => {
        if (valid) {
            let params = {...this.formData}
            if(this.action == 'ADD') {
              params = {...this.$parent.formData, ...params}
            }
            collectSearchSave(params).then(res => {
              if(res.code == 200) {
                this.$message({
                  message: '操作成功',
                  type: 'success',
                });
                this.onCancel();
                if(this.action == 'EDIT') {
                    this.$emit('onUpdate')
                }
              }
            });        
        } else {
          console.log('error submit!!');
          return false;
        }
      });
      
    },
    onCancel() {
        this.dialogVisible = false;
        this.$refs.formDataRef && this.$refs.formDataRef.resetFields()
    }
  },
};
</script>

<style lang="scss" scoped>
::v-deep .el-dialog__header {
  background-color: hsl(205.32deg 43.43% 49.22%);
}

::v-deep .el-dialog__close {
  color: #fff;
  border: 1px solid #fff;
  border-radius: 20px;
}

::v-deep .el-dialog__title {
  color: #fff;
}
</style>
