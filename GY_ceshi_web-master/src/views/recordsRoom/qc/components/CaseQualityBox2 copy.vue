<template>
  <div>

    <div ref="box" class="box22" :style="{width: width ? width + 'px' : '100%'}">

      <div class="score-box" :class="scoreLevel == '甲'? 'scoreLevel_1' : ( scoreLevel == '乙'?'scoreLevel_2': (scoreLevel == '丙'?'scoreLevel_3':'' ) ) ">
        <div>病案评分<span class="score-f">{{ data.score  }}分</span></div>
        <!-- <h2 class="score-dj">{{ scoreLevel }}</h2> -->
      </div>
      <el-scrollbar ref="scrollRef" class="scrollBox" :style="`height: ${scrollHeight};padding-bottom:60px;`">
        <template v-for="(item, index) in tableData">
          <div class="list-box box-card" :key="index" v-if="is_show && !item.appeal_status && item.is_appeal == 1">
            <div class="list-score-tips-box">
              <div class="list-left-score" :class=" item.level == 1 ? 'hover-1' : 'hover-2' ">
                <div> {{ item.level == 1? '必改':'建议' }} </div>
                <div>-{{ item.score }}</div>
              </div>
              <div class="list-right-tips">
                <div><span class="font-size12 title-color">字段：</span><span class="font-size12">{{ item.category }}</span></div>
                <div class="notice-box"><span class="font-size12 title-color">提示：</span><span class="font-size12">{{ item.notice }}</span></div>
              </div>
            </div>
            <div class="list-basis-box">
              <div class="list-basis-title" @click="clickListItem(index)">
                <span>质控依据</span>
                <span> >> </span>
                <el-image class="typeImg" v-if="item.is_ai" :src="require('../../../../assets/images/jiqiren.png')" fit="contain">
                </el-image>
                <el-image v-else class="typeImg" :src="require('../../../../assets/images/kefu.png')" fit="contain">
                </el-image>
              </div>
              <div class="list-basis-text">
                <div class="list-basis-text-t" :class="item.show?'show':''">
                  <div v-for="(yItem, yIndex) of item.basis" :key="yIndex" style="margin-bottom: 10px;">
                    <div>
                      <span class="span-index">{{ yIndex+1 }}</span>
                      <span>
                        <span v-for="(cItem, cIndex) of yItem" :key="cIndex" style="font-size: 12px;">{{ cItem }}</span>
                      </span>
                    </div>
                  </div>
                </div>
                
                <div class="list-basis-bottom-box">
                  <div class="list-basis-bottom-tips">
                    <el-tooltip class="appeal-status-box" effect="dark" :content="item.reject_content" placement="top">
                      <el-button type="primary" class="appeal-status-1" v-if="item.appeal_status == 1">通过</el-button>
                      <el-button type="danger" class="appeal-status-2"  v-if="item.appeal_status == 2">驳回</el-button>
                    </el-tooltip>
                  </div>
                  <div class="list-basis-bottom-btn">
                    <el-button type="primary" @click="clickAppeal( item,index,1 )">通过</el-button>
                    <el-button @click="clickAppeal( item,index,2 )">驳回</el-button>
                  </div>
                </div>
              </div>
              
            </div>
            
          </div>
        </template>
      </el-scrollbar>
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
            data: {}
          }
        }
      },
      type:{
        type: String,
        default() {
          return ''
        }
      },
      height: {
        type: Number,
        default() {
          return 0
        }
      },
      width: {
        type: Number,
        default() {
          return 0
        }
      }
    },
    data(){
      return {
        tableData: [],
        is_show: true,
        appeal_document: '',
        appeal_docter: ''
      }
    },
    computed: {
      scoreLevel() {
        /**
         * 甲＞90分
         * 乙75-90分
         * 丙＜75分
         * */ 
        let str
        const { score } = this.data
        console.log(score)
        if (score > 90) {
          str = '甲'
        } else if (score < 75) {
          str = '丙'
        } else if ( score <= 90 && score >= 75 ) {
          str = '乙'
        }
        return str
      },
      scrollHeight() {
        // if (this.height) {
        //   return (this.height - 214)+'px'
        // } else {
        //   return `calc(100vh - 314px)`
        // }
        return `100%`
      }
    },
    created(){
      this.getTableData();
    },
    methods: {
      editSubmit(e){
        let that = this;
        console.log(e);
        let tableData = this.tableData;
        let index = e;
        setTimeout( () =>{
          tableData.splice(index,1);
          that.tableData = tableData;
        },2000)
      },
      getTableData(){
        let data = this.data.data;
        console.log(data)

        for(let i=0; i<data.length; i++) {
          data[i].show = true;
        }
        this.tableData = data;
        this.appeal_document = this.data.appeal_document;
        this.appeal_docter = this.data.appeal_docter;
      },
      onScroll(index) {
        const el = this.$el.querySelector(`.category${index}`);
        const node = el.parentNode.parentNode.parentNode
        this.$refs["scrollRef"].wrap.scrollTop = node.offsetTop;
      },
      hightRight(hightKeyWord, bllb, zyh) {
        this.$emit('hightRight',hightKeyWord,bllb,zyh)
      },
      clickListItem(idx){
        let tableData = this.tableData;
        tableData[idx].show = !tableData[idx].show;
        this.is_show = false;
        this.$nextTick( () =>{
          this.tableData = tableData; 
          this.is_show = true;
        })
        console.log(tableData)

      },
      // 点击通过、驳回按钮
      clickAppeal(i,idx,type){
        let that = this;
        let item = i;
        let index = idx;
        let params = {
          id: item.id, // 质控错误结果的数据ID
          status: type,
          case_document: that.appeal_document, //申诉科室名称
          case_docter: that.appeal_docter,  // 申诉医生名称
          index,
        }
        that.$emit('clickAppealBtn',params);
      }
    }
  }
