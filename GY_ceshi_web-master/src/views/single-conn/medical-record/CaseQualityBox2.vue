<template>

  <div class="CaseQualityBox2" :style="{
    width: $route.path == '/whitelist-caseControl' ? '100%' :
      tabsActive == 3 ? '400px' :
        currentTaskName ? '840px' : '400px'
  }">

    <div v-if="tabsActive == 1" class="tabContent">
      <div class="taskNameListAndMessage">
        <el-menu v-if="showTaskNameMenu" :default-active="currentTaskName" class="el-menu-vertical-demo" :style="{
          width: currentTaskName ? '230px' : '100%'
        }">
          <div v-for="item in taskNameList" :key="item.id">
            <el-submenu :index="`${item.id}`" v-if="item.child">
              <template slot="title">
                <span style="padding-left:20px">{{ item.name }}</span>
              </template>
              <div v-for="element in item.child" :key="element.id">
                <el-submenu :index="`${element.parent_id}-${element.id}`" v-if="element.child">
                  <template slot="title">
                    <span style="padding-left:40px">{{ element.name }}</span>
                  </template>
                  <el-menu-item v-for="elementItem in element.child" :key="elementItem.id"
                    :index="`${item.id}-${elementItem.parent_id}-${elementItem.id}`"
                    @click="handleTaskNameSelect(elementItem, `${item.id}-${elementItem.parent_id}-${elementItem.id}`)">
                    <span slot="title">{{ elementItem.name }}</span>
                  </el-menu-item>
                </el-submenu>
                <el-menu-item :index="`${element.parent_id}-${element.id}`" v-else
                  @click="handleTaskNameSelect(element, `${element.parent_id}-${element.id}`)">
                  <span slot="title">{{ element.name }}</span>
                </el-menu-item>
              </div>
            </el-submenu>
            <el-menu-item :index="`${item.id}`" v-else @click="handleTaskNameSelect(item, item.id)">
              <span slot="title">{{ item.name }}</span>
            </el-menu-item>
          </div>
        </el-menu>
        <div class="messageBox" v-if="currentTaskName">
          <div class="message">
            <div v-for="(item, index) in chatMessages" :key="index">
              <div v-if="item.role == 'user'" class="user-message">
                <div class="user-content">
                  <div>{{ item.content }}</div>
                </div>
                <el-avatar src="static/img/avatar07.7b002992.png" fit="contain"></el-avatar>
              </div>
              <div v-if="item.role == 'assistant'" class="record-message">
                <el-avatar :src="require('../../../assets/images/jiqiren.png')" fit="contain"></el-avatar>
                <div class="record-content">
                  <div>{{ item.content }}</div>
                </div>
              </div>
            </div>
          </div>
          <div class="sendMessageBox">
            <div style="width: calc(100%)">
              <textarea class="inputText" v-model="inputMessage" placeholder="请输入您的问题,帮您深度解答"></textarea>
              <el-row type="flex" justify="space-between" align="middle">
                <div @click="showTaskNameMenu = !showTaskNameMenu">
                  <Hamburger />
                </div>
                <el-button type="primary" @click="sendMessage()" icon="el-icon-top" circle></el-button>
              </el-row>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!--病案首页-->
    <div v-else-if="tabsActive == 3" class="tabContent">
      <div ref="box" class="box22" :style="{ width: '100%' }" :class="{ 'nocopy': $route.meta.nocopy }">

        <div class="score-box"
          :class="scoreLevel == '甲' ? 'scoreLevel_1' : (scoreLevel == '乙' ? 'scoreLevel_2' : (scoreLevel == '丙' ? 'scoreLevel_3' : ''))">
          <div>病案评分<span class="score-f">{{ data.score }}分</span></div>
        </div>

        <el-scrollbar ref="scrollRef" class="scrollBox" :style="`height: ${scrollHeight}`">
          <template
            v-if="(!filterFormData.type ? data.data && !!(data.data.length) : data.summary && !!((data.summary[filterFormData.type]).length))">
            <template v-for="(item, index) in (!filterFormData.type ? data.data : data.summary[filterFormData.type])">
              <div class="list-box box-card" :key="index"
                v-if="item.rule_type != '时效性' || (item.rule_type === '时效性' && isVisible)">
                <div class="list-score-tips-box">
                  <el-row type="flex" align="middle" justify="space-between" style="margin-bottom: 15px;">
                    <el-row type="flex">
                      <div class="list-item-score"
                        :class="item.level == -1 ? 'hover-0' : item.level == 1 ? 'hover-1' : 'hover-2'">
                        {{ item.level == -1 ? '预警' : item.level == 1 ? '必改' : '建议' }}
                      </div>
                      <div class="list-item-field" :title="item.error_field">
                        {{ item.error_field }}
                      </div>
                      <div v-if="item.one_no == 1" style="margin-left: 40px;">
                        <no-circle :size="40" />
                      </div>

                    </el-row>
                    <div v-if="item.level != -1" style="color: #DA1515;" class="text-bold">
                      -{{ item.score }}分
                    </div>
                  </el-row>
                  <el-row type="flex" justify="space-between" align="top">
                    <div>
                      <div class="list-item-title">错误描述：</div>
                      <div class="list-item-value" style="margin-bottom: 15px">{{ item.notice }}</div>
                    </div>
                    <el-image class="list-item-image" v-if="item.is_artificial == 0"
                      :src="require('../../../assets/images/zsicon.png')" fit="contain">
                    </el-image>
                    <el-image v-if="item.is_artificial == 1" class="list-item-image"
                      :src="require('../../../assets/images/ysicon.png')" fit="contain">
                    </el-image>
                  </el-row>
                  <div class="list-item-title" @click="item.basis && clickListItem(index)">
                    质控依据 <i :class="`el-icon-arrow-${!item.show ? 'up' : 'down'}`"
                      style="cursor: pointer;font-weight: bold"></i>
                  </div>
                  <div class="list-item-basis-box">
                    <div class="list-basis-text">
                      <div class="list-basis-text-t" :class="!item.show ? 'show' : ''">
                        <div v-for="(yItem, yIndex) of item.basis" :key="yIndex" style="margin-bottom: 10px;">
                          <div v-if="typeof yItem == 'string'">
                            <span class="span-index">{{ yIndex + 1 }}</span>
                            <span class="list-item-value">{{ yItem }}</span><br />
                          </div>
                          <el-row v-else class="list-basis-text-t-noString">
                            <span class="span-index">{{ yIndex + 1 }}</span>
                            <span v-for="(cItem, cIndex) in yItem" :key="cIndex" v-if="!isNaN(parseFloat(cIndex))">
                              <span class="list-item-value">{{ cItem }}</span>
                              <div style="height: 10px"></div>
                            </span>
                          </el-row>
                        </div>
                      </div>
                      <div class="list-basis-bottom-box" v-if="$route.path == '/whitelist-caseControl'">
                        <div v-if="item.rule_type === '时效性'" :class="item.isKnow === 1 ? 'appeal_in_ignore' : 'gotIt'"
                          @click="item.isKnow !== 1 && gotIt(item)">知道了</div>
                        <div class="list-basis-bottom-tips">
                          <div class="appeal_no" @click="clickAppeal('appeal_no', item, 2)"
                            v-if="item.appeal_type == 2 && item.appeal_status == 2">驳回</div>
                          <div class="appeal_progress" style="cursor: pointer;"
                            v-if="item.appeal_type == 2 && item.appeal_status == 0"
                            @click="clickAppeal('appeal_ing', item, 2)">申诉中</div>
                          <div class="appeal_in_edit" @click="clickAppealEdit(item, 2)" v-if="item.is_artificial == 1">
                            已整改</div>
                        </div>
                        <div class="list-basis-bottom-btn" v-if="item.appeal_type == 0">
                          <div class="appeal" @click="clickAppeal('appeal', item, 2)">申诉</div>
                          <div class="appeal_in_ignore" @click="clickAppeal('appeal_in_ignore', item, 2)">忽略</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </template>
          <template v-else>
            <!-- <el-empty :image-size="100">暂无质控结果</el-empty> -->
            <div class="empty-result">暂无质控结果</div>
          </template>
        </el-scrollbar>
      </div>
    </div>
    <AppealModal ref="AppealModalRef" @onUpdate="getTableData()" />


    <!-- 消息中心 -->
    <message-center ref="messageCenterRef"></message-center>

    <!-- 质控记录 -->
    <QualityControlRecordDialog ref="qualityControlRecordDialogRef" @funGoto="funGoto" />

    <div v-if="showPageType === 1">
      <ButtonPopUp ref="buttonPopUpRef" @toggleFilter="handleFilterToggle" :data="data" :baseInfo="baseInfo"
        @showMessage="showMessage" @depCaseQualityList="depCaseQualityList"
        @generateMedicalRecords="generateMedicalRecords" :type="bottomType" @toggleVisibility="toggleVisibility" />
    </div>


    <div class="filterBox popup-filter" ref="filterPopupRef" v-if="filterCollapse" :style="filterPopupStyle">
      <div class="filter-arrow"></div>
      <!-- 筛选内容 -->
      <div class="filter-content">
        <el-col :span="24" v-for="(value, key) in filterList"
          :class="`${filterFormData.type === key ? 'filterRow selected' : 'filterRow'}`" :key="key">
          <div class="filterRowContent" @click="setFilterForm(key)">
            <div class="filterLabel text-bold">{{ value.label }}</div>
            <span>({{ getSummaryCount(key) }})</span>
          </div>
        </el-col>
      </div>
    </div>
  </div>

