<template>
  <div class="btn-box">
    <slot />
  </div>
</template>

<script>
  export default {
    
  }
</script>

<style lang="scss" scoped>
.btn-box {
  padding: 10px;
  background: rgba(0,73,131,0.1);
  border-radius: 6px;
  margin: 20px 10px;
  text-align: center;
  cursor: pointer;
  display: inline-block;
  &:hover {
    opacity: 0.7;
  }
}
</style>