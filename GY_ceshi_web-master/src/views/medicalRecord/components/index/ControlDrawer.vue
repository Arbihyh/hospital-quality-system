<template>
  <div class="control-box">
    <!-- <div style="text-align: right; margin-bottom: 10px">
      <i class="el-icon-close" style="font-size: 16px" @click="onClose"></i>
    </div> -->
    <div class="score-box" :class="{ scoreLevel_1: scoreLevel == '优', scoreLevel_2: scoreLevel == '良', scoreLevel_3: scoreLevel == '中', scoreLevel_4: scoreLevel == '差' }">
      <span>病案评分</span>
      <span class="score">{{ controls.score.score }}</span>
      <el-image v-if="scoreLevel == '优'" class="level" style="width: 47px; height: 41px" :src="require('../../../../assets/images/you.png')" fit="contain"></el-image>
      <el-image v-if="scoreLevel == '良'" class="level" style="width: 47px; height: 41px" :src="require('../../../../assets/images/liang.png')" fit="contain"></el-image>
      <el-image v-if="scoreLevel == '中'" class="level" style="width: 47px; height: 41px" :src="require('../../../../assets/images/zhong.png')" fit="contain"></el-image>
      <el-image v-if="scoreLevel == '差'" class="level" style="width: 47px; height: 41px" :src="require('../../../../assets/images/cha.png')" fit="contain"></el-image>
    </div>
    <div class="legend-box">
      <span class="qz">强制</span>
      <span class="jy">建议</span>
    </div>
    <el-scrollbar style="height: calc(100vh - 320px)">
      <div v-for="(item, index) in qxTableList" :key="index" >
        <div class="cont-reight-bottom" @click="toJump(item.basis[0], item, index)">
          <div :class="item.level == 1 ? 'cont-reight-bottom-title-null' : 'cont-reight-bottom-title'">
            <span v-if="item.category == 0">A类</span>
            <span v-if="item.category == 1">B类</span>
            <span v-if="item.category == 2">C类</span>
            <span v-if="item.category == 3">D类</span>
            <span v-if="item.category == 4">其他</span>
            -{{ item.down }}
          </div>
          <div class="cont-reight-bottom-conter">
            <p>
              <span class="bold">字段名称：</span>
              {{ item.field_name }}
            </p>
            <p>
              <span class="bold">缺陷问题：</span>
              {{ item.desc }}
            </p>
          </div>
        </div>
        <!-- <div v-if="item.basis.length" class="zkyj">
          <span class="bold">质控依据：</span>
          {{ item.basis.length ? item.basis[0] : '' }}
        </div> -->
        <!-- <div 
          v-if="Array.isArray(item.basis) && item.basis.filter(str => !!str).length" 
          class="zkyj"
        >
          <span class="bold">质控依据：</span>
          <span v-for="(basis, index) in item.basis" :key="index">
          {{ basis }}
          <template v-if="index !== item.basis.length - 1"><br></template>
          </span>
        </div> -->
        <!-- 质控依据 -->
        <!-- <div v-for="(yjItem, yjIndex) of item.basis" :key="'yj' + yjIndex">
          <div class="zkyj" @click="toJump(yjItem, item, index)">质控依据：{{ yjItem.desc }}</div>
        </div> -->
        <QualityControlBasis :basis-data="item.basis" :initially-expanded="item.show" 
            :index="index" content-type="1" @toggle="clickListItem" />

      </div>
    </el-scrollbar>
  </div>
</template>

