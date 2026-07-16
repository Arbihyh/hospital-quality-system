<template>
  <div class="stats-card">
    <div class="stats-card__label">{{ label }}</div>
    <div
      class="stats-card__value"
      :class="[valueColor === 'red' ? 'stats-card__value--red' : '', showUnderline ? 'stats-card__value--underline' : '', showUnderline ? 'stats-card__value--clickable' : '']"
      @click="handleValueClick"
    >
      {{ value }}
    </div>
    <div class="stats-card__trend" v-if="trend.length > 0">
      <span v-for="(trendItem, index) in trend" :key="index" class="stats-card__trend-item" :class="trendItem.text.includes('+') ? 'trend-up' : 'trend-down'">
        <svg class="trend-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
          <path
            v-if="trendItem.text.includes('+')"
            d="M740.266667 262.4l-213.333333-213.333333C522.666667 44.8 518.4 42.666667 512 42.666667c-6.4 0-10.666667 2.133333-14.933333 6.4l-213.333333 213.333333C279.466667 266.666667 277.333333 270.933333 277.333333 277.333333c0 12.8 8.533333 21.333333 21.333333 21.333333 6.4 0 10.666667-2.133333 14.933333-6.4L490.666667 115.2 490.666667 960c0 12.8 8.533333 21.333333 21.333333 21.333333 12.8 0 21.333333-8.533333 21.333333-21.333333L533.333333 115.2l177.066667 177.066667c4.266667 4.266667 8.533333 6.4 14.933333 6.4 12.8 0 21.333333-8.533333 21.333333-21.333333C746.666667 270.933333 744.533333 266.666667 740.266667 262.4z"
          ></path>
          <path v-else d="M495.616 726.528V0h32.768v726.528H660.48L512 1024l-148.48-297.472z"></path>
        </svg>
        <span class="trend-text">{{ index == 0 ? '同比 ' : '环比 ' }} {{ trendItem.text }}%</span>
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StatsCard',
  props: {
    label: {
      type: String,
      required: true,
    },
    value: {
      type: String | Number,
      required: true,
    },
    trend: {
      type: Array,
      default: () => [],
    },
    valueColor: {
      type: String,
      default: 'default',
      validator: val => ['default', 'red'].includes(val),
    },
    showUnderline: {
      type: Boolean,
      default: false,
    },
    onValueClick: {
      type: Function,
      default: null,
    },
  },
  methods: {
    handleValueClick() {
      if (this.showUnderline) {
        this.$emit('value-click', {
          value: this.value,
        });
        if (typeof this.onValueClick === 'function') {
          this.onValueClick({
            value: this.value,
          });
        }
      }
    },
  },
};
</script>

<style lang="scss" scoped>
.stats-card {
  background: #fff;
  border-radius: 4px;
  padding: 20px 24px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  border: 1px solid #e8e8e8;
  transition: box-shadow 0.3s;
}

.stats-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stats-card__label {
  font-size: 14px;
  color: #666;
  margin-bottom: 12px;
}

.stats-card__value {
  font-size: 32px;
  font-weight: 600;
  color: #185da6;
  transition: color 0.2s;
}

.stats-card__value--red {
  color: #ef4444;
}

.stats-card__value--underline {
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-color: currentColor;
  text-decoration-thickness: 2px;
}

.stats-card__value--clickable {
  cursor: pointer;
  &:hover {
    opacity: 0.9;
    text-decoration-thickness: 3px;
    transition: all 0.2s;
  }
  &:active {
    opacity: 0.8;
  }
}

.stats-card__trend {
  display: flex;
  gap: 12px;
  margin-top: 12px;
  font-size: 13px;
}

.stats-card__trend-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.trend-up {
  color: #22c55e;
  .trend-icon path {
    fill: #22c55e;
  }
}

.trend-down {
  color: #ef4444;
  .trend-icon path {
    fill: #ef4444;
  }
}

.trend-icon {
  flex-shrink: 0;
}

.trend-text {
  line-height: 1;
}
</style>