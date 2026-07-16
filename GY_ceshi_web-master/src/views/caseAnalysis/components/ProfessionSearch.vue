<template>
  <div>
    <div class="content-box">
      <!-- 病历搜索 -->
      <div class="search-box">
        <div class="row-box">
        <el-row  v-for="(items, indexs) of formInline3.field" :key="indexs" style="margin-bottom: 16px;">
        <el-col class="" :span="24" >
          <el-col class="" :span="4">
            <el-button type="primary" v-if="isShow" class="el-icon-arrow-down heightClass" style="padding-bottom:10px" @click="toggleSideBar(indexs)">查询组({{toChineseNumber(indexs+1)}})</el-button>
            <el-button type="primary" v-if="!isShow" class="el-icon-arrow-up heightClass" style="padding-bottom:10px" @click="toggleSideBar(indexs)">查询组({{toChineseNumber(indexs+1)}})</el-button>

          </el-col>
          <div class="block dataClass">
            <div class="radio" style="display:flex;justify-content: flex-start;">
             <span class="" style="display:inline-block;margin-right:10px;">条件之前的逻辑：</span>
             <el-radio-group v-model="items.select_type" style="margin-top: 18px;">
              <el-radio :label="0">且（满足所有条件）</el-radio>
              <el-radio :label="1">或（满足一个条件）</el-radio>
              <!-- <el-radio :label="2">不包含</el-radio> -->
              </el-radio-group>
              <el-col class="" :span="4">
                <el-button type="text" class="minusClass" circle size="mini" icon="el-icon-remove-outline" :disabled="formInline3.field.length === 1" @click="onMinuss(index)"></el-button>
                <el-button class="circleClass bottomCirclePlus" type="text" circle size="mini" icon="el-icon-circle-plus-outline" @click="onPluss(indexs)"></el-button>
            </el-col>
            </div>
         </div>
        </el-col>
       <el-col style="margin-top: 20px;" class="" :span="24" v-if="isShow"> 
        <el-row v-for="(item, index) of items.formInline2.field" :key="index" style="margin-bottom: 16px;margin-top: 10px;">
          <span style="display: inline-block;
         float: left;
         text-align: center;
         font-size: 16px;margin-right:10px;margin-top:10px;">条件{{ index+1 }}：</span>
            <el-col :span="8.5" class="firClass" style="margin-right:5px;">
              <!-- <el-cascader
                v-model="item.key"
                :options="bl"
                ref="cascader"
                clearable
                filterable
                :show-all-levels="true"
                :props="{
                  label: 'name',
                  value: 'key',
                  expandTrigger: 'hover',
                  emitPath: false
                }"
                @change="handleCascaderChange(index)">
              </el-cascader>  style="width: 240px;margin-right:5px;"-->
              <el-input
              
                v-model="item.key"
                placeholder="请选择查询的病历目录"
                clearable
                @click.native="onChangePassword(index)"
              />
            </el-col>
            <el-col :span="9" style="margin-right:5px;">
              <el-input v-if="item.type === 'input'" v-model="item.value" clearable placeholder="请输入" ></el-input>
              <el-date-picker
                v-if="item.type === 'time'"
                v-model="item.value"
                type="datetimerange"
                range-separator="至"
                value-format="timestamp"
                :default-time="['00:00:00', '23:59:59']"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                style="width: 100%;">
              </el-date-picker>
              <el-select v-if="item.type === 'select'" v-model="item.value" placeholder="请选择" style="width: 100%;">
                <el-option v-for="(sItem, sIndex) of item.selects" :key="sIndex" :label="sItem.value" :value="sItem.name"></el-option>
              </el-select>
            </el-col>
            <el-col class="" :span="2">
              <el-select v-model="item.select_type" filterable placeholder="模糊" style="border-top-right-radius: 4px;border-bottom-right-radius:4px ;">
                <el-option label="模糊" :value="0"></el-option>
                <el-option label="精准" :value="1"></el-option>
                <el-option label="不包含" :value="2"></el-option>
              </el-select>
            </el-col>
            <el-col :span="2">
              <el-button type="text" size="mini" icon="el-icon-minus"  :disabled="items.formInline2.field.length === 1" @click="onMinus(index,indexs)" style="margin-left: 16px;"></el-button>
              <el-button type="text" size="mini" icon="el-icon-plus" @click="onPlus(indexs)"></el-button>
            </el-col>
       </el-row>
        </el-col> 
      </el-row>
          <!-- <el-row v-for="(item, index) of formInline2.field" :key="index" style="margin-bottom: 16px;">
            <el-col :span="4">
              <el-select v-model="item.select_type" filterable placeholder="请选择">
                <el-option label="且" :value="0"></el-option>
                <el-option label="或" :value="1"></el-option>
                <el-option label="不包含" :value="2"></el-option>
              </el-select>
            </el-col>
            <el-col :span="7">
              <el-input
                v-model="item.key"
                placeholder="请选择查询的病历目录"
                clearable
                @click.native="onChangePassword"
               
              />
            </el-col>
            <el-col :span="9">
              <el-input v-if="item.type === 'input'" v-model="item.value" clearable placeholder="请输入"></el-input>
              <el-date-picker
                v-if="item.type === 'time'"
                v-model="item.value"
                type="datetimerange"
                range-separator="至"
                value-format="timestamp"
                :default-time="['00:00:00', '23:59:59']"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                style="width: 100%;">
              </el-date-picker>
              <el-select v-if="item.type === 'select'" v-model="item.value" placeholder="请选择" style="width: 100%;">
                <el-option v-for="(sItem, sIndex) of item.selects" :key="sIndex" :label="sItem.value" :value="sItem.name"></el-option>
              </el-select>
            </el-col>
            <el-col :span="4">
              <el-button type="text" size="mini" icon="el-icon-minus" :disabled="formInline2.field.length === 1" @click="onMinus(index)" style="margin-left: 16px;"></el-button>
              <el-button type="text" size="mini" icon="el-icon-plus" @click="onPlus"></el-button>
            </el-col>
          </el-row> -->
          
        </div>
      </div>
      <!-- 信息搜索 -->
       <div class="info-search">
        <el-form  :model="formInline" class="demo-form-inline" label-width="68px">
         
          <el-form-item label="出院时间">
            <el-date-picker
              v-model="formInline.AAC01_START"
              type="date"
              format="yyyy年MM月dd日"
              value-format="timestamp"
              placeholder="开始日期"
              :picker-options="pickerOptions"
              style="margin-right: 10px;"
             
            />
            至
            <el-date-picker
              v-model="formInline.AAC01_END"
              type="date"
              format="yyyy年MM月dd日"
              value-format="timestamp"
              placeholder="结束日期"
              :picker-options="pickerOptions"
              style="margin-left: 10px;"
           
            />
          </el-form-item>
           <!-- <el-from-item class="">
            <i class="el-icon-star-on"></i>
         
          </el-from-item>  -->
         <!-- <el-form-item label="" class="more-btn">
            <el-button type="text" @click="showMore = !showMore">{{ showMore ? '收回' : '更多'}}</el-button>
          </el-form-item> -->
        </el-form>
       <!-- <el-collapse-transition>
          <div v-show="showMore" style="width: 1124px; margin: 0 auto;">
            <el-form :inline="true" :model="formInline2" class="demo-form-inline2" label-width="68px">
              <el-form-item label="住院号码">
            <el-input v-model="formInline.AAA28" clearable placeholder="请输入"></el-input>
          </el-form-item>
          <el-form-item label="出院科室">
            <el-select v-model="formInline.AAC11N" clearable filterable :disabled="AAC11N_lock" placeholder="请选择">
              <el-option v-for="(item, index) in departmentList" :key="index" :label="item.name" :value="item.name" />
            </el-select>
          </el-form-item>
              <el-form-item label="姓名">
                <el-input v-model="formInline2.AAA01" clearable placeholder="请输入"></el-input>
              </el-form-item>
              <el-form-item label="性别">
                <el-select v-model="formInline2.AAA02C" clearable filterable placeholder="请选择">
                  <el-option v-for="(item, index) in sexs" :key="index" :label="item.name" :value="item.id" />
                </el-select>
              </el-form-item>
              <el-form-item label="入院时间">
                <el-date-picker
                  v-model="formInline2.AAB01_START"
                  type="date"
                  format="yyyy年MM月dd日"
                  value-format="timestamp"
                  placeholder="开始日期"
                  :picker-options="pickerOptions"
                  style="margin-right: 10px;"
                />
                <el-date-picker
                  v-model="formInline2.AAB01_END"
                  type="date"
                  format="yyyy年MM月dd日"
                  value-format="timestamp"
                  placeholder="结束日期"
                  :picker-options="pickerOptions"
                />
              </el-form-item>
              <el-form-item label="住院天数">
                <el-input placeholder="起始天数" v-model="formInline2.AAC04_START" clearable style="width: 230px; margin-right: 10px;">
                  <template slot="append">天</template>
                </el-input>
                <el-input placeholder="终止天数" v-model="formInline2.AAC04_END" clearable style="width: 230px;">
                  <template slot="append">天</template>
                </el-input>
              </el-form-item>
              <el-form-item label="年龄">
                <el-input placeholder="起始年龄" v-model="formInline2.ageStart" clearable style="width: 230px; margin-right: 10px;">
                  <el-select v-model="formInline2.ageType" slot="append" placeholder="请选择">
                    <el-option label="天" :value="1"></el-option>
                    <el-option label="岁" :value="2"></el-option>
                  </el-select>
                </el-input>
                <el-input placeholder="终止年龄" v-model="formInline2.ageEnd" clearable style="width: 230px;">
                  <el-select v-model="formInline2.ageType" slot="append" placeholder="请选择">
                    <el-option label="天" :value="1"></el-option>
                    <el-option label="岁" :value="2"></el-option>
                  </el-select>
                </el-input>
              </el-form-item>
              <el-form-item label="体温">
                <el-input placeholder="起始体温" v-model="formInline2.TIWEN_START" clearable style="width: 230px; margin-right: 10px;">
                  <template slot="append">℃</template>
                </el-input>
                <el-input placeholder="终止体温" v-model="formInline2.TIWEN_END" clearable style="width: 230px; margin-right: 10px;">
                  <template slot="append">℃</template>
                </el-input>
                <el-select
                  v-model="formInline2.TIWEN_FIELD"
                  multiple
                  collapse-tags
                  style="margin-left: 20px; width: 240px;"
                  placeholder="请选择">
                  <el-option
                    v-for="item in tiwens"
                    :key="item.id"
                    :label="item.name"
                    :value="item.id">
                  </el-option>
                </el-select>
              </el-form-item>
             </el-form>
          </div> 
         </el-collapse-transition>  -->
        <!-- <div class="btn-group"> -->
          <div class="btn-class">
            <el-button @click="onReset">重置</el-button>
            <el-button type="primary" @click="onSearch">查询</el-button>
          </div>
        <!-- </div> -->
        <el-row>
            <div class="btn-class" style="margin-top:10px;">
              <!-- <el-from-item class=""> -->
                <!-- <i class="el-icon-star-on"></i> -->
                <!-- <el-button style="float: right;margin-top: -10px; margin-right: -10px" ><el-icon><Star /></el-icon></el-button> -->
            <el-button plain icon="el-icon-star-on" style="margin-bottom:-35px;" class="caiClass" >收藏</el-button>
          <!-- </el-from-item> -->
            <!-- <button  size="small" icon="el-icon-upload" type="primary">收藏</button> -->
           </div>
            <el-tabs v-model="activeName" @tab-click="handleClick" style="width:92%;">
            <el-tab-pane label="收藏检索条件" name="first">收藏检索条件</el-tab-pane>
            <el-tab-pane label="常用查询词语" name="second">常用查询词语</el-tab-pane>
            <el-tab-pane label="历史检索条件" name="third">历史检索条件</el-tab-pane>
            </el-tabs>
          </el-row>
           <!-- 修改密码 -->
      <DirectoryDialog v-if="pwdData.bSwitch" :data="pwdData" @handleCascaderChange="handleCascaderChange" ref="DirRef" />
      </div> 
    </div>
  </div>
