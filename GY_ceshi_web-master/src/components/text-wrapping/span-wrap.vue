<template>
    <span style="white-space: pre-wrap">{{ formatLineBreak(cItem) }}</span>
</template>
<script>
export default {
  props: {
    cItem: {
      type: String,
      default: '',
    },
  },
  methods: {
    formatLineBreak(str) {
      const result = str.replace(/\\n/g, '\n');
      return result;
    },
  },
};
</script>