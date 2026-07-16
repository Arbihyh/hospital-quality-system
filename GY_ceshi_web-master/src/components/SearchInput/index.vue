<template>
  <el-input v-model="inputValue" :placeholder="placeholderText" class="search-input-custom" clearable>
    <svg t="1767069620883" class="icon search-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1699" width="25" height="25" slot="prefix">
      <path
        d="M875.088 795.088L723.547 643.547c17.786-22.67 32.439-47.526 43.744-74.255C784.351 528.959 793 486.132 793 442s-8.649-86.959-25.709-127.292c-16.471-38.942-40.045-73.91-70.067-103.932-30.021-30.022-64.99-53.596-103.932-70.067C552.959 123.65 510.132 115 466 115s-86.959 8.65-127.292 25.708c-38.942 16.471-73.91 40.045-103.932 70.067-30.022 30.022-53.596 64.99-70.067 103.932C147.65 355.041 139 397.868 139 442s8.65 86.959 25.708 127.292c16.472 38.941 40.045 73.91 70.067 103.932 30.022 30.022 64.99 53.597 103.932 70.067C379.041 760.351 421.868 769 466 769s86.959-8.649 127.292-25.709c26.93-11.39 51.957-26.183 74.765-44.151l151.49 151.49c15.337 15.337 40.204 15.337 55.542 0 15.336-15.338 15.336-40.205-0.001-55.542zM466 713c-149.43 0-271-121.57-271-271s121.57-271 271-271 271 121.57 271 271-121.57 271-271 271z"
        p-id="1700"
        fill="#8a8a8a"
        class="selected"
      ></path>
      <path
        d="M572.79 230.729C541.212 218.965 507.974 213 474 213c-27.498 0-54.689 3.942-80.819 11.716-14.822 4.41-23.263 20-18.853 34.822s20.001 23.263 34.822 18.853C430.094 272.16 451.913 269 474 269c27.277 0 53.938 4.779 79.24 14.206a27.917 27.917 0 0 0 9.771 1.77c11.358-0.001 22.044-6.964 26.242-18.233 5.398-14.492-1.973-30.616-16.463-36.014z"
        p-id="1701"
        fill="#8a8a8a"
      ></path>
    </svg>
  </el-input>
</template>

<script>
export default {
  name: 'SearchInput',
  props: {
    value: {
      type: String,
      default: '',
    },
    placeholderText: {
      type: String,
      default: '请输入关键词进行查询',
    },
  },
  data() {
    return {
      inputValue: this.value,
    };
  },
  watch: {
    value(newVal) {
      this.inputValue = newVal;
    },
    inputValue(val) {
      this.$emit('input', val);
      this.$emit('change', val);
    },
  },
};
</script>

<style lang="scss" scoped>

::v-deep .el-input__prefix {
  display: flex;
  align-items: center;
  height: 100%;
  padding-right: 10px;
  left: 4px;
  top: 0;
}
.search-icon {
  display: inline-block;
  vertical-align: middle;
}
</style>