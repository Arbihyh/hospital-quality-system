<template>
  <div class="qc-tabs">
    <div class="qc-tabs__wrapper">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="qc-tabs__tab"
        :class="{ 'qc-tabs__tab--active': active === tab.key }"
        @click="$emit('change', tab.key)"
      >
        <span class="qc-tabs__label">{{ tab.label }}</span>
        <span class="qc-tabs__badge" v-if="tab.badge">{{ tab.badge }}</span>
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: "QcTab",
  props: {
    tabs: { type: Array, required: true },
    active: { type: String, default: "" },
  },
};
</script>

<style lang="scss" scoped>

$qc-tab-height: 40px;
$qc-tab-radius: 10px;
// $qc-tab-padding: 0 0px;
$qc-tab-gap: 8px;

$qc-tab-border: #c3d2fb;
$qc-tab-bg: transparent;
$qc-tab-hover-bg: #f0f7ff;
$qc-tab-active-bg: #dde8fe;
$qc-tab-active-color: #2f6bff;

$qc-tabs-bg: #eaedf1;
$qc-tabs-border: #dbe8ff;

$qc-badge-bg: #f04438;
$qc-badge-size: 16px;
$qc-badge-font: 9px;

%flex-center {
  display: flex;
  align-items: center;
  justify-content: center;
}

.qc-tabs {
  width: 70%;
  margin-bottom: 2px;

  &__wrapper {
    @extend %flex-center;
    width: 100%;
    gap: $qc-tab-gap;
    padding: 2px;
    background: $qc-tabs-bg;
    border: 1px solid $qc-tabs-border;
    border-radius: $qc-tab-radius;
  }

  &__tab {
    position: relative;
    @extend %flex-center;
    flex: 1; 
    height: $qc-tab-height;
    background: $qc-tab-bg;
    border: none; 
    border-radius: $qc-tab-radius - 2px;
    font-size: 14px;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.22s ease-in-out;

    &:hover {
      background: $qc-tab-hover-bg;
    }

    &--active {
      background: $qc-tab-active-bg;
      color: $qc-tab-active-color;
      font-weight: 700;
      border: 1px solid $qc-tab-border;
    }
  }

  &__label {
    line-height: 1;
  }

  &__badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: $qc-badge-size;
    height: $qc-badge-size;
    background: $qc-badge-bg;
    color: #fff;
    font-size: $qc-badge-font;
    font-weight: 700;
    border-radius: 999px;
    border: 1px solid #fff;
    @extend %flex-center;
    z-index: 2;
  }
}
</style>