<template>
  <div class="box">
    <!-- 筛选 -->
    <div class="box_card mb16" :class=" box_card_show_1 ? 'box_card_show' : '' ">
      <el-form :inline="true" :model="formInline" class="demo-form-inline" v-if="!box_card_show_1">
        <el-form-item label="指标状态:">
          <el-select v-model="formInline.status" clearable filterable placeholder="请选择" style="width: 210px;">
            <el-option label="正确" :value="1"></el-option>
            <el-option label="错误" :value="3"></el-option>
            <el-option label="警告" :value="2"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="出院科室:">
          <el-select v-model="formInline.AAC11N" clearable filterable placeholder="请选择" style="width: 210px;">
            <el-option
                v-for="(item, index) in departmentList"
                :key="index"
                :label="item.name"
                :value="item.name">
              </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="主治医师:">
          <el-select v-model="formInline.AEE03" clearable filterable placeholder="请选择" style="width: 210px;">
            <el-option
              v-for="(item, index) in staffList"
              :key="index"
              :label="item.name"
              :value="item.name">
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="出院时间:">
          <el-date-picker
            v-model="formInline.startTime"
            type="date"
            format="yyyy年MM月dd日"
            value-format="yyyyMMdd"
            placeholder="开始日期"
            style="width: 210px;">
          </el-date-picker>
          <el-date-picker
            v-model="formInline.endTime"
            type="date"
            style="margin-left: 10px; width: 210px;"
            format="yyyy年MM月dd日"
            value-format="yyyyMMdd"
            placeholder="结束日期">
            </el-date-picker>
        </el-form-item>
      </el-form>
      <el-form :inline="true" :model="formInline" class="demo-form-inline" v-if="!box_card_show_1">
        <el-form-item label="住院号码:">
          <el-input v-model="formInline.zyh" placeholder="请输入"  style="width: 210px;"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="onSearch">查询</el-button>
          <el-button @click="onReset">重置</el-button>
        </el-form-item>
      </el-form>
      <div class="show-span" @click="clickShow">{{box_card_show_1 ? '展开' : '收起'}}</div>
    </div>
    <div class="box_card mb16">
      <CardTitle :title="indexData.name" />
      <el-row :gutter="32">
        <el-col :span="12">
          <span>分子: {{ indexData.fenzi_name }}</span>
          <el-popover
            placement="right-start"
            trigger="hover">
            <div v-for="(fItem, fIndex) in fenziList" :key="fIndex" class="mb16">
              <div class="input_label" style="width: 140px; padding: 8px 15px; line-height: 24px; display: inline-block; border: 1px solid #dcdfe6; border-radius: 4px; vertical-align: top;">{{ fItem.name }}</div>
              <div class="input_label" style="width: 280px; margin-left: 16px; padding: 8px 15px; line-height: 24px; display: inline-block; border: 1px solid #dcdfe6; border-radius: 4px; vertical-align: top;">{{ fItem.info }}</div>
            </div>
            <el-button slot="reference" plain size="mini" type="primary" class="ml16">计算口径</el-button>
          </el-popover>
        </el-col>
        <el-col :span="12">
          <span>分母: {{ indexData.fenmu_name }}</span>
          <el-popover
            placement="right-start"
            width="500px"
            popper-class="index-popover"
            trigger="hover">
            <div v-for="(mItem, mIndex) in fenmuList" :key="mIndex" class="mb16">
              <div class="input_label" style="width: 140px; padding: 8px 15px; line-height: 24px; display: inline-block; border: 1px solid #dcdfe6; border-radius: 4px; vertical-align: top;">{{ mItem.name }}</div>
              <div class="input_label" style="width: 280px; margin-left: 16px; padding: 8px 15px; line-height: 24px; display: inline-block; border: 1px solid #dcdfe6; border-radius: 4px; vertical-align: top;">{{ mItem.info }}</div>
            </div>
            <el-button slot="reference" plain size="mini" type="primary" class="ml16">计算口径</el-button>
          </el-popover>
        </el-col>
      </el-row>
    </div>
    <!-- 列表 -->
    <div class="box_card">
      <div class="btn-box">
        <span class="page-msg">
          共找到 <span class="num">{{ page.total }}</span> 条结果
          <!-- <span class="page" v-if="page.total">{{ page.page }}/{{ Math.ceil(page.total / page.limit) }}</span> -->
          <span class="page_limit_box">
            显示
            <el-select v-model="page.limit" size="mini" @change="handleLimitChange" style="width: 100px;">
              <el-option label="10条/页" :value="10"></el-option>
              <el-option label="50条/页" :value="50"></el-option>
              <el-option label="100条/页" :value="100"></el-option>
              <el-option label="200条/页" :value="200"></el-option>
            </el-select>
          </span>
          <el-pagination
            :total="page.total"
            :page-size="page.limit"
            :current-page.sync="page.page"
            layout="prev, pager, next"
            @current-change="pageHasChanged"
          />
        </span>
        <el-button type="primary" @click="onExport">导出</el-button>
      </div>
      <el-table
        :data="tableData" :height="tableHeight"
        @sort-change="handleSortChange"
        :row-class-name="tableRowClassName"
        style="width: 100%; margin-bottom: 16px;">
        <el-table-column
          width="80"
          label="序号"
          align="center">
          <template slot-scope="scope">{{ (page.page -1 ) * page.limit + scope.$index + 1 }}</template>
        </el-table-column>
        <el-table-column
          prop="AAA28"
          label="住院号码"
          align="center">
          <template slot-scope="scope">
            <span class="link" @click="toDetailPage(scope.row)">{{ scope.row.AAA28 }}</span>
          </template>
        </el-table-column>
        <el-table-column
          prop="AAA01"
          label="患者姓名"
          align="center">
        </el-table-column>
        <el-table-column
          prop="AAC01"
          label="出院时间"
          sortable="custom"
          align="center">
        </el-table-column>
        <el-table-column
          prop="AAC11N"
          label="出院科室"
          align="center">
        </el-table-column>
        <el-table-column
          prop="AEE03"
          label="主治医师"
          align="center">
        </el-table-column>
        <!-- <el-table-column
          prop="pingfenleixing"
          label="评分类型"
          align="center">
        </el-table-column>
        <el-table-column
          prop="pingfen"
          label="评分"
          align="center">
        </el-table-column> -->
        <el-table-column
          prop=""
          label="计算详情"
          align="center">
          <template slot-scope="scope">
            <span class="link" @click="clickckxq(scope.row)">查看详情</span>
          </template>
        </el-table-column>
        <el-table-column
          prop="status"
          label="指标状态"
          align="center">
          <template slot-scope="scope">
            <el-tag :type="scope.row.status == '正确'?'success':(scope.row.status == '警告'?'warning':'danger')">{{ scope.row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column
          prop="update_time"
          label="更新时间"
          align="center">
        </el-table-column>
      </el-table>
      <!-- <div style="overflow: hidden; text-align: center; background: #fff; padding-bottom: 16px;">
        <el-pagination
          :total="page.total"
          background
          :page-size="page.limit"
          :current-page.sync="page.page"
          layout="prev, pager, next, jumper"
          @current-change="pageHasChanged"
        />
      </div> -->
    </div>
    <div class="VueDragResize-box" v-if="is_VueDragResize">
      <!-- 弹窗 -->
      <VueDragResize :style="`z-index:${zInfex_0};`" 
        dragHandle=".VueDragResize-title-box" 
        :isActive="true" :isDraggable="true" 
        :parentW="parentW" :parentH="parentH" :parentLimitation="true" :preventActiveBehavior="true"
        :w="width" :h="height" 
        :minw="minw" :minh="minh" 
        :x='left' :y='top' @dragstop="onDragstop" 
        @resizing="resize" @dragging="resize" @deactivated="onDeactivated"
        >
        <div class="VueDragResize-centent-box">
          <div class="VueDragResize-title-box">
            <div class="title"><span>指标结果</span></div>
            <div class="icon-box">
              <span @click="clickcloseBtn">X</span>
            </div>
          </div>
          <div class="navbaerMag-content-box">
            <div class="list-box">
              <div class="list-item-box">
                <div class="listItem-title-box">
                  <span>指标名称：</span>
                  <span style="color: #ff0000;">{{ show_name }}</span>
                  <span class="span-btn" @click="clickJsTitleBtn">指标计算</span>
                  <!-- <img src="../../assets/images/arrow-down.png" alt="" @click.stop="clickListItem"  class="arrow-down" :class="show_box?'show':''"/> -->
                </div>

                <div class="items-show-box" :class=" show_box ? 'show': '' ">
                  <div class="xqItemError-box" v-for="(item,index) in xqItemError" :key="index">

                    <div class="items-titleBox">
                      <span class="idx-span">{{ index+1 }}</span>
                      <el-tag :type="item.status == 1?'success':'danger'">{{ item.status == 1?'正确':'错误' }}</el-tag>
                      <!-- <img src="../../assets/images/arrow-down.png" alt="" @click.stop="clickxqItemErrorItems(index)"  class="arrow-down" :class="item.show ?'show':''"/> -->
                    </div>

                    <div class="itemsDiv-box" :class=" item.show ? 'show': '' ">
                      <div class="items-box" v-for="(items,idx) in item.content" :key="idx">
                        <div class="items-content" v-html="items.content" :style="`color: ${items.status == 1?'#000':'#ff0000'};`"></div>
                      </div>
                    </div>
                    
                  </div>
                  

                </div>
                
              </div>
            </div>
          </div>
        </div>
      </VueDragResize>
    </div>


  </div>
</template>

<script>
import VueDragResize from 'vue-drag-resize';
import { majorIndexDetailExport } from '@/api/excel';

import { number } from 'echarts';
  export default {
    components: {
      VueDragResize,
    },
    data() {
      return {
        box_card_show_1: true,
        indexData: {},
        formInline: {
          status: '',
          AAC11N: '',
          AEE03: '',
          zyh: '',
          startTime: '',
          endTime: '',
          chuyuanshijian_order: ''
        },
        page: {
          total: 0,
          page: 1,
          limit: 10,
        },
        show_box: false,
        tableData: [],
        departmentList: [],
        staffList: [],
        // 弹窗信息
        width: 0,
        height: 0,
        minw: 620,
        minh: 340,
        parentH: 0,
        parentW: 0,
        top: 1,
        left: 500,
        zInfex_0: 9999,
        is_VueDragResize: false,
        tabStatus: 1,
        xqItemError: [],
        show_name: '',
        tableHeight: 0,
        currentRow: {},
        rowClickZYH: -1,
      }
    },
    computed: {
      fenziList() {
        return this.indexData.fenzi ? JSON.parse(this.indexData.fenzi) : []
      },
      fenmuList() {
        return this.indexData.fenmu ? JSON.parse(this.indexData.fenmu) : []
      }
    },
    created() {
      // 弹窗
      let getViewportSize = this.$getViewportSize();
      this.tableHeight = Number(getViewportSize.height - 320);
      this.parentH = getViewportSize.height; // 组件范围
      this.parentW = getViewportSize.width; // 组件范围
      this.width = 400; // 可拖动div 宽
      this.height = Number(getViewportSize.height - 100); // 可拖动div 高度
      this.left = Number(getViewportSize.width)/2 - Number(this.width)/2;
      this.top = 60;

      const saveData = localStorage.getItem('majorIndexData')
      this.indexData = saveData ? JSON.parse(saveData) : {}
      const status = this.$route.query.status ? Number(this.$route.query.status) : '';
      this.formInline.startTime = this.$route.query.startTime ? this.$route.query.startTime : '';
      this.formInline.endTime = this.$route.query.endTime ? this.$route.query.endTime : '';
      this.formInline.AAC11N = this.$route.query.AAC11N ? this.$route.query.AAC11N : '';
      this.formInline.AEE03 = this.$route.query.AEE03 ? this.$route.query.AEE03 : '';
      // this.formInline.status = this.$route.query.status ? this.$route.query.status : '';
      this.indexData.url = this.$route.query.catalog ? this.$route.query.catalog : '';
      if(status === 1){
        this.formInline.status = 1
      }
      this.getList();
      this.getDepList();
      this.getstaffList();
    },
    activated() {
      this.getList();
    },
    methods: {
      tableRowClassName({row}) {
        if (row.selected) {
          return 'selected-row'
        }
        return ''
      },
      clickShow(){
        this.box_card_show_1 = !this.box_card_show_1;
      },
      clickJsTitleBtn(){
        const params = {
          category: this.indexData.url,
          zyh: this.currentRow.zyh
        }
        this.$axios2.post('/quality_index_recalculate', params).then(res => {
          if(res.code == 200) {
            this.is_VueDragResize = false
            this.getList()
          }
        });
      },
      clickListItem(){
        this.show_box = !this.show_box;
        this.resize();
      },
      clickxqItemErrorItems(i){
        let xqItemError = this.xqItemError;
        xqItemError[i].show = !xqItemError[i].show;
        this.xqItemError = xqItemError;
        this.resize();
      },
      clickStatus(n){
        this.tabStatus = Number(n);
      },
      clickcloseBtn(){
        this.is_VueDragResize = false;
      },
      // 拖拽时可以确定元素位置
      resize(newRect) {
        if(newRect){
          this.width = newRect.width;
          this.height = newRect.height;
          this.top = newRect.top;
          this.right = newRect.right;
        }
      },
      onDeactivated(e){
        
      },
      onDragstop(newRect) {
        
      
      },
      getstaffList(){
        this.$axios2.get('/get_staff?ygjb=主治医师').then(res => {
          this.staffList = res.data;
        });
      },
      strToList(str) {
        return str ? str.split('|') : []
      },
      toDetailPage(row) {
        this.rowClickZYH = row.zyh;
        this.storageSet('getData', row.zyh);
        this.storageSet('xqItemError', JSON.stringify(row.error));
        let path= `/caseViews?from=majorIndexDetail&show_name=${row.name}&category=${this.indexData.url}&zyh=${row.zyh}`
        // this.goto(path);
        this.$router.push(path);
      },
      getDepList() {
        // this.$axios2.get('/get_kesi').then(res => {
        //   this.departmentList = res.data;
        // });
        this.$axios.post('/get_department_list').then(res => {
          this.departmentList = res.data;
        });
      },
      handleSortChange(val) {
        const { prop, order } = val
        if (prop === 'chuyuanshijian') {
          if (order) {
            if (order === 'ascending') {
              this.formInline.chuyuanshijian_order = 1
            } else {
              this.formInline.chuyuanshijian_order = 2
            }
          } else {
            this.formInline.chuyuanshijian_order = 0
          }
        }
        this.getList()
      },
      getList() {
        console.log("getList",this.indexData)
        let pramse = {
          is_export: 0,
          category: this.indexData.url,
          AAC11N: this.formInline.AAC11N, //出院科室
          AEE03: this.formInline.AEE03, //师主治医
          status: this.formInline.status, //状态
          cysj_start: this.formInline.startTime, //开始时间
          cysj_end: this.formInline.endTime, //结束时间
          zyh: this.formInline.zyh,
          page: this.page.page, //页码
          pagesize: this.page.limit, //条数
        };
        this.$axios2.post('/quality_index_detail_list',pramse).then(res => {

          this.tableData = Array.isArray(res.data.data) ? res.data.data : []
          this.page.total = res.data.total;
          // 切换选中状态
          Array.isArray(this.tableData) && this.tableData.map((item) => {
            item.selected = item.zyh == this.rowClickZYH ? true : false
          })
        }).catch( e =>{
          console.log(e);
        })

        // const { year, month } = this.$route.query
        // const { page, limit } = this.page
        // const { zhuangtai, chuyuankesi, zyh, chuyuanshijian_order } = this.formInline;
        // let url = `/quality_index_list?type=0&year=${year}&is_export=0&category=${this.indexData.url}&page=${page}&perpage=${limit}&zhuangtai=${zhuangtai}&chuyuankesi=${chuyuankesi}&zyh=${zyh}`
        // if (month) {
        //   url+=`&month=${month}`
        // }
        // if (chuyuanshijian_order) {
        //   url+=`&chuyuanshijian_order=${chuyuanshijian_order}`
        // }
        // this.$axios2.get(url).then(res => {
        //   this.tableData = Array.isArray(res.data.data) ? res.data.data : []
        //   this.page.total = res.data.total
        // });
      },
      clickckxq(e){
        console.log(e)
        this.show_name = e.name;
        this.xqItemError = [];
        let arr = e.error;
        this.xqItemError = arr;
        this.is_VueDragResize = true;
        this.currentRow = {...e}
      },
      onSearch() {
        this.page.page = 1
        this.getList()
      },
      onReset() {
        this.formInline = {
          status: '',
          AAC11N: '',
          AEE03: '',
          zyh: '',
          startTime: '',
          endTime: '',
          chuyuanshijian_order: ''
        }
        this.getList()
      },
      handleLimitChange(val) {
        this.page.page = 1;
        this.page.limit = val;
        this.getList();
      },
      pageHasChanged(val) {
        this.page.page = val;
        this.getList();
      },
      onExport() {
        let pramse = {
          is_export: 1,
          category: this.indexData.url,
          AAC11N: this.formInline.AAC11N, //出院科室
          AEE03: this.formInline.AEE03, //师主治医
          status: this.formInline.status, //状态
          cysj_start: this.formInline.startTime, //开始时间
          cysj_end: this.formInline.endTime, //结束时间
          zyh: this.formInline.zyh,
          page: this.page.page, //页码
          pagesize: this.page.limit, //条数
        };
        majorIndexDetailExport(pramse).then(res => {
          const content = res.data; // 后台返回二进制数据
          const blob = new Blob([content]);
          const fileName = `${this.indexData.name}_详情.csv`;
          if ('download' in document.createElement('a')) {
            // 非IE下载
            const elink = document.createElement('a');
            elink.download = fileName;
            elink.style.display = 'none';
            elink.href = URL.createObjectURL(blob);
            document.body.appendChild(elink);
            elink.click();
            URL.revokeObjectURL(elink.href); // 释放URL 对象
            document.body.removeChild(elink);
          } else {
            // IE10+下载
            navigator.msSaveBlob(blob, fileName);
          }
        });
      }
    }
  }
</script>

<style lang="scss" scoped>
.box {
  padding: 0 16px 16px;
  .box_card {
    background: #fff;
    padding: 16px;
    border-radius: 4px;
    position: relative;
    height: auto;
    &.box_card_show{
      height: 26px;
      overflow: hidden;
    }
    .show-span{
      position: absolute;
      bottom: 8px;
      right: 30px;
      cursor: pointer;
      color: #666666;
      &:hover{
        color: #409eff;
      }
    }
  }
  .mb16 {
    margin-bottom: 16px;
  }
  .ml16 {
    margin-left: 16px;
  }
  .link {
    color: #409eff;
    cursor: pointer;
  }
}

.btn-box {
  text-align: right;
  margin-bottom: 15px;
  .page-msg {
    float: left;
    height: 40px;
    font-size: 14px;
    display: flex;
    align-items: center;
    .num {
      color: #F56C6C;
    }
    .page {
      margin-left: 40px;
    }
    .page_limit_box {
      margin-left: 40px;
    }
  }
}
.demo-form-inline{
  display: flex;
  align-items: center;
  justify-content: space-between;
}

// 拖拽弹窗样式
.VueDragResize-box{
  position: fixed;
  width: 100%;
  height: 100%;
  z-index: 9999;
  top: 0;
  left: 0;
  pointer-events: none; /* 鼠标事件穿透 */
  & ::v-deep .vdr-stick{
    display: none;
  }
  & ::v-deep .vdr.active:before{
    display: none;
  }
  .VueDragResize-centent-box{
    width: 100%;
    height: 100%;
    background: #fff;
    box-shadow: 0 1px 8px 0 rgb(206 206 206);
    border-radius: 6px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    pointer-events: all; /* 鼠标事件穿透 */

    .vdr-icon{
      width: 28px;
      height: 28px;
      position: absolute;
      bottom: -4px;
      right: -4px;
      background: #ff0000;
      z-index: auto;
      display: block;
    }
    .VueDragResize-title-box{
      background: rgb(32 138 183);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 14px;
      height: auto;
      height: 40px;
      padding: 0 12px;
      cursor: move;
      .icon-box{
        display: flex;
        align-items: center;
        justify-content: flex-end;
        &>span{
          font-size: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          width: 20px;
          height: 20px;
          line-height: 20px;
          border: 1px solid #fff;
          border-radius: 50%;
          cursor: pointer;
        }
      }
    }
    .navbaerMag-content-box{
      flex: 1;
      height: calc(100% - 40px);
      overflow: auto;
      padding: 10px;
      .navbaerMag-tab-box{
        width: auto;
        display: flex;
        align-items: center;
        &>div{
          cursor: pointer;
          width: 82px;
          font-size: 14px;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 6px 0;
          border: 1px solid rgb(23 80 154);
          &.hover-items{
            background: rgb(23 80 154);
            color: #fff;
          }
        }
      }
      .list-box{
        width: 100%;
        .list-item-box{
          width: 100%;
          border-radius: 4px;
          padding: 10px;
          margin-top: 14px;
          background: rgb(241 246 255);
          font-size: 14px;
          .listItem-title-box{
            display: flex;
            align-items: center;
            & span{
              font-size: 14px;
            }
            .span-btn{
              display: inline-block;
              background: #2b8f53;
              margin-left: 6px;
              font-size: 12px;
              color: #fff;
              padding: 6px 12px;
              border-radius: 4px;
              text-align: center;
              cursor: pointer;
            }
            .arrow-down{
              width: 12px;
              height: 12px;
              margin-left: 12px;
              cursor: pointer;

            }
            .arrow-down.show{
              transform: rotate(180deg);
            }
          }
          .items-show-box{
            height: auto;
          }
          .items-show-box.show{
            height: 0;
            overflow: hidden;
          }
          .xqItemError-box{
            padding: 10px 0;
            border-bottom: 1px solid rgb(211, 225, 243);
            .items-titleBox{
              display: flex;
              align-items: center;
              margin-top: 10px;
              .idx-span{
                display: inline-block;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                line-height: 20px;
                text-align: center;
                background: rgb(23 80 154);
                color: #fff;
                margin-right: 4px;
              }
              & ::v-deep .el-tag{
                height: 26px;
                line-height: 26px;
              }
              .arrow-down{
                width: 12px;
                height: 12px;
                margin-left: 12px;
                cursor: pointer;

              }
              .arrow-down.show{
                transform: rotate(180deg);
              }
            }
            .itemsDiv-box{
              height: auto;
            }
            .itemsDiv-box.show{
              height: 0;
              overflow: hidden;
            }
            .items-box{
              margin-top: 10px;
              padding-left: 10px;
              box-sizing: border-box;
              .items-content{
                font-size: 14px;
                margin-top: 6px;
                line-height: 20px;
              }
            }
          }
          
        }
        
      }
    }
  }
}

</style>