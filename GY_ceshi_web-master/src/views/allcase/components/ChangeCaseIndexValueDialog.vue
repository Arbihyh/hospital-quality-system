<template>
  <div>
    <el-dialog
      title="修改"
      :visible.sync="data.bSwitch"
      width="400px"
      :top="data.rows.length > 1 ? '3vh' : '15vh'">
      <span style="line-height: 40px;">请输入指标数据</span>
      <el-form ref="form" label-width="80px">
        <el-form-item v-for="(item, index) of data.rows" :key="index" :label="`${item.year}-${item.month}`">
          <el-input-number v-model="item.num" :min="0" label="请输入" :controls="false" style="width: 100%;"></el-input-number>
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
            bSwitch: false,
            rows: []
          }
        }
      }
    },
    methods: {
      onSubmit() {
        const params = {
          save_data: JSON.parse(JSON.stringify(this.data.rows))
        }
        this.$axios2.post('/add_zb', params).then(res => {
          this.$message.success('修改成功')
          this.data.bSwitch = false
          this.$emit('refresh')
        });
      }
    }
  }
</script>

<style lang="scss" scoped>
::v-deep .el-dialog__body {
  padding: 10px 20px;
}
::v-deep .el-input-number .el-input__inner {
  text-align: left;
}
</style>