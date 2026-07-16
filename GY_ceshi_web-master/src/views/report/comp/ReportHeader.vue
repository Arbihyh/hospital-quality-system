<template>
  <header class="report-header">
    <h1 class="report-header__title">{{ headerMeta.title }}</h1>
    <div class="report-header__meta" v-if="isShow === '1'">
      <span>{{ headerMeta.year }}年 第{{ headerMeta.qi }}期（总第{{ headerMeta.qinum }}期）</span>
      <span class="report-header__dept">{{ headerMeta.current_departments }}</span>
      <span>{{ headerMeta.queryTime }}</span>
    </div>
    <div class="report-header__divider"></div>
  </header>
</template>

<script>
export default {
  name: 'ReportHeader',
  props: {
    basicInfo: {
      type: Object,
      default: () => ({}),
    },
    isShow: {
      type: String,
      default: '1',
    },
  },
  computed: {
    headerMeta() {
      const defaultMeta = {
        year: '-',
        qi: '-',
        qinum: '-',
        queryTime: '-',
        current_departments: '-',
        title: '-',
      };
      return { ...defaultMeta, ...this.basicInfo };
    },
  },
};
</script>

<style lang="scss" scoped>
.report-header {
  text-align: center;
  margin-bottom: 20px;
}

.report-header__title {
  font-size: 22px;
  color: #333;
  margin-bottom: 8px;
  font-weight: 600;
  padding: 16px 0;
}

.report-header__meta {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #666;
  font-size: 14px;
  gap: 24px;
  margin-bottom: 16px;
}

.report-header__dept {
  color: #185da6;
}

.report-header__divider {
  width: 100%;
  height: 1px;
  background-color: #e8e8e8;
  margin: 8px 0;
}
</style>