<template>
  <div ref="box" class="box" :style="{width: width ? width + 'px' : '100%'}">
    <!-- 评分 -->
    <div class="storeBox">
      <div class="text">{{ data.score.score?data.score.score:'100' }}</div>
      <div class="spa">
        <!-- 0优 1良 2中 3差 -->
        <div class="spaview" v-if="data.score.score>=97">优</div>
        <div class="spaview" v-else-if="data.score.score>=90&&data.score.score<=96">良</div>
        <div class="spaview" v-else-if="data.score.score>=75&&data.score.score<=89">中</div>
        <div class="spaview" v-else>差</div>
      </div>
    </div>

    <!-- end -->
    <!-- 强制 -->
    <div class="flexNox">
      <div>
        <div class="Yradio">
          <div class="Yradiobox"></div>
          强制
        </div>
      </div>
      <div>
        <div class="Yradio">
          <div class="Rradiobox"></div>
          建议
        </div>
      </div>
      
    </div>

    <!-- end -->
    <div class="cont-reight-bottom" v-for="(item, index) in errorlist" :key="index">
      <div :class=" item.level == 1 ?'cont-reight-bottom-title-null' : 'cont-reight-bottom-title' ">
        <span v-if="item.category == 0">A类</span>
        <span v-if="item.category == 1">B类</span>
        <span v-if="item.category == 2">C类</span>
        <span v-if="item.category == 3">D类</span>
        <!-- <span v-if="item.category == 4">E类</span> -->
        -{{ item.down }}
      </div>
      <div class="cont-reight-bottom-conter">
        <p>字段：{{ item.field_name }}</p>
        <p>提示：{{ item.desc }}</p>
      </div>
    </div>
  </div>
</template>
<script>
  export default {
    props: {
      data: {
        type: Object,
        default() {
          return {
            data: {}
          }
        }
      },
      type:{
        type: String,
        default() {
          return ''
        }
      },
      height: {
        type: Number,
        default() {
          return 0
        }
      },
      width: {
        type: Number,
        default() {
          return 0
        }
      }
    },
    data(){
      return {
        is_tab: 'qz', // 'qz'、 强制 'jy、建议
      }
    },
    computed: {
      errorlist(){
        return [...this.data.qz, ...this.data.jy]
      },
      scrollHeight() {
        return `calc(100vh - 320px)`
      }
    },
    methods: {
      onScroll(index) {
        const el = this.$el.querySelector(`.category${index}`);
        const node = el.parentNode.parentNode.parentNode
        this.$refs["scrollRef"].wrap.scrollTop = node.offsetTop;
      },
      clickTab(n){
        this.is_tab = n;
      }
    }
  }
</script>
<style lang="scss" scoped>
  
::v-deep .el-scrollbar__wrap {
  overflow-x: hidden;
}
::v-deep .el-divider--horizontal {
  margin: 10px 0;
}
.box{
  padding: 16px;
  background: #FFFFFF;
  border-radius: 5px;
  width: 100%;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  box-sizing: border-box;
  height: 100vh;
}

.storeBox {
  height: 80px;
  line-height: 80px;
  font-size: 30px;
  text-align: center;
  align-items: center;
  position: relative;
  margin: 0 auto 20px;
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
  width: 100%;
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
    background: #348ceb;
    height: 10px;
    margin-right: 5px;
    border-radius: 50%;
  }
  .Yradio {
    width: 120px;
    padding: 6px;
    display: flex;
    align-items: center;
    text-align: center;
    justify-content: center;
    // border: 1px solid #ccc;
    // border-radius: 4px;
  }
  .hoverClass{
    border: 2px solid #185da6;
  }
}
.flexNox>div{
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cont-reight-bottom {
  margin: 24px 7px;
  display: flex;
  cursor: pointer
}
.cont-reight-bottom div span{
  font-size: 24px;
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
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.cont-reight-bottom-title-null {
  width: 70px;
  background: #348ceb10;
  border-right: 3px solid #348ceb;
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  color: #348ceb;
  position: relative;
  display: flex;
  flex-direction: column;
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

</style>