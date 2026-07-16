<!-- DividerLine.vue -->
<template>
  <div class="divider-line" :style="customStyle"></div>
</template>

<script>
export default {
  name: 'DividerLine',
  props: {
    customStyle: {
      type: Object,
      default: () => ({})
    },
    bgColor: {
      type: String,
      default: 'rgba(255,255,255,1)'
    },
    borderColor: {
      type: String,
      default: 'rgba(187,187,187,1)'
    },
    left: {
      type: [String, Number],
      default: '72px'
    },
    top: {
      type: [String, Number],
      default: '124px'
    },
    width: {
      type: [String, Number],
      default: '880px'
    },
    height: {
      type: [String, Number],
      default: '1px'
    }
  },
  computed: {
    mergedStyle() {
      return {
        left: this.left,
        top: this.top,
        width: this.width,
        height: this.height,
        backgroundColor: this.bgColor,
        borderColor: this.borderColor,
        ...this.customStyle
      }
    }
  }
}
</script>

<style scoped>
.divider-line {
  position: relative;
  box-sizing: border-box;
  border: 0.2px solid rgba(187,187,187,1);
  background-color: rgba(255,255,255,1);
}
</style>