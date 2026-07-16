<template>
  <div id="MyDiv">
    <div class="header">
      <!-- <el-button type="success" v-if="!ifFile && is_active == 0" @click="funEdit">编辑</el-button> -->
      <!-- <el-button type="warning" v-if="ifFile  && is_active == 0" @click="funNext">保存</el-button> -->
      <!-- <el-button type="primary">导出详情</el-button> -->
      <el-button @click="getback">返回</el-button>
    </div>
    <div class="cont_container">
      <!-- <Mmenu class="cont-left-tiem"></Mmenu> -->
      <!-- 左侧点击列表部分 -->
      <div class="cont-left-tiem">
        <ul class="el-menu-vertical-demo el-menu">
          <li class="el-menu-item" :class=" is_active == 0?'is-active':'' " @click="clickTree(0,'病案首页')">
            <span>病案首页</span>
          </li>
          <li class="el-menu-item" :class=" is_active == item.bllb?'is-active':'' " v-for="(item,index) in treeList" :key="index" @click="clickTree(item.bllb,item.name)">
            <span>{{item.name}}</span>
          </li>
        </ul>
      </div>
      <!-- <router-view class="cont-left-file" :data="data"/> -->
      <div class="cont-left-file">
        <div v-if="is_active == 0">
           <mainHomePage :data="mainHomeData" :ifFile="ifFile"></mainHomePage>
        </div>
        <div v-else-if="is_active == 292">
           <!-- 入院记录 -->
           <admissionRecord :data="admissionRecord" :ifFile="ifFile"></admissionRecord>
        </div>
        <div v-else-if="is_active == 49">
           <!-- 医嘱 -->
           <!-- 长期期医嘱 -->
           <medicalAdvice :data="longAdvice" ></medicalAdvice>
            <!-- 临时医嘱 -->
            <!-- <medicalTemporary :data="happensAdvice" ></medicalTemporary> -->
        </div>
        <div v-else>
          <newContFile :text="text" :name='name_title' v-if="update"></newContFile>
        </div>
      </div>
      <!-- <McontReight  class="cont-reight" :titleName="titleName" :errorList="errorList"></McontReight> -->
    </div>
  </div>
</template>
  <script>
// import OtherComponent from '@/components/OtherComponent'
import Mmenu from '@/components/m-menu';
import McontReight from '@/components/m-cont-reight';
import mainHomePage from '@/views/allcase/contFile/mainHomePage'
import newContFile from '@/views/allcase/contFile/newContFile'
import admissionRecord from '@/views/allcase/contFile/admissionRecord'
import medicalAdvice from '@/views/allcase/contFile/medicalAdvice'
import medicalTemporary from '@/views/allcase/contFile/medicalTemporary'

