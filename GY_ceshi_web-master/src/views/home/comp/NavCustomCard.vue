<template>
  <div class="custom-card-container" @click="handleCardClick">
    <el-image class="card-img" :src="imgSrc" fit="contain" :lazy="false"></el-image>
    <div class="card-text">{{ cardText }}</div>
  </div>
</template>

<script>
export default {
  name: 'NavCustomCard',
  props: {
    imgSrc: {
      type: String,
      required: true,
    },
    cardText: {
      type: String,
      required: true,
    },
  },
  methods: {
    handleCardClick() {
      this.$emit('cardClick')
    }
  }
};
</script>

<style lang="scss" scoped>
$card-width: 252px;
$card-height: 244px;
$card-radius: 16px;
$card-normal-bg: #ffffff;
$card-hover-bg: rgba(229,230,235,0.63);
$card-active-bg: rgba(219,220,226,0.7);

.custom-card-container {
  width: $card-width;
  height: $card-height;
  border-radius: $card-radius;
  background-color: $card-normal-bg;
  box-shadow: 0px 2px 6px 0px rgba(0, 0, 0, 0.4);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  box-sizing: border-box;
  padding: 0 10px;
  gap: 10px;
  cursor: pointer;
  transition: all 0.25s ease-in-out;

  &:hover {
    background-color: $card-hover-bg;
    box-shadow: 0px 4px 8px 0px rgba(0, 0, 0, 0.35);
    transform: translateY(-2px);
  }
  &:active {
    background-color: $card-active-bg;
    transform: translateY(0);
    box-shadow: 0px 2px 4px 0px rgba(0, 0, 0, 0.4);
  }
}

.card-img {
  width: 125px;
  height: 122px;
  img {
    width: 100% !important;
    height: 100% !important;
  }
}

.card-text {
  width: 251px;
  height: 37px;
  line-height: 39px;
  color: rgba(78, 89, 105, 1);
  font-size: 28px;
  text-align: center;
	font-family: PingFangSC-bold;
	box-sizing: border-box;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}
</style>