</template>

<script>

import QualityControlRecordDialog from '@/views/allcase/components/quality-control-record-dialog.vue'
import MessageCenter from '@/views/allcase/components/message-center-dialog'
import ButtonPopUp from '@/components/button-pop-up'
import Hamburger from '@/components/Hamburger';
import NoCircle from '../../allcase/components/no-circle.vue';
import AppealModal from '@/components/appealModal/index.vue'
import { setCorrection } from '@/api/qc'
import { examineAppeal, getAppealData, getBrry } from '@/api/qc';
export default {
  components: {
    Hamburger,
    AppealModal,
    NoCircle,
    ButtonPopUp,
    MessageCenter,
    QualityControlRecordDialog
  },

  props: {
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
    },
    MED_REC_ID: {
      type: String,
      default() {
        return ''
      }
    }
  },

  data() {
    return {
      showPageType:1,
      params: {
        id: this.$props.MED_REC_ID,
        show_correction: this.$route.path == '/whitelist-caseControl' ? 2 : 1,
      },
      isVisible: true,
      bottomType: 'normal',
      filterPopupStyle: {},
      data: {},
      showPage: 'normal',
      bigModuleParams: {},
      inputMessage: '',//消息输入框
      chatMessages: [],//内容显示
      tabsActive: '3',//tabs选择
      tabsArray: [
        { 'name': '3', 'label': '质控结果' },
        { 'name': '1', 'label': '病历生成' },
        // {'name':'2','label':'病案首页'},

        // {'name':'4','label':'编目首页'},
      ],
      is_show: true,
      taskNameList: [],
      currentTaskName: '',
      showTaskNameMenu: true,
      baseInfo: {},
      filterCollapse: false,
      filterFormData: {
        type: ''
      },
      filterList: {
        ryjl: {
          label: '入院记录',
          value: 0
        },
        bcjl: {
          label: '病程记录',
          value: 0
        },
        ssjl: {
          label: '手术记录',
          value: 0
        },
        cyjl: {
          label: '出院记录',
          value: 0
        },
        tys: {
          label: '同意书',
          value: 0
        },
        qt: {
          label: '其他文书',
          value: 0
        }
      }
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
      if (score > 90) {
        str = '甲'
      } else if (score < 75) {
        str = '丙'
      } else if (score <= 90 && score >= 75) {
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
  watch: {
    // 监听组件是否准备好
    isButtonPopUpReady(newVal) {
      if (newVal) {
        this.calculateFilterPopupStyle();
      }
    },
    // 监听筛选框显示状态（现在监视的是data中的filterCollapse）
    filterCollapse(newVal) {
      if (!newVal && this.isButtonPopUpReady) {
        this.calculateFilterPopupStyle();
      }
    }
  },
  mounted() {
    this.getInitData();
    this.getAiInfo();
    document.addEventListener('click', this.handleOutsideClick);
  },
  beforeDestroy() {
    // 组件销毁前移除事件监听，防止内存泄漏
    document.removeEventListener('click', this.handleOutsideClick);
  },
  methods: {
    handleOutsideClick(event) {
      // 如果弹框处于显示状态
      if (this.filterCollapse) {
        // 获取弹框元素
        const filterPopup = this.$refs.filterPopupRef;
        // 获取按钮弹窗元素
        const buttonPopUpEl = this.$refs.buttonPopUpRef?.$el;

        // 判断点击位置是否在弹框内部或触发按钮内部
        const isClickInsideFilter = filterPopup && filterPopup.contains(event.target);
        const isClickInsideButton = buttonPopUpEl && buttonPopUpEl.contains(event.target);

        // 如果点击位置在弹框和触发按钮外部，则关闭弹框
        if (!isClickInsideFilter && !isClickInsideButton) {
          this.filterCollapse = false;
        }
      }
    },

    generateMedical(){
      this.showPageType = 2
      this.tabsActive = '1'
    },
    funGoto(val) {
      this.getTableData();
      this.params.id = val;
    },

    gotIt(item) {
      this.$axios2.post('/tk/is_know', {
        zyh: this.$route.query.id,
        rule_id: item.rule_id
      }).then(res => {
        if (res.code === 200) {
          this.getTableData();
        }
      })
    },

    toggleVisibility(isVisible) {
      this.isVisible = isVisible
    },

    depCaseQualityList(list) {
      this.tabsActive = '3'
      this.bottomType = 'normal'
      this.$refs.qualityControlRecordDialogRef.init(list)
    },

    generateMedicalRecords() {
      this.tabsActive = '1'
      this.bottomType = 'AI'
    },

    showMessage(type, msgNum) {
      this.tabsActive = '3'
      this.bottomType = 'normal'
      if (type == 'normal') {

      } else {
        this.$refs.messageCenterRef.init(msgNum)
      }
    },

    calculateFilterPopupStyle() {
      // 确保DOM已更新
      this.$nextTick(() => {
        // 查找图标元素
        const buttonPopUpEl = this.$refs.buttonPopUpRef.$el;
        const iconEl = buttonPopUpEl.querySelector('svg.icon.filter-icon') ||
          buttonPopUpEl.querySelector('.icon.filter-icon') ||
          buttonPopUpEl.querySelector('svg');

        // 计算位置
        const iconRect = iconEl.getBoundingClientRect();
        const parentRect = this.$el.getBoundingClientRect();

        this.filterPopupStyle = {
          left: `${iconRect.right + window.scrollX - 45}px`,
          top: `${iconRect.top + window.scrollY - 280}px`,
          position: 'fixed',
          zIndex: 9999
        };
      });
    },
    // // 处理筛选框显示/隐藏
    // handleFilterToggle(flag) {
    //   this.filterCollapse = flag;
    //   // 显示时重新计算位置
    //   if (flag) {
    //     this.calculateFilterPopupStyle();
    //   }
    // },
    // 处理筛选框显示/隐藏的方法中添加对事件的兼容处理
    handleFilterToggle(flag) {
      this.filterCollapse = flag;
      if (flag) {
        this.calculateFilterPopupStyle();
        // 确保弹框显示后能正确捕获点击事件
        this.$nextTick(() => {
          const filterPopup = this.$refs.filterPopupRef;
          if (filterPopup) {
            filterPopup.focus();
          }
        });
      }
    },
    getSummaryCount(key) {
      if (!this.data.summary || !this.data.summary[key]) {
        return 0;
      }
      return Array.isArray(this.data.summary[key]) ? this.data.summary[key].length : 0;
    },
    setFilterForm(key) {
      console.log('>>>>>>>', key)
      this.filterFormData.type = key;
    },
    getInitData() {
      if (this.$route.path != '/whitelist-caseControl') {
        this.getTableData();
      }
      this.getTaskNameList();
      this.getBaseInfo();
    },
    getBaseInfo() {
      getBrry({ zyh: this.$props.MED_REC_ID }).then(res => {
        if (res.code == 200) {
          this.baseInfo = res.data || {}
        }
      })
    },
    getTaskNameList() {
      this.$axios2.get('/big_model/get_task_name', {}).then(res => {
        if (res.code == 200) {
          this.taskNameList = res.data || []
        }
      })
    },

    getAiInfo() {
      this.$axios2.get('/get_ai_info', {}).then(res => {
        if (res.code == 200) {
          this.bigModuleParams.Authorization = res.data.key
          this.bigModuleParams.url = res.data.url
          this.bigModuleParams.model = res.data.modelname
        }
      })
    },
    handleTaskNameSelect(menuItem, indexId) {
      this.currentTaskName = indexId
      const indexIdStr = String(indexId);
      this.$axios.post('/get_big_model_task', {
        rule_id: indexIdStr.split('-').map(Number),
        MED_REC_ID: this.$props.MED_REC_ID
      }).then(res => {
        if (res.code == 200) {
          this.sendMessage(res.data)
        }
      })
      window.electronAPI.middle()
    },
    //发送消息
    async sendMessage(params) {
      const messages = params ? params : this.inputMessage.trim();
      if (messages) {
        //添加用户信息到聊天记录
        this.chatMessages.push({
          role: 'user',
          content: messages
        });

        // 清空输入框
        this.inputMessage = '';
        // this.$axios.post('/predict', {
        //   query: messages
        // }).then(res => {
        //   if(res.code == 200){  
        //    // 添加模型响应消息到聊天记录
        //     this.chatMessages.push({
        //       role: 'assistant',
        //       content: res.data
        //     });   
        //   }
        // })

        //大模型请求
        const modelResponse = await this.simulateModelResponse(messages);

        // 添加模型响应消息到聊天记录
        this.chatMessages.push({
          role: 'assistant',
          content: modelResponse
        });
      }
    },
    //大模型请求
    simulateModelResponse(userMessage) {
      //请求内容
      const options = {
        method: 'POST',
        // headers: {
        //   Authorization: 'Bearer sk-ttnryoxayznjgquagdfwjefbkubdnezabsotuczokunwkjin',
        //   'Content-Type': 'application/json'
        // },
        headers: {
          Authorization: this.bigModuleParams.Authorization,
          'Content-Type': 'application/json'
        },
        // body: '{"model":"Qwen/QwQ-32B","messages":[{"role":"user","content":"当前时间"}],"stream":false,"max_tokens":512,"stop":null,"temperature":0.7,"top_p":0.7,"top_k":50,"frequency_penalty":0.5,"n":1,"response_format":{"type":"text"},"tools":[{"type":"function","function":{"description":"<string>","name":"<string>","parameters":{},"strict":false}}]}'
      };
      const body = {};
      // body.model = "deepseek-ai/DeepSeek-R1";
      body.model = this.bigModuleParams.model;
      body.url = this.bigModuleParams.url;
      body.messages = [{ "role": "user", "content": userMessage }];
      options.body = JSON.stringify(body);

      //请求 https://api.siliconflow.cn/v1/chat/completions
      return fetch(this.bigModuleParams.url, options).then(response => {
        return response.json();
      }).then(data => {
        const content = data.choices[0].message.content;
        return content;
      }).catch(err => console.error(err));
    },
    getTableData(isRender = false) {

      this.$axios2.post('/get_case_quality_v2', this.params, {
        headers: {
          isNoLoading: isRender ? true : false
        }
      }).then(res => {
        this.$nextTick(() => {
          if (isRender) {
            if (this.data.is_case != res.data.is_case) {
              this.data = res.data;
            }
          } else {
            this.data = res.data;
          }
          let ruleId = this.storageGet('getDataRule');
          if (this.data && this.data.data && Array.isArray(this.data.data) && ruleId) {
            this.data.data = this.data.data.filter(item => item.rule_id == ruleId).concat(this.data.data.filter(item => item.rule_id != ruleId))
          }
        })
      });
    },
    clickAppealEdit(item, quality_type) {
      this.$confirm('是否确认已整改?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
        customClass: 'customClass-el-message-box-center'
      }).then(() => {
        setCorrection({
          id: item.id,
          quality_type
        }).then(res => {
          if (res.code == 200) {
            this.$message.success('已整改成功！')
            this.getTableData()
          }
        });
      }).catch(() => {
      });
    },
    onScroll(index) {
      const el = this.$el.querySelector(`.category${index}`);
      const node = el.parentNode.parentNode.parentNode
      this.$refs["scrollRef"].wrap.scrollTop = node.offsetTop;
    },
    hightRight(hightKeyWord, bllb, zyh) {
      this.$emit('hightRight', hightKeyWord, bllb, zyh)
    },
    clickListItem(idx) {
      if (this.filterFormData.type) {
        (this.data.summary[this.filterFormData.type])[idx].show = !((this.data.summary[this.filterFormData.type])[idx]).show
      } else {
        this.data.data[idx].show = !this.data.data[idx].show
      }
      this.$forceUpdate();
    },
    // 点击申诉、忽略按钮
    clickAppeal(type, items, quality_type) {
      if (type == 'appeal_in_ignore') {
        this.$refs.AppealModalRef.handleIgnore(items, quality_type)
      } else {
        this.$refs.AppealModalRef.openAppealDialog(type, items, quality_type,"single")
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.CaseQualityBox2 {
  height: 100%;
  //padding-bottom: 170px;

  .tabContent {
    // height: calc(100% - 94px);
    padding-bottom: 50px;
  }

  .text-bold {
    font-weight: bold;
  }

  .problem_count {
    color: rgba(239, 31, 58, 1);
    font-size: 14px;
    font-weight: 700;
    line-height: 20px;
  }

  .filterBox {
    // padding: 0 5px;
    // display: grid;
    // grid-template-columns: repeat(3, 1fr); /* 创建4列，每列占据可用空间的1份 */   
    // gap: 5px; /* 可选，添加间隙 */  
    font-size: 14px;
    line-height: 23px;
    margin-bottom: 10px;

    .filterRow {
      cursor: pointer;
      line-height: 25px;

      .filterRowContent {
        text-align: center;

        .filterLabel {
          width: 56px;
          white-space: nowrap;
          text-align: right;
          display: inline-block;
        }
      }
    }

    .selected {
      color: #409EFF
    }
  }
}

::v-deep.el-submenu {
  &>.el-submenu__title {
    padding-left: 0 !important;

    .el-submenu__icon-arrow {
      display: block !important;
    }
  }
}

.taskNameListAndMessage {
  width: 100%;
  height: 100%;
  overflow-y: auto;
  background-color: white;
  border-radius: 10px;
  display: flex;

  .el-menu-vertical-demo {
    // width:340px;
    height: 100%;
    overflow-y: scroll;
    width: 230px;
  }
}

.messageBox {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100%;

  .message {
    flex: 1;
    margin-bottom: 20px;
    max-height: 85%;
    overflow-y: auto;
    height: 480px;
    background-color: white;
    border-radius: 10px;

    .user-message {
      display: flex;
      margin: 10px 10px 20px 10px;
      justify-content: right;

      .user-content {
        line-height: 40px;
        margin-right: 10px;
        border-radius: 5px;
        flex: 1;

        & div {
          background: #bee2f8;
          padding: 0 10px;
          float: right;
        }
      }
    }

    .record-message {
      display: flex;
      margin: 10px 10px 20px 10px;

      .record-content {
        margin-left: 10px;
        border-radius: 5px;
        line-height: 40px;
        flex: 1;

        & div {
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
          padding: 0 10px;
          float: right;
        }
      }
    }
  }

  .sendMessageBox {
    margin: 10px;
    padding: 10px;
    border: 1px solid #bbb;
    border-radius: 8px;
    display: flex;
    height: 150px;

    .inputText {
      height: 80px;
      border: none;
      outline: none;
      width: 100%;
      resize: none;
    }
  }
}



::v-deep .el-scrollbar__wrap {
  overflow-x: hidden;
}

::v-deep .el-divider--horizontal {
  margin: 10px 0;
}

.empty-result {
  color: #909399;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 80vh;
  /* 视图高度 */
  margin: 0;
  font-size: 22px;
}

.box22 {
  padding: 0 10px;
  background: #FFFFFF;
  border-radius: 5px;
  height: 100%;
  overflow-y: scroll;
  // display: flex;
  // flex-direction: column;

  .score-box {
    width: 100%;
    margin-bottom: 16px;
    padding: 30px 50px 30px 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #fff;
    position: sticky;
    top: 0;
    div {
      font-size: 20px;
    }

    .score-f {
      padding-left: 20px;
      font-size: 20px;
    }

    .score-dj,
    .score-f {
      font-weight: bold;
    }
  }

  .score-box.scoreLevel_1 {
    background: rgb(11, 133, 63);
    background-image: url('../../../assets/images/icon-jia.png');
    background-repeat: no-repeat;
    background-size: 60px 52px;
    background-position: 80% 50%;
  }

  .score-box.scoreLevel_2 {
    background: rgb(152, 112, 20);
    background-image: url('../../../assets/images/icon-yi.png');
    background-repeat: no-repeat;
    background-size: 60px 52px;
    background-position: 80% 50%;
  }

  .score-box.scoreLevel_3 {
    background: rgb(199, 54, 13);
    background-image: url('../../../assets/images/icon-bing.png');
    background-repeat: no-repeat;
    background-size: 60px 52px;
    background-position: 80% 50%;
  }


  .card-box-noStyle {
    background: transparent !important;
    padding: 0px !important;
    margin-bottom: 8px !important;
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

    .card-icon-btn {
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

  .box-card .el-table ::v-deep tr {
    background: transparent;
  }

  ::v-deep .el-table__row {
    background: #185DA6 !important;
    color: #FFFFFF;

    .el-icon-arrow-right {
      color: #FFFFFF;
    }
  }

  ::v-deep .el-descriptions__body {
    background: transparent;
  }

  ::v-deep .el-table tr {
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

.span-index {
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

::v-deep .el-tag {
  height: auto;
  line-height: 22px;
}

// =================   2024-07-27 新样式  ↓  ===============
.font-size12 {
  font-size: 12px;
}

.list-box {
  width: 100%;
  padding: 10px 0;

  .title-color {
    color: rgba(27, 100, 176, 1);
    font-weight: bold;
  }

  .list-score-tips-box {
    width: 100%;
    padding: 0 10px;
    box-sizing: border-box;
    position: relative;

    .list-item-image {
      // position: absolute;
      // top: 0;
      // right: 0;
      width: 25px;
      height: 25px;
    }

    .list-item-title {
      font-family: PingFang-SC, PingFang-SC;
      font-weight: bold;
      font-size: 18px;
      color: #333333;
      line-height: 25px;
      text-align: left;
      font-style: normal;
      margin-bottom: 10px;
    }

    .list-item-value {
      font-family: PingFangSC, PingFang SC;
      font-weight: 400;
      font-size: 14px;
      color: #333333;
      line-height: 20px;
      text-align: left;
      font-style: normal;
    }

    .list-item-field {
      padding: 10px;
      background: #D3E3FF;
      border-radius: 8px;
      font-family: PingFangSC, PingFang SC;
      font-weight: 600;
      font-size: 16px;
      color: #333333;
      line-height: 16px;
      text-align: center;
      font-style: normal;
      // max-width: 200px;
      text-overflow: ellipsis;
      white-space: inherit;
      overflow: hidden;
    }

    .list-item-score {
      padding: 10px 15px;
      border-radius: 6px;
      font-family: DINAlternate, DINAlternate;
      font-weight: bold;
      font-size: 16px;
      // color: #DA1515;
      line-height: 16px;
      text-align: center;
      font-style: normal;
      margin-right: 5px;

      &.hover-1 {
        background: #FFDFDF;
        color: #DA1515;
      }

      &.hover-2 {
        background: rgb(190, 226, 248);
        color: rgb(52, 140, 235);
      }

      &.hover-0 {
        background: rgb(253, 246, 236);
        color: rgb(230, 162, 60);
      }
    }

    .list-item-basis-box {
      .list-basis-title {
        padding: 10px 0 10px 10px;
        position: relative;

        &>span {
          font-size: 12px;
          color: rgba(27, 100, 176, 1);
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

        .list-basis-text-t {
          height: 0;
          overflow: hidden;
          position: relative;

          &>div:last-child {
            margin-bottom: 0 !important;
          }

          &.show {
            height: auto;
            // padding: 10px 0 10px 10px;
          }

          .list-basis-text-t-noString {
            &>span:last-child {
              div {
                height: 0 !important;
              }
            }
          }
        }
      }

      .list-basis-bottom-box {
        margin-top: 14px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 10px;

        .list-basis-bottom-tips {
          flex: 1;
          display: flex;
          gap: 10px;
        }

        .list-basis-bottom-btn {
          flex: 1;
          display: flex;
          justify-content: flex-end;
          gap: 10px;
          // width: auto;
        }
      }
    }

    // .list-left-score{
    //   width: 70px;
    //   height: 66px;
    //   text-align: center;
    //   font-size: 20px;
    //   font-weight: 700;
    //   position: relative;
    //   display: flex;
    //   flex-direction: column;
    //   align-items: center;
    //   justify-content: center;
    //   padding: 10px 0;
    //   &.hover-1{
    //     background: rgb(254, 240, 240);
    //     color: rgb(238, 14, 14);
    //     border-right: 3px solid rgb(238, 14, 14);
    //   }
    //   &.hover-2{
    //     background: rgb(236, 245, 255);
    //     color: rgb(52, 140, 235);
    //     border-right: 3px solid rgb(52, 140, 235);
    //   }
    //   &.hover-0{
    //     background: rgb(253, 246, 236);
    //     color: rgb(230, 162, 60);
    //     border-right: 3px solid rgb(230, 162, 60);
    //   }
    //   &>div{
    //     font-size: 20px;
    //   }
    // }
    // .list-right-tips{
    //   flex: 1;
    //   font-size: 12px;
    //   padding-left: 10px;
    //   .notice-box{
    //     margin-top: 8px;
    //   }
    // }
  }

  // .list-basis-box{
  //   .list-basis-title{
  //     padding: 10px 0 10px 10px;
  //     position: relative;
  //     &>span{
  //       font-size: 12px;
  //       color: rgba(27,100,176,1);
  //       font-weight: bold;
  //     }
  //     .typeImg {
  //       width: 34px;
  //       height: 34px;
  //       position: absolute;
  //       top: 0;
  //       right: 12px;
  //       z-index: 999;
  //     }

  //   }
  //   .list-basis-text {
  //     height: auto;
  //     .list-basis-text-t{
  //       height: 0;
  //       overflow: hidden;
  //       position: relative;
  //       &.show{
  //         height: auto;
  //         padding: 10px 0 10px 10px;
  //       }
  //     }
  //   }

  //   .list-basis-bottom-box{
  //     margin-top: 14px;
  //     width: 100%;
  //     display: flex;
  //     align-items: center;
  //     justify-content: space-between;
  //     padding: 0 10px;
  //     .list-basis-bottom-tips{
  //       flex: 1;
  //       display: flex;
  //       gap: 10px;
  //     }
  //     .list-basis-bottom-btn{
  //       flex: 1;
  //       display: flex;
  //       justify-content: flex-end;
  //       gap: 10px;
  //       // width: auto;
  //     }
  //   }
  // }
}


.CaseQualityBox2 {
  position: relative; // 确保筛选框相对父容器定位
}

// 筛选弹框样式
.filterBox.popup-filter {
  position: absolute;
  width: 200px;
  z-index: 9999;
  padding: 0;
  background: rgba(50, 50, 50, 0.9);
  border-radius: 8px;
  box-shadow: 0 3px 15px rgba(0, 0, 0, 0.3);
  overflow: hidden;
  animation: fadeIn 0.2s ease-out;
}

// 箭头指向加号左侧
.filter-arrow {
  position: absolute;
  top: 10px;
  left: -12px; // 箭头在筛选框左侧
  width: 0;
  height: 0;
  border-top: 6px solid transparent;
  border-bottom: 6px solid transparent;
  border-right: 6px solid rgba(50, 50, 50, 0.9); // 箭头指向右侧(指向加号)
}

// 筛选内容样式 - 强制左对齐
.filter-content {
  padding: 5px 0;
  text-align: left !important;
}

.popup-filter .filterRow {
  width: 100%;
  padding: 10px 15px;
  margin: 0;
  color: white;
  text-align: left !important;
  white-space: nowrap;
  cursor: pointer;
  transition: background-color 0.2s ease;

  &:hover {
    background-color: rgba(255, 255, 255, 0.1);
  }
}

.popup-filter .filterRowContent {
  text-align: left !important;
  display: flex;
  align-items: center;
  justify-content: flex-start;
}

.popup-filter .filterLabel {
  width: auto;
  text-align: left !important;
  flex: 1;
  padding: 0;
  margin: 0;
}

.popup-filter .filterRowContent span {
  color: rgba(255, 255, 255, 0.8);
  font-size: 12px;
  margin-left: 10px;
}

.popup-filter .selected {
  background-color: rgba(64, 158, 255, 0.2);
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateX(-5px);
  }

  to {
    opacity: 1;
    transform: translateX(0);
  }
}
</style>