export default {
  components: {
    // OtherComponent
    Mmenu,McontReight,mainHomePage,newContFile,admissionRecord,medicalAdvice,medicalTemporary
  },
  directives: {},
  filters: {},
  extends: {},
  mixins: {},
  props: {},
  data() {
    return {
      mainHomeData: {},
      admissionRecord:{},
      longAdvice:{},
      happensAdvice:{},
      errorList: [],
      valData: '',
      valAAA28:'',
      score: 0,
      ifFile: false,
      treeList:[],
      is_active: 0,
      name_title:'',
      text:'',
      update:true,
      titleName:'病案首页'
    };
  },
  computed: {},
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
    // this.valData = this.storageGet('getData');
    this.valData = this.storageGet('getData');

    console.log(this.storageGet('getData'));

    if(this.valData){
      this.funQuery();
    }

    // 生命周期钩子：模板编译、挂载之后(此时不保证已在 document 中)
    this.getTree();
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
    reload() {
      // 移除组件
      this.update = false
      // 在组件移除后，重新渲染组件
      // this.$nextTick可实现在DOM 状态更新后，执行传入的方法。
      this.$nextTick(() => {
          this.update = true
      })
    },
    funNext() {
      this.$axios.post('/medicalRecordEdit', this.mainHomeData).then(res => {
        console.log(res);
        this.$message('修改成功');
        this.funQuery();
        this.ifFile = false;
      });
      // this.$message('errer:功能待开发')
    },
    funEdit() {
      this.ifFile = true;
      this.$message('errer:功能待开发')
    },
    getback() {
      this.$router.go(-1);
    },
    /**
     * 跳转对应病历首页
     */
    getBlankIndex(item) {
      let eleClass = document.querySelectorAll('.choose-twinkle');
      console.log(eleClass);
      // const tb = this.$refs.tables.value;
      console.log(item.error_field);
      // this.$refs[item.error_field]
      console.log(this.$refs[item.error_field]);
      for (let item = 0; item < eleClass.length; item++) {
        console.log(eleClass[item]);
        eleClass[item].className = 'table-label';
      }
      // return;
      // document
      // this.$refs[item.error_field].classList.value + ' choose-twinkle';
      this.$refs[item.error_field].className = 'choose-twinkle';
      this.$refs[item.error_field].scrollIntoView({ block: 'start', behavior: 'smooth' });
      //   .getElementById("agentTitle")
      //   .scrollIntoView({ block: "start", behavior: "smooth" });
      // 跳转到指定位置并且平滑滚动
      // this.$el.querySelector('.table-labelon1').scrollIntoView({ behavior: 'smooth' });
      // this.$el.querySelector('.table-labelon1').style.color = 'red';
    },

    funQuery() {
      let pramse = {
        id: this.valData,
      };
      console.log(this.valData)
      this.$axios.post('/medical_record', pramse).then(res => {
        console.log(res);
        this.mainHomeData = res.data;
        this.score = res.data.score;
      });
      this.$axios.post('/get_case',{
        id:  this.valData,
        // id: '00200287',
      }).then(res => {
        console.log(res.data)
        console.log('__________')
        this.errorList = res.data;
      });
    },

    getTree(){
      let that = this;
      let pramse = {
        id: this.valData,
      };
      this.$axios.post('/getTree', pramse).then(res => {
        that.treeList = res.data;
      });
    },
    clickTree(b,n){
      this.titleName = n
      let that = this;
      that.is_active = b;
      console.log('is_active',that.is_active)
      that.name_title = n;
      if(b != 0){
        that.$axios.post('/getAllCase',{
          MED_REC_ID:  that.valData,
          bllb: b
        }).then(res => {
          that.text = res.data.text;
          that.reload();
        });
        that.$axios.post('/get_case',{
          id: this.valData,
          bllb: b
        }).then(res => {
          this.errorList = res.data;
        });
      }
      if(b == 292 ){ // 入院记录
        that.$axios2.post('/get_case_platform',{
          id: this.valData,
          // id: 642461,
          bllb: b
        }).then(res => {
         this.admissionRecord = res.data
        });
      }
      if(b == 49 ){ // 长期医嘱
        that.$axios.post('/long',{
          AAA28:  that.valData,
        }).then(res => {

         this.longAdvice = res.data
        });
        that.$axios.post('/temporary',{
          AAA28:  that.valData,
        }).then(res => {

         this.happensAdvice = res.data
        });
      }
    }
  },
};
</script>
<style lang="scss" scoped>
#MyDiv {
  margin: 0;
  padding: 0 !important;
}
.header {
  margin: 10px 20px;
  text-align: right;
  display: flex;
  justify-content: flex-end;
}
.cont_container {
  //   margin-top: 60px;
  display: flex;
  justify-content: center;
  //   flex-direction: column;
  //   align-items: center;
}
.cont-left-tiem {
  // padding: 10px;
  width: 180px;
  margin: 0 0 0 15px;
  min-height: 650px;
  height: 800px;
  overflow-y: scroll;
  background: #ffffff;
}
.cont-left-file {
  flex: 1;
  min-height: 650px;
  margin: 0 5px;
  height: 800px;
  overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  padding: 10px;
}
.cont-reight {
  width: 220px;
  min-height: 650px;
  height: 800px;
  overflow-y: scroll;
  background: #ffffff;
  border: 1px solid #e2e2e2;
  margin: 0 15px 0 0;
}
.li-router{
  display: inline-block;
  width: 100%;
  height: 100%;
}
</style>
<style>
/*  ================================== 文本形式样式 ↓ ======================== */
  .refachInput span{
    height: auto;
    line-height: 1;
    padding: 10px 0;
  }
  .refachInput span.refachInput-text{
    padding-left: 12px;
  }
  .el-row--flex.is-justify-space-around{
    justify-content: flex-start;
  }
  .member-infobox{
    width: 100%;
  }
  .member-infobox .info-box-1{
    /* display: flex;
    flex-wrap: wrap; */
    margin-top: 20px;
  }
  .infoBox-items{
    width: auto;
    display: flex;
    align-items: center;
    padding: 8px 0;
  }
  .padding-left20{
    padding-left: 20px;
  }
  .padding-right20{
    padding-right: 20px;
  }
  .infoBox-title{
    color: #333;
    font-size: 12px;
    font-weight: bold;
  }
  .infoBox-items-text{
    color: #666;
    font-size: 12px;
    padding-left: 5px;
    padding-right: 20px;
  }
  .title-ff0000{
    color: #ff0000;
  }

/* ================================== 文本形式样式 ↑ ======================== */
</style>
