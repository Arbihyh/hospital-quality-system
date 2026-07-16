<template>
  <div class="custom-card" :style="cardSizeStyle">
    <span class="card-left-text" :style="leftTextStyle">{{ leftText }}</span>
    <span class="card-right-text" :style="{
      color: rightTextColor,
      borderBottomColor: rightTextColor,
      ...rightTextStyle
    }" @click="handleRightClick">
      {{ rightText }}
    </span>
  </div>
</template>

<script>
import { number } from 'echarts';

export default {
  name: 'CardItem',
  props: {
    leftText: { type: String, default: '就诊记录（人）' },
    rightText: { type: String | number, default: '0' },
    rightTextColor: { type: String, default: '#666666' },
    size: { type: String, default: 'normal', validator: (val) => ['normal', 'small'].includes(val) }
  },
  computed: {
    cardSizeStyle() {
      return this.size === 'small'
        ? {
          width: '100%',
          maxWidth: '500px',
          height: '51px',
          lineHeight: '20px',
          backgroundColor: '#3333355',
        }
        : {
          width: '100%',
          maxWidth: '1338px',
          height: '84px',
          lineHeight: '20px',
          backgroundColor: '#3333355',
        };
    },
    leftTextStyle() {
      return this.size === 'small'
        ? { fontSize: '14px' }
        : { fontSize: '18px' };
    },
    rightTextStyle() {
      return this.size === 'small'
        ? { fontSize: '18px', paddingBottom: '2px' }
        : { fontSize: '28px', paddingBottom: '5px' };
    }
  },
  methods: {
    handleRightClick() {
      this.$emit('right-click');
    }
  }
};
</script>

<style scoped>
.custom-card {
  box-sizing: border-box;
  border-radius: 15px;
  background-color: rgba(255, 255, 255, 1);
  box-shadow: 0px 2px 6px 0px rgba(152, 149, 149, 1);
  font-family: -regular;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 40px;

  margin: 0 auto;
}

.card-left-text {
  flex: 1;
  text-align: left;
  font-weight: bold;
  color: #666666;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.card-right-text {
  cursor: pointer;
  border-bottom: 2px solid currentColor;
  text-align: right;
  /* font-weight: bold; */
  color: #f5efef;
  margin-left: 10px;
  white-space: nowrap;
}
</style>