<template>
  <div class="box">
    <div class="title">
      {{ data.hospital }}
      <br />
      手术安全核查表
    </div>
    <div class="table">
      <table ref="tables">
        <tr>
          <td class="table-label">科室</td>
          <td class="table-value" colspan="2">{{ data.keshi }}</td>
          <td class="table-label">姓名</td>
          <td class="table-value">{{ data.brxm }}</td>
          <td class="table-label">性别</td>
          <td class="table-value">{{ data.sex }}</td>
          <td class="table-label">年龄</td>
          <td class="table-value">{{ data.age }}</td>
        </tr>
        <tr>
          <td class="table-label">住院号码</td>
          <td class="table-value" colspan="2">{{ data.brbh }}</td>
          <td class="table-label">术者</td>
          <td class="table-value" colspan="2">{{ data.sz }}</td>
          <td class="table-label">手术日期</td>
          <td class="table-value" colspan="2">{{ data.ssrq }}</td>
        </tr>
        <tr>
          <td class="table-label">麻醉方式</td>
          <td class="table-value" colspan="4">{{ data.mzfs }}</td>
          <td class="table-label">手术方式</td>
          <td class="table-value" colspan="3">{{ data.ssfs }}</td>
        </tr>

        <tr>
          <td class="table-label" colspan="3">麻醉实施前</td>
          <td class="table-label" colspan="3">手术开始前</td>
          <td class="table-label" colspan="3">患者离开手术前</td>
        </tr>

        <tr>
          <td class="table-value" colspan="3">
            <div>
              患者姓名、性别、年龄正确：
              <span class="ml40">{{ data.mzssq.xm_sex_age }}</span>
            </div>
            <div>
              手术方式确认：
              <span class="ml40">{{ data.mzssq.ssfsqr }}</span>
            </div>
            <div>
              手术部位与标识正确：
              <span class="ml40">{{ data.mzssq.ssbwybsqr }}</span>
            </div>
            <div>
              手术知情同意：
              <span class="ml40">{{ data.mzssq.sszqty }}</span>
            </div>
            <div>
              麻醉知情同意：
              <span class="ml40">{{ data.mzssq.mzzqty }}</span>
            </div>
            <div>
              麻醉方式确认：
              <span class="ml40">{{ data.mzssq.mzfsqr }}</span>
            </div>
            <div>
              麻醉设备安全检查完成：
              <span class="ml40">{{ data.mzssq.mzsbaqjc }}</span>
            </div>
            <div>
              皮肤是否完整：
              <span class="ml40">{{ data.mzssq.pfsfwz }}</span>
            </div>
            <div>
              术野皮肤准备正确：
              <span class="ml40">{{ data.mzssq.sypfzbzq }}</span>
            </div>
            <div>
              静脉通道建立完成：
              <span class="ml40">{{ data.mzssq.jmtdjlwc }}</span>
            </div>
            <div>
              患者是否有过敏史：
              <span class="ml40">{{ data.mzssq.hzsfygms }}</span>
            </div>
            <div>
              抗菌药物皮试结果：
              <span class="ml40">{{ data.mzssq.kjywpsjg }}</span>
            </div>
            <div>
              术前备血：
              <span class="ml40">{{ data.mzssq.sqbx }}</span>
            </div>
            <div>
              假体：
              <span class="ml40">{{ data.mzssq.jt }}</span>
            </div>
            <div>
              体内植入物：
              <span class="ml40">{{ data.mzssq.tnzrw }}</span>
            </div>
            <div class="mb20">
              影像学资料：
              <span class="ml40">{{ data.mzssq.yxxzl }}</span>
            </div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div>其他：</div>
          </td>
          <td class="table-value" colspan="3">
            <div>
              患者姓名、性别、年龄正确：
              <span class="ml40">{{ data.ssksq.xm_sex_age }}</span>
            </div>
            <div>
              手术方式确认：
              <span class="ml40">{{ data.ssksq.ssfsqr }}</span>
            </div>
            <div>
              手术部位与标识确认：
              <span class="ml40">{{ data.ssksq.ssbwybsqr }}</span>
            </div>
            <div>手术、麻醉风险预警：</div>
            <div>手术医师陈述：</div>
            <div class="text_indent50">
              预计手术时间
              <span class="ml40">{{ data.ssksq.ssyscs.yjsssj }}</span>
            </div>
            <div class="text_indent50">
              预计失血量
              <span class="ml40">{{ data.ssksq.ssyscs.yjsxy }}</span>
            </div>
            <div class="text_indent50">
              手术关注点
              <span class="ml40">{{ data.ssksq.ssyscs.ssgzd }}</span>
            </div>
            <div class="text_indent50">
              其它
              <span class="ml40">{{ data.ssksq.ssyscs.qt }}</span>
            </div>
            <div>麻醉医师陈述：</div>
            <div class="text_indent50">
              麻醉关注点
              <span class="ml40">{{ data.ssksq.mzyscs.mzgzd }}</span>
            </div>
            <div class="text_indent50">
              其它
              <span class="ml40">{{ data.ssksq.mzyscs.qt }}</span>
            </div>
            <div>手术护士陈述：</div>
            <div class="text_indent50">
              物品灭菌合格
              <span class="ml40">{{ data.ssksq.sshscs.wpmjhg }}</span>
            </div>
            <div class="text_indent50">
              仪器设备
              <span class="ml40">{{ data.ssksq.sshscs.yqsb }}</span>
            </div>
            <div class="text_indent50">
              术前术中特殊用药情况
              <span class="ml40">{{ data.ssksq.sshscs.sqsztsyyqk }}</span>
            </div>
            <div class="text_indent50">
              其它
              <span class="ml40">{{ data.ssksq.sshscs.qt }}</span>
            </div>
            <div class="mb20">
              是否需要相关影像资料：
              <span class="ml40">{{ data.ssksq.xgyxzl }}</span>
            </div>
            <div></div>
            <div></div>
            <div>
              其他：
              <span class="ml40">{{ data.ssksq.qt }}</span>
            </div>
          </td>
          <td class="table-value" colspan="3">
            <div>
              患者姓名、性别、年龄正确：
              <span class="ml40">{{ data.hzlkssq.xm_sex_age }}</span>
            </div>
            <div>
              实际手术方式确认：
              <span class="ml40">{{ data.hzlkssq.sjssfsqr }}</span>
            </div>
            <div>
              手术用物清点正确：
              <span class="ml40">{{ data.hzlkssq.ssywqd }}</span>
            </div>
            <div>
              手术标本确认：
              <span class="ml40">{{ data.hzlkssq.ssbb }}</span>
            </div>
            <div>
              皮肤是否完整：
              <span class="ml40">{{ data.hzlkssq.pfsfwz }}</span>
            </div>
            <div>各种管路：</div>
            <div class="text_indent50">
              中心静脉通路
              <span class="ml40">{{ data.hzlkssq.gzgl.zxjmtl }}</span>
            </div>
            <div class="text_indent50">
              动脉通路
              <span class="ml40">{{ data.hzlkssq.gzgl.dmtl }}</span>
            </div>
            <div class="text_indent50">
              气管插管
              <span class="ml40">{{ data.hzlkssq.gzgl.qgcg }}</span>
            </div>
            <div class="text_indent50">
              伤口引流
              <span class="ml40">{{ data.hzlkssq.gzgl.skyl }}</span>
            </div>
            <div class="text_indent50">
              胃管
              <span class="ml40">{{ data.hzlkssq.gzgl.wg }}</span>
            </div>
            <div class="text_indent50">
              尿管
              <span class="ml40">{{ data.hzlkssq.gzgl.ng }}</span>
            </div>
            <div class="text_indent50">
              其他
              <span class="ml40">{{ data.hzlkssq.gzgl.qt }}</span>
            </div>
            <div>患者去向：</div>
            <div class="text_indent50">
              恢复室
              <span class="ml40">{{ data.hzlkssq.hzqx.hfs }}</span>
            </div>
            <div class="text_indent50">
              病房
              <span class="ml40">{{ data.hzlkssq.hzqx.bf }}</span>
            </div>
            <div class="text_indent50">
              ICU 病房
              <span class="ml40">{{ data.hzlkssq.hzqx.icubf }}</span>
            </div>
            <div class="text_indent50">
              急诊
              <span class="ml40">{{ data.hzlkssq.hzqx.jz }}</span>
            </div>
            <div class="text_indent50 mb20">
              离院
              <span class="ml40">{{ data.hzlkssq.hzqx.ly }}</span>
            </div>
            <div>
              其他：
              <span class="ml40">{{ data.hzlkssq.qt }}</span>
            </div>
          </td>
        </tr>
        <tr>
          <td class="table-label">手术医师签名</td>
          <td class="table-value" colspan="2">{{ data.ssysqm }}</td>
          <td class="table-label">麻醉医师签名</td>
          <td class="table-value" colspan="2">{{ data.mzysqm }}</td>
          <td class="table-label">手术室护士签名</td>
          <td class="table-value" colspan="2">{{ data.ssshsqm }}</td>
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
  .ml40 {
    margin-left: 10px;
  }
  .text-blod {
    font-weight: bold;
  }
  .pd16 {
    padding: 8px 16px;
  }
  .text_left {
    text-align: left;
  }
  .minH200 {
    min-height: 200px;
  }
  .text_indent50 {
    text-indent: 50px;
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
    font-size: 14px;
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
    div {
      text-align: left;
      padding: 8px 16px;
    }
  }
}
</style>
