<template>
  <div class="box">
    <div class="title">
      {{ data.hospital }}
      <br>
      手术记录
    </div>
    <div class="info-header mb40">
      <el-row :gutter="32">
        <el-col :span="6">
          <span class="text-blod">科室：</span>
          <span>{{ data.department }}</span>
        </el-col>
        <el-col :span="6">
          <span class="text-blod">姓名：</span>
          <span>{{ data.brxm }}</span>
        </el-col>
        <el-col :span="6">
          <span class="text-blod">床号：</span>
          <span>{{ data.ch }}</span>
        </el-col>
        <el-col :span="6">
          <span class="text-blod">住院号：</span>
          <span>{{ data.brbh }}</span>
        </el-col>
      </el-row>
    </div>
    <div class="table">
      <table ref="tables">
        <tr>
          <td class="table-label">患者姓名</td>
          <td class="table-value">{{ data.brxm }}</td>
          <td class="table-label">性别</td>
          <td class="table-value">{{ data.sex }}</td>
          <td class="table-label">年龄</td>
          <td class="table-value">{{ data.age }}</td>
        </tr>
        <tr>
          <td class="table-label">手术日期</td>
          <td class="table-value" colspan="2">{{ data.ssrq }}</td>
          <td class="table-label">手术时间</td>
          <td class="table-value" colspan="2">{{ data.sssj }}</td>
        </tr>
        <tr>
          <td class="table-label">术前诊断</td>
          <td class="table-value" colspan="5">{{ data.sqzd }}</td>
        </tr>
        <tr>
          <td class="table-label">术中诊断</td>
          <td class="table-value" colspan="5">{{ data.szzd }}</td>
        </tr>
        <tr>
          <td class="table-label">手术名称</td>
          <td class="table-value" colspan="5">{{ data.ssmc }}</td>
        </tr>
        <tr>
          <td class="table-label">手术指导</td>
          <td class="table-value">{{ data.sszdz }}</td>
          <td class="table-label">手术者</td>
          <td class="table-value">{{ data.ssz }}</td>
          <td class="table-label">助手</td>
          <td class="table-value">{{ data.zs }}</td>
        </tr>
        <tr style="min-height: 300px">
          <td class="table-label">手术经过、术中出现的情况及处理</td>
          <td class="table-value" colspan="5">
            <div class="pd16 text_left minH200">{{ data.describe }}</div>
          </td>
        </tr>
        <tr>
          <td class="table-label">手术者签名</td>
          <td class="table-value" colspan="2">{{ data.sszqm }}</td>
          <td class="table-label">记录时间</td>
          <td class="table-value" colspan="2">{{ data.jlsj }}</td>
        </tr>
      </table>
    </div>
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
.box {
  margin: 0 30px;
  line-height: 1.5;
  padding-bottom: 100px;
  .title {
    font-size: 24px;
    font-weight: bold;
    color: #2c3240;
    text-align: center;
    margin: 20px;
  }
  .mb10 {
    margin-bottom: 10px;
  }
  .mb20 {
    margin-bottom: 20px;
  }
  .mb40 {
    margin-bottom: 40px;
  }
  .text-blod {
    font-weight: bold;
  }
  .pd16 {
    padding: 16px;
  }
  .text_left {
    text-align: left;
  }
  .minH200 {
    min-height: 200px;
  }
}

table {
  width: 100%;
  min-width: 600px;
  table-layout: fixed;
  empty-cells: show;
  border-collapse: collapse;
  margin: 0 auto;
  margin-bottom: 30px;
  td {
    border: 1px solid #dddddd;
    text-align: center;
    font-size: 12px;
    box-sizing: border-box;
  }
  .table-label {
    width: 150px;
    height: 40px;
    color: #666666;
    background-color: #f9f9f9;
  }
  .table-value {
    height: 40px;
    color: #333333;
    text-align: center;
  }
}
</style>
