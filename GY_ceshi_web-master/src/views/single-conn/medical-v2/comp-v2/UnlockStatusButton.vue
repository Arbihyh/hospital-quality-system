<template>
  <div>
    <button
      v-if="!showUnlocked"
      type="button"
      class="unlock-btn unlock-btn--idle"
      @click="$emit('click')"
    >解锁</button>
    <button
      v-if="showUnlocked"
      type="button"
      class="unlock-btn unlock-btn--active"
      disabled
    >已解锁 | {{ countdownText }}后锁定</button>
  </div>
</template>

<script>
export default {
  name: 'UnlockStatusButton',

  props: {
    unlocked: {
      type: Boolean,
      default: false,
    },
    leftSeconds: {
      type: Number,
      default: 0,
    },
    rowData: {
      type: Object,
      default: () => ({
        unlock_status: '未解锁',
        rule_id: 0,
      }),
    },
  },

  computed: {
    showUnlocked() {
      return this.unlocked || this.rowData.unlock_status === '已解锁';
    },

    countdownText() {
      return this.formatUnlockLeft(this.leftSeconds);
    },
  },

  methods: {
    formatUnlockLeft(seconds) {
      const hours = Math.floor(seconds / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      const restSeconds = seconds % 60;
      if (hours > 0) {
        return `${hours}小时${String(minutes).padStart(2, '0')}分${String(restSeconds).padStart(2, '0')}秒`;
      }
      if (minutes <= 0) return restSeconds + 's';
      return minutes + '分' + String(restSeconds).padStart(2, '0') + '秒';
    },
  },
};
</script>

<style lang="scss" scoped>
.unlock-btn {
  min-width: 52px;
  height: 26px;
  padding: 0 10px;
  border-radius: 13px;
  border: 1px solid transparent;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  outline: none;
  font-family: inherit;

  &--idle {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fed7aa;

    &:hover {
      background: #ffedd5;
      color: #9a3412;
      border-color: #fdba74;
    }
  }

  &--active {
    min-width: 140px;
    background: #f5f3ff;
    color: #6d28d9;
    border-color: #c4b5fd;
    cursor: default;
  }
}
</style>
