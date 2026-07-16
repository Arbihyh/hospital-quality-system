<template>
  <div>
    <el-dialog title="修改" :visible.sync="data.bSwitch" width="400px" :top="data.rows.length > 1 ? '3vh' : '15vh'">
      <span style="line-height: 40px;">请输入指标数据</span>
      <el-form ref="form" label-width="80px" style="width: 100%; margin: 0; padding: 0;">
        <div class="form-items-container" :class="{
          'two-col': data.rows.length > 2,
          'one-col': data.rows.length <= 2
        }">
          <el-form-item v-for="(item, index) of data.rows" :key="index" :label="`${item.year}-${item.month}`"
            class="form-item-item" style="margin: 0 0 16px 0 !important;">
            <el-input-number v-model="item.num" :min="0" label="请输入" :controls="false"
              style="width: 100%;"></el-input-number>
          </el-form-item>
        </div>
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
    },
    indexName: {
      type: String,
      default: ''
    }
  },
  methods: {
    onSubmit() {
      const customData = this.data.rows.map(item => ({
        month: item.month,
        year: item.year,
        fenzi: this.data.type === 'fenzi' ? item.num : item.fenzi,
        fenmu: this.data.type === 'fenmu' ? item.num : item.fenmu
      }));
      const params = {
        index_name: this.indexName,
        custom_data: customData,
      }
      this.$axios2.post('/catalog_update_custom_data', params).then(res => {
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
  box-sizing: border-box;
}

::v-deep .el-input-number .el-input__inner {
  text-align: left;
}

/* 基础容器样式 */
::v-deep .form-items-container {
  display: flex !important;
  flex-wrap: wrap !important;
  width: 100% !important;
  gap: 10px !important;
  box-sizing: border-box !important;
  padding: 0 !important;
}

/* 1个/2个表单项：单行100%宽度 */
::v-deep .one-col .form-item-item {
  width: 100% !important;
  flex: 1 0 100% !important;
  max-width: 100% !important;
}

/* 超过2个表单项：一行两个 */
::v-deep .two-col .form-item-item {
  width: calc(50% - 5px) !important;
  flex: 0 0 calc(50% - 5px) !important;
  max-width: calc(50% - 5px) !important;
  min-width: 150px !important;
  /* 防止窄屏挤压 */
}
</style>