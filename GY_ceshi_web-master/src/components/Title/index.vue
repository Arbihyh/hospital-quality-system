<template>
  <div class="h-title">
    <span class="blue"></span>
    <span class="text">{{ title }}</span>
  </div>
</template>
<script>
export default {
  props: {
    title: {
      type: String,
      default: '标题',
    },
  },
  data() {
    return {};
  },
};
</script>
<style lang="scss" scoped>
.h-title {
  display: flex;
  margin: 0 0 15px 0;
  .blue {
    display: block;
    width: 6px;
    height: 17px;
    background: linear-gradient(180deg, #185da6 0%, #3195ff 100%);
    border-radius: 3px;
  }
  .text {
    font-size: 16px;
    font-weight: 600;
    color: #13171e;
    margin: 0 0 0 14px;
  }
}
</style>