</template>

<script>

import Hamburger from '@/components/Hamburger';
  import DirectoryDialog from './DirectoryDialog.vue'
export default {
  components: {
    DirectoryDialog,
    Hamburger
    },
  data() {
    return {
      radio: 3,
      textCenter:"center",
      activeName:"first",
      sexs: [
        {
          id: 1,
          name: '男'
        },
        {
          id: 2,
          name: '女'
        },
        {
          id: 0,
          name: '未知的性别'
        },
        {
          id: 9,
          name: '未说明的性别'
        }
      ],
      pickerOptions: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
      },
      bl: [],
      tiwens: [],
      formInline: {
        AAA28: '',
        AAC11N: '',
        AAC01_START: '',
        AAC01_END: ''
      },
      formInline2: {
        AAA01: '',
        AAA02C: '',
        AAB01_START: '',
        AAB01_END: '',
        AAC04_START: '',
        AAC04_END: '',
        ageStart: '',// 自定义需根据ageType转化相应字段
        ageEnd: '',// 自定义需根据ageType转化相应字段
        ageType: 2,
        TIWEN_START: '',
        TIWEN_END: '',
        TIWEN_FIELD: '',
        field: [{
          select_type: 0,
          type: 'input',
          key: '',
          selects: [],
          value: '',
        }]
      },
      formInline3:{
        field: [{
        select_type: 0,
        formInline2: {
        AAA01: '',
        AAA02C: '',
        AAB01_START: '',
        AAB01_END: '',
        AAC04_START: '',
        AAC04_END: '',
        ageStart: '',// 自定义需根据ageType转化相应字段
        ageEnd: '',// 自定义需根据ageType转化相应字段
        ageType: 2,
        TIWEN_START: '',
        TIWEN_END: '',
        TIWEN_FIELD: '',
        field: [{
          select_type: 0,
          type: 'input',
          key: '',
          selects: [],
          value: '',
        }]
        }
         
        }]
      },
      indexData:0,
      menusData:[],
      departmentList: [],
      showMore: false,
      AAC11N_lock: false,
      isShow:true,
      pwdData: {
          bSwitch: false,
        },
    }
  },
  created() {
    this.getSelects()
    this.getTiwensSelects()
    this.selectInfo()
   
  },
  methods: {
toChineseNumber(num) {
    const chineseNumbers = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
    const units = ['', '十', '百', '千'];
    let str = '';
    
    if (num >= 10) {
        str += chineseNumbers[Math.floor(num / 10)] + units[1];
        num = num % 10;
        if (num > 0) {
            str += chineseNumbers[num];
        }
    } else {
        str += chineseNumbers[num];
    }
    
    return str;
    },
    toggleSideBar(index){
     this.isShow= !this.isShow
    },
    // 切换页签
    handleClick(val){

    },
    onChangePassword(index) {
      this.indexData=index;
       // this.pwdData.row = row
        this.pwdData.bSwitch = true
      },
    // 获取选择得项
    handleCascaderChange(menuName) {
    this.formInline2.field.key=this.$refs.DirRef.key
      if(menuName==='home'){
        this.$refs.DirRef.home.forEach((item)=> {
          if(item.key===this.$refs.DirRef.key){
            this.$set(this.formInline2.field[this.indexData], 'type', item.type)
            const timeVal = item.type === 'time' ? [] : undefined
          this.$set(this.formInline2.field[this.indexData], 'value', timeVal)
          this.$set(this.formInline2.field[this.indexData], 'selects', item.value)
          }
          
        });
      }
        if(menuName==='main_diagnosis'){
        this.$refs.DirRef.main_diagnosis.forEach((item)=> {
          if(item.key===this.$refs.DirRef.key){
            this.$set(this.formInline2.field[this.indexData], 'type', item.type)
            const timeVal = item.type === 'time' ? [] : undefined
          this.$set(this.formInline2.field[this.indexData], 'value', timeVal)
          this.$set(this.formInline2.field[this.indexData], 'selects', item.value)
          }
          
        });
      }
      if(menuName==='other_diagnosis'){
        this.$refs.DirRef.other_diagnosis.forEach((item)=> {
          if(item.key===this.$refs.DirRef.key){
            this.$set(this.formInline2.field[this.indexData], 'type', item.type)
            const timeVal = item.type === 'time' ? [] : undefined
          this.$set(this.formInline2.field[this.indexData], 'value', timeVal)
          this.$set(this.formInline2.field[this.indexData], 'selects', item.value)
          }
          
        });
      }

      if(menuName==='main_operation'){
        this.$refs.DirRef.main_operation.forEach((item)=> {
          if(item.key===this.$refs.DirRef.key){
            this.$set(this.formInline2.field[this.indexData], 'type', item.type)
            const timeVal = item.type === 'time' ? [] : undefined
          this.$set(this.formInline2.field[this.indexData], 'value', timeVal)
          this.$set(this.formInline2.field[this.indexData], 'selects', item.value)
          }
          
        });
      }
      if(menuName==='other_operation'){
        this.$refs.DirRef.other_operation.forEach((item)=> {
          if(item.key===this.$refs.DirRef.key){
            this.$set(this.formInline2.field[this.indexData], 'type', item.type)
            const timeVal = item.type === 'time' ? [] : undefined
          this.$set(this.formInline2.field[this.indexData], 'value', timeVal)
          this.$set(this.formInline2.field[this.indexData], 'selects', item.value)
          }
          
        });
      }
      if(menuName==='SSJL'){
        this.$refs.DirRef.SSJL.forEach((item)=> {
          if(item.key===this.$refs.DirRef.key){
            this.$set(this.formInline2.field[this.indexData], 'type', item.type)
            const timeVal = item.type === 'time' ? [] : undefined
          this.$set(this.formInline2.field[this.indexData], 'value', timeVal)
          this.$set(this.formInline2.field[this.indexData], 'selects', item.value)
          }
          
        });
      }
      if(menuName==='FYMX'){
        this.$refs.DirRef.FYMX.forEach((item)=> {
          if(item.key===this.$refs.DirRef.key){
            this.$set(this.formInline2.field[this.indexData], 'type', item.type)
            const timeVal = item.type === 'time' ? [] : undefined
          this.$set(this.formInline2.field[this.indexData], 'value', timeVal)
          this.$set(this.formInline2.field[this.indexData], 'selects', item.value)
          }
          
        });
      }

        // if(this.$refs.DirRef.key===this.$refs.DirRef.home[0].key){
        //   const node=this.$refs.DirRef.home[0].type
        //   this.$set(this.formInline2.field[this.indexData], 'type', node)
        // }
        
      
   
      // const node = this.$refs.DirRef[this.$refs.DirRef.length-1].getCheckedNodes(false)
      // this.$set(this.formInline2.field[index], 'type', node[0].data.type)
      // const timeVal = node[0].data.type === 'time' ? [] : undefined
      // this.$set(this.formInline2.field[index], 'value', timeVal)
      // this.$set(this.formInline2.field[index], 'selects', node[0].data.value)
    },
    // 获取select条件
    getSelects() {
      this.$axios3.post('/bl/serach_where', {}).then(res => {
        this.bl = res.data.bl || [];
        if (res.data.dep_name) {
          this.formInline.AAC11N = res.data.dep_name
          this.AAC11N_lock = true
        }
      });
    },
    getTiwensSelects() {
      this.$axios3.post('/bl/tiwenWhere', {}).then(res => {
        this.tiwens = res.data || [];
      });
    },
    // 获取科室
    selectInfo() {
      this.$axios.post('/selectInfo').then(res => {
        this.departmentList = res.data.department;
      });
    },
    // 新增一行
    onPlus(i) {
     if(this.formInline3.field[i]){
      this.formInline3.field[i].formInline2.field.push({
        select_type: 0,
        value: '',
        type: 'input',
        selects: [],
        key: ''


      })
     }
    },
    onMinuss(i){
        this.formInline3.field.splice(i, 1)
    },
    onPluss(){
      this.formInline3.field.push({
        select_type: 0,
        formInline2: {
        AAA01: '',
        AAA02C: '',
        AAB01_START: '',
        AAB01_END: '',
        AAC04_START: '',
        AAC04_END: '',
        ageStart: '',// 自定义需根据ageType转化相应字段
        ageEnd: '',// 自定义需根据ageType转化相应字段
        ageType: 2,
        TIWEN_START: '',
        TIWEN_END: '',
        TIWEN_FIELD: '',
        field: [{
          select_type: 0,
          type: 'input',
          key: '',
          selects: [],
          value: '',
        }]
        }
         
        })
     
    },
    // 减一行
    onMinus(i,j) {
      if(this.formInline3.field[j]){
        this.formInline3.field[j].formInline2.field.splice(i, 1)
      }
      
    },
    // 搜索
    onSearch() {
      const { ageType, ageStart, ageEnd, TIWEN_START, TIWEN_END } = this.formInline2
      if (TIWEN_START) {
        if (TIWEN_START <0 || TIWEN_START > 60) {
          this.$message.error('体温开始温度（不能小于0，不能大于60）')
          return
        }
      }
      if (TIWEN_END) {
        if (TIWEN_END <0 || TIWEN_END > 60) {
          this.$message.error('体温结束温度（不能小于0，不能大于60）')
          return
        }
      }
      if (TIWEN_START && TIWEN_END) {
        if (Number(TIWEN_START) > Number(TIWEN_END)) {
          this.$message.error('体温开始温度不能大于结束温度')
          return
        }
      }
      if (ageType === 1) {
        this.formInline2.AAA40_START = ageStart
        this.formInline2.AAA40_END = ageEnd
      } else if (ageType === 2) {
        this.formInline2.AAA04_START = ageStart
        this.formInline2.AAA04_END = ageEnd
      }
      const params = {
        ...this.formInline,
        ...this.formInline2
      }
      const { field } = JSON.parse(JSON.stringify(this.formInline2))
      field.map(item => {
        if (item.type === 'time') {
          item.value = item.value.join(',')
        }
      })
      params.field = field
      this.$emit('search', params)
    },
    // 导出
    onExport() {
      const { ageType, ageStart, ageEnd } = this.formInline2
      if (ageType === 1) {
        this.formInline2.AAA40_START = ageStart
        this.formInline2.AAA40_END = ageEnd
      } else if (ageType === 2) {
        this.formInline2.AAA04_START = ageStart
        this.formInline2.AAA04_END = ageEnd
      }
      const params = {
        ...this.formInline,
        ...this.formInline2
      }
      const { field } = JSON.parse(JSON.stringify(this.formInline2))
      field.map(item => {
        if (item.type === 'time') {
          item.value = item.value.join(',')
        }
      })
      params.field = field
      this.$emit('export', params)
    },
    // 重置
    onReset() {
      this.formInline = {
        AAA28: '',
        AAC11N: '',
        AAC01_START: '',
        AAC01_END: ''
      }

      this.formInline2 = {
        AAA01: '',
        AAA02C: '',
        AAB01_START: '',
        AAB01_END: '',
        AAC04_START: '',
        AAC04_END: '',
        ageStart: '',// 自定义需根据ageType转化相应字段
        ageEnd: '',// 自定义需根据ageType转化相应字段
        ageType: 2,
        TIWEN_START: '',
        TIWEN_END: '',
        TIWEN_FIELD: '',
        field: [{
          select_type: 0,
          key: '',
          value: '',
        }]
      }

      this.$emit('reset')
    }
  }
}
</script>

