<template>
  <!-- 入院记录 -->
  <div id="MyDivA">
    <div style="margin-left: 20px">
      <!-- <el-row>
        <el-button type="primary" @click="funEdit()">编辑</el-button>
        <el-button type="primary" @click="funSave()">保存</el-button>
      </el-row> -->
    </div>
    <div class="admrec-cont">
      <div class="cont-title-description">入院记录</div>

      <!-- <div class="admrec-cont-header">
              <div class="admrec-cont-header-list">
                <span class="cont-textbold">科室:</span>
                <span v-html="data.department"></span>
              </div>
              <div class="admrec-cont-header-list">
                <span  class="cont-textbold">姓名:</span>
                <span v-if="data.name" v-html="data.name.value"></span>
              </div>
              <div class="admrec-cont-header-list">
                <span  class="cont-textbold">床号:</span>
                <span v-if="data.bed_no" v-html="data.bed_no.value"></span>
              </div>
              <div class="admrec-cont-header-list">
                <span  class="cont-textbold">住院号码:</span>
                <span v-if="data.hospital_no" v-html="data.hospital_no.value"></span>
              </div>
          </div> -->
      <div class="admrec-cont-infor">
        <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">姓名:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.name" v-html="data.name.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.name" v-model="data.name.value"></el-input></div>
        <div class="cont-textbold">出生地:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.local_address" v-html="data.local_address.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.local_address" v-model="data.local_address.value"></el-input></div>
      </div>
      <div class="admrec-cont-infor">
        <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">性别:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.sex" v-html="data.sex.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.sex" v-model="data.sex.value"></el-input></div>
        <div class="cont-textbold">职业:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.job" v-html="data.job.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.job" v-model="data.job.value"></el-input></div>
      </div>
      <!-- <div class="admrec-cont-infor">
            <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">年龄:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.age" v-html="data.age.value"></span></div>
            <div class="cont-textbold">入院时间: </div>
            <div class="admrec-cont-infor-list"><span v-if="data.ry_time" v-html="data.ry_time.value"></span> </div>
          </div>
          <div class="admrec-cont-infor">
            <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">民族:</div>
            <div class="admrec-cont-infor-list"> <span v-if="data.nation" v-html="data.nation.value"></span> </div>
            <div class="cont-textbold">记录时间: </div>
            <div class="admrec-cont-infor-list"> <span v-if="data.record_time" v-html="data.record_time.value"></span> </div>
          </div>
          <div class="admrec-cont-infor">
            <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">婚姻:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.marriage" v-html="data.marriage.value"></span></div>
            <div class="cont-textbold">病史陈述者:</div>
            <div class="admrec-cont-infor-list"><span v-if="data.narrator" v-html="data.narrator.value"></span> </div>
          </div> -->
      <div class="admrec-cont-infor">
        <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">年龄:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.age" v-html="data.age.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.age" v-model="data.age.value"></el-input></div>
        <div class="cont-textbold">入院时间:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.ry_time" v-html="data.ry_time.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.ry_time" v-model="data.ry_time.value"></el-input></div>
      </div>
      <div class="admrec-cont-infor">
        <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">民族:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.nation" v-html="data.nation.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.nation" v-model="data.nation.value"></el-input></div>
        <div class="cont-textbold">记录时间:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.record_time" v-html="data.record_time.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.record_time" v-model="data.record_time.value"></el-input></div>
      </div>
      <div class="admrec-cont-infor">
        <div class="admrec-cont-infor-list admrec-cont-infor-text cont-textbold">婚姻:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.marriage" v-html="data.marriage.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.marriage" v-model="data.marriage.value"></el-input></div>
        <div class="cont-textbold">病史陈述者:</div>
        <div v-if="!isEdit" class="admrec-cont-infor-list"><span v-if="data.narrator" v-html="data.narrator.value"></span></div>
        <div v-if="isEdit" class="admrec-cont-infor-list"><el-input v-if="data.narrator" v-model="data.narrator.value"></el-input></div>
      </div>

      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          <div v-if="!isEdit">
            主诉:
            <span class="admrec-cont-textnor" v-if="data.zhusu" v-html="data.zhusu.value"></span>
          </div>
          <div v-if="isEdit">
            主诉:
            <el-input type="textarea" :autosize="{ minRows: 2, maxRows: 15 }" class="admrec-cont-textnor" v-if="data.zhusu" v-model="data.zhusu.value"></el-input>
          </div>
        </div>
      </div>
      <!-- <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          现病史:
          <span class="admrec-cont-textnor" v-if="data.xianbingshi" v-html="data.xianbingshi.value"></span>
        </div>
      </div>
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          既往史:
          <span class="admrec-cont-textnor" v-if="data.jiwangshi" v-html="data.jiwangshi.value"></span>
        </div>
      </div>
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          个人史:
          <span class="admrec-cont-textnor" v-if="data.gerenshi" v-html="data.gerenshi.value"></span>
        </div>
      </div>
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          婚育史:
          <span class="admrec-cont-textnor" v-if="data.hys" v-html="data.hys.value"></span>
        </div>
      </div> -->
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          <div v-if="!isEdit">
            现病史:
            <span class="admrec-cont-textnor" v-if="data.xianbingshi" v-html="data.xianbingshi.value"></span>
          </div>
          <div v-if="isEdit">
            现病史:
            <el-input type="textarea" :autosize="{ minRows: 2, maxRows: 15 }" class="admrec-cont-textnor" v-if="data.xianbingshi" v-model="data.xianbingshi.value"></el-input>
          </div>
        </div>
      </div>
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          <div v-if="!isEdit">
            既往史:
            <span class="admrec-cont-textnor" v-if="data.jiwangshi" v-html="data.jiwangshi.value"></span>
          </div>
          <div v-if="isEdit">
            既往史:
            <el-input type="textarea" :autosize="{ minRows: 2, maxRows: 15 }" class="admrec-cont-textnor" v-if="data.jiwangshi" v-model="data.jiwangshi.value"></el-input>
          </div>
        </div>
      </div>
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          <div v-if="!isEdit">
            个人史:
            <span class="admrec-cont-textnor" v-if="data.gerenshi" v-html="data.gerenshi.value"></span>
          </div>
          <div v-if="isEdit">
            个人史:
            <el-input type="textarea" :autosize="{ minRows: 2, maxRows: 15 }" class="admrec-cont-textnor" v-if="data.gerenshi" v-model="data.gerenshi.value"></el-input>
          </div>
        </div>
      </div>
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          <div v-if="!isEdit">
            婚育史:
            <span class="admrec-cont-textnor" v-if="data.hys" v-html="data.hys.value"></span>
          </div>
          <div v-if="isEdit">
            婚育史:
            <el-input type="textarea" :autosize="{ minRows: 2, maxRows: 15 }" class="admrec-cont-textnor" v-if="data.hys" v-model="data.hys.value"></el-input>
          </div>
        </div>
      </div>
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title" v-if="data.sex &&  data.sex.value.includes('女')">
          <el-row v-if="data.yjjhys_format && Array.isArray(data.yjjhys_format.value) && !!data.yjjhys_format.value.length" type="flex" align="middle">
            <span>月经生育史:</span>
            <el-row class="admrec-cont-textnor" type="flex" align="middle">
              <span v-if="!isEdit">{{ data.yjjhys_format.value[0] }}</span>
              <el-input style="width: 50px" v-if="isEdit" v-model="data.yjjhys_format.value[0]"></el-input>
              <el-row type="flex" style="flex-direction: column; text-align: center; margin: 0 5px">
                <span v-if="!isEdit">{{ data.yjjhys_format.value[1] }}</span>
                <el-input style="width: 60px" v-if="isEdit" v-model="data.yjjhys_format.value[1]"></el-input>
                <div style="height: 1px; margin: 5px 0; background: #000" />
                <span v-if="!isEdit">{{ data.yjjhys_format.value[2] }}</span>
                <el-input style="width: 60px" v-if="isEdit" v-model="data.yjjhys_format.value[2]"></el-input>
              </el-row>
              <span v-if="data.yjjhys_format.value[3] && !isEdit">
                {{ data.yjjhys_format.value[3] }}
              </span>
              <el-input style="width: 60px" v-if="data.yjjhys_format.value[3] && isEdit" v-model="data.yjjhys_format.value[3]"></el-input>
              <span v-else>
                <span>已绝经</span>
                <span v-if="!isEdit">{{ data.yjjhys_format.value[4] }}</span>
                <el-input style="width: 50px" v-if="isEdit" v-model="data.yjjhys_format.value[4]"></el-input>
              </span>
            </el-row>
          </el-row>
          <span v-else>
            月经生育史:
            <span class="admrec-cont-textnor" v-if="data.yjjhys && !isEdit" v-html="data.yjjhys.value"></span>
            <el-input v-if="isEdit" v-model="data.yjjhys.value"></el-input>
          </span>
        </div>
      </div>
      <!-- <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          家族史:
          <span class="admrec-cont-textnor" v-if="data.jzs" v-html="data.jzs.value"></span>
        </div>
      </div> -->
      <div class="admrec-cont-chief admrec-cont-mg20">
        <div class="admrec-cont-chief-title">
          <div v-if="!isEdit">
            家族史:
            <span class="admrec-cont-textnor" v-if="data.jzs" v-html="data.jzs.value"></span>
          </div>
          <div v-if="isEdit">
            家族史:
            <el-input type="textarea" :autosize="{ minRows: 2, maxRows: 15 }" class="admrec-cont-textnor" v-if="data.jzs" v-model="data.jzs.value"></el-input>
          </div>
        </div>
      </div>
      <!-- <div class="admrec-cont-sign">
            经核对，以上病史属实，请病人或家属签字确认：      <span class="admrec-cont-sign-pat">(患者签名)</span>
          </div> -->
      <div class="admrec-cont-physique">体格检查</div>
      <!-- <div class="admrec-cont-physique-cont">T36.5 P105次/分 R20次/分 BP139/70mmHg</div> -->
      <div v-if="!isEdit" class="admrec-cont-physique-desc">
        <span v-if="data.tgjc" v-html="data.tgjc.value"></span>
      </div>
      <div v-if="isEdit" class="admrec-cont-chief admrec-cont-mg5">
        <div><el-input type="textarea" :autosize="{ minRows: 2, maxRows: 15 }" v-if="data.tgjc" v-model="data.tgjc.value"></el-input></div>
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
      <div v-if="!isEdit" class="admrec-cont-physique-desc">
        <span v-if="data.fzjc">{{ data.fzjc.value }}</span>
      </div>

      <div v-if="isEdit" class="admrec-cont-chief admrec-cont-mg5">
        <div><el-input type="textarea" :autosize="{ minRows: 2, maxRows: 15 }" v-if="data.fzjc" v-model="data.fzjc.value"></el-input></div>
      </div>
      <!-- <div class="admrec-cont-prel admrec-cont-mg20 cont-textbold">
            初步诊断：
          </div> -->

      <div v-if="!isEdit" style="width: 100%; margin-left: 10px; margin-top: 20px; margin-bottom: 30px">
        <div style="margin-top: 30px; padding-left: 0" v-for="(item, index) in data.diagnose_list" :key="index">{{ item }}</div>
      </div>

      <div v-if="isEdit" style="width: 100%; margin-left: 10px; margin-top: 10px; margin-bottom: 10px">
        <el-form :model="dynamicValidateForm">
          <el-form-item v-for="(diagnose, index) in data.diagnose_list" :key="index">
            <el-row :gutter="10">
              <el-col :span="18">
                <el-input size="mini" v-model="data.diagnose_list[index]"></el-input>
              </el-col>
              <el-col :span="2">
                <el-button size="mini" icon="el-icon-minus" :disabled="data.diagnose_list.length <= 1" @click="removeDiagnose(diagnose, index)"></el-button>
              </el-col>
              <el-col :span="1">
                <el-button size="mini" icon="el-icon-plus" @click="addDiagnose(diagnose, index)"></el-button>
              </el-col>
            </el-row>
          </el-form-item>
        </el-form>
      </div>
      <div class="admrec-cont-physing">
        <div v-if="!isEdit">
          <span class="cont-textbold">医师签名:</span>
          <span v-html="data.doctor_name"></span>
        </div>
        <div v-if="isEdit">
          <span class="cont-textbold">医师签名:</span>
          <el-input v-model="data.doctor_name"></el-input>
        </div>
      </div>
      <div class="admrec-bottom-time">
        <div class="admrec-bottom-time-list">
          <span class="cont-textbold">创建时间:</span>
          <span v-if="data.CJSJ" v-html="data.CJSJ"></span>
        </div>
        <div class="admrec-bottom-time-list">
          <span class="cont-textbold">首次签名时间:</span>
          <span v-if="data.ZXSJ" v-html="data.ZXSJ"></span>
        </div>
      </div>
      <div class="admrec-bottom-time">
        <div class="admrec-bottom-time-list">
          <span class="cont-textbold">末次修改时间:</span>
          <span v-if="data.WCSJ" v-html="data.WCSJ"></span>
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
import { data } from 'autoprefixer';

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
      isEdit: false,
      diagnose: '',
      dynamicValidateForm: {},
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
  watch: {
    data(val) {
      console.log(val);
    },
  },
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
  methods: {
    funEdit() {
      this.isEdit = true;
    },
    funSave() {
      this.isEdit = false;
      console.log('保存', this.data);
      this.$axios.post('/bazb/edit_hjnr', this.data).then(res => {
        if (res.code == 200) {
          this.$message({
            message: `编辑成功`,
            type: 'success',
          });
        } 
      });
    },

    removeDiagnose(diagnose, index) {
      console.log('删除', diagnose, index);
      if (this.data.diagnose_list.length > 1) {
        this.data.diagnose_list.splice(index, 1);
      } else {
        this.$message.warning('至少保留一个诊断项');
      }
    },
    addDiagnose(diagnose, index) {
      console.log('添加', diagnose, index);
      this.data.diagnose_list.splice(index + 1, 0, '');
    },
  },
};
</script>
  <style lang='scss' scoped>
