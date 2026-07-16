<template>
    <div class="button-container">
        <el-card class="box-card">
            <!-- 基本信息行 -->
            <el-row type="flex" justify="space-between"
                v-if="type == 'normal'">
                <span>
                    <span class="text-bold">病案号：</span>
                    <span class="desc-value">{{ baseInfo.AAA28 }}</span>
                </span>
                <span>
                    <span class="text-bold">床号：</span>
                    <span class="desc-value">{{ baseInfo.CH }}</span>
                </span>
                <span>
                    <span class="text-bold">姓名：</span>
                    <span class="desc-value">{{ baseInfo.BRXM }}</span>
                </span>
            </el-row>

            <!-- 折叠/展开控制器 -->
            <div class="divider-wrapper" v-if="type == 'normal'">
                <el-row class="divider-row" type="flex" justify="center" align="middle">
                    <div class="divider-line"></div>
                    <div class="divider-content" @click="toggleCollapse">
                        <svg t="1756521788634" class="icon" viewBox="0 0 1024 1024" version="1.1"
                            xmlns="http://www.w3.org/2000/svg" p-id="8640" width="16" height="16"
                            :class="{ 'rotate-icon': isCollapsed }">
                            <path
                                d="M1024 512C1024 227.194435 793.6 0 512 0S0 230.4 0 512 230.4 1024 512 1024 1024 796.805565 1024 512z"
                                fill="#EEEEEE" opacity=".502" p-id="8641"></path>
                            <path
                                d="M205.289739 421.932522H822.316522c14.514087 0 27.202783 13.534609 27.202782 29.028174 0 15.449043-12.688696 29.028174-27.202782 29.028174H205.245217c-14.514087 0-27.202783-13.57913-27.202782-29.028174 0-15.493565 12.688696-29.028174 27.202782-29.028174z m301.234087 359.913739l273.986783-199.323826c12.733217-9.661217 29.028174-5.787826 38.110608 7.746782a29.829565 29.829565 0 0 1-7.257043 40.648348l-288.50087 208.940522c-9.082435 7.746783-21.815652 7.746783-32.678956 0l-283.069218-210.899478c-12.688696-9.661217-14.514087-27.069217-7.257043-40.648348 7.257043-13.534609 25.377391-15.449043 38.110609-7.702261l268.55513 201.238261zM205.289739 267.130435H822.316522c14.514087 0 27.202783 13.534609 27.202782 29.028174 0 15.493565-12.688696 29.028174-27.202782 29.028174H205.245217C190.775652 325.186783 178.086957 311.652174 178.086957 296.158609 178.086957 280.665043 190.775652 267.130435 205.289739 267.130435z"
                                fill="#999999" p-id="8642"></path>
                        </svg>
                        <span class="collapse-text">{{ isCollapsed ? '展开' : '折叠' }}</span>
                    </div>
                    <div class="divider-line"></div>
                </el-row>
            </div>

            <!-- 可折叠内容区域 -->
            <transition name="slide-fade">
                <div v-if="!isCollapsed" class="collapsible-content">
                    <el-row class="expanded-row" type="flex" justify="space-between" align="middle"
                        v-if="type == 'normal' && middleShow ">

                        <div class="left-content">
                            <svg t="1756732333644" class="icon filter-icon" viewBox="0 0 1024 1024" version="1.1"
                                xmlns="http://www.w3.org/2000/svg" p-id="7141" width="22" height="22"
                                style="cursor: pointer;margin-right: 4px;" @click="handleFilterClick">
                                <path
                                    d="M512.268258 64.416721c-247.194586 0-447.583279 200.388692-447.583279 447.583279s200.388692 447.583279 447.583279 447.583279 447.583279-200.388692 447.583279-447.583279S759.462844 64.416721 512.268258 64.416721z m0 839.218392c-216.294751 0-391.635113-175.340362-391.635113-391.635113s175.340362-391.635113 391.635113-391.635113 391.635113 175.340362 391.635113 391.635113-175.340362 391.635113-391.635113 391.635113z m195.817044-419.608684H540.241829V316.181932c0-15.45043-12.524165-27.973571-27.973571-27.973571s-27.973571 12.524165-27.973571 27.973571v167.843473H316.45019c-15.45043 0-27.973571 12.524165-27.973571 27.973571 0 15.45043 12.524165 27.973571 27.973571 27.973571h167.843473v167.843474c0 15.45043 12.524165 27.973571 27.973571 27.973571s27.973571-12.524165 27.973571-27.973571V539.973571h167.843474c15.45043 0 27.973571-12.524165 27.973571-27.973571 0.002048-15.45043-12.522118-27.973571-27.972548-27.973571z"
                                    fill="#333" p-id="7142"></path>
                            </svg>


                            <span class="text-bold">问题数量:
                                <span class="red-number">{{ data.data && Array.isArray(data.data) ? data.data.length : 0
                                }}</span>
                            </span>

                            <span class="visibility-icon" @click.stop="toggleVisibility" title="切换可见状态">

                                <svg v-if="isVisible" t="1756525652090" class="icon" viewBox="0 0 1024 1024"
                                    version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="14612" width="20"
                                    height="20">
                                    <path
                                        d="M143.872 512a566.125714 566.125714 0 0 0 126.390857 136.777143C335.725714 699.611429 418.011429 738.742857 512 738.742857c93.988571 0 176.274286-39.204571 241.737143-89.965714A566.125714 566.125714 0 0 0 880.054857 512a566.125714 566.125714 0 0 0-126.317714-136.923429C688.274286 324.388571 605.988571 285.257143 512 285.257143c-94.061714 0-176.274286 39.131429-241.737143 89.819428A566.125714 566.125714 0 0 0 143.872 512z m90.550857-183.149714C306.688 272.822857 401.188571 226.742857 512 226.742857c110.738286 0 205.312 46.08 277.577143 102.107429 72.118857 55.808 123.977143 122.88 150.089143 168.594285l8.338285 14.555429-8.338285 14.482286c-26.112 45.787429-77.970286 112.786286-150.089143 168.594285C717.312 751.030857 622.811429 797.257143 512 797.257143c-110.811429 0-205.312-46.226286-277.577143-102.180572-72.118857-55.808-123.977143-122.88-150.162286-168.594285L76.068571 512l8.265143-14.555429C110.445714 451.730286 162.377143 384.731429 234.422857 328.850286z"
                                        fill="#27272E" opacity=".7" p-id="14613"></path>
                                    <path
                                        d="M512 394.971429a117.028571 117.028571 0 1 0 0 234.057142 117.028571 117.028571 0 0 0 0-234.057142zM336.457143 512a175.542857 175.542857 0 1 1 351.085714 0 175.542857 175.542857 0 0 1-351.085714 0z"
                                        fill="#27272E" opacity=".7" p-id="14614"></path>
                                </svg>

                                <svg v-else t="1756525684122" class="icon" viewBox="0 0 1024 1024" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg" p-id="14931" width="20" height="20">
                                    <path
                                        d="M724.3008 789.0432l36.1984-36.1984L263.6544 256l-36.1984 36.1984 62.0032 62.0032a404.5568 404.5568 0 0 0-129.8688 148.9664l-5.9904 11.776 5.9904 11.776c68.8384 135.168 204.032 218.624 352.6656 218.624 49.3312 0 97.2544-9.2672 141.7216-26.624l70.3232 70.3232z m-212.0448-94.8992c-125.696 0-239.9488-68.096-301.7984-179.2a351.0016 351.0016 0 0 1 115.5584-124.1856l55.4752 55.4752a147.456 147.456 0 0 0 199.1936 199.1936l33.28 33.28a341.5808 341.5808 0 0 1-101.7088 15.4368z m29.4912-87.6544a96.256 96.256 0 0 1-121.344-121.344l121.344 121.344zM870.4 514.944l-5.9904-11.776c-68.8384-134.656-204.032-218.624-352.1536-218.624-43.8528 0-86.528 7.296-126.6688 21.0432l40.96 40.96c27.5968-7.0912 56.32-10.8032 85.7088-10.8032 125.184 0 239.4368 68.096 301.7728 179.2a351.5392 351.5392 0 0 1-103.3216 115.7632l36.2752 36.2496a404.096 404.096 0 0 0 117.4272-140.2368l5.9904-11.776z m-221.2608 54.1952l-41.7024-41.728a96.256 96.256 0 0 0-107.9552-107.9552l-41.728-41.6768a147.456 147.456 0 0 1 191.3856 191.3856z"
                                        fill="#777777" p-id="14932"></path>
                                </svg>
                            </span>
                        </div>

                        <span class="middle-content desc-value">更新时间：{{ data.quality_time }}</span>
                        <div>
                            <el-tag type="danger" v-if="data.is_case == 0" class="font-size12">未质控</el-tag>
                            <el-tag type="danger" v-if="data.is_case == 1" class="font-size12">质控中</el-tag>
                            <el-tag v-if="data.is_case == 2" class="font-size12">已质控</el-tag>
                        </div>
                    </el-row>

                    <!-- 底部按钮 -->
                    <el-row class="expanded-row margin-top" type="flex" justify="space-between" align="middle">
                        <BottomNavButton v-for="menu in menuList" :key="menu.id" :name="menu.title"
                            :imageSrc="menu.pic_url" @click="handleLeftClick(menu.title)" :messageCount="messageCount"
                            @right-click="handleRightClick(menu.title)" />
                    </el-row>
                </div>
            </transition>
        </el-card>

        <div class="dialog-box">
            <el-dialog title="通知" :show-close="false" :visible.sync="rightLoginVisible" width='400px' v-draggable>
                <el-form ref="form" :model="form" label-width="100px">
                    <el-form-item label="登录账号：">
                        <el-input v-model="form.account" placeholder="请输入工号"></el-input>
                    </el-form-item>
                    <el-form-item label="登录密码：">
                        <el-input v-model="form.pwd" placeholder="请输入密码"></el-input>
                    </el-form-item>
                    <el-form-item label="所属科室：">
                        <el-select style="width: 100%" v-model="form.region" placeholder="请选择活动区域">
                            <el-option v-for="(item, index) in deptOptions" :label="item.name" :value="item.id"
                                :key="index"></el-option>
                        </el-select>
                    </el-form-item>
                </el-form>
                <span slot="footer" class="dialog-footer">
                    <el-button size="mini" type="primary" @click="onCreate">保 存</el-button>
                    <el-button size="mini" @click="rightLoginVisible = false">测 试</el-button>
                </span>
            </el-dialog>
        </div>

        <div class="dialog-box">
            <el-dialog title="科室信息" :show-close="false" :visible.sync="deptVisible" width='400px' v-draggable>
                <el-form ref="form" :model="form" label-width="100px">
                    <el-form-item label="所属科室：">
                        <el-select style="width: 100%" v-model="form.region" placeholder="请选择科室">
                            <el-option v-for="(item, index) in deptOptions" :label="item.name" :value="item.id"
                                :key="index"></el-option>
                        </el-select>
                    </el-form-item>
                </el-form>
                <span slot="footer" class="dialog-footer">
                    <el-button size="mini" type="primary" @click="deptHandle">确认</el-button>
                </span>
            </el-dialog>
        </div>

    </div>