<script>
import QualityControlBasis from '@/components/quality-control-basis/index.vue'
export default {
  name: 'InfoCard',
  components: { QualityControlBasis },
  props: {
    data: {
      type: Object,
      default: {
        bSwitch: false,
        zyh: '',
        rule_id: '',
      },
    },
  },
  data() {
    return {
      qxArray: [], //缺陷问题
      controls: {
        ZYH: '',
        score: {
          score: 100,
        },
        list: [],
      },
      zkcodeIndex: 0,
      zk_codes: {
        qz: [],
        jy: [],
      },
      active_zk_index: 0,
    };
  },
  computed: {
    scoreLevel() {
      /**
       * 优＞=97分
       * 良90-96分
       * 中75~89分
       * 差＜75分
       * */
      let str;
      const { score } = this.controls.score;
      if (score >= 97) {
        str = '优';
      } else if (score < 97 && score >= 90) {
        str = '良';
      } else if (score < 90 && score >= 75) {
        str = '中';
      } else {
        str = '差';
      }
      return str;
    },
    qxTableList() {
      let qxList = this.controls.list;
      if ( qxList && Array.isArray(qxList)) {
          qxList.map((item) => {
            item.show = true
          })
        }
      let qxArray = [];
      qxList.forEach((item, index) => {
        if (item.error_rule == this.data.rule_id) {
          qxArray.unshift(item);
        } else {
          qxArray.push(item);
        }
      });
      return qxArray;
    },
  },
  mounted() {
    this.getData();
  },
  methods: {

    clickListItem(idx, quality_type) {
      this.errorList[idx].show = !this.errorList[idx].show
      this.$forceUpdate();
    },
    // 跳转锚点及高亮
    toJump(item, pItem, pIndex) {
      this.$emit('zkTest', pItem);
      if (this.active_zk_index !== pIndex) {
        this.zkcodeIndex = 0;
        this.active_zk_index = pIndex;
      }
      const { user, zd, ss } = item.location;
      const arr = [];
      const { level } = pItem;
      const level_arr = {
        qz: [],
        jy: [],
      };
      if (user && user.length) {
        user.map(uItem => {
          const obj = {
            field: uItem,
            key: 'user',
          };
          arr.push(obj);

          if (level) {
            // 建议
            level_arr.jy.push(uItem);
          } else {
            // 强制
            level_arr.qz.push(uItem);
          }
        });
      }

      if (zd && zd.length) {
        zd.map(zItem => {
          zItem.key = 'zd';
          arr.push(zItem);

          var index;
          if (zItem.ZZPB) {
            index = 0;
          } else {
            index = zItem.DIA_ORDER;
          }
          if (level) {
            // 建议
            level_arr.jy.push(`zd-${index}-${zItem.field}`);
          } else {
            // 强制
            level_arr.qz.push(`zd-${index}-${zItem.field}`);
          }
        });
      }
      if (ss && ss.length) {
        ss.map(sItem => {
          sItem.key = 'ss';
          arr.push(sItem);

          var index;
          if (sItem.ZZPB) {
            index = 0;
          } else {
            index = sItem.OPE_ORDER;
          }
          if (level) {
            // 建议
            level_arr.jy.push(`ss-${index}-${sItem.field}`);
          } else {
            // 强制
            level_arr.qz.push(`ss-${index}-${sItem.field}`);
          }
        });
      }
      const maxIndex = arr.length - 1;
      this.$emit('zk', { level: 0, anchor: arr[this.zkcodeIndex], codes: level_arr });
      if (this.zkcodeIndex === maxIndex) {
        this.zkcodeIndex = 0;
      } else {
        this.zkcodeIndex++;
      }
    },
    onClose() {
      this.$emit('close');
    },
    // 获取详情
    getData() {
      let rule_id = this.data.rule_id;

      const params = {
        ZYH: this.data.zyh,
      };
      this.$axios.post('/bmy/qualityResult', params).then(res => {
        const { qz, jy } = res.data;
        const list = [...qz, ...jy];

        console.log('编码员数据', list);
        this.$set(this, 'controls', { ...res.data, list });

        list.map(item => {
          item.basis.map(bItem => {
            const { user, zd, ss } = bItem.location;
            if (user && user.length) {
              user.map(uItem => {
                if (item.level) {
                  // 建议
                  this.zk_codes.jy.push(uItem);
                } else {
                  // 强制
                  this.zk_codes.qz.push(uItem);
                }
              });
            }
            if (zd && zd.length) {
              zd.map(zItem => {
                var index;
                if (zItem.ZZPB) {
                  index = 0;
                } else {
                  index = zItem.DIA_ORDER;
                }
                if (item.level) {
                  // 建议
                  this.zk_codes.jy.push(`zd-${index}-${zItem.field}`);
                } else {
                  // 强制
                  this.zk_codes.qz.push(`zd-${index}-${zItem.field}`);
                }
              });
            }
            if (ss && ss.length) {
              ss.map(sItem => {
                var index;
                if (sItem.ZZPB) {
                  index = 0;
                } else {
                  index = sItem.OPE_ORDER;
                }
                if (item.level) {
                  // 建议
                  this.zk_codes.jy.push(`ss-${index}-${sItem.field}`);
                } else {
                  // 强制
                  this.zk_codes.qz.push(`ss-${index}-${sItem.field}`);
                }
              });
            }
          });
        });

        //console.log(this.controls.list);
        this.$emit('codes', this.zk_codes);
      });
    },
  },
};
</script>

