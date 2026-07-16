<template>
  <div class="no_format_text">
    <div class="name">{{ name }}</div>
    <div class="content">
      <el-input :value="value" @input="$emit('input', $event)" placeholder="请输入内容" type="textarea" :autosize="{ minRows: 40, maxRows: 80 }"></el-input>
    </div>
    <div class="bottom-time bottom-time-top">
      <div class="bottom-time-list">
        <span class="bottom-time-bold">医生签名:</span>
        <span>{{ data.doctor_name }}</span>
      </div>
    </div>
    <div class="bottom-time bottom-time-top">
      <div class="bottom-time-list" v-if="data.CJSJ">
        <span class="bottom-time-bold">创建时间:</span>
        <span>{{ data.CJSJ }}</span>
      </div>
      <div class="bottom-time-list" v-if="data.ZXSJ">
        <span class="bottom-time-bold">首次签名时间:</span>
        <span>{{ data.ZXSJ }}</span>
      </div>
    </div>
    <div class="bottom-time bottom-time-botom" v-if="data.WCSJ">
      <div class="bottom-time-list">
        <span class="bottom-time-bold">末次修改时间:</span>
        <span>{{ data.WCSJ }}</span>
      </div>

      <div class="bottom-time-list">
        <span class="bottom-time-bold">业务时间:</span>
        <span v-if="data.YWSJ">{{ data.YWSJ }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    value: {
      type: String,
      default() {
        return '';
      },
    },
    name: {
      type: String,
      default() {
        return '';
      },
    },
    data: {
      type: Object,
      default() {
        return {};
      },
    },
  },
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
</style>
