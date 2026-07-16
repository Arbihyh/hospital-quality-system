<!-- 弹窗底部按钮 -->
<template>
  <div class="page-container">
    <el-button class="cancel-btn" size="small" @click="handleCancel">{{cancelText}}</el-button>
    <el-button class="confirm-btn" size="small" type="primary" @click="handleConfirm">{{ confirmText }}</el-button>
  </div>
</template>

<script>
export default {
  name: 'DialogFooterBtn',
  props: {
    cancelText: {
      type: String,
      default: '取消',
    },
    confirmText: {
      type: String,
      default: '确定',
    },
  },
  methods: {
    handleCancel() {
      this.$emit('cancel');
    },
    handleConfirm() {
      this.$emit('confirm');
    },
  },
};
</script>

<style  lang="scss" scoped>
.page-container {
  width: 100%;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  .cancel-btn {
    width: 80px;
    border-radius: 4px;
  }
  .confirm-btn {
    width: 80px;
    background-color: rgba(27, 100, 176, 1);
    border-radius: 4px;
    border-color: rgba(27, 100, 176, 1);
  }
}
</style>