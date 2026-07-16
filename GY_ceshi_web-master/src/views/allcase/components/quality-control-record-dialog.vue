<template>
    <div class="dashboard-container">
        <el-dialog :visible.sync="showDialog" width="90%" :close-on-click-modal="false" class="custom-height-dialog"
            :show-close="true">
           <div slot="title" class="dialog-header">
            <div class="header-content">
                <div class="dialog-title">AI质控记录</div>
            </div>
        </div>

            <div class="tableBox">
                <div class="block">
                    <el-form :model="queryParams" ref="filterFormRef">
                        <el-row>
                            <el-col :span="12">
                                <el-form-item label-width="80px" label="病案号" prop="AAA28">
                                    <el-input style="width: 94%" placeholder="请输入病案编号" v-model="queryParams.AAA28"
                                        clearable></el-input>
                                </el-form-item>
                            </el-col>

                            <el-col :span="12">
                                <el-form-item label-width="80px" label="床号" prop="ch">
                                    <el-input style="width: 94%" placeholder="请输入床号" v-model="queryParams.ch"
                                        clearable></el-input>
                                </el-form-item>
                            </el-col>


                        </el-row>
                        <el-row>
                            <el-col :span="12">
                                <el-form-item label-width="80px" label="患者名称" prop="BRXM">
                                    <el-input style="width: 94%" placeholder="请输入患者名称" v-model="queryParams.BRXM"
                                        clearable></el-input>
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item>
                                    <div style=" width: 94%; display: flex; justify-content:space-between">
                                        <div>
                                            <el-button class="btn1" type="primary" @click="funQuery">查询</el-button>
                                            <el-button @click="reset">重置</el-button>
                                        </div>
                                    </div>
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </div>
                <el-table :data="tableData" align="center" header-align="center" height="450" border
                    style="width: 100%">
                    <el-table-column prop="index" label="序号">
                        <template slot-scope="scope">
                            <span style="color: black;">
                                {{ scope.row.index }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="AAA28" label="病案号" sortable>
                        <template slot-scope="scope">
                            <span class="blue" @click="funGoto(scope.row.ZYH)">
                                {{ scope.row.AAA28 }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="score" label="病历评分" sortable>
                        <template slot-scope="scope">
                            <span :style="{
                                color: scope.row.num_lv === '甲' ? 'green' : scope.row.num_lv === '乙' ? 'orange' : 'red',
                            }">
                                {{ scope.row.quality_issue_count }} | {{ scope.row.num_lv }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="CH" label="床号" sortable>
                        <template slot-scope="scope">
                            <span style="color: black;">
                                {{ scope.row.CH }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="BRXM" label="患者姓名" sortable>
                        <template slot-scope="scope">
                            <span style="color: black;">
                                {{ scope.row.BRXM }}
                            </span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

        </el-dialog>

    </div>
</template>

<script>
import Title from '@/components/Title';
import { mapGetters } from 'vuex';

export default {
    name: 'Dashboard',
    components: {
        Title,
    },
    computed: {
        ...mapGetters(['name']),
    },
    data() {
        return {
            tableData: [],
            showDialog: false,
            ksArray: [],
            queryParams: {
                page: 1,
                page_size: 10,
                AAA28: '',
                ZYH: '',
                ch: '',
                BRXM: ''
            },
        };
    },
    mounted() {
        this.funQuery();
    },
    activated() {
        this.funQuery();
    },

    methods: {

        init(list) {

            this.showDialog = true;
            this.funQuery()

        },
        reset() {
            this.queryParams.AAA28 = '1'
            this.queryParams.ch = ''
            this.queryParams.BRXM = ''
        },
        funGoto(val) {
            this.showDialog = false;
            this.$emit('funGoto', val);
        },
        funQuery() {
            //查询
            const zyh = this.$route.query.id;
            this.$axios2.get(`/tk/get_dep_case_quality?zyh=${zyh}`).then(res => {
                // this.paginationData.total = res.data.count;
                if (res.code === 200) {
                    this.tableData = res.data.list.map((item, index) => {
                        return {
                            ...item,
                            num_lv: item.quality_issue_count < 75 ? '甲' : '乙',
                            index: index + 1
                        }
                    });
                }

            });
        }
    },
};
</script>
<style scoped></style>
<style lang="scss" scoped>
.tableBox {
    background: #fff;
    padding: 19px;
    border-radius: 5px;
    font-size: 12px;
}


.dialog-header {
    // padding-bottom: 15px;
    border-bottom: 1px solid #eee;

    .header-content {
        display: flex;
        align-items: center;
        // 改为左对齐，只在元素之间添加间距
        gap: 20px;
        /* 标题和清除按钮之间的间隔 */
    }
}
// ::v-deep .el-dialog__header {
//     background-color: #1B64B0 ;
// }

.dialog-title {
    color: #0a0101;
    font-size: 20px;
    font-family: PingFangSC-bold, sans-serif;
}

::v-deep .custom-height-dialog {
    height: 90vh !important;
    max-height: 90vh !important;
}

// 调整对话框内容区域高度
::v-deep .custom-height-dialog .el-dialog__body {
    max-height: calc(90vh - 160px) !important;
    height: calc(90vh - 160px) !important;
    overflow-y: auto;
}

// 确保对话框容器高度正确
::v-deep .custom-height-dialog .el-dialog__wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
}
::v-deep .custom-height-dialog .el-dialog__body {
    max-height: calc(90vh - 160px) !important;
    height: calc(90vh - 160px) !important;
    // overflow-y: auto; /* 删除这一行 */
    padding: 0;
    /* 可选：移除内边距避免样式冲突 */
}

.dialog-header {
    // position: relative;
    // 抵消dialog默认的padding
    // margin: -20px -20px 20px;
    padding: 0;
    
    .dialog-header-bg {
        // 背景样式
        position: absolute;
        left: 17px;
        top: 67px;
        width: 464px;
        height: 57px;
        border-radius: 4px 4px 0px 0px;
        background-color: rgba(27, 100, 176, 1);
        z-index: 1;
    }
    
    .dialog-header-text {
        // 文字样式
        position: relative;
        left: 36px;
        top: 81px;
        width: 183px;
        height: 33px;
        line-height: 23px;
        color: rgba(255, 255, 255, 1);
        font-size: 16px;
        text-align: left;
        font-family: SourceHanSansSC-bold, sans-serif;
        z-index: 2;
    }
}

.block {
    background: #fff;
    border-radius: 5px;
    padding: 20px 30px;
    margin-bottom: 20px;

    .fBtn {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bnh {
        margin-bottom: 20px;
    }

    .barBtn {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .selects {
        width: 100%;
    }

    .rowsa {
        margin-bottom: 20px;
    }
}

.blue {
    color: #185da6;
    cursor: pointer;
}

.block {
    background: #fff;
    width: 100%;
    align-items: center;
    border-radius: 5px;
    height: 75px;
    padding-left: 10px;
    margin-bottom: 20px;
    padding-left: 0;
    padding-right: 0;

    .blockCon {
        align-items: center;

        .selectDns {
            span {
                margin-right: 5px;
            }
        }

        .demonstration {
            margin-left: 10px;
        }

        .pickers {
            margin-left: 5px;
        }

        .lsxd {
            margin-left: 20px;
        }

        .ins {
            width: 150px;
            margin: 0 10px;
        }
    }

    .sc {
        background: #185da6;
        color: #fff;
    }
}
</style>