<style lang="scss" scoped>
.content-box {
  background: #fff;
  border-radius: 5px;
  margin: 20px;
  .search-box {
    .row-box {
      // display: flex;
      // justify-content: flex-start;
      width: 1000px;
      margin: 0 50px 16px;
    }
  }
  .info-search {
    margin-top: 20px;
    .demo-form-inline {
      width: 1124px;
      margin: 0 auto;
      position: relative;
      .more-btn {
        position: absolute;
        right: -70px;
      }
    }
  }
  .search-btn {
    width: 240px;
  }
}
::v-deep .el-input-group__append {
  width: 65px;
}
::v-deep .el-form--inline .el-form-item {
  margin-right: 20px;
}
.btn-group {
  text-align: center;
  position: relative;
  .btn-group-right {
    position: absolute;
    right: 0;
    top: 0;
  }
}
::v-deep .el-cascader {
  width: 100%;
  .el-input {
    .el-input__inner {
      height: 40px !important;
    }
  }
  .el-cascader__tags {
    .el-tag {
      max-width: 70%;
    }
  }
}
.btn-class{
      display: flex;
      justify-content: flex-end;
    }
    .el-button--primary {
      color: #FFF;
      background-color: #005FA6;
       border-color: #005FA6;
  }
  .el-button:focus, .el-button{
    // border-color: #005FA6 !important;
  }
