<template>
  <div id="MyDiv" v-if="errorList.length!=0">
    <div class="cont-reight">
      <div class="cont-reight-top">
        <div class="cont-reight-btn">{{ titleName }}</div>
      </div>
      <!-- 评分 -->
      <!-- <div class="storeBox">
          <div class="text">{{ score }}</div>
          <div class="spa">
            <div class="spaview" v-if="score > 90">优</div>
            <div class="spaview" v-else-if="score >= 70 && score == 90">良</div>
            <div class="spaview" v-else>差</div>
          </div>
        </div> -->

      <!-- end -->
      <!-- 强制 -->
      <!-- <div class="flexNox">
          <div class="Yradio">
            <div class="Yradiobox"></div>
            强制
          </div>
          <div class="Rradio">
            <div class="Rradiobox"></div>
            建议
          </div>
        </div> -->

      <!-- end -->
      <div v-for="(item,index) in errorList" :key="index" class="errorcase">
        <span class="errorcase-zi">{{ index+1}}.</span>
        <span class="errorcase-zi1">{{ item.notice }}</span>
      </div>
    </div>
  </div>
</template>
  <script>
// import OtherComponent from '@/components/OtherComponent'
export default {
  name: 'm-cont-reight',
  components: {
    // OtherComponent
  },
  directives: {},
  filters: {
  },
  extends: {},
  mixins: {},
  props: {
    titleName: {
      type: null,
      default: false,
    },
    errorList: {
      type: null,
      default: false,
    },
  },
  data() {
    return {
      // errorList: [],
      score: 0,
    };
  },
  computed: {},
  watch: {},
  beforeCreate() {
    // 生命周期钩子：组件实例刚被创建，组件属性计算之前，如 data 属性等
  },
  created() {
    // 生命周期钩子：组件实例创建完成，属性已绑定，但 DOM 还未生成，el 属性还不存在
    // 初始化渲染页面
  },
  beforeMount() {
    // 生命周期钩子：模板编译/挂载之前
  },
  mounted() {
    // 生命周期钩子：模板编译、挂载之后（此时不保证已在 document 中）
  },
  beforeUpate() {
    // 生命周期钩子：组件更新之前
  },
  updated() {
    // 生命周期钩子：组件更新之后
  },
  activated() {
    // 生命周期钩子：keep-alive 组件激活时调用
  },
  deactivated() {
    // 生命周期钩子：keep-alive 组件停用时调用
  },
  beforeDestroy() {
    // 生命周期钩子：实例销毁前调用
  },
  destroyed() {
    // 生命周期钩子：实例销毁后调用
  },
  errorCaptured(err, vm, info) {
    // 生命周期钩子：当捕获一个来自子孙组件的错误时被调用。此钩子会收到三个参数：错误对象、发生错误的组件实例以及一个包含错误来源信息的字符串。
    console.log(err, vm, info);
  },
  methods: {
    /**
     * 跳转对应病历首页
     */
    getBlankIndex(item) {
      let eleClass = document.querySelectorAll('.choose-twinkle');
      console.log(eleClass);
      // const tb = this.$refs.tables.value;
      console.log(item.error_field);
      // this.$refs[item.error_field]
      console.log(this.$refs[item.error_field]);
      for (let item = 0; item < eleClass.length; item++) {
        console.log(eleClass[item]);
        eleClass[item].className = 'table-label';
      }
      // return;
      // document
      // this.$refs[item.error_field].classList.value + ' choose-twinkle';
      this.$refs[item.error_field].className = 'choose-twinkle';
      this.$refs[item.error_field].scrollIntoView({ block: 'start', behavior: 'smooth' });
      //   .getElementById("agentTitle")
      //   .scrollIntoView({ block: "start", behavior: "smooth" });
      // 跳转到指定位置并且平滑滚动
      // this.$el.querySelector('.table-labelon1').scrollIntoView({ behavior: 'smooth' });
      // this.$el.querySelector('.table-labelon1').style.color = 'red';
    },
  },
};
</script>
  <style lang='scss' scoped>
#MyDiv {
  margin: 0;
  padding: 0;
}

.choose-twinkle {
  // background: red;
  font-size: 20px;
  color: red;
  font-weight: 600;
  background: yellow;
}
.el-menu-vertical-demo {
  width: 200px;
}
.flexNox {
  width: 200px;
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
    width: 80px;
    display: flex;
    align-items: center;
    text-align: center;
  }
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
  width: 100%;
  margin: 24px 7px;
  display: flex;
}
.cont-reight-bottom-title {
  width: 60px;
  height: 70px;
  background: #ffdfdf;
  border-right: 3px solid #ff0000;
  text-align: center;
  line-height: 88px;
  font-size: 24px;
  font-weight: bold;
  color: #da1515;
  position: relative;
}
.cont-reight-bottom-title-zi {
  // height: 10px;
  position: absolute;
  top: 23px;
  left: 15px;
  font-size: 18px;
  color: #da1515;
  line-height: 0px;
}
.cont-reight-bottom-title-null {
  width: 60px;
  height: 70px;
  background: #fffbec;
  border-right: 3px solid #fa6400;
  text-align: center;
  line-height: 88px;
  font-size: 24px;
  font-weight: bold;
  color: #ff5700;
  position: relative;
}
.cont-reight-bottom-title-null-zi {
  position: absolute;
  top: 23px;
  left: 15px;
  font-size: 18px;
  color: #ff5700;
  line-height: 0px;
}
.cont-reight-bottom-conter {
  margin-left: 8px;
  height: 70px;
  display: flex;
  justify-content: space-around;
  flex-direction: column;
  p {
    font-size: 8px;
    color: #333333;
  }
}
.cont-reight-top-cont {
  margin: 8px 0 0 20px;
  p {
    font-size: 12px;
    font-weight: bold;
    color: #333333;
    padding: 3px 0;
  }
}

//
.storeBox {
  width: 200px;
  height: 80px;
  line-height: 80px;
  font-size: 30px;
  text-align: center;
  align-items: center;
  position: relative;
  margin: 30px auto;
  border: 1px solid #ccc;
  .text {
    font-size: 30px;
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
.errorcase{
  padding: 10px 5px;
  // overflow: scroll;
}
.errorcase-zi{
  padding-right:5px;
  font-size: 16px;
  // color:#ff0000;
}
.errorcase-zi1{
  // color:#ff0000;

}
</style>
  