</template>

<script>
import BottomNavButton from '@/components/bottom-nav-button'
import { clearAllMessages, markMessageAsRead } from '@/api/message'

export default {
    name: 'ButtonPopUp',
    components: {
        BottomNavButton,
    },
    props: {
        baseInfo: {
            type: Object,
            default: () => ({})
        },
        type: {
            type: String,
            default: 'normal'
        },
        show: {
            type: Boolean,
            default: false
        },
        data: {
            type: Object,
            default: () => ({})
        },
        middleShow: {
            type: Boolean,
            default: true
        }

    },
    data() {
        return {
            menuList: [],
            deptOptions: [],
            depCaseQualityList: [],
            deptVisible: false,
            messageCount: 0,
            isFlag: false,
            zyh: this.$route.path == '/whitelist-bmyQualityResult'  ? this.$route.query.ZYH : this.$route.query.id ,
            isCollapsed: this.type == 'normal' ? true : false,
            isVisible: true,
            rightLoginVisible: false,
            filterFormData: {
                type: ''
            },
            form: {
                account: '',
                pwd: '',
                date1: '',
                date2: '',
                delivery: false,
                type: [],
                resource: '',
                desc: ''
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
    directives: {
        draggable: {
            inserted(el) {
                const dialog = el.querySelector('.el-dialog');
                const header = el.querySelector('.el-dialog__header');

                if (!dialog || !header) return;

                let startX, startY, initialLeft, initialTop;
                let isDragging = false;

                // 鼠标按下事件
                header.addEventListener('mousedown', (e) => {
                    isDragging = true;
                    startX = e.clientX;
                    startY = e.clientY;
                    initialLeft = dialog.offsetLeft;
                    initialTop = dialog.offsetTop;

                    // 添加样式表示可拖拽
                    header.style.cursor = 'move';
                });

                // 鼠标移动事件
                document.addEventListener('mousemove', (e) => {
                    if (!isDragging) return;

                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;

                    // 设置对话框位置
                    dialog.style.left = `${initialLeft + dx}px`;
                    dialog.style.top = `${initialTop + dy}px`;
                    dialog.style.margin = '0';
                });

                // 鼠标释放事件
                document.addEventListener('mouseup', () => {
                    isDragging = false;
                    header.style.cursor = '';
                });
            }
        }
    },
    mounted() {
        this.get_menu()
        this.get_all_department()
        this.get_msg_count()

        // 监听窗口大小变化，确保卡片始终固定在底部
        window.addEventListener('resize', this.adjustCardPosition);
    },
    beforeDestroy() {
        // 移除事件监听
        window.removeEventListener('resize', this.adjustCardPosition);
    },
    methods: {
        // 调整卡片位置，确保固定在底部
        adjustCardPosition() {
            const container = document.querySelector('.button-container');
            if (container) {
                // 确保不会超出视口
                const windowHeight = window.innerHeight;
                const cardHeight = container.offsetHeight;
                if (cardHeight > windowHeight * 0.8) {
                    // 如果卡片过高，限制最大高度
                    container.style.maxHeight = `${windowHeight * 0.8}px`;
                    container.style.overflowY = 'auto';
                } else {
                    container.style.maxHeight = 'none';
                    container.style.overflowY = 'visible';
                }
            }
        },

        deptHandle(){
            this.$store.deptHandle
        },

        // 根据菜单标题执行不同的左击操作
        handleLeftClick(title) {
            switch (title) {
                case '登录':
                    this.leftLogin();
                    break;
                case '消息':
                    // 消息的左击处理
                    this.leftMessage();
                    break;
                case '质控记录':
                    // 质控记录的左击处理
                    this.handleQualityRecordClick();
                    break;
                case '病历生成':
                    // 病历生成的左击处理
                    this.handleMedicalRecordClick();
                    break;
                case 'AI提醒':
                    // AI提醒的左击处理
                    this.handleAiReminderClick();
                    break;
            }
        },

        // 根据菜单标题执行不同的右击操作
        handleRightClick(title) {
            switch (title) {
                case '登录':
                    this.rightLogin();
                    break;
                // 其他菜单的右击处理...
            }
        },

        handleQualityRecordClick() {
            this.$emit("depCaseQualityList", this.depCaseQualityList)
        },
        handleMedicalRecordClick() {
            this.$emit("generateMedicalRecords")
        },
        handleAiReminderClick() {
            this.$emit("generateMedicalRecords")
        },

        handleFilterClick() {
            // 切换筛选框状态并通知父组件
            this.$emit('toggleFilter', this.isFlag = !this.isFlag);
        },
        leftLogin() {
            // this.$emit('showPageType', 'normal');
            this.$router.push(`/login`);
        },
        leftMessage() {
            this.$emit('showMessage', 'message-center', this.messageCount);
        },
        onCreate() { },
        getSummaryCount(key) {
            if (!this.data.summary || !this.data.summary[key]) {
                return 0;
            }
            return Array.isArray(this.data.summary[key]) ? this.data.summary[key].length : 0;
        },
        rightLogin() {
            // this.rightLoginVisible = true;
        },
        toggleCollapse() {
            this.isCollapsed = !this.isCollapsed;
            // 折叠/展开后调整位置
            this.$nextTick(() => {
                this.adjustCardPosition();
            });
        },
        // 切换眼睛状态
        toggleVisibility() {
            this.isVisible = !this.isVisible;
            this.$emit('toggleVisibility', this.isVisible);
        },

        // 获取所有科室信息
        get_all_department() {
            this.$axios.get('/get_all_department').then(res => {
                if (res.c === 200) {
                    this.deptOptions = res.p;
                }
            }).catch(error => {
                console.error('获取所有科室信息:', error);
            });
        },

        //获取按钮
        get_menu() {
            this.$axios2.get('/tk/get_menu').then(res => {
                if (res.code === 200) {
                    this.menuList = res.data;
                    if (this.menuList.length > 0) {
                        console.log("get_menu", this.type , !this.middleShow)
                        if( this.type == 'normal' && !this.middleShow ){
                            this.menuList = this.menuList.filter(item => item.title != '质控记录');
                        }
                    }
                }
            });
        },

        //获取质控记录
        // get_dep_case_quality() {
        //     this.$axios2.get('/tk/get_dep_case_quality').then(res => {
        //         if (res.code === 200) {
        //             this.depCaseQualityList = res.data.list;
        //             console.log("get_dep_case_quality", this.depCaseQualityList);
        //         }
        //     });
        // },

        //获取消息
        // get_msg() {
        //     this.$axios.get('/tk/get_msg').then(res => {
        //         console.log(res.data);
        //     });
        // },

        //消息统计
        get_msg_count() {

            this.$axios2.get(`/tk/get_msg_count?zyh=${this.zyh}`).then(res => {
                this.messageCount = res.data.unread_count;
            });
        },

        //知道了
        // read_msg() {
        //     this.$axios.post('/tk/read_msg').then(res => {
        //         console.log(res.data);
        //     });
        // },
    }
}
</script>

<style lang="scss" scoped>
.text-bold {
    font-weight: bold;
}

.margin-top {
    margin-top: 20px;
}

// 卡片容器固定在底部
.button-container {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000; // 确保在其他内容之上
    max-width: 100%;
    box-sizing: border-box;
    padding: 0 5px 5px; // 底部和左右留出空间
    transition: all 0.3s ease;
}

.box-card {
    margin: 0;
    border-radius: 8px 8px 0 0;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.desc-value {
    font-family: PingFangSC, PingFang SC;
    font-weight: 400;
    font-size: 14px;
    color: #333333;
    line-height: 20px;
    text-align: left;
    font-style: normal;
}

.divider-wrapper {
    width: 100%;
    cursor: pointer;
}

.zk-text {
    padding: 5px 20px;
    background: #D3E3FF;
    font-family: PingFangSC, PingFang SC;
    font-weight: 600;
    font-size: 14px;
    color: #3A84FF;
    line-height: 14px;
    text-align: center;
    font-style: normal;
    text-overflow: ellipsis;
    white-space: inherit;
    overflow: hidden;
}

.divider-row {
    height: 25px;
    width: 100%;
    // padding: 0 10px;
    transition: background-color 0.2s;
}

.divider-line {
    flex: 1;
    height: 1px;
    background-color: #e5e5e5;
}

.filterBox {
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

.divider-content {
    display: flex;
    align-items: center;
    margin: 0 10px;
    color: #999;

    .icon {
        margin-right: 1px;
        transition: transform 0.3s ease;
    }

    .collapse-text {
        font-size: 14px;
    }
}

.rotate-icon {
    transform: rotate(180deg);
}

// 确保展开内容不超出视口，无需滚动
.collapsible-content {
    // border-top: 1px solid #f0f0f0;
    // padding-bottom: 10px;
    box-sizing: border-box;
}

.dialog-box {
    ::v-deep .el-dialog__header {
        padding: 10px 20px;
        background: rgb(27, 100, 169);
        color: #fff !important;

        .el-dialog__title {
            color: #fff;
        }

        .el-dialog__headerbtn {
            top: 14px;
        }
    }

    ::v-deep .el-dialog__body {
        .el-form-item {
            background: #fff;
        }

        .el-input {
            width: 100%;

            input {
                height: 35px;
                border: 1px solid #C0C4CC;
                border-radius: 6px;
            }
        }
    }
}

.expanded-row {
    // padding: 10px;
    width: 100%;
    height: 40px;
    box-sizing: border-box;
}

.font-size12 {
    font-size: 12px;
}

.left-content {
    display: flex;
    align-items: center;
    color: #333;
    gap: 1px;

    .el-icon-circle-plus-outline {
        margin-right: 5px;
        color: #666;
    }

    // 眼睛图标容器样式
    .visibility-icon {
        cursor: pointer;
        color: #666;
        margin-left: 10px;

        &:hover {
            color: #409eff;
        }

        // 确保SVG图标有合适的大小和对齐
        svg {
            vertical-align: middle;
            transition: color 0.2s ease;
        }
    }
}

.red-number {
    color: rgba(239, 31, 58, 1);
    font-size: 14px;
    font-weight: 700;
    line-height: 20px;
}

.middle-content {
    text-align: center;
    flex: 1;
}

.right-content {
    padding: 2px 8px;
    background-color: #fff7e6;
    color: #fa8c16;
    border-radius: 4px;
    font-size: 12px;
}

// 折叠/展开动画优化
.slide-fade-enter-active {
    transition: all 0.3s ease-out;
    max-height: 500px; // 足够大的值
    opacity: 1;
}

.slide-fade-leave-active {
    transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
    max-height: 0;
    opacity: 0;
    overflow: hidden;
}

.slide-fade-enter,
.slide-fade-leave-to {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
}


// 筛选按钮容器
.filter-button-container {
    position: relative;
    display: inline-block;
    margin-right: 8px;
    text-align: left !important;
    z-index: 10;
}

// 弹出式筛选框样式
.popup-filter {
    position: absolute;
    bottom: 100%;
    left: 0;
    text-align: left !important;
    transform: translateY(-8px);
    width: 200px;
    z-index: 9999;
    padding: 0;
    background: rgba(50, 50, 50, 0.9);
    border-radius: 8px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    animation: fadeIn 0.2s ease-out;
}

// 箭头样式
.filter-arrow {
    position: absolute;
    top: 100%;
    left: 18px;
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-top: 6px solid rgba(50, 50, 50, 0.9);
}

// 筛选内容容器
.filter-content {
    padding: 5px 0;
    text-align: left !important;
}

// 筛选项样式
.popup-filter .filterRow {
    width: 100%;
    padding: 10px 15px;
    text-align: left !important;
    margin: 0;
    color: white;
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
}

.popup-filter .filterLabel {
    width: auto;
    text-align: left !important;
    flex: 1;
    padding: 0;
    margin: 0;
}

// 选中项样式
.popup-filter .selected {
    background-color: rgba(64, 158, 255, 0.2);
    font-weight: 500;
}

// 数量文字样式
.popup-filter .filterRowContent span {
    color: rgba(255, 255, 255, 0.8);
    font-size: 12px;
    margin-left: 10px;
}

::v-deep .el-card__body{
    padding: 10px;
}

// 淡入动画
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-12px);
    }

    to {
        opacity: 1;
        transform: translateY(-8px);
    }
}
</style>
