<template>
  <div class="key-value-item">
    <!-- 有keyName的情况 -->
    <template v-if="keyName">
      <span :class="{ key: textIndent === 'indent', 'indent-key': textIndent === 'no-indent' }">{{ keyName }}：</span>
      <span class="value" v-html="processedValueName"></span>
    </template>

    <!-- 没有keyName的情况，只有value -->
    <template v-else>
      <span class="value indent-only-value" v-html="processedValueName"></span>
    </template>
  </div>
</template>

<script>
export default {
  name: 'KeyValueParagraph',
  props: {
    keyName: {
      type: String,
      default: '',
    },
    valueName: {
      type: [String, Number],
      default: '',
    },
    textIndent: {
      type: String,
      default: 'indent',
      validator: val => ['no-indent', 'indent'].includes(val),
    },
  },
  computed: {
    processedValueName() {
      if (typeof this.valueName !== 'string') {
        // 替换\n为“换行+两个全角空格”
        return String(this.valueName).replace(/\n/g, '\n&#12288;&#12288;');
      }
      return this.valueName.replace(/\n/g, '\n&#12288;&#12288;');
    },
  },
};
</script>

<style scoped>
.key-value-item {
  color: rgba(16, 16, 16, 1);
  padding: 4px 0;
  font-size: 14px;
  font-family: PingFangSC-regular;
  line-height: 1.8;
  text-align: justify;
}

/* key缩进两个字符 */
.key {
  font-weight: bold;
  display: inline-block;
  text-indent: 2em;
}
.indent-key {
  font-weight: bold;
  display: inline-block;
}

/* value紧跟在key后面，不缩进 */
.value {
  display: inline;
  word-wrap: break-word;
  overflow-wrap: break-word;
}

/* 只有value时的缩进样式 */
.value.indent-only-value {
  white-space: pre-wrap;
  display: block;
  text-indent: 2em;
}
</style>