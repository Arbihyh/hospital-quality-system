<template>
  <div :class="['loading-overlay', { 'hidden': !visible }]" @click.stop>
    <div class="loading-spinner" :style="{ borderColor: color + ' ' + color + ' ' + color + ' ' + spinnerColor }"></div>
    <p class="loading-text" :style="{ color: textColor }">{{ text }}</p>
  </div>
</template>

<script>
export default {
  name: 'LoadingComponent',
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    text: {
      type: String,
      default: '加载中，请稍候...'
    },
    color: {
      type: String,
      default: '#3B82F6'
    },
    spinnerColor: {
      type: String,
      default: '#3B82F620'
    },
    textColor: {
      type: String,
      default: '#4B5563'
    }
  }
}
</script>

<style scoped>
.loading-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(4px);
  z-index: 50;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  transition: opacity 0.3s ease;
}

.loading-spinner {
  width: 4rem;
  height: 4rem;
  border-width: 4px;
  border-style: solid;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.loading-text {
  margin-top: 1.5rem;
  font-size: 1.125rem;
  font-weight: 500;
  max-width: 24rem;
  text-align: center;
  padding: 0 1rem;
}

.hidden {
  opacity: 0;
  pointer-events: none;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>    