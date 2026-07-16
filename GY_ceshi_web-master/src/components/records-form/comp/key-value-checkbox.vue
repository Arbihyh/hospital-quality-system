<!-- 多选框组件 -->
<template>
  <div class="key-value-item">
    <span class="key">{{ keyName }}：</span>
    <div class="value checkbox-group">
      <label class="checkbox-item" v-for="option in options" :key="option.value">
        <input type="checkbox" :value="option.value" :checked="selectedValue.includes(option.value)" disabled @change="handleCheckboxChange" />
        <span class="checkbox-label">{{ option.label }}</span>
      </label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'KeyValueCheckbox',
  props: {
    keyName: {
      type: String,
      required: true,
    },
    options: {
      type: Array,
      required: true,
      validator: val => {
        return val.every(item => item.hasOwnProperty('label') && item.hasOwnProperty('value'));
      },
    },
    selectedValue: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['change'],
  methods: {
    handleCheckboxChange(val) {
      this.$emit('change', val);
    },
  },
};
</script>

<style scoped>
.key-value-item {
  /* color: rgba(16, 16, 16, 1);
    padding: 4px 0;
    font-size: 14px;
    font-family: PingFangSC-bold; */
  display: flex;
  align-items: center;
  width: 100%;
}

.key {
  /* font-weight: bold; */
  margin-right: 16px;
  white-space: nowrap;
  font-size: 14px;
  color: rgba(16, 16, 16, 1);
  font-weight: bold;
  font-family: PingFangSC-bold;
  flex-shrink: 0;
  align-self: center;
  /* text-indent: 2em; */
}

.value {
  line-height: 1.8;
  flex: 1;
  align-self: center;
}

.checkbox-group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  width: 100%;
  gap: 8px 100px;
}

.checkbox-item {
  display: flex;
  align-items: center;
  cursor: pointer;
  user-select: none;
  flex: 0 0 auto;
  margin: 2px 0;
}

.checkbox-item input {
  margin: 0 6px 0 0;
  width: 14px;
  height: 14px;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
  border: 1px solid #d9d9d9;
  border-radius: 2px;
  background: #fff;
  position: relative;
  vertical-align: middle;
}

.checkbox-item input:disabled {
  background-color: #f5f5f5;
  cursor: not-allowed;
}

.checkbox-item input:checked {
  background-color: #1890ff;
  border-color: #1890ff;
}

.checkbox-item input:checked::after {
  content: '✓';
  position: absolute;
  color: #fff;
  font-size: 14px;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.checkbox-label {
  cursor: pointer;
  white-space: nowrap;
  color: rgba(16, 16, 16, 0.7);
  vertical-align: middle;
}
</style>