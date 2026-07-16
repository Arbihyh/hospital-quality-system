<template>
  <el-select
    :class="className"
    filterable
    v-model="innerValue"
    :placeholder="placeholder"
    remote
    clearable
    :remote-method="handleRemoteSearch"
    :virtual-scroll="true"
    @blur="handleBlur"
    :virtual-scroll-item-size="virtualScrollItemSize"
    @visible-change="handleVisibleChange"
    @remove-tag="handleRemoveTag"
    @clear="clearHandle"
  >
    <el-option
      v-for="(item, index) in options"
      :key="`option_${index}_${item[valueKey] || item}`"
      :label="typeof item === 'string' ? item : item[labelKey]"
      :value="typeof item === 'string' ? item : item[valueKey]"
    ></el-option>
  </el-select>
</template>

<script>
export default {
  name: 'BigDataRemoteSelect',
  props: {
    value: {
      type: Array,
      default: () => [],
    },
    apiUrl: {
      type: String,
      required: true,
    },
    className: {
      type: String,
      default: 'width150',
    },
    placeholder: {
      type: String,
      default: '请选择',
    },
    labelKey: {
      type: String,
      default: 'label',
    },
    valueKey: {
      type: String,
      default: 'value',
    },
    keywordParam: {
      type: String,
      default: 'keyword',
    },
    listKey: {
      type: String,
      default: '',
    },
    pageSize: {
      type: Number,
      default: 100,
    },
    debounceTime: {
      type: Number,
      default: 300,
    },
    emptyDebounceTime: {
      type: Number,
      default: 100,
    },
    extraParams: {
      type: Object,
      default: () => ({}),
    },
    virtualScrollItemSize: {
      type: Number,
      default: 34,
    },
  },
  data() {
    return {
      innerValue: [...this.value],
      options: [],
      loading: false,
      searchTimer: null,
      isFirstLoad: true,
      currentKeyword: '',
      isUpdating: false,
    };
  },
  watch: {
    value: {
      immediate: true,
      handler(newVal) {

        if (!this.isUpdating && JSON.stringify(newVal) !== JSON.stringify(this.innerValue)) {
          this.innerValue = [...newVal];
        }
      },
    },
    innerValue(newVal) {
      this.isUpdating = true;
      this.$emit('input', newVal);
      this.$emit('change', newVal);
      setTimeout(() => {
        this.isUpdating = false;
      }, 0);
    },
  },
  methods: {
    handleVisibleChange(visible) {
      if (visible && this.isFirstLoad) {
        // this.handleRemoteSearch('');
        this.isFirstLoad = false;
      }
    },
    handleBlur(val) {
      this.innerValue = this.currentKeyword
    },
    handleRemoveTag() {
      this.currentKeyword = this.currentKeyword;
    },
    clearHandle() {
      this.currentKeyword = '';
      this.initData();
    },
    async initData() {
      try {
        const params = {
          [this.keywordParam]: this.currentKeyword,
          limit: this.pageSize,
          ...this.extraParams,
        };
        const res = await this.$axios.post(this.apiUrl, params);
        if (this.listKey) {
          this.options = res.data.list[this.listKey] || [];
        } else {
          this.options = Array.isArray(res.data.list) ? res.data.list : res.data.list || [];
        }
        if (this.options.length === 0) {
          this.innerValue = this.currentKeyword;
        }
      } catch (error) {
        console.error('远程搜索失败：', error);
        this.options = [];
      }
    },
    handleRemoteSearch(keyword) {
      const trimmedKeyword = keyword.trim();
      if (keyword == '') return;
      if (trimmedKeyword === this.currentKeyword) return;

      clearTimeout(this.searchTimer);
      const debounce = trimmedKeyword ? this.debounceTime : this.emptyDebounceTime;
      this.currentKeyword = trimmedKeyword;

      this.searchTimer = setTimeout(() => {
        this.initData();
      }, debounce);
    },
  },
  beforeDestroy() {
    clearTimeout(this.searchTimer);
    clearTimeout(this.isUpdatingTimer);
  },
};
</script>

<style scoped>
.width150 {
  width: 200px;
}
.width100 {
  width: 100%;
}
::v-deep .el-select__tags {
  flex-wrap: wrap;
  max-height: 80px;
  overflow-y: auto;
}
::v-deep .el-virtual-list {
  max-height: 400px;
}
</style>