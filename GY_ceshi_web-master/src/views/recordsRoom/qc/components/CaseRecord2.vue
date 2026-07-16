<template>
  <div class="bcjl">
    <div class="title">{{ titleName }}</div>
    <div class="cont-title-item" v-if="data.type == 5">{{ data.zc_time }}</div>
    <div class="cont-title-item" v-if="data.type == 6">{{ data.zr_time }}</div>
    <div class="info-header mb40" v-if="data.type != 5 && data.type != 6">
      <el-row :gutter="32">
        <el-col :span="6">
          <span class="text-blod">科室：</span>
          <span>{{ baseInfo.BRKS}}</span>
        </el-col>
        <el-col :span="6">
          <span class="text-blod">姓名：</span>
          <span>{{ baseInfo.BRXM }}</span>
        </el-col>
        <el-col :span="6">
          <span class="text-blod">床号：</span>
          <span>{{ baseInfo.CH }}</span>
        </el-col>
        <el-col :span="6">
          <span class="text-blod">住院号码：</span>
          <span>{{ baseInfo.ZYH }}</span>
        </el-col>
      </el-row>
    </div>
    <div class="header-main" v-if="data.type == 5">
      {{ data.user_info }}
    </div>
    <div class="header-main" v-if="data.type == 6">
      {{ data.zrjl }}
    </div>
    <div class="info-content">
      <el-row :gutter="32">
        <el-col :span="24" class="mb40">
          <el-row :gutter="32">
            <el-col :span="10">
              <span class="date">{{ data ? data.date : '' }}</span>
            </el-col>
            <el-col :span="8">
              <span class="content-title">{{ data ? data.title : '' }}</span>
            </el-col>
          </el-row>
        </el-col>
      </el-row>
      <el-row :gutter="32">
        <!-- type 1为普通病程记录 2. 术前 3术后 4.查房 -->
        <template v-if="data.type === 1">
          <!-- <el-col :span="24" class="mb10">
            <span class="text-blod">病历特点：</span>
            <div v-for="(item, index) of data.BLTD" :key="`bltd1${index}`" class="pl76 mb20">{{ item.trim() }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">初步诊断：</span>
            <div v-for="(item, index) of data.CBZD" :key="`cbzd1${index}`" class="pl76 mb20">{{ item.trim() }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">诊断依据：</span>
            <div v-for="(item, index) of data.ZDYJ" :key="`zdyj1${index}`" class="pl76 mb20">{{ item.trim() }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">鉴别诊断：</span>
            <div v-for="(item, index) of data.JBZD" :key="`jbzd1${index}`" class="pl76 mb20">{{ item.trim() }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">诊疗计划：</span>
            <div v-for="(item, index) of data.ZLJH" :key="`zljh1${index}`" class="pl76 mb20">{{ item.trim() }}</div>
          </el-col> -->
          <el-col :span="24" class="mb10">
            <span class="text-blod">病历特点：</span>
            <div class="pl76 mb20">{{ data.BLTD}}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">初步诊断：</span>
            <div class="pl76 mb20">{{ data.CBZD}}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">诊断依据：</span>
            <div class="pl76 mb20">{{ data.ZDYJ }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">鉴别诊断：</span>
            <div class="pl76 mb20">{{ data.JBZD }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">诊疗计划：</span>
            <div class="pl76 mb20">{{ data.ZLJH }}</div>
          </el-col>
        </template>
        <!-- type 2术前 -->
        <template v-if="[2].includes(data.type)">
          <el-col :span="24" class="mb10" v-if="data.SSZC">
            <div class="text-blod">
              术前讨论由
              <span style="padding: 0 20px; border-bottom: 1px solid #000">{{ data.SSZC }}</span>
              医师主持，讨论结论及术前小结记录如下:
            </div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.JYBQ">
            <span class="text-blod">简要病情：</span>
            <div v-for="(item, index) of data.JYBQ" :key="`jybq2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.SSZZ">
            <span class="text-blod">术前诊断：</span>
            <div v-for="(item, index) of data.SSZZ" :key="`sszz2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.SZZY">
            <span class="text-blod">手术指征：</span>
            <div v-for="(item, index) of data.SZZY" :key="`szzz2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.NSSS">
            <span class="text-blod">拟施手术名称和方式：</span>
            <div v-for="(item, index) of data.NSSS" :key="`nsss2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.NSMZ">
            <span class="text-blod">拟施麻醉方式：</span>
            <div v-for="(item, index) of data.NSMZ" :key="`nsmz2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.OTHER">
            <span class="text-blod">术前准备：</span>
            <div v-for="(item, index) of data.OTHER" :key="`other2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.SZZY">
            <span class="text-blod">术中注意事项：</span>
            <div v-for="(item, index) of data.SZZY" :key="`szzz2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.SHCL">
            <span class="text-blod">术后处理：</span>
            <div v-for="(item, index) of data.SHCL" :key="`shcl2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10" v-if="data.desc">
            <span class="text-blod">其他描述：</span>
            <div v-for="(item, index) of data.desc" :key="`desc2${index}`" class="pl76 mb20">{{ item }}</div>
          </el-col>
        </template>
        <!-- type 3术后查房 -->
        <template v-if="[3].includes(data.type)">
          <el-col :span="24" class="mb10" v-if="data.desc">
            <div v-for="(item, index) of data.desc" :key="`bltd3${index}`" class="mb20">{{ item.trim() }}</div>
          </el-col>
        </template>
        <!-- type 4术后查房 -->
        <template v-if="[4].includes(data.type)">
          <el-col :span="24" class="mb10" v-if="data.desc">
            <div v-for="(item, index) of data.desc" :key="`bltd3${index}`" class="mb20">{{ item.trim() }}</div>
          </el-col>
        </template>
        <!-- type 5转出记录 -->
        <template v-if="data.type === 5">
          <el-col :span="24" class="mb10">
            <span class="text-blod">入院情况：</span>
            <div class="pl76 mb20">{{ data.ryqk }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">入院诊断：</span>
            <div class="pl76 mb20">{{ data.ryzd }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">诊疗过程：</span>
            <div class="pl76 mb20">{{ data.zljg }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">目前情况：</span>
            <div class="pl76 mb20">{{ data.mqqk }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">目前诊断：</span>
            <div class="pl76 mb20">
              <div v-for="(item, index) of data.mqzd" :key="`bltd3${index}`" class="mb20">{{ item.trim() }}</div>
            </div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">转科室及注意事项：</span>
            <div class="pl76 mb20">
              <div class="pl76 mb20">{{ data.zysx }}</div>
            </div>
          </el-col>
        </template>
        <!-- type 6转入记录 -->
        <template v-if="data.type === 6">
          <el-col :span="24" class="mb10">
            <span class="text-blod">目前情况：</span>
            <div class="pl76 mb20">{{ data.mqqk }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">目前诊断：</span>
            <div class="pl76 mb20">
              <div v-for="(item, index) of data.mqzd" :key="`bltd3${index}`" class="mb20">{{ item }}</div>
            </div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">转入诊疗计划：</span>
            <div class="pl76 mb20">
              <div v-for="(item, index) of data.zljh" :key="`zrjh${index}`" class="mb20">{{ item }}</div>
            </div>
          </el-col>
        </template>
        <el-col :span="24" class="mt80" v-if="data.type === 5 || data.type === 6">
          <div class="fr" style="margin-right: 100px">
            <span class="text-blod">上级医师审核日期：</span>
            <span>{{ data.shrq }}</span>
          </div>
        </el-col>
        <el-col :span="24" class="mt30">
          <div class="fr" style="margin-right: 100px">
            <span class="text-blod">医生签名：</span>
            <span>{{ data.doctor_name }}</span>
            <!-- 接口暂无字段 -->
          </div>
        </el-col>
      </el-row>
      <div class="bottom-time bottom-time-top">
        <div class="bottom-time-list">
          <span class="bottom-time-bold">创建时间:</span>
          <span v-if="data.CJSJ">{{ data.CJSJ }}</span>
        </div>
        <div class="bottom-time-list">
          <span class="bottom-time-bold">首次签名时间:</span>
          <span v-if="data.ZXSJ">{{ data.ZXSJ }}</span>
        </div>
      </div>
      <div class="bottom-time bottom-time-botom">
        <div class="bottom-time-list">
          <span class="bottom-time-bold">末次修改时间:</span>
          <span v-if="data.WCSJ">{{ data.WCSJ }}</span>
        </div>
        <div class="bottom-time-list">
          <span class="bottom-time-bold">业务时间:</span>
          <span v-if="data.YWSJ">{{ data.YWSJ }}</span>
        </div>
      </div>
     
    </div>
  </div>
</template>

<script>
import { getBrry } from '@/api/qc';

export default {
  props: {
    data: {
      type: Object,
      default() {
        return {};
      },
    },
    ZYH: {
      type: String,
      default() {
        return '';
      },
    }
  },
  computed: {
    titleName() {
      let str;
      const type = this.data.type;
      if (type === 1) {
        str = '首次病程记录';
      } else if (type === 2) {
        str = '术前小结及术前讨论结论记录';
      } else if (type === 3) {
        str = '术后首次病程记录';
      } else if (type === 4) {
        str = '查房记录';
      } else if (type === 5) {
        str = '转出记录';
      } else if (type === 6) {
        str = '转入记录';
      }
      return str;
    },
  },
  data() {
    return {
      baseInfo: {}
    }
  },
  mounted() {
    this.getBaseInfo()
  },
  methods: {
    getBaseInfo() {
        getBrry({zyh: this.$props.ZYH}).then(res => {
          if (res.code == 200) {
           this.baseInfo = res.data || {}
          }
        })
    },
        
  }
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
  .mt30 {
    margin-top: 30px;
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
  .info-header {
    padding: 20px 0;
    border-bottom: 1.5px solid #e2dfdf;
  }
  .header-main {
    padding: 20px 0;
  }
  .info-content {
    overflow: hidden;
    .date {
      font-weight: 600;
    }
    .content-title {
      font-weight: 600;
    }
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
.cont-title-item {
  width: 100%;
  margin: 0 auto;
  display: flex;
  justify-content: flex-end;
  font-size: 16px;
  padding: 30px 0 10px 0;
  font-weight: 600;
}
</style>
