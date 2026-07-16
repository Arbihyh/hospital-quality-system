<template>
  <div class="no_format_text">
    <div class="name">{{ name }}</div>
    <div class="content" v-html="text" />
    <div class="bottom-time bottom-time-top">
      <div class="bottom-time-list">
        <span class="bottom-time-bold">医生签名:</span>
        <span>{{ data.doctor_name }}</span>
      </div>
    </div>
    <div class="bottom-time bottom-time-top">
      <div v-if="data.CJSJ" class="bottom-time-list">
        <span class="bottom-time-bold">创建时间:</span>
        <span>{{ data.CJSJ }}</span>
      </div>
      <div v-if="data.ZXSJ" class="bottom-time-list">
        <span class="bottom-time-bold">修改时间:</span>
        <span>{{ data.ZXSJ }}</span>
      </div>
    </div>
    <div v-if="data.WCSJ" class="bottom-time bottom-time-botom">
      <div class="bottom-time-list">
        <span class="bottom-time-bold">完成时间:</span>
        <span>{{ data.WCSJ }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    text: {
      type: String,
      default() {
        return ''
      }
    },
    name: {
      type: String,
      default() {
        return ''
      }
    },
    data: {
      type: Object,
      default() {
        return {}
      }
    }
  }
}
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
</style>
