<template>
  <div :id="`card-${id}`" class="card" :title="tip" @dblclick="handleDoubleClick">
    <div class="card-header">
      <span class="card-title">{{ title }}</span>
      <span class="card-count">
        <span :id="`count-${id}`" class="count-num">{{ total }}</span>
        <span class="count-unit">条</span>
      </span>
    </div>
    <div class="card-stats">
      <span class="stat-item">
        <span class="stat-dot enable" />
        启用
        <span :id="`enable-${id}`">{{ enable }}</span>
      </span>
      <span class="stat-item">
        <span class="stat-dot disable" />
        停用
        <span :id="`disable-${id}`">{{ disable }}</span>
      </span>
      <span v-if="model > 0" class="stat-item">
        <span class="stat-dot unmap" />
        模型
        <span :id="`model-${id}`">{{ model }}</span>
      </span>
      <span v-if="maintain > 0" class="stat-item">
        <span class="stat-dot maintain" />
        可维护
        <span :id="`maintain-${id}`">{{ maintain }}</span>
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StatCard',
  props: {
    id: {
      type: String,
      required: true
    },
    title: {
      type: String,
      required: true
    },
    tip: {
      type: String,
      default: '双击跳转到规则列表'
    },
    total: {
      type: [Number, String],
      default: 0
    },
    enable: {
      type: [Number, String],
      default: 0
    },
    disable: {
      type: [Number, String],
      default: 0
    },
    model: {
      type: [Number, String],
      default: 0
    },
    maintain: {
      type: [Number, String],
      default: 0
    }
  },
  methods: {
    handleDoubleClick() {
      this.$emit('double-click', this.title)
    }
  }
}
</script>

<style lang="scss" scoped>
$color-bg: #ffffff;
$color-border: #e5e6eb;
$color-text-main: #333;
$color-text-normal: #666;
$color-text-desc: #999;
$color-primary: #1989fa;

$dot-enable: #00b42a;
$dot-disable: #999;
$dot-unmap: #722ed1;
$dot-maintain: #ff7d00;

.card {
  background: $color-bg;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  padding: 16px 20px;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid $color-border;

  &:hover {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
  }

  &-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;

    .card-title {
      font-size: 15px;
      font-weight: 500;
      color: $color-text-main;
    }

    .card-count {
      font-size: 14px;
      color: $color-text-normal;

      .count-num {
        font-weight: 600;
        color: $color-primary;
        margin-right: 2px;
      }

      .count-unit {
        color: $color-text-desc;
      }
    }
  }

  &-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 13px;
    color: $color-text-normal;

    .stat-item {
      display: inline-flex;
      align-items: center;

      .stat-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;

        &.enable {
          background-color: $dot-enable;
        }
        &.disable {
          background-color: $dot-disable;
        }
        &.unmap {
          background-color: $dot-unmap;
        }
        &.maintain {
          background-color: $dot-maintain;
        }
      }
    }
  }
}
</style>
