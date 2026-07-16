<template>
    <div class="app-container">
        <SearchBoxVue class="filter-list-form" ref="SearchBoxRef" @search="handleSearch" @reset="handleReset" />
        <FullscreenContainer :showBtn="false" ref="customMaxContainerRef">
            <div class="filter-list-action">
                <el-row type="flex" justify="space-between" align="middle">
                    <mPagination v-if="tableData && tableData.length !== 0" :data="paginationData"
                        @pageChangeEvent="pageHasChanged"></mPagination>
                    <button class="max-toggle-btn" @click="handleToggleMax">{{ isMaximized ? closeText : openText }}</button>
                </el-row>

                <div class="content">
                    <el-card class="tableCard" :class="{ 'table-card-max': isMaximized }">
                        <TableBoxVue :loading="loading" :data="tableData" ref="tableRef" @onClickRow="getDetailData"
                            @sortChange="handleSortChange" />
                    </el-card>
                    <el-card class="detailCard" :class="{ 'detail-card-max': isMaximized }"  v-loading="detailLoading">
                        <div slot="header" class="detailCardHeader">
                            <el-row type="flex" align="middle" v-for="(value, key) in detailHeaderData">
                                <div class="title">{{ key }}：</div>
                                <span :class="`${key != '病案号' ? 'value' : 'blue-link'}`" @click="toPage">{{ value
                                    }}</span>
                            </el-row>
                        </div>
                        <DetailBoxVue :dataSource="detailData" :currentRow="currentRow" />
                    </el-card>
                </div>


            </div>
        </FullscreenContainer>

    </div>
</template>

<script>
import mPagination from '@/components/m-pagination';
import SearchBoxVue from '@/views/recordsRoom/qc/components/expertQualityControl/SearchBox.vue'
import TableBoxVue from '@/views/recordsRoom/qc/components/expertQualityControl/TableBox.vue'
import DetailBoxVue from '@/views/recordsRoom/qc/components/expertQualityControl/detailBox.vue'
import pagination from '@/components/Pagination/index2.vue'
import { getZJZKList, getBrry, getDefaultCollectSearch } from '@/api/qc'
import FullscreenContainer from '@/components/fullscreen-container'

export default {
    components: {
        mPagination,
        SearchBoxVue,
        TableBoxVue,
        DetailBoxVue,
        pagination,
        FullscreenContainer
    },
    data() {
        return {
            loading: false,
            detailLoading: false,
            tableData: [],
            openText: '全屏显示',
            closeText: '退出全屏',
            isMaximized: false,
            paginationData: {
                total: 0,
                currentPage: 1,
                pageSize: 10
            },
            detailHeaderData: {
                病案号: '',
                床号: '',
                管床医师: '',
                病人科室: '',
                住院天数: '',
                总费用: '',
            },
            detailData: [],
            currentRow: {}
        }
    },
    created() {
    },
    mounted() {
        this.initData()
    },
    methods: {
        initData() {
            getDefaultCollectSearch().then((res) => {
                if (res.code == 200) {
                    const data = res.data || {}
                    if (data.filter_content) {
                        this.$refs.SearchBoxRef.formData = { ...this.$refs.SearchBoxRef.formData, ...JSON.parse(data.filter_content) }
                        this.getList()
                    } else {
                        this.getList()
                    }
                } else {
                    this.getList()
                }
            }).catch(() => {
                this.getList()
            })
        },

        handleToggleMax() {
            if (!this.isMaximized) {
                this.isMaximized = true
                this.$refs.customMaxContainerRef.openMax()
            } else {
                this.isMaximized = false
                this.$refs.customMaxContainerRef.closeMax()
            }

        },
        // table 字段排序
        handleSortChange(column) {
            this.$refs.SearchBoxRef.handleSortChange(column)
        },
        getList() {
            this.loading = true
            getZJZKList({
                ...this.$refs.SearchBoxRef.formData,
                page: this.paginationData.currentPage,
                page_size: this.paginationData.pageSize
            }).then(res => {
                this.paginationData.total = res.data.count
                this.tableData = res.data.list
                this.$refs.tableRef.selectedArray = []
                if (Array.isArray(this.tableData) && !!(this.tableData.length)) {
                    this.getDetailData(this.tableData[0])
                }
            }).catch(error => {
                console.log(error)
            }).finally(() => {
                this.loading = false
            })
        },

        pageHasChanged() {
            this.getList()
        },
        handleSearch() {
            this.paginationData.currentPage = 1
            this.getList()
        },
        handleReset() {
            this.$refs.tableRef.$refs.filterTableRef.clearSort();
            this.handleSearch()
        },
        getDetailData(row) {
            this.detailLoading = true
            this.currentRow = { ...row }
            this.$axios.post('/getTree', {
                id: row.ZYH,
            }).then(res => {
                this.detailLoading = false
                this.detailData = res.data || {}
            }).catch(() => {
                this.detailLoading = false
            })
            getBrry({ zyh: row.ZYH }).then(res => {
                if (res.code == 200) {
                    const data = res.data || {}
                    this.detailHeaderData.病案号 = data.AAA28
                    this.detailHeaderData.床号 = data.CH
                    this.detailHeaderData.管床医师 = data.GCYSMC
                    this.detailHeaderData.病人科室 = data.ZY_KSMC
                    this.detailHeaderData.住院天数 = data.AAC04
                    this.detailHeaderData.总费用 = data.ADA01
                    this.baseInfo = res.data || {}
                }
            })
        },
        toPage() {
            this.$router.push(`/caseViews?from=expertQualityControl&ZYH=123456`)
        }
    }
}
</script>

<style lang="scss" scoped>
/* 最大化按钮样式 */
.max-toggle-btn {
    position: absolute;
    right: 10px;
    padding: 6px 12px;
    background: #409eff;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    z-index: 10;
    outline: none;
}

.max-toggle-btn:hover {
    background: #66b1ff;
}

.content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    /* 或者使用其他单位如百分比或像素 */
    gap: 10px;

    /* 可选，添加间隙 */
    .tableCard {
        ::v-deep .el-card__header {
            height: 0;
        }

        ::v-deep .el-card__body {
            height: 504px;
            overflow-y: scroll;
        }
    }
     // 新增：tableCard 最大化样式
    .table-card-max {
        ::v-deep .el-card__body {
            height: 730px !important;
        }
    }
     // 新增：detail-card 最大化样式
    .detail-card-max {
        ::v-deep .el-card__body {
            height: 730px !important;
        }
    }

    .detailCard {
        ::v-deep .el-card__body {
            height: 400px;
            overflow-y: scroll;
        }

        ::v-deep .el-card__header {
            background-color: rgb(240, 240, 240);

            .detailCardHeader {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                /* 创建4列，每列占据可用空间的1份 */
                gap: 5px;
                /* 可选，添加间隙 */
                font-size: 14px;
                font-weight: 700;

                & .title {
                    width: 70px;
                    text-align: right;
                }

                & .value {
                    color: rgba(78, 89, 105, 1);
                    font-size: 14px;
                    font-weight: 400;
                }
            }
        }
    }
}
</style>