<template>
  <nav class="tab-nav">
    <button v-for="tab in tabs" :key="tab.key" class="tab-nav__item" :class="{ 'is-active': activeTab === tab.key }" @click="handleTabClick(tab.key)">
      {{ tab.name }}
    </button>
  </nav>
</template>

<script>
export default {
  name: 'TabNav',
  props: {
    tabs: {
      type: Array,
      required: true,
      default: () => [],
    },
    activeTab: {
      type: String,
      required: true,
      default: '',
    },
  },
  methods: {
    handleTabClick(tabKey) {
      this.$emit('tab-change', tabKey);
    },
  },
};
</script>

<style lang="scss" scoped>
.tab-nav {
  display: flex;
  gap: 0;
  margin-bottom: 20px;
  background: #fff;
  border-radius: 4px;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.tab-nav__item {
  background: #fff;
  border: none;
  padding: 14px 28px;
  cursor: pointer;
  font-size: 14px;
  color: #333;
  transition: all 0.3s;
  font-weight: 500;
  border-bottom: 2px solid transparent;
}

.tab-nav__item:hover {
  color: #185da6;
  background: #e8f4fc;
}

.tab-nav__item.is-active {
  background: #fff;
  color: #185da6;
  border-bottom: 2px solid #185da6;
}
</style>