</script>

<style lang="scss" scoped>
::v-deep .el-scrollbar__wrap {
  overflow-x: hidden;
}
::v-deep .el-divider--horizontal {
  margin: 10px 0;
}


.box22 {
  padding: 16px;
  background: #FFFFFF;
  border-radius: 5px;
  height: 100vh;
  .score-box{
    width: 100%;
    margin-bottom: 16px;
    padding: 30px 50px 30px 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #fff;
    div{
      font-size: 20px;
    }
    .score-f{
      padding-left: 20px;
      font-size: 20px;
    }
    .score-dj,.score-f{
      font-weight: bold;
    }
  }
  .score-box.scoreLevel_1{
    background: rgb(11, 133, 63);
    background-image: url('../../../../assets/images/icon-jia.png');
    background-repeat: no-repeat;
    background-size: 60px 52px;
    background-position:80% 50%;
  }
  .score-box.scoreLevel_2{
    background: rgb(152, 112, 20);
    background-image: url('../../../../assets/images/icon-yi.png');
    background-repeat: no-repeat;
    background-size: 60px 52px;
    background-position:80% 50%;
  }
  .score-box.scoreLevel_3{
    background: rgb(199, 54, 13);
    background-image: url('../../../../assets/images/icon-bing.png');
    background-repeat: no-repeat;
    background-size: 60px 52px;
    background-position:80% 50%;
  }
  .card-box {
    // height: 175px;
    // background: #FFFFFF;
    // border: 1px solid #E2E2E2;
    background: #f1f5fe;
    padding: 10px;
    box-sizing: border-box;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    .title {
      font-size: 12px;
      font-family: PingFang-SC-Bold, PingFang-SC;
      // font-weight: bold;
      color: #333333;
      line-height: 22px;
      // span {
      //   margin-left: 7px;
      // }
    }
    .card-icon-btn{
      width: auto;
      padding: 4px 8px;
      border-radius: 4px;
      background: rgb(254, 240, 240);
      color: rgb(245, 128, 140);
      border: 1px solid rgb(245, 128, 140);
      font-size: 10px;
    }
    .title2 {
      font-size: 14px;
      font-family: PingFang-SC-Bold, PingFang-SC;
      // font-weight: bold;
      color: #333333;
      line-height: 26px;
      cursor: pointer;
      span {
        margin-left: 7px;
      }
    }
    .error {
      color: #D81E06;
    }
  }
  .box-card {
    margin-bottom: 10px;
    position: relative;
    background: rgb(241, 245, 254);
    .category {
      font-family: PingFangSC-Semibold, PingFang SC;
      font-weight: bold;
      color: #333333;
    }
    .koufen {
      font-weight: bold;
      vertical-align: middle;
      margin-left: 16px;
    }
    .typeImg {
      width: 53px;
      height: 53px;
      position: absolute;
      top: 12px;
      right: 12px;
      z-index: 999;
    }
  }

  .box-card .el-table ::v-deep tr{
    background: transparent;
  }
  ::v-deep .el-table__row {
    background: #185DA6 !important;
    color: #FFFFFF;
    .el-icon-arrow-right {
      color: #FFFFFF;
    }
  }
  ::v-deep .el-descriptions__body{
    background: transparent;
  }
  ::v-deep .el-table tr{
    background: transparent;
  }
  ::v-deep .el-table--enable-row-hover .el-table__body tr:hover>td.el-table__cell {
    background: #185DA6;
  }
  ::v-deep .el-table_1_column_1 {
    border-radius: 8px 0 0 8px;
  }
  ::v-deep .el-table_1_column_2 {
    border-radius: 0 8px 8px 0;
    font-weight: bold;
  }
  ::v-deep .el-descriptions-item__label {
    font-family: PingFang-SC-Bold, PingFang-SC;
    font-weight: bold;
    color: #333333;
  }
}
.span-index{
  width: 20px;
  height: 20px;
  line-height: 20px;
  text-align: center;
  display: inline-block;
  border-radius: 50%;
  background: #185DA6;
  color: #fff;
  margin-right: 10px;
  margin-bottom: 4px;
  font-size: 12px;
}

