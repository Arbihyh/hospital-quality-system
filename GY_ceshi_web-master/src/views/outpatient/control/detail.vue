<template>
  <div class="bcjl">
    <div class="content-box">
      <el-button v-if="pageType === 'outpatient'" type="primary" style="background-color: #328240" @click="onControl">重新质控</el-button>

      <div class="title">
        {{ data.title }}
        <span class="subtitle">{{ data.subtitle }}</span>
      </div>
      <div class="info-header mb40">
        <el-row :gutter="32">
          <el-col :span="8" class="mb20">
            <span class="text-blod">门诊号：</span>
            <span>{{ data.mzh }}</span>
          </el-col>
          <el-col :span="8" class="mb20">
            <span class="text-blod">姓名：</span>
            <span>{{ data.xm }}</span>
          </el-col>
          <el-col :span="8" class="mb20">
            <span class="text-blod">就诊时间：</span>
            <span>{{ data.jzsj }}</span>
          </el-col>
          <el-col :span="8">
            <span class="text-blod">科室：</span>
            <span>{{ data.ks }}</span>
          </el-col>
          <el-col :span="8">
            <span class="text-blod">性别：</span>
            <span>{{ data.xb }}</span>
          </el-col>
          <el-col :span="8">
            <span class="text-blod">年龄：</span>
            <span>{{ data.nl }}</span>
          </el-col>
        </el-row>
      </div>
      <div class="info-content">
        <el-row :gutter="32">
          <el-col :span="24" class="mb10">
            <span class="text-blod">主诉：</span>
            <div class="pl76 mb20">{{ data.zs }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">现病史：</span>
            <div class="pl76 mb20">{{ data.xbs }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">既往史：</span>
            <div class="pl76 mb20">{{ data.jws }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">体格检查：</span>
            <div class="pl76 mb20">{{ data.tgjc }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">辅助检查：</span>
            <div class="pl76 mb20">{{ data.fzjc }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">初步诊断：</span>
            <div class="pl76 mb20">{{ data.cbzd }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">诊疗意见：</span>
            <div class="pl76 mb20" v-for="(item, index) of data.zlyj" :key="index">{{ item }}</div>
          </el-col>
          <el-col :span="24" class="mb10">
            <span class="text-blod">药品：</span>
            <div class="pl76 mb20" v-for="(item, index) of data.xy" :key="index">{{ item }}</div>
          </el-col>

          <!-- 判断四个核心字段是否至少有一个有效 -->
          <div v-if="hasValidDiagnosisData">
            <el-col :span="24" class="mb10">
              <span class="text-blod">四诊资料：</span>
              <div class="pl76 mb20">{{ data.sz }}</div>
            </el-col>
            <el-col :span="24" class="mb10">
              <span class="text-blod">望诊：</span>
              <div class="pl76 mb20">{{ data.wangz }}</div>
            </el-col>
            <el-col :span="24" class="mb10">
              <span class="text-blod">闻诊：</span>
              <div class="pl76 mb20">{{ data.wenz }}</div>
            </el-col>
            <el-col :span="24" class="mb10">
              <span class="text-blod">问诊：</span>
              <div class="pl76 mb20">{{ data.wz }}</div>
            </el-col>
            <el-col :span="24" class="mb10">
              <span class="text-blod">切诊：</span>
              <div class="pl76 mb20">{{ data.qiez }}</div>
            </el-col>
          </div>

          <!-- <el-col :span="12" style="margin-top: 80px">
            <div class="text_right">
              书写医生：
              <span style="margin-right: 100px">{{ data.SXYS }}</span>
            </div>
          </el-col>
          <el-col :span="12" style="margin-top: 80px">
            <div class="text_right" style="padding-right: 150px">
              医生签名：
              <span>{{ data.doctor_name }}</span>
            </div>
          </el-col> -->

          <el-col :span="12" style="margin-top: 80px">
            <div class="text_right">
              书写医生：
              <span style="margin-right: 100px">{{ data.SXYS }}</span>
            </div>
          </el-col>
          <el-col :span="12" style="margin-top: 80px">
            <div class="text_right" style="padding-right: 150px">
              医生签名：
              <span>{{ data.doctor_name }}</span>
            </div>
          </el-col>
          <el-col :span="12" style="margin-top: 10px; margin-bottom: 20px">
            <div class="text_right">
              创建时间：
              <span style="margin-right: 100px">{{ data.create_time }}</span>
            </div>
          </el-col>
          <el-col :span="12" style="margin-top: 10px; margin-bottom: 20px">
            <div class="text_right" style="padding-right: 150px">
              首次签名时间：
              <span>{{ data.first_sign_time }}</span>
            </div>
          </el-col>
        </el-row>
      </div>
    </div>
    <CaseQualityBox v-if="!$route.query.from" @confirmFeedback="confirmFeedback" :basicData="basicData" :data="results" @refresh="handleRefresh" style="margin: 20px 0 0 20px" />
  </div>
</template>

<script>
import CaseQualityBox from '@/views/allcase/components/CaseQualityBox';

export default {
  components: {
    CaseQualityBox,
  },
  inject: ['t_title'],
  data() {
    return {
      data: {
        xy: [],
      },
      results: {
        score: 0,
        quality_time: '',
        data: {},
      },
      basicData: {
        ks: '-',
        sxys: '-',
      },
      pageType: this.$route.query.pageType,
    };
  },
  computed: {
    hasValidDiagnosisData() {
      if (!this.data) return false;
      const { wangz, wenz, wz, qiez } = this.data;
      const isEffective = value => {
        return value !== null && value !== undefined && String(value).trim() !== '';
      };
      return isEffective(wangz) || isEffective(wenz) || isEffective(wz) || isEffective(qiez);
    },
  },
  created() {
    this.getDetails();
    this.t_title(this.$route.query.xm);
    if (!this.$route.query.from) {
      this.getCaseQualityResults();
    }
  },

  methods: {
    onControl() {
      this.$axios2
        .post(`/quality_handle_mz?BLBH=${this.$route.query.blbh}&ISMZ=1`, {
          timeout: 180000,
        })
        .then(res => {
          if (res.code == 200) {
            this.getCaseQualityResults();
          }
        });
    },
    // 刷新
    handleRefresh() {
      this.getDetails();
      if (!this.$route.query.from) {
        this.getCaseQualityResults();
      }
    },
    confirmFeedback() {
      this.getCaseQualityResults();
    },
    // 获取新病案指控结果
    // getCaseQualityResults() {
    //   const params = {
    //     blbh: this.$route.query.blbh,
    //   };
    //   this.$axios.post('/omr_zk/get_omr_quality', params).then(res => {
    //     console.log("getCaseQualityResults",res.data);
    //     this.results = res.data;
    //   });
    // },
    getCaseQualityResults() {
      this.results = { score: 0, quality_time: '', data: {} };
      const targetNotice = this.$route.query.notice;

      const params = {
        blbh: this.$route.query.blbh,
      };

      this.$axios
        .post('/omr_zk/get_omr_quality', params)
        .then(res => {
          const responseData = res.data || {};
          const { score = 0, quality_time, data = {} } = responseData;

          const processedData = JSON.parse(JSON.stringify(data));
          if (targetNotice) {
            Object.entries(processedData).forEach(([fieldName, fieldValue]) => {
              if (Array.isArray(fieldValue)) {
                const matchedItems = [];
                const restItems = [];

                fieldValue.forEach(item => {
                  if (item?.notice === targetNotice) {
                    matchedItems.push(item);
                  } else {
                    restItems.push(item);
                  }
                });

                processedData[fieldName] = [...matchedItems, ...restItems];
              }
            });
          }
          this.results = {
            score: score,
            quality_time: quality_time,
            data: processedData,
          };
        })
        .catch(error => {
          console.error('获取病历质量结果失败：', error);
          this.results = { score: 0, quality_time: '', data: {} };
        });
    },
    getDetails() {
      this.$axios.post('/omr_zk/omr_info', { blbh: this.$route.query.blbh }).then(res => {
        this.data = res.data;
        this.basicData.ks = res.data.ks;
        this.basicData.sxys = res.data.SXYS;
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.bcjl {
  background: #fff;
  margin: 0 20px;
  line-height: 1.5;
  display: flex;
  .content-box {
    background: #fff;
    padding: 20px;
    margin-top: 20px;
    flex: 1;
  }
  .title {
    font-size: 24px;
    font-weight: bold;
    color: #2c3240;
    text-align: center;
    margin: 20px;
    position: relative;
    .subtitle {
      position: absolute;
      right: 300px;
      line-height: 36px;
    }
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