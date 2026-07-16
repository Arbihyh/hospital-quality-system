<template>
  <span>
    <span v-if="data" style="padding: 0 5px;">{{ data }}</span>
    <span v-else class="no_value"></span>
  </span>
</template>

<script>
  export default {
    props: {
      data: {
        type: [String, Number],
        default: ''
      }
    }
  }
</script>

<style lang="scss" scoped>
.no_value {
  display: inline-block;
  width: 60px;
  border-bottom: 1px solid #333;
}
</style>