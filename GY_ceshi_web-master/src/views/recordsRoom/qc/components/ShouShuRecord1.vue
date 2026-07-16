<template>
  <div class="box">
    <div class="title">
      {{ data.hospital }}
      <br>
      手术风险评估表
    </div>
    <div class="table">
      <table ref="tables">
        <tr>
          <td class="table-label">科室</td>
          <td class="table-value">{{ data.keshi }}</td>
          <td class="table-label">患者姓名</td>
          <td class="table-value">{{ data.brxm }}</td>
          <td class="table-label">性别</td>
          <td class="table-value">{{ data.sex }}</td>
          <td class="table-label">年龄</td>
          <td class="table-value">{{ data.age }}</td>
        </tr>
        <tr>
          <td class="table-label">住院号</td>
          <td class="table-value">{{ data.brbh }}</td>
          <td class="table-label">术者</td>
          <td class="table-value" colspan="2">{{ data.sz }}</td>
          <td class="table-label">手术日期</td>
          <td class="table-value" colspan="2">{{ data.ssrq }}</td>
        </tr>
        <tr>
          <td class="table-label" rowspan="4">手术切口清洁程度</td>
          <td class="table-value" colspan="2">
            Ⅰ类切口
            <br>
            （清洁手术）
          </td>
          <td class="table-value text_left pd16" colspan="4">{{ data.ssqk[0].desc }}</td>
          <td class="table-value">{{ data.ssqk[0].jk }}</td>
        </tr>
        <tr>
          <td class="table-value" colspan="2">
            Ⅱ类切口
            <br>
            （清洁-污染手术）
          </td>
          <td class="table-value text_left pd16" colspan="4">
            {{ data.ssqk[1].desc }}
          </td>
          <td class="table-value">
            {{ data.ssqk[1].jk }}
          </td>
        </tr>
        <tr>
          <td class="table-value" colspan="2">
            Ⅲ类切口
            <br>
            （污染手术）
          </td>
          <td class="table-value text_left pd16" colspan="4">
            {{ data.ssqk[2].desc }}
          </td>
          <td class="table-value">{{ data.ssqk[2].jk }}</td>
        </tr>
        <tr>
          <td class="table-value" colspan="2">
            Ⅳ类切口
            <br>
            （污秽-感染手术）
          </td>
          <td class="table-value text_left pd16" colspan="4">{{ data.ssqk[3].desc }}</td>
          <td class="table-value">{{ data.ssqk[3].jk }}</td>
        </tr>

        <tr>
          <td class="table-label" rowspan="6">麻醉分级（ASA分级）麻醉医生或局麻时手术医师填写</td>
          <td class="table-value" colspan="2">ASA Ⅰ</td>
          <td class="table-value text_left pd16" colspan="4">{{ data.mzfj[0].desc }}</td>
          <td class="table-value">{{ data.mzfj[0].jk }}</td>
        </tr>
        <tr>
          <td class="table-value" colspan="2">ASA Ⅱ</td>
          <td class="table-value text_left pd16" colspan="4">{{ data.mzfj[1].desc }}</td>
          <td class="table-value">{{ data.mzfj[1].jk }}</td>
        </tr>
        <tr>
          <td class="table-value" colspan="2">ASA Ⅲ</td>
          <td class="table-value text_left pd16" colspan="4">{{ data.mzfj[2].desc }}</td>
          <td class="table-value">{{ data.mzfj[2].jk }}</td>
        </tr>

        <tr>
          <td class="table-value" colspan="2">ASA Ⅳ</td>
          <td class="table-value text_left pd16" colspan="4">{{ data.mzfj[3].desc }}</td>
          <td class="table-value">{{ data.mzfj[3].jk }}</td>
        </tr>
        <tr>
          <td class="table-value" colspan="2">ASA Ⅴ</td>
          <td class="table-value text_left pd16" colspan="4">{{ data.mzfj[4].desc }}</td>
          <td class="table-value">{{ data.mzfj[4].jk }}</td>
        </tr>
        <tr>
          <td class="table-value" colspan="2">ASA Ⅵ</td>
          <td class="table-value text_left pd16" colspan="4">{{ data.mzfj[5].desc }}</td>
          <td class="table-value">{{ data.mzfj[5].jk }}</td>
        </tr>
        <tr>
          <td class="table-label" rowspan="2">手术持续时间</td>
          <td class="table-value text_left pd16" colspan="6">{{ data.sscxsj[0].name }}</td>
          <td class="table-value">{{ data.sscxsj[0].jk }}</td>
        </tr>
        <tr>
          <td class="table-value text_left pd16" colspan="6">{{ data.sscxsj[1].name }}</td>
          <td class="table-value">{{ data.sscxsj[1].jk }}</td>
        </tr>

        <tr>
          <td class="table-label" />
          <td class="table-value pd16 text-blod" colspan="6">合计分值（以上均为单选）</td>
          <td class="table-value text-blod">{{ data.hjfz }}</td>
        </tr>

        <tr>
          <td class="table-value" colspan="2">预防使用抗菌药物</td>
          <td class="table-value" colspan="5">术前0.5-1小时应用</td>
          <td class="table-value">是口 否口</td>
        </tr>

        <tr>
          <td class="table-value" colspan="2">规范备皮</td>
          <td class="table-value" colspan="5">避免不必要备皮,术前即刻,减少损伤</td>
          <td class="table-value">是口 否口</td>
        </tr>

        <tr>
          <td class="table-value" colspan="7">围术期血糖控制正常水平</td>
          <td class="table-value">是口 否口</td>
        </tr>
        <tr>
          <td class="table-value" colspan="7">术中给患者保暖</td>
          <td class="table-value">是口 否口</td>
        </tr>

        <tr>
          <td class="table-label">切口愈合</td>
          <td class="table-value" colspan="2">甲级愈合</td>
          <td class="table-value" colspan="2">{{ data.qkyh.jk }}</td>
          <td class="table-value" colspan="2">感染</td>
          <td class="table-value">{{ data.gr.jk }}</td>
        </tr>

        <tr>
          <td class="table-label" rowspan="4">手术类别</td>
          <td class="table-value" colspan="2">浅层组织手术</td>
          <td class="table-value" colspan="4" />
          <td class="table-value">{{ data.sslb[0].jk }}</td>
        </tr>

        <tr>
          <td class="table-value" colspan="2">深部组织手术</td>
          <td class="table-value" colspan="4" />
          <td class="table-value">{{ data.sslb[1].jk }}</td>
        </tr>

        <tr>
          <td class="table-value" colspan="2">器官手术</td>
          <td class="table-value" colspan="4" />
          <td class="table-value">{{ data.sslb[2].jk }}</td>
        </tr>
        <tr>
          <td class="table-value" colspan="2">腔隙手术</td>
          <td class="table-value" colspan="4" />
          <td class="table-value">{{ data.sslb[3].jk }}</td>
        </tr>

        <tr>
          <td class="table-label">手术者签名</td>
          <td class="table-value" colspan="3">{{ data.sszqm }}</td>
          <td class="table-label">麻醉医师签名</td>
          <td class="table-value" colspan="3">{{ data.jlsj }}</td>
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
    padding: 8px 16px;
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
  }
}
</style>
