<template>

    <div class="CaseQualityBox21">
        <div class="tabContent">
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
                                <el-avatar :src="require('@/assets/images/jiqiren.png')" fit="contain"></el-avatar>
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

    </div>
</template>

<script>
import Hamburger from '@/components/Hamburger';
export default {

    components: { Hamburger },
    data() {
        return {
            inputMessage: '',//消息输入框
            chatMessages: [],//内容显示
            taskNameList: [],
            currentTaskName: '',
            showTaskNameMenu: true,
            bigModuleParams: {},
        }
    },

    mounted() {
        this.getTaskNameList()
        this.getAiInfo()
    },

    methods: {

        init() {
            this.getTaskNameList()
        },
        getTaskNameList() {
            this.$axios2.get('/big_model/get_task_name', {}).then(res => {
                if (res.code == 200) {
                    this.taskNameList = res.data || []
                    console.log(this.taskNameList)
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
                MED_REC_ID: this.$route.path == '/whitelist-bmyQualityResult'  ? this.$route.query.ZYH : this.$route.query.id
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
    }


}
</script>

<style lang="scss" scoped>
.CaseQualityBox21 {
    height: 100vh;

    .tabContent {
        height: calc(100vh - 94px);
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
    display: flex;
    flex-direction: column;

    .score-box {
        width: 100%;
        margin-bottom: 16px;
        padding: 30px 50px 30px 20px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: #fff;

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


.CaseQualityBox21 {
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