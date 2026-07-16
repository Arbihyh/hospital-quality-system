<template>
  <div class="no_format_text">
    <div v-for="(item, index) in dataObjArr" :key="index" class="death-bottom">
      <div class="name">{{ item.title }}</div>
      <div class="content" v-html="item.HJNR"></div>
      <div class="bottom-time bottom-time-top">
        <div class="bottom-time-list">
          <span class="bottom-time-bold">医生签名:</span>
          <span>{{ item.doctor_name }}</span>
        </div>
      </div>
      <div class="bottom-time bottom-time-top">
        <div class="bottom-time-list" v-if="item.CJSJ">
          <span class="bottom-time-bold">创建时间:</span>
          <span>{{ item.CJSJ }}</span>
        </div>
        <div class="bottom-time-list" v-if="item.ZXSJ">
          <span class="bottom-time-bold">首次签名时间:</span>
          <span>{{ item.ZXSJ }}</span>
        </div>
      </div>
      <div class="bottom-time bottom-time-botom" v-if="item.WCSJ">
        <div class="bottom-time-list">
          <span class="bottom-time-bold">完成时间:</span>
          <span>{{ item.WCSJ }}</span>
        </div>

        <div class="bottom-time-list">
          <span class="bottom-time-bold">业务时间:</span>
          <span v-if="item.YWSJ">{{ item.YWSJ }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    dataObjArr: {
      type: Array,
      default() {
        return [];
      },
    },
  },
  mounted() {
    this.getObjArr();
  },
  methods: {
    getObjArr() {
      console.log("getObjArr",this.dataObjArr);
    },
  }
};
</script>

<style lang="scss" scoped>
.no_format_text {
  line-height: 1.5;
  padding-bottom: 100px;
  .name {
    font-size: 24px;
    font-weight: bold;
    color: #2c3240;
    text-align: center;
    margin: 20px;
  }
  .content {
    padding: 20px;
  }
}
.bottom-time-top {
  margin-top: 30px;
}
.bottom-time-botom {
  margin-bottom: 30px;
}
.bottom-time {
  width: 100%;
  display: flex;
  justify-content: flex-start;
  align-items: center;
  margin-bottom: 10px;
  .bottom-time-bold {
    font-weight: bold;
  }
  .bottom-time-list {
    width: 50%;
  }
}
.death-bottom {
  margin-bottom: 60px;
}
</style>