<style lang="scss">
.el-scrollbar__wrap {
  overflow-x: hidden;
}
</style>

<style lang="scss" scoped>
.control-box {
  // position: fixed;
  // top: 106px;
  // top: 70px;
  // right: 13px;
  width: 400px;
  padding: 20px 0 20px 20px;
  background: #fff;
  border-radius: 5px;
  overflow-y: hidden;
  transition: all 0.5s;
}
::v-deep .el-dialog {
  margin-right: 30px;
}
::v-deep .el-dialog__body {
  padding-top: 10px;
}
.score-box {
  height: 80px;
  background: #67c772;
  border-radius: 8px;
  padding: 20px;
  box-sizing: border-box;
  span {
    font-size: 20px;
    font-family: Source Han Sans CN-Medium, Source Han Sans CN;
    font-weight: 500;
    color: #ffffff;
    line-height: 40px;
    vertical-align: middle;
  }
  .score {
    font-size: 40px;
    font-family: Source Han Sans CN-Medium, Source Han Sans CN;
    font-weight: 500;
    color: #ffffff;
    line-height: 40px;
    vertical-align: middle;
    margin-left: 20px;
  }
  .level {
    float: right;
  }
  &.scoreLevel_1 {
    background: #328240;
  }
  &.scoreLevel_2 {
    background: #8ac410;
  }
  &.scoreLevel_3 {
    background: #ef8a0d;
  }
  &.scoreLevel_4 {
    background: #f0203a;
  }
}
.legend-box {
  text-align: center;
  margin: 20px 0 10px;
  span {
    position: relative;
    font-size: 14px;
    font-family: Source Han Sans CN-Regular, Source Han Sans CN;
    font-weight: 400;
    color: #666666;
    line-height: 20px;
    &:nth-child(1) {
      margin-right: 40px;
    }
    &::before {
      position: absolute;
      top: 5px;
      left: -20px;
      content: '';
      width: 10px;
      height: 10px;
      border-radius: 5px;
    }
    &.qz {
      &::before {
        background: #ed3028;
      }
    }
    &.jy {
      &::before {
        background: #178691;
      }
    }
  }
}
.cont-reight-bottom {
  margin: 24px 0 15px;
  display: flex;
  cursor: pointer;
}
.cont-reight-bottom div span {
  font-size: 24px;
}
.cont-reight-bottom-title {
  width: 90px;
  background: #ffdfdf;
  border-right: 3px solid #ed3028;
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  color: #ed3028;
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.cont-reight-bottom-title-null {
  width: 90px;
  background: #e7f3f4;
  border-right: 3px solid #178691;
  text-align: center;
  font-size: 24px;
  font-weight: bold;
  color: #178691;
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
    font-size: 14px;
    color: #666666;
  }
}
.zkyj {
  font-size: 14px;
  font-family: Source Han Sans CN-Regular, Source Han Sans CN;
  color: #ed3028;
  line-height: 20px;
  cursor: pointer;
}
.bold {
  font-size: 14px !important;
  font-weight: 600;
}

.choose-twinkle {
  // background: red;
  // font-size: 20px;
  // color: red;
  // font-weight: 600;
  background: #F5F3DF;
  // border: 2px solid red;
}
.choose-twinkle-1{
  // font-size: 20px;
  // color: red;
  // font-weight: 600;
  background: #F5F3DF;
  // border: 2px solid #e26e01;
}
</style>
