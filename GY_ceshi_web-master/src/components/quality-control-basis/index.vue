<!-- 质控依据 【参考病历质控样式封装】 -->
<template>
  <div class="quality-control-basis">
    <div v-if="hasBasisData" class="list-item-title" @click="toggleShow">
      {{ title }}
      <i :class="`el-icon-arrow-${isExpanded ? 'up' : 'down'}`" style="cursor: pointer;font-weight: bold"></i>
    </div>

    <div class="list-item-basis-box" v-if="hasBasisData">
      <div class="list-basis-text">
        <div class="list-basis-text-t" :class="isExpanded ? 'show' : ''">
          <div v-for="(item, index) of basisData" :key="index" style="margin-bottom: 10px;">
            <div v-if="isStringItem(item)">
              <span class="span-index">{{ index + 1 }}</span>
              <span class="list-item-value">{{ item }}</span>
              <br />
            </div>

            <el-row v-else class="list-basis-text-t-noString">
              <span class="span-index">{{ index + 1 }}</span>
              <span v-for="(content, key) in getValidItems(item)" :key="key">
                <span class="list-item-value">{{ content }}</span>
                <div style="height: 10px" v-if="!isLastItem(item, key)"></div>
              </span>
            </el-row>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'QualityControlBasis',

  props: {
    // 质控数据
    basisData: {
      type: Array,
      default: () => [],
      required: true
    },
    title: {
      type: String,
      default: '质控依据'
    },
    initiallyExpanded: {
      type: Boolean,
      default: false
    },
    // 数据索引
    index: {
      type: String,
      default: ''
    },
    contentType: {
      type: Number,
      default: 1,
      required: false
    }
  },

  computed: {
    // hasBasisData() {
    //   return Array.isArray(this.basisData) && this.basisData.length > 0;
    // }
     hasBasisData() {
      // 不是数组直接返回false
      if (!Array.isArray(this.basisData)) return false;
      
      // 数组长度为0返回false
      if (this.basisData.length === 0) return false;
      
      // 长度为1且唯一元素是空字符串返回false
      if (this.basisData.length === 1 && this.basisData[0] === '') return false;
      
      // 检查是否所有元素都是空内容（空字符串、空对象等）
      const allEmpty = this.basisData.every(item => {
        // 空字符串
        if (typeof item === 'string' && item.trim() === '') return true;
        // 空对象
        if (Object.prototype.toString.call(item) === '[object Object]' && Object.keys(item).length === 0) return true;
        // 其他情况视为非空
        return false;
      });
      
      return !allEmpty;
    },
  },

  data() {
    return {
      isExpanded: this.initiallyExpanded
    };
  },

  methods: {
    toggleShow() {
      this.isExpanded = !this.isExpanded;
      this.$emit('toggle', this.index, this.contentType, this.isExpanded);
    },

    isStringItem(item) {
      return typeof item === 'string';
    },

    isValidKey(key) {
      return !isNaN(parseFloat(key));
    },

    // 过滤出有效的键值对
    getValidItems(item) {
      return Object.keys(item).reduce((result, key) => {
        if (this.isValidKey(key)) {
          result[key] = item[key];
        }
        return result;
      }, {});
    },

    // 判断是否为对象的最后一项
    isLastItem(obj, key) {
      const keys = Object.keys(this.getValidItems(obj));
      return keys.indexOf(key) === keys.length - 1;
    }
  }
};
</script>

<style lang="scss" scoped>
.quality-control-basis {
  width: 100%;
  box-sizing: border-box;
}

// 索引样式
.span-index {
  width: 20px;
  height: 20px;
  line-height: 20px;
  text-align: center;
  display: inline-block;
  border-radius: 50%;
  background: #185DA6;
  color: #fff;
  margin-right: 10px;
  margin-bottom: 4px;
  font-size: 12px;
}

// 标题样式
.list-item-title {
  font-family: PingFang-SC, PingFang-SC;
  font-weight: bold;
  font-size: 18px;
  color: #333333;
  line-height: 25px;
  text-align: left;
  font-style: normal;
  margin-bottom: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}

// 内容区域样式
.list-item-basis-box {
  .list-basis-text {
    height: auto;

    .list-basis-text-t {
      height: 0;
      overflow: hidden;
      position: relative;
      transition: height 0.3s ease;

      &>div:last-child {
        margin-bottom: 0 !important;
      }

      &.show {
        height: auto;
      }

      // 对象类型内容的最后一项样式调整
      .list-basis-text-t-noString {
        &>span:last-child {
          div {
            height: 0 !important;
          }
        }
      }
    }
  }
}

// 内容文本样式
.list-item-value {
  font-family: PingFangSC, PingFang SC;
  font-weight: 400;
  font-size: 14px;
  color: #333333;
  line-height: 20px;
  text-align: left;
  font-style: normal;
}
</style>