//   .el-icon-plus:before {
//     content: "\e6d9";
//     color:#005FA6 !important;
//     font-weight: 700;
// }
  // .el-icon-minus{
  //   color:#005FA6 !important;
  //   border-color: #005FA6 !important;
  // }
  .el-button:focus, .el-button:hover {
      color: #409EFF !important;
      border-color: #c6e2ff !important;
      background-color: #ecf5ff !important;
  }
  .el-pager li:not(.disabled).active {
      background-color: #185da6 !important;
      color: #FFF;
  }
  ::v-deep .el-tabs__item:hover {
    color: #00C797 !important;
    cursor: pointer;
}
::v-deep .el-tabs__item.is-active {
  color: #00C797 !important;
}
::v-deep .el-tabs__active-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 2px;
    background-color: #00C797 !important;
    z-index: 1;
}
.caiClass:hover {
    color: #EE8A0C !important;
    border-color: #EE8A0C !important;
    background-color: #fff !important;
   
}
.el-icon-star-on:after {
    content: "\e797";
    color: #EE8A0C
}
::v-deep .el-icon-plus:before {
    content: "\e6d9";
    color: #005FA6 !important;
    font-size: 22px !important;
    font-weight: 700 !important;
    /* border: 2px solid #005fa6; */
}

::v-deep .el-icon-minus:before {
    content: "\e6d8";
    color: #005FA6 !important;
    font-size: 22px !important;
    font-weight: 700 !important;
}

