<template>
  <div class="cont-reight">
    <div class="cont-reight-top">
      <div class="cont-reight-btn">病案首页</div>
    </div>
    <div class="storeBox">
      <div class="text">{{ score }}</div>
      <div class="spa">
        <!-- 0优 1良 2中 3差 -->
        <div class="spaview" v-if="score >= 97">优</div>
        <div class="spaview" v-else-if="score >= 90 && score <= 96">良</div>
        <div class="spaview" v-else-if="score >= 75 && score <= 89">中</div>
        <div class="spaview" v-else>差</div>
      </div>
    </div>
    <div class="flexNox">
      <div class="Yradio">
        <div class="Yradiobox"></div>
        强制
      </div>
      <div class="Rradio">
        <div class="Rradiobox"></div>
        建议
      </div>
    </div>
    <div class="cont-reight-bottom" @click="handleClick(item)" v-for="(item, index) in errorList" :key="index">
      <div v-if="item.category == 0 || item.category == 1" class="cont-reight-bottom-title">
        <!-- {{ item.category == 0 ? 'A类' : 'B类' }}
        <br /> -->
        <!-- -{{ item.down }} -->
      </div>
      <div v-else class="cont-reight-bottom-title-null">
        <!-- {{ item.category == 2 ? 'C类' : 'D类' }}
        <br /> -->
        <!-- -{{ item.down }} -->
      </div>
      <div class="cont-reight-bottom-conter">
        <!-- <p>字段：{{ item.error_name }}</p> -->
        <p>提示：{{ item.notice }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    titleName: {
      type: String,
      default: ''
    },
    errorList: {
      type: Array,
      default() {
        return []
      }
    },
    score: {
      type: Number,
      default() {
        return ''
      }
    }
  },
  methods: {
    handleClick(item) {
      // this.$emit('ss', item)
    }
  }
};
</script>

<style lang="scss" scoped>
.cont-reight {
  width: 300px;
  min-height: 650px;
  height: 830px;
  overflow-x: hidden;
  overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  box-sizing: border-box;
  padding: 0 10px;
}
.cont-reight-btn {
  margin-top: 20px;
  width: 100%;
  height: 43px;
  background: #d3e3ff;
  border-radius: 8px;
  font-size: 14px;
  font-weight: bold;
  color: #333333;
  text-align: center;
  line-height: 43px;
}

.cont-reight-bottom {
  margin: 24px 7px;
  display: flex;
  // cursor: pointer
}
.cont-reight-bottom-title {
  width: 70px;
  background: #ffdfdf;
  border-right: 3px solid #ff0000;
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  color: #da1515;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cont-reight-bottom-title-null {
  width: 70px;
  background: #fffbec;
  border-right: 3px solid #fa6400;
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  color: #ff5700;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cont-reight-bottom-conter {
  margin-left: 8px;
  min-height: 70px;
  display: flex;
  justify-content: space-around;
  flex-direction: column;
  width: 100%;
  p {
    font-size: 13px;
    color: #333333;
  }
}
.storeBox {
  height: 80px;
  line-height: 80px;
  font-size: 30px;
  text-align: center;
  align-items: center;
  position: relative;
  margin: 30px auto;
  border: 1px solid #ccc;
  box-sizing: border-box;
  overflow: hidden;
  .text {
    font-size: 30px;
  }
  .spa {
    overflow: hidden;
  }
  .spa::before {
    content: '';
    width: 0;
    height: 0;
    border: 30px solid transparent;
    border-right: 30px solid red;
    -webkit-transform: rotate(135deg);
    transform: rotate(135deg);
    color: #fff;
    position: absolute;
    right: -30px;
    top: -30px;
    cursor: pointer;
  }
  .flexTable {
    display: flex;
    align-items: center;
  }

  .spaview {
    position: absolute;
    right: 5px;
    top: -1px;
    font-size: 13px;
    text-align: right;
    height: 30px;
    line-height: 30px;
    width: 100px;
    color: #fff;
  }
}
.flexNox {
  display: flex;
  margin: 0 auto;
  justify-content: center;
  align-items: center;
  .Yradiobox {
    width: 10px;
    background: red;
    margin-right: 5px;
    height: 10px;
    border-radius: 50%;
  }
  .Rradiobox {
    width: 10px;
    background: #fa6400;
    height: 10px;
    margin-right: 5px;
    border-radius: 50%;
  }
  .Yradio {
    width: 80px;
    display: flex;
    align-items: center;
    text-align: center;
  }
  .Rradio {
    display: flex;
    align-items: center;
    text-align: center;
  }
}
</style>