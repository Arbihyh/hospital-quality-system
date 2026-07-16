<!-- 带折叠得标题头组件 -->
<template>
  <div class="title_container">
    <div class="title">
      <span class="title_text">{{ title }}</span>
      <el-image
        class="title_arrow"
        :class="{ arrow_top: !summaryStatus }"
        :src="require('../../assets/images/arrow-down.png')"
        fit="contain"
        @click="summaryStatus = !summaryStatus"
      ></el-image>
    </div>
    <div class="title_content">
      <el-collapse-transition>
        <div v-if="summaryStatus">
          <slot></slot>
        </div>
      </el-collapse-transition>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    title: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      summaryStatus: true,
    };
  },
};
</script>

<style lang="scss" scoped>
.title {
  position: relative;
  font-size: 18px;
  line-break: 24px;
  font-weight: 600;
  padding-left: 14px;
  margin-bottom: 20px;
  color: #13171e;

  &::before {
    position: absolute;
    content: '';
    width: 6px;
    height: 16px;
    border-radius: 3px;
    top: 50%;
    margin-top: -8px;
    background: linear-gradient(180deg, #185da6 0%, #3195ff 100%);
    left: 0;
  }
}

.title_arrow {
  width: 10px;
  height: 11px;
  margin-left: 5px;
  cursor: pointer;

  &.arrow_top {
    transform: rotate(180deg);
  }
}
</style>