::v-deep .el-icon-circle-plus-outline:before {
    content: "\e723";
    color: #005FA6 !important;
    font-size: 22px;
    font-weight: 700;
}
::v-deep .el-icon-remove-outline:before {
    content: "\e722";
    color: #005FA6 !important;
    font-size: 22px;
    font-weight: 700;
}
.minusClass{
  float: left !important;
   margin-top: 5px !important;
  margin-left: 190% !important;
}
::v-deep .el-descriptions-item__label.has-colon::after {
    content: '';
    position: relative;
    top: -0.5px;
}

::v-deep .el-descriptions__body .el-descriptions__table {
    border-collapse: collapse;
     width: 60% !important; 
    table-layout: fixed;
}
// .plusClass{
//   float: right !important;
//     margin-top: -42px !important;
//     margin-right: -28px !important;
// }
// ::v-deep .el-button--mini.is-circle {
//     padding: 7px;
  
//     float: left !important;
//     // margin-top: 28px !important;
//     margin-left: 450% !important;
// }
.circleClass{
  padding: 7px;
  
  float: left !important;
  //  margin-top: 20px !important;
  margin-left: 222% !important;
}
.dataClass{
  // margin:10px;
  width: 98%;
  height :50px;
  line-height:50px;
  background-color:#ECF5FF;
  // box-shadow:  var(--el-box-shadow-light);
 border-radius: 4px; 
  // padding:20px;
}

