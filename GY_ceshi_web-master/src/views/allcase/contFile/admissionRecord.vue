<template>
    <!-- 入院记录 -->
    <div id="MyDivA" >
        <div class="admrec-cont">
          <div class="cont-title-description">入院记录</div>
          
          <div class="admrec-cont-header">
              <div class="admrec-cont-header-list">
                <span class="cont-textbold">科室:</span>
                <span>{{data.department}}</span>
              </div>
              <div class="admrec-cont-header-list">
                <span  class="cont-textbold">姓名:</span>
                <span v-if="data.name">{{data.name.value}}</span>
              </div>
              <div class="admrec-cont-header-list">
                <span  class="cont-textbold">床号:</span>
                <span v-if="data.bed_no">{{data.bed_no.value}}</span>
              </div>
              <div class="admrec-cont-header-list">
                <span  class="cont-textbold">住院号码:</span>
                <span v-if="data.hospital_no">{{data.hospital_no.value}}</span>
              </div>
          </div>
          <div class="admrec-cont-infor">
            <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">姓名:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.name">{{data.name.value}}</span> </div>
            <div class="cont-textbold">出生地:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.local_address">{{data.local_address.value}}</span></div>
          </div>
          <div class="admrec-cont-infor">
            <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">性别:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.sex">{{data.sex.value}}</span></div>
            <div class="cont-textbold">职业:</div>
            <div class="admrec-cont-infor-list" ><span v-if="data.job">{{data.job.value}}</span></div>
          </div>
          <div class="admrec-cont-infor">
            <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">年龄:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.age">{{data.age.value}}</span></div>
            <div class="cont-textbold">入院时间: </div>
            <div class="admrec-cont-infor-list"><span v-if="data.ry_time">{{ data.ry_time.value}}</span> </div>
          </div>
          <div class="admrec-cont-infor">
            <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">民族:</div>
            <div class="admrec-cont-infor-list"> <span v-if="data.nation">{{data.nation.value}}</span> </div>
            <div class="cont-textbold">记录时间: </div>
            <div class="admrec-cont-infor-list"> <span v-if="data.record_time">{{ data.record_time.value}}</span> </div>
          </div>
          <div class="admrec-cont-infor">
            <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">婚姻:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.marriage">{{data.marriage.value}}</span></div>
            <div class="cont-textbold">病史陈述者:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.narrator">{{data.narrator.value}}</span> </div>
          </div>

          <div class="admrec-cont-chief admrec-cont-mg20">
            <div class="admrec-cont-chief-title ">主诉:<span class="admrec-cont-textnor" v-if="data.zhusu">{{data.zhusu.value}}</span></div>
          </div>
          <div class="admrec-cont-chief admrec-cont-mg20">
            <div class="admrec-cont-chief-title ">现病史:<span class="admrec-cont-textnor" v-if="data.xianbingshi">{{data.xianbingshi.value}}</span></div>
          </div>
          <div class="admrec-cont-chief admrec-cont-mg20">
            <div class="admrec-cont-chief-title ">既往史:<span class="admrec-cont-textnor" v-if="data.jiwangshi">{{data.jiwangshi.value}}</span></div>
          </div>
          <div class="admrec-cont-chief admrec-cont-mg20">
            <div class="admrec-cont-chief-title">个人史:<span class="admrec-cont-textnor" v-if="data.gerenshi">{{data.gerenshi.value}}</span></div>
          </div>
          <div class="admrec-cont-chief admrec-cont-mg20">
            <div class="admrec-cont-chief-title" v-if="data.sex&&data.sex.value == '女\r'">月经及婚育史:<span class="admrec-cont-textnor" v-if="data.yjjhys">{{data.yjjhys.value}}</span></div>
            <div class="admrec-cont-chief-title" v-else>婚育史:<span class="admrec-cont-textnor" v-if="data.hys">{{data.hys.value}}</span></div>
          </div>
          <div class="admrec-cont-chief admrec-cont-mg20">
            <div class="admrec-cont-chief-title">家族史:<span class="admrec-cont-textnor" v-if="data.jzs">{{data.jzs.value}}</span></div>
          </div>
          <div class="admrec-cont-sign">
            经核对，以上病史属实，请病人或家属签字确认：      <span class="admrec-cont-sign-pat">(患者签名)</span>
          </div>
          <div class="admrec-cont-physique">体格检查</div>
          <!-- <div class="admrec-cont-physique-cont">T36.5 P105次/分 R20次/分 BP139/70mmHg</div> -->
          <div class="admrec-cont-physique-desc">
            <span v-if="data.tgjc" v-html="data.tgjc.value">
            </span>
          </div>
          <div class="admrec-cont-physique">辅助检查</div>
          <!-- <el-table
            :data="fuzhujianchaData"
            border
            style="width: 100%">
            <el-table-column
              prop="time"
              label="检查时间">
            </el-table-column>
            <el-table-column
              prop="project"
              label="检查项目">
            </el-table-column>
            <el-table-column
              prop="result"
              label="检查结果">
            </el-table-column>
            <el-table-column
              prop="org"
              label="检查医院">
            </el-table-column>
          </el-table> -->
          <div class="admrec-cont-physique-desc"><span v-if="data.fzjc">{{data.fzjc.value}}</span> </div>
          <div class="admrec-cont-prel admrec-cont-mg20 cont-textbold">
            初步诊断：
          </div>
          <div class="admrec-cont-prel-text"   v-for="(item,index) in data.diagnose_list" :key="index">{{item}}</div>
          <div class="admrec-cont-physing"><span class="cont-textbold">医师签名:</span>{{data.doctor_name}}</div>
          <div class="admrec-bottom-time">
              <div class="admrec-bottom-time-list">
                <span class="cont-textbold">创建时间:</span>
                <span v-if="data.CJSJ">{{data.CJSJ}}</span>
              </div>
              <div class="admrec-bottom-time-list">
                <span  class="cont-textbold">首次签名时间:</span>
                <span v-if="data.ZXSJ">{{data.ZXSJ}}</span>
              </div>
          </div>
          <div class="admrec-bottom-time">   
              <div class="admrec-bottom-time-list">
                <span  class="cont-textbold">末次修改时间:</span>
                <span v-if="data.WCSJ">{{data.WCSJ}}</span>
              </div>
              <div class="bottom-time-list">
                <span class="cont-textbold">业务时间:</span>
                <span v-if="data.YWSJ">{{ data.YWSJ }}</span>
             </div>
          </div>
          
        </div>
    </div>