#MyDivA {
  padding: 0;
  margin: 0 30px;
  .admrec-cont {
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
    .cont-textbold {
      font-weight: bold;
    }
    .admrec-cont-header {
      height: 50px;
      width: 100%;
      border-bottom: 1.5px solid #e2dfdf;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
      margin-bottom: 20px;
      .admrec-cont-header-list {
        width: 25%;
      }
    }
    .admrec-cont-header-top {
      width: 100%;
      display: flex;
      justify-content: flex-start;
      align-items: center;
      margin-top: 20px;
      .admrec-cont-header-list {
        width: 25%;
      }
    }
    .admrec-cont-infor {
      width: 100%;
      height: 40px;
      display: flex;
      align-items: center;
      .admrec-cont-infor-list {
        width: 25%;
        padding-left: 5px;
      }
      .admrec-cont-infor-text {
        text-align: right;
      }
    }
    .admrec-cont-chief {
      width: 100%;
      .admrec-cont-chief-title {
        font-weight: bold;
      }
    }
    .admrec-cont-mg20 {
      margin-top: 20px;
    }
    .admrec-cont-mg5 {
      margin-top: 5px;
    }
    .admrec-cont-sign {
      width: 100%;
      height: 30px;
      margin-top: 30px;
      padding-left: 10%;
      .admrec-cont-sign-pat {
        margin-left: 100px;
      }
    }
    .admrec-cont-physique {
      width: 100%;
      height: 30px;
      text-align: center;
      margin-top: 30px;
      font-weight: bold;
    }
    .admrec-cont-physique-cont {
      width: 100%;
      text-align: center;
      line-height: 20px;
    }
    .admrec-cont-physique-desc {
      width: 100%;
      text-align: left;
      line-height: 20px;
      text-indent: 30px;
    }
    .admrec-cont-auxi {
      width: 100%;
      min-height: 30px;
      display: flex;
      .admrec-cont-auxi-list {
        width: 25%;
        text-align: left;
      }
    }
    .admrec-cont-prel {
      width: 100%;
      padding-left: 50%;
    }
    .admrec-cont-prel-text {
      width: 100%;
      padding-left: 58%;
      line-height: 20px;
    }
    .admrec-cont-physing {
      width: 100%;
      padding-left: 65%;
      margin-top: 20px;
      margin-bottom: 30px;
    }
    .admrec-cont-textnor {
      font-weight: normal;
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

::v-deep .el-textarea {
  // align-items: center;
  // margin-left: 10px;
}

::v-deep .el-form-item  {
  margin-bottom: 0px;
}
</style>