// .el-button {
//     display: inline-block;
//     line-height: 1.7;
//     white-space: nowrap;
//     cursor: pointer;
//     background: #FFF;
//     border: 1px solid #DCDFE6;
//     color: #606266;
//     -webkit-appearance: none;
//     text-align: center;
//     -webkit-box-sizing: border-box;
//     box-sizing: border-box;
//     outline: 0;
//     margin: 0;
//     -webkit-transition: .1s;
//     transition: .1s;
//     font-weight: 500;
//     padding: 12px 20px;
//     font-size: 14px;
//     border-radius: 4px;
// }
.heightClass{
  line-height: 1.7 !important;
  border-top-right-radius: 0px !important;
    border-bottom-right-radius: 0px !important;

}
.bottomCirclePlus{
  margin-top: -38px;
}
::v-deep .content-box .info-search .demo-form-inline {
    width: 1124px;
    margin-left: 45px;
    /* margin: 0 auto; */
    position: relative;
}
.hamburger-container {
  background-color:#005FA6;
    line-height: 50px;
    height: 100%;
    float: left;
    cursor: pointer;
    transition: background 0.3s;
    -webkit-tap-highlight-color: transparent;
    border-top-left-radius:4px ;
    border-bottom-left-radius:4px ;
    // &:hover {
    //   background: rgba(0, 0, 0, 0.025);
    // }
  }
  .firClass{
    border-top-left-radius:4px;
    border-bottom-left-radius:4px;
   
   
  }
  ::v-deep .el-input__inner {
    -webkit-appearance: none;
    background-color: #FFF;
    background-image: none;
    // border-top-right-radius:0px !important;
    // border-bottom-right-radius:0px !important;
     border-radius: 4px; 
    border: 1px solid #DCDFE6;
    -webkit-box-sizing: border-box;
    box-sizing: border-box;
    color: #606266;
    display: inline-block;
    height: 40px;
    line-height: 40px;
    outline: 0;
    padding: 0 20px;
    -webkit-transition: border-color .2s cubic-bezier(.645,.045,.355,1);
    transition: border-color .2s cubic-bezier(.645,.045,.355,1);
    width: 100%;
    }
   ::v-deep .el-select>.el-input__inner {
      border-top-right-radius: 4px;
    }
    .el-button:hover {
    // color: #409EFF !important;
    // border-color: #c6e2ff !important;
    // background-color: #ecf5ff !important;
}
::v-deep .el-radio__input.is-checked .el-radio__inner {
    border-color: #005FA6;
    background: #005FA6;
}
::v-deep .el-radio__input.is-checked+.el-radio__label {
    color:#005FA6;
}

::v-deep .el-input__inner {
    // margin-left: 10px !important;
    text-align: center !important;
    
}
::v-deep .el-icon-arrow-up:before {
    content: "\e6e1";
    /* float: left; */
    font-size: 14px;
    font-weight: 700;
    margin-right:10px;
}
::v-deep .el-icon-arrow-down:before {
    content: "\e6df";
    font-size: 14px;
    font-weight: 700;
    margin-right:10px;
}
//EE8A0C
</style>