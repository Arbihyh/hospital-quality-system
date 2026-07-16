<template>
  <div class="time-picker" ref="pickerWrapper">
    <button class="picker-trigger" @click="togglePicker">
      <span>{{ displayTime }}</span>
      <span class="picker-arrow" :class="{ active: isPickerOpen }">▼</span>
    </button>
    <div class="picker-dropdown" v-show="isPickerOpen">
      <!-- 年份选择 -->
      <div class="year-select">
        <button class="year-nav" @click="changeYear(-1)">◀</button>
        <input 
          type="number" 
          class="year-input"
          v-model="currentYear"
          min="2000"
          max="2030"
          @blur="validateYear"
        >
        <button class="year-nav" @click="changeYear(1)">▶</button>
      </div>
      
      <!-- 月份选择 -->
      <div class="month-grid">
        <button 
          v-for="month in 12"
          :key="month"
          class="month-btn"
          :class="{ active: currentMonth === month }"
          @click="selectMonth(month)"
        >
          {{ month }}月
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ExportTimePicker',
  data() {
    return {
      isPickerOpen: false,
      currentYear: 2025,
      currentMonth: 12,
      displayTime: '2025年12月'
    };
  },
  mounted() {
    document.addEventListener('click', this.handleClickOutside);
  },
  destroyed() {
    document.removeEventListener('click', this.handleClickOutside);
  },
  methods: {
    togglePicker() {
      this.isPickerOpen = !this.isPickerOpen;
    },
    changeYear(delta) {
      this.currentYear += delta;
      this.validateYear();
      this.updateDisplayTime();
    },
    validateYear() {
      this.currentYear = Math.max(2000, Math.min(2030, this.currentYear));
      this.updateDisplayTime();
    },
    selectMonth(month) {
      this.currentMonth = month;
      this.updateDisplayTime();
      this.isPickerOpen = false;
    },
    updateDisplayTime() {
      this.displayTime = `${this.currentYear}年${this.currentMonth}月`;
    },
    handleClickOutside(e) {
      if (this.$refs.pickerWrapper && !this.$refs.pickerWrapper.contains(e.target)) {
        this.isPickerOpen = false;
      }
    }
  }
};
</script>

<style lang="scss" scoped>
.time-picker {
  position: relative;
  min-width: 120px;

  .picker-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 16px;
    border: 1px solid #d9d9d9;
    border-radius: 6px;
    background: #fff;
    font-size: 14px;
    color: #333;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;

    &:hover {
      border-color: #185da6;
      box-shadow: 0 2px 4px rgba(24, 93, 166, 0.1);
    }
  }

  .picker-arrow {
    transition: transform 0.2s;

    &.active {
      transform: rotate(180deg);
      color: #185da6;
    }
  }

  .picker-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    overflow: hidden;
    min-width: 220px;
    padding: 12px;
    animation: dropdownFadeIn 0.2s ease-out;
  }

  .year-select {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0 12px 0;
    margin-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
    gap: 8px;

    .year-nav {
      padding: 4px 8px;
      border: 1px solid #e5e7eb;
      background: #fff;
      cursor: pointer;
      font-size: 12px;
      border-radius: 2px;
      transition: all 0.2s;

      &:hover {
        color: #185da6;
        background: #f0f9ff;
      }
    }

    .year-input {
      width: 80px;
      padding: 4px 8px;
      border: 1px solid #e5e7eb;
      border-radius: 2px;
      font-size: 12px;
      text-align: center;
      outline: none;

      &:focus {
        border-color: #185da6;
      }
    }
  }

  .month-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;

    .month-btn {
      padding: 6px;
      border: 1px solid #e5e7eb;
      background: #fff;
      cursor: pointer;
      font-size: 12px;
      border-radius: 4px;
      text-align: center;
      transition: all 0.2s;

      &:hover {
        border-color: #185da6;
        color: #185da6;
      }

      &.active {
        background: #185da6;
        color: #fff;
        border-color: #185da6;
      }
    }
  }
}

@keyframes dropdownFadeIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>