</template>
  <script>
export default {
  name: '',
  components: {
    // OtherComponent
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {
    data: {
      type: null,
      default: false,
    },
  },
  data() {
    return {
      ifFile: false,
    };
  },
  computed: {
    // fuzhujianchaData() {
    //   const arr = []
    //   const length = this.data.fzjc.value.length
    //   this.data.fzjc.value.slice(1, length).map(item => {
    //     const obj = {
    //       time: item[0],
    //       project: item[1],
    //       result: item[2],
    //       org: item[3]
    //     }
    //     arr.push(obj)
    //   })
    //   return arr
    // }
  },
  watch: {},
  beforeCreate() {
    // 生命周期钩子：组件实例刚被创建，组件属性计算之前，如 data 属性等
  },
  created() {
    // 生命周期钩子：组件实例创建完成，属性已绑定，但 DOM 还未生成，el 属性还不存在
    // 初始化渲染页面
  },
  beforeMount() {
    // 生命周期钩子：模板编译/挂载之前
  },
  mounted() {
    // 生命周期钩子：模板编译、挂载之后（此时不保证已在 document 中）
  },
  beforeUpate() {
    // 生命周期钩子：组件更新之前
  },
  updated() {
    // 生命周期钩子：组件更新之后
  },
  activated() {
    // 生命周期钩子：keep-alive 组件激活时调用
  },
  deactivated() {
    // 生命周期钩子：keep-alive 组件停用时调用
  },
  beforeDestroy() {
    // 生命周期钩子：实例销毁前调用
  },
  destroyed() {
    // 生命周期钩子：实例销毁后调用
  },
  errorCaptured(err, vm, info) {
    // 生命周期钩子：当捕获一个来自子孙组件的错误时被调用。此钩子会收到三个参数：错误对象、发生错误的组件实例以及一个包含错误来源信息的字符串。
    console.log(err, vm, info);
  },
  methods: {},
};
</script>
  <style lang='scss' scoped>
  #MyDivA {
    padding: 0;
    margin:0 30px;
    .admrec-cont{
      width: 100%;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: center;
      .cont-title-description {
        margin: 20px;
        font-size: 24px;
        font-weight: bold;
        color: #2c3240;
        text-align: center;
      }
      .cont-textbold{
        font-weight: bold;
      }
      .admrec-cont-header{
        height: 50px;
        width: 100%;
        border-bottom: 1.5px solid #e2dfdf;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        margin-bottom: 20px;
        .admrec-cont-header-list{
          width: 25%;
        }

      }
      .admrec-cont-header-top{

        width: 100%;
        display: flex;
        justify-content: flex-start;
        align-items: center;
        margin-top: 20px;
        .admrec-cont-header-list{
          width: 25%;
        }

      }
      .admrec-cont-infor{
        width: 100%;
        height: 40px;
        display: flex;
        align-items: center;
        .admrec-cont-infor-list{
          width: 25%;
          padding-left: 5px;
        }
        .admrec-cont-infor-text{
          text-align: right;
        }
      }
      .admrec-cont-chief{
        width: 100%;
        .admrec-cont-chief-title{
          font-weight: bold;
        }
      }
      .admrec-cont-mg20{
        margin-top: 20px;
      }
      .admrec-cont-sign{
        width: 100%;
        height: 30px;
        margin-top: 30px;
        padding-left: 10%;
        .admrec-cont-sign-pat{
          margin-left: 100px;
        }
      }
      .admrec-cont-physique{
        width: 100%;
        height: 30px;
        text-align: center;
        margin-top: 30px;
        font-weight: bold;
      }
      .admrec-cont-physique-cont{
        width: 100%;
        text-align: center;
        line-height: 20px;
      }
      .admrec-cont-physique-desc{
        width: 100%;
        text-align: left;
        line-height: 20px;
        text-indent: 30px;
      }
      .admrec-cont-auxi{
        width: 100%;
        min-height: 30px;
        display: flex;
        .admrec-cont-auxi-list{
          width: 25%;
          text-align: left;
        }
      }
      .admrec-cont-prel{
        width: 100%;
        padding-left: 50%;
      }
      .admrec-cont-prel-text{
        width: 100%;
        padding-left: 58%;
        line-height: 20px;
      }
      .admrec-cont-physing{
        width: 100%;
        padding-left: 65%;
        margin-top: 20px;
        margin-bottom: 30px;
      }
      .admrec-cont-textnor{
        font-weight: normal;
      }
    }
  }
  .admrec-bottom-time{
      width: 100%;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-bottom: 20px;
      .admrec-bottom-time-list{
        width: 50%;
      }

  }




</style>
