<template>
  <div class="no-circle-container" :style="{ width: size + 'px', height: size + 'px' }">
    <div class="no-circle" :style="{ width: size + 'px', height: size + 'px', borderRadius: (size / 2) + 'px' }">
      <span class="no-text" :style="{ fontSize: (size * 0.6) + 'px' }">{{ text }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'NoCircle',
  props: {
    size: {
      type: [Number, String],
      default: 100,
      validator: function(value) {
        return !isNaN(Number(value)) && Number(value) > 0;
      }
    },
    text: {
      type: String,
      default: '否'
    }
  },
  computed: {
    circleSize() {
      return `${this.size}px`;
    },
    circleRadius() {
      return `${this.size / 2}px`;
    },
    textSize() {
      return `${this.size * 0.6}px`;
    }
  }
}
</script>

<style scoped>
.no-circle-container {
  display: flex;
  justify-content: center;
  align-items: center;
}

.no-circle {
  background-color: #FFD700;
  display: flex;
  justify-content: center;
  align-items: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.no-text {
  color: white;
  font-weight: bold;
  font-family: Arial, sans-serif;
  user-select: none;
}
</style>