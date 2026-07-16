<template>
  <div class="bcjl">
    <div class="title">出院记录</div>
    <div class="info-header mb40">
      <el-row :gutter="32">
        <el-col :span="6">
          <span class="text-blod">科室：</span>
          <span v-if="data.department">{{ data.department }}</span>
          <!-- 接口暂无字段 -->
        </el-col>
        <el-col :span="6">
          <span class="text-blod">姓名：</span>
          <span v-if="data.name">{{ data.name.value }}</span>
          <!-- 接口暂无字段 -->
        </el-col>
        <el-col :span="6">
          <span class="text-blod">床号：</span>
          <span></span>
          <!-- 接口暂无字段 -->
        </el-col>
        <el-col :span="6">
          <span class="text-blod" v-if="data.hospital_no">住院号码：</span>
          <span v-if="data.hospital_no">{{ data.hospital_no.value }}</span>
          <!-- 接口暂无字段 -->
        </el-col>
      </el-row>
    </div>
    <div class="info-content">
      <div class="user-info mb30">
        <el-row :gutter="32">
          <el-col :span="12">
            <span>姓名：</span>
            <span v-if="data.name">{{ data.name.value }}</span>
          </el-col>
          <el-col :span="12">
            <span>入院日期：</span>
            <span v-if="data.ry_time">{{ data.ry_time.value }}</span>
          </el-col>
          <el-col :span="12">
            <span>性别：</span>
            <span v-if="data.sex">{{ data.sex.value }}</span>
          </el-col>
          <el-col :span="12">
            <span>出院日期：</span>
            <span v-if="data.cy_time">{{ data.cy_time.value }}</span>
          </el-col>
          <el-col :span="12">
            <span>年龄：</span>
            <span v-if="data.age">{{ data.age.value }}</span>
          </el-col>
          <el-col :span="12">
            <span>住院天数：</span>
            <span v-if="data.zyts">{{ data.zyts.value }}</span>
          </el-col>
        </el-row>
      </div>
      <el-row :gutter="32">
        <el-col :span="24" class="mb10">
          <span class="text-blod">入院情况：</span>
          <div class="pl76 mb20" v-if="data.ryqk">{{ data.ryqk.value }}</div>
        </el-col>
        <el-col :span="24" class="mb10">
          <span class="text-blod">初步诊断：</span>
          <div class="pl76 mb20" v-if="data.cbzd">{{ data.cbzd.value }}</div>
        </el-col>
        <el-col :span="24" class="mb10">
          <span class="text-blod">诊疗经过：</span>
          <div class="pl76 mb20" v-if="data.zljg">{{ data.zljg.value }}</div>
        </el-col>
        <el-col :span="24" class="mb10">
          <span class="text-blod">出院情况：</span>
          <div class="pl76 mb20" v-if="data.cyqk">{{ data.cyqk.value }}</div>
        </el-col>
        <el-col :span="24" class="mb10">
          <span class="text-blod">出院诊断：</span>
          <div class="pl76 mb20" v-if="data.cyzd">{{ data.cyzd.value }}</div>
        </el-col>
        <el-col :span="24" class="mb200">
          <span class="text-blod">出院医嘱：</span>
          <div class="pl76 mb20" v-if="data.cyyz">{{ data.cyyz.value }}</div>
        </el-col>
        <el-col :span="24" class="mb40">
          <div class="text_right">
            医生签名：
            <span v-if="data.doctor_name">{{ data.doctor_name }}</span>
            <!-- 接口暂无字段 -->
          </div>
        </el-col>
      </el-row>
      <div class="admrec-bottom-time">
        <div class="admrec-bottom-time-list">
          <span class="text-blod">创建时间:</span>
          <span v-if="data.CJSJ">{{ data.CJSJ }}</span>
        </div>
        <div class="admrec-bottom-time-list">
          <span class="text-blod">首次签名时间:</span>
          <span v-if="data.ZXSJ">{{ data.ZXSJ }}</span>
        </div>
      </div>
      <div class="admrec-bottom-time">
        <div class="admrec-bottom-time-list">
          <span class="text-blod">末次修改时间:</span>
          <span v-if="data.WCSJ">{{ data.WCSJ }}</span>
        </div>

         <div class="bottom-time-list">
          <span class="text-blod">业务时间:</span>
          <span v-if="data.YWSJ">{{ data.YWSJ }}</span>
        </div>
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
          name: {},
          ry_time: {},
          sex: {},
          age: {},
          cy_time: {},
          zyts: {},
          ryqk: {},
          cbzd: {},
          zljg: {},
          cyqk: {},
          cyzd: {},
          cyyz: {},
        };
      },
    },
  },
};
</script>

<style lang="scss" scoped>
.bcjl {
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
  .mt80 {
    margin-top: 80px;
  }
  .mb200 {
    margin-bottom: 200px;
  }
  .pl76 {
    padding-left: 76px;
  }
  .fr {
    float: right;
  }
  .text-blod {
    font-weight: bold;
  }
  .text_right {
    text-align: right;
  }
  .info-header {
    padding: 20px 0;
    border-bottom: 1.5px solid #e2dfdf;
  }
  .info-content {
    overflow: hidden;
    .user-info {
      width: 800px;
      margin: 0 auto;
      line-height: 32px;
    }
  }
}
.admrec-bottom-time {
  width: 100%;
  display: flex;
  justify-content: flex-start;
  align-items: center;
  margin-bottom: 20px;
  .admrec-bottom-time-list {
    width: 50%;
  }
}
</style>
