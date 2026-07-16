<template>
  <el-select
    v-model="selectedValues"
    :clearable="clearable"
    multiple
    :placeholder="placeholder"
    :style="{ width: width }"
    @change="handleChange"
    @input="handleInputFilter"
    @clear="handleClear"
  >
    <el-option
      v-for="(item, index) in filteredOptions"
      :label="item[labelKey]"
      :value="item[valueKey]"
      :key="index"
    />
  </el-select>
</template>

<script>
export default {
  name: 'FilterMultiSelect',
  props: {
    // 绑定值（选中的选项值数组）
    value: {
      type: Array,
      default: () => []
    },
    // 原始选项数据（数组对象）
    options: {
      type: Array,
      required: true,
      default: () => []
    },
    // 选项中用于显示的字段名（如 'notice'）
    labelKey: {
      type: String,
      default: 'label'
    },
    // 选项中用于作为value的字段名（如 'notice'）
    valueKey: {
      type: String,
      default: 'value'
    },
    // 选项的唯一标识字段（用于key）
    keyField: {
      type: String,
      default: 'id'
    },
    // 占位符文本
    placeholder: {
      type: String,
      default: '请选择'
    },
    // 宽度样式（如 '450px'）
    width: {
      type: String,
      default: '100%'
    },
    // 是否显示清除按钮
    clearable: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      filteredOptions: [], // 过滤后的选项
      filterText: '' // 当前过滤文本
    };
  },
  computed: {
    // 双向绑定选中值
    selectedValues: {
      get() {
        return this.value;
      },
      set(val) {
        this.$emit('input', val);
      }
    }
  },
  watch: {
    // 监听原始选项变化，重新初始化过滤列表
    options: {
      immediate: true,
      handler(newVal) {
        this.filteredOptions = newVal;
      }
    }
  },
  methods: {
    // 处理输入过滤
    
    handleInputFilter(text) {
      console.log('handleInputFilter',this.options)
      this.filterText = text || '';
      // 执行过滤（不区分大小写）
      this.filteredOptions = this.options.filter(item => {
        const label = item[this.labelKey] || '';
        return label.toLowerCase().includes(this.filterText.toLowerCase());
      });
    },
    // 处理选择变化
    handleChange(values) {
      this.$emit('change', values);
      // 选择后保持过滤状态
      this.handleInputFilter(this.filterText);
    },
    // 处理清除操作
    handleClear() {
      this.filterText = '';
      this.filteredOptions = this.options; // 清除后显示全部选项
      this.$emit('clear');
    }
  }
};
</script>