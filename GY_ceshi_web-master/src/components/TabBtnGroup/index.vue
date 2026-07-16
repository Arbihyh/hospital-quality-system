<!-- 切换按钮 -->
<template>
  <el-button-group>
    <el-button 
      size="small" 
      v-for="item in tabList" 
      :key="item.key"
      :style="getButtonStyle(activeKey === item.key)"
      @click="handleClick(item.key)"
    >
      {{ item.label }}
    </el-button>
  </el-button-group>
</template>

<script>
export default {
  name: 'TabBtnGroup',
  props: {
    activeKey: {
      type: String,
      required: true
    },
    tabList: {
      type: Array,
      required: true,
      validator: (val) => {
        return val.every(item => item.key && item.label)
      }
    },
    width: {
      type: String,
      default: '60px'
    }
  },
  computed: {
    getButtonStyle() {
      return (isActive) => ({
        borderRadius: '2px',
        width: this.width,
        backgroundColor: isActive ? '#1B64B0' : '',
        color: isActive ? '#fff' : '',
        borderColor: isActive ? '#1B64B0' : '',
      });
    }
  },
  methods: {
    handleClick(activeKey) {
      this.$emit('tab-change', activeKey)
    }
  }
};
</script>

<style lang="scss" scoped>
::v-deep .el-button-group {
  .el-button {
    margin: 0;

    &:first-child {
      border-top-right-radius: 0 !important;
      border-bottom-right-radius: 0 !important;
    }

    &:last-child {
      border-top-left-radius: 0 !important;
      border-bottom-left-radius: 0 !important;
    }
    &:not(:first-child):not(:last-child) {
      border-radius: 0 !important;
    }
  }
}
</style>