::v-deep .el-table .el-table__row td {
  color: #fff;
}
::v-deep .el-tag{
  height: auto;
  line-height: 22px;
}
// =================   2024-07-27 新样式  ↓  ===============
.font-size12{
  font-size: 12px;
}
.list-box{
  width: 100%;
  padding: 10px 0;
  .title-color{
    color: rgba(27,100,176,1);
    font-weight: bold;
  }
  .list-score-tips-box{
    width: 100%;
    display: flex;
    .list-left-score{
      width: 70px;
      text-align: center;
      font-size: 20px;
      font-weight: 700;
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 10px 0;
      &.hover-1{
        background: rgb(254, 240, 240);
        color: rgb(238, 14, 14);
        border-right: 3px solid rgb(238, 14, 14);
      }
      &.hover-2{
        background: rgb(236, 245, 255);
        color: rgb(52, 140, 235);
        border-right: 3px solid rgb(52, 140, 235);
      }
      &>div{
        font-size: 20px;
      }
    }
    .list-right-tips{
      flex: 1;
      font-size: 12px;
      padding-left: 10px;
      .notice-box{
        margin-top: 8px;
      }
    }
  }
  .list-basis-box{
    .list-basis-title{
      padding: 10px 0 10px 10px;
      position: relative;
      &>span{
        font-size: 12px;
        color: rgba(27,100,176,1);
        font-weight: bold;
      }
      .typeImg {
        width: 34px;
        height: 34px;
        position: absolute;
        top: 0;
        right: 12px;
        z-index: 999;
      }
      
    }
    .list-basis-text {
      height: auto;
      .list-basis-text-t{
        height: 0;
        overflow: hidden;
        position: relative;
        &.show{
          height: auto;
          padding: 10px 0 10px 10px;
        }
      }
    }
    .list-basis-bottom-box{
      margin-top: 14px;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      .list-basis-bottom-tips{
        flex: 1;
        &::v-deep .el-button{
          padding: 0;
          font-size: 12px;
          width: 40px;
          height: 40px;
          border-radius: 50%;
          &>span{
            font-size: 12px;
          }
        }
      }
      .list-basis-bottom-btn{
        width: auto;
        &::v-deep .el-button{
          padding: 6px 14px;
        }
        &::v-deep .el-button--primary,&::v-deep .el-button--primary:focus,&::v-deep .el-button--primary:hover{
          background: #185DA6;
          border-color: #185DA6;
        }
      }
    }

    

  }
}

</style>