<template>
  <div class="pages">
    <div class="upload-block">

      <div class="titlebox">
        <div class="titlebox-text">病案首页数据导入</div>
        <div class="switch-box">
          <el-switch v-model="switch_value" active-color="#185da6" :disabled="switch_disabled"></el-switch>
          <div class="switch-text">{{ switch_value?'上传 + 质控':'上传' }}</div>
        </div>
      </div>
      <!-- 上传拖拽区域模块 开始 -->
      <div class="upload-blockCon-box">
        <el-upload class="upload-demo" 
          drag ref="upload" 
          :accept="accept" 
          :limit="limit"
          action="none" 
          multiple 
          :on-exceed='limitCheck'
          :http-request="uploadArticleCover" 
          
        >
          <i class="el-icon-upload"></i>
          <div class="el-upload__text">将文件拖到此处，或<em>点击上传</em></div>
          <div class="el-upload__tip">支持excel、csv</div>

          <div class="pop-box" v-if="is_loging">
            <div class="pop-div">{{ loging_text }}</div>
            <div class="pop-modal"></div>
          </div>

        </el-upload>
      </div>
      <!-- 上传拖拽区域模块 结束 -->

    </div>
    <div class="tableBox">

      <div class="titlebox">
        <div class="titlebox-text">导入结果</div>
        <!-- <div><el-button type="primary" size="small" class="export-btn">导出数据</el-button></div> -->
      </div>
      <p style="margin-top: 10px;">共上传: {{ sum_count }}份<span style="padding-left: 20px;">导入失败: {{ error_count }}份</span></p>
      <div class="table-block">
        <el-table :data="tableData" style="width: 100%">
          <el-table-column prop="hospital_name" align="center" label="医院名称"></el-table-column>
          <el-table-column prop="AAA28" align="center" label="病案号"></el-table-column>
          <el-table-column prop="AAA01" align="center" label="姓名"></el-table-column>
          <el-table-column prop="AAB01" align="center" label="入院时间"></el-table-column>
          <el-table-column prop="AAC01" align="center" label="出院时间"></el-table-column>
          <el-table-column prop="error_msg" align="center" label="导入失败原因"></el-table-column>
        </el-table>
      </div>
      

      <!-- <div class="footers">
        <mPagination v-if="tableData && tableData.length !== 0" layout="sizes, prev, pager, next, slot" :data="paginationData" @sizeChange="handleSizeChange" @pageChangeEvent="pageHasChanged"></mPagination>
      </div> -->


    </div>


  </div>
</template>
<script>
import uploadRequest from '../../../api/uploadRequest'//自定义封装的请求

export default {
  name:'recordsImport',
  data() {
    return {
      switch_value: true,
      switch_disabled: true,
      fileList: [],
      accept:'.xlsx,.xls,.csv', // 接受上传文件
      limit: 1, // 选着文件时限制总数
      actionUrl:'',
      tableData: [],
      sum_count: 0, //导入总数
      error_count: 0, // 导入失败数
      is_loging: false,
      loging_text:'',
    };
  },
  mounted() {
 
  },
  activated() {

  },
  methods: {
    
    // 选择的文件超出限制的文件总数量时触发
    limitCheck() {
      this.$message.warning('每次只能上传一个文件')
    },

    uploadArticleCover(param){
      console.log(param.file)
      const formData = new FormData();
      formData.append('file',param.file);
      formData.append('pictureCategory','articleCover');
      this.is_loging = true;
      this.loging_text = '上传质控中...';
      console.log(formData)
      uploadRequest.uploadFile('/bl_import/importData',formData).then( res=>{
        this.tableData = res.data.list;
        this.sum_count = res.data.sum_count; //导入总数
        this.error_count = res.data.error_count; // 导入失败数
        this.loging_text = '质控完成';
        
        setTimeout( ()=>{
          this.is_loging = false;
        },2000)
        this.$refs['upload'].clearFiles()
      }).catch( e=>{
        console.log('erro')
        this.is_loging = false;
        this.$message.error('上传失败');
        this.$refs['upload'].clearFiles()
      })
    },
   
  },
};
</script>
<style lang="scss" scoped>
  .pages{
    margin: 16px;
  }
  .upload-block {
    background: #fff;
    border-radius: 5px;
    height: auto;
    padding: 16px;
    margin-bottom: 20px;
  }
  .titlebox{
    display: -webkit-box;
    display: -ms-flexbox;
    display: flex;
    -webkit-box-align: center;
    -ms-flex-align: center;
    align-items: center;
    justify-content: space-between;
  }
  .titlebox-text{
    font-size: 15px;
    font-weight: bold;
  }
  .upload-blockCon-box{
    width: 100%;
    display: -webkit-box;
    display: -ms-flexbox;
    display: flex;
    -webkit-box-align: center;
    -ms-flex-align: center;
    align-items: center;
    justify-content: center;
    margin-top: 10px;
    position: relative;
  }

  .upload-demo::v-deep .el-upload-dragger{
    width: 650px;
    height: 220px;
  }
  .upload-demo::v-deep .el-upload-dragger .el-icon-upload{
    font-size: 120px;
  }
  .tableBox {
    background: #fff;
    padding: 16px;
    border-radius: 5px;
  }
  .table-block{
    margin-top: 10px;
  }
  .switch-box{
    display: -webkit-box;
    display: -ms-flexbox;
    display: flex;
    -webkit-box-align: center;
    -ms-flex-align: center;
    align-items: center;
    justify-content: center;
  }
  .switch-box .switch-text{
    text-align: left;
    width: 100px;
    padding-left: 10px;
  }
  .pop-box{
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2001;
  }
  .pop-modal {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    opacity: .3;
    background: #000;
    z-index: 2000;
  }
  .pop-div{
    display: inline-block;
    width: 420px;
    height: 70px;
    line-height: 70px;
    vertical-align: middle;
    background-color: #FFF;
    border-radius: 4px;
    border: 1px solid #EBEEF5;
    font-size: 18px;
    -webkit-box-shadow: 0 2px 12px 0 rgb(0 0 0 / 10%);
    box-shadow: 0 2px 12px 0 rgb(0 0 0 / 10%);
    text-align: left;
    overflow: hidden;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
    text-align: center;
    font-size: 15px;
    z-index: 2001;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%,-50%);
  }
</style>
