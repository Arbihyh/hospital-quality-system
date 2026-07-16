<template>
  <div class="custom-card-box" :style="customStyle">
    <slot name="title"></slot>
    <slot></slot>
  </div>
</template>

<script>
export default {
  name: 'CustomCardBox',
  props: {
    bgColor: {
      type: String,
      default: 'rgba(255,255,255,1)'
    },
    textColor: {
      type: String,
      default: 'rgba(16,16,16,1)'
    },
    width: {
      type: [String, Number],
      default: '100%'
    },
    height: {
      type: [String, Number],
      default: '100%'
    },
    overflow: {
      type: String,
      default: 'visible' 
    },
    overflowX: {
      type: String,
      default: 'visible' 
    },
    overflowY: {
      type: String,
      default: 'visible' 
    }
  },
  computed: {
    customStyle() {
      const baseStyle = {
        width: this.width,
        height: this.height,
        backgroundColor: this.bgColor,
        color: this.textColor,
        position: 'relative' 
      }
      this.overflowX && (baseStyle.overflowX = this.overflowX)
      this.overflowY && (baseStyle.overflowY = this.overflowY)
      if (!this.overflowX && !this.overflowY) {
        baseStyle.overflow = this.overflow
      }
      return baseStyle
    }
  }
}
</script>

<style scoped>
.custom-card-box {
  line-height: 20px;
  border-radius: 10px;
	font-size: 14px;
	box-shadow: 0px 2px 6px 0px rgba(152,149,149,1);
	font-family: -regular;
  box-sizing: border-box;
  padding: 10px 15px;
}
</style>