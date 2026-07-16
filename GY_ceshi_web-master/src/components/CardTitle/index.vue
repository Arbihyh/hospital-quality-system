<template>
  <div class="title" :class="{ hasLine: showLine }">
    {{ title }}
    <slot></slot>
  </div>
</template>

<script>
export default {
  props: {
    title: {
      type: String,
      default: '',
    },
    showLine: {
      type: Boolean,
      default: true,
    },
  },
};
</script>

<style lang="scss" scoped>
.title {
  position: relative;
  font-size: 18px;
  line-height: 24px; 
  font-weight: 600;
  padding-left: 14px;
  color: #13171e;
  display: inline-block;

  vertical-align: middle;
  display: inline-flex;
  align-items: center;
  gap: 4px; 

  &.hasLine::before {
    position: absolute;
    content: '';
    width: 6px;
    height: 16px;
    border-radius: 3px;
    background: linear-gradient(180deg, #185da6 0%, #3195ff 100%);
    left: 0;
    top: 50%;
    transform: translateY(-50%);
  }

  ::v-deep * {
    line-height: inherit;
  }
}
</style>