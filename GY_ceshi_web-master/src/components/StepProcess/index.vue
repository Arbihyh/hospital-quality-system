<!-- 步骤条 -->
<template>
  <div class="step-process-container" :style="{ width: containerWidth, '--step-count': stepList.length, '--container-width': containerWidth }">
    <div class="step-list">
      <div class="step-item" v-for="(item, index) in stepList" :key="index" @click="handleStepClick(index + 1)">
        <div
          class="step-node"
          :class="{
            completed: index + 1 < currentStep,
            active: index + 1 === currentStep,
            pending: index + 1 > currentStep,
          }"
          :style="{ cursor: 'pointer' }"
        >
          <span class="check-icon" v-if="index + 1 < currentStep"></span>
          <span class="step-num" v-else>{{ index + 1 }}</span>
        </div>
        <div
          class="step-title"
          :class="{
            completed: index + 1 < currentStep,
            active: index + 1 === currentStep,
            pending: index + 1 > currentStep,
          }"
        >
          {{ item.title }}
        </div>
        <div
          class="step-line"
          v-if="index < stepList.length - 1"
          :class="{
            normal: index + 1 < currentStep,
            gray: index + 1 >= currentStep,
          }"
        ></div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StepProcess',
  props: {
    currentStep: {
      type: Number,
      required: true,
      default: 1,
    },
    stepList: {
      type: Array,
      required: true,
      default: () => [],
    },
    // 不传默认700px，支持 px / vw 单位
    containerWidth: {
      type: String,
      default: '700px',
    },
  },
  methods: {
    handleStepClick(clickStep) {
      // 规则：点击任意步骤节点，将当前步骤切换为点击的步骤，点击的步骤「之后所有步骤」全部变为【已完成状态】
      this.$emit('step-change', clickStep);
    },
  },
};
</script>

<style scoped>
.step-process-container {
  padding: 20px 0;
  box-sizing: border-box;
}
.step-list {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  width: 100%;
  box-sizing: border-box;
}
.step-item {
  display: flex;
  align-items: center;
  position: relative;
  white-space: nowrap;
  cursor: pointer;
}

.step-node {
  width: 31px;
  height: 31px;
  line-height: 20px;
  font-size: 14px;
  text-align: center;
  font-family: -regular;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 2;
}
.step-node.active {
  background-color: rgba(25, 144, 255, 1);
  color: #ffffff;
  border: 1px solid rgba(25, 144, 255, 1);
}
.step-node.pending {
  background-color: rgb(246, 247, 248);
  color: #d9d9d9;
  border: 1px solid rgba(217, 217, 217, 1);
}
.step-node.completed {
  background-color: rgba(255, 255, 255, 1);
  color: rgba(16, 16, 16, 1);
  border: 1px solid rgba(25, 144, 255, 1);
}
.step-num {
  display: inline-block;
  line-height: 1;
}

.check-icon {
  width: 17px;
  height: 17px;
  display: inline-block;
  position: relative;
  background-color: rgba(25, 144, 255, 1);
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffffff'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E")
    no-repeat center center;
  mask-size: 100% 100%;
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffffff'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E")
    no-repeat center center;
  -webkit-mask-size: 100% 100%;
}

.step-title {
  margin-left: 8px;
  line-height: 24px;
  text-align: left;
  font-family: SourceHanSansSC-regular;
}
.step-title.active,
.step-title.completed {
  width: 64px;
  height: 24px;
  color: rgba(92, 92, 92, 1);
  font-size: 16px;
}
.step-title.pending {
  height: 20px;
  line-height: 20px;
  color: rgb(217, 217, 217);
  font-size: 14px;
  overflow: hidden;
  white-space: nowrap;
}

.step-line {
  height: 1px;
  background-color: rgba(255, 255, 255, 1);
  margin: 0 8px;
  width: calc((var(--container-width) - (31px + 8px + 64px)) / (var(--step-count) - 1) - 16px);
  min-width: 60px;
  flex-shrink: 0;
}
.step-line.normal {
  border: 1px solid rgba(25, 144, 255, 1);
}
.step-line.gray {
  border: 1px solid rgba(217, 217, 217, 1);
}
</style>