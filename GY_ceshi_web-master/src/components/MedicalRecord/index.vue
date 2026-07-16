<template>
    <div>
        <div v-if="$route.query.from != 'review'">
            <div class="score-box score-box_bl" :class="{
                scoreLevel_1_1: scoreLevel_ylzc == '优',
                scoreLevel_2_2: scoreLevel_ylzc == '良',
                scoreLevel_3_3: scoreLevel_ylzc == '中',
                scoreLevel_4_4: scoreLevel_ylzc == '差',
            }">
                <span>首页评分</span>
                <span class="score">{{ responseData.score.score }}</span>
                <el-image v-if="scoreLevel_ylzc == '优'" class="level" style="width: 47px; height: 41px"
                    :src="require('@/assets/images/you.png')" fit="contain"></el-image>
                <el-image v-if="scoreLevel_ylzc == '良'" class="level" style="width: 47px; height: 41px"
                    :src="require('@/assets/images/liang.png')" fit="contain"></el-image>
                <el-image v-if="scoreLevel_ylzc == '中'" class="level" style="width: 47px; height: 41px"
                    :src="require('@/assets/images/zhong.png')" fit="contain"></el-image>
                <el-image v-if="scoreLevel_ylzc == '差'" class="level" style="width: 47px; height: 41px"
                    :src="require('@/assets/images/cha.png')" fit="contain"></el-image>
            </div>
            <div class="legend-box">
                <span class="qz">强制</span>
                <span class="jy">建议</span>
            </div>
        </div>
        <div class="suggest-content" v-for="(items, index) in responseData.list" :key="index">
            <div class="cont-reight-bottom" @click="toJump(items.basis[0], items, index)">
                <div :class="items.level == 1 ? 'cont-reight-bottom-title-null' : 'cont-reight-bottom-title'">
                    <span v-if="items.category == 0">A类</span>
                    <span v-if="items.category == 1">B类</span>
                    <span v-if="items.category == 2">C类</span>
                    <span v-if="items.category == 3">D类</span>
                    <span v-if="items.category == 4">其他</span>
                    <span>-{{ items.down }}</span>
                </div>
                <div class="cont-reight-bottom-conter">
                    <div class="cont-reight-bottom-conter-flex">
                        <p>
                            <span class="bold">字段：</span>
                            {{ items.error_name }}
                        </p>
                        <el-image class="zsIcon" v-if="items.is_artificial == 0"
                            :src="require('@/assets/images/zsicon.png')" fit="contain"></el-image>
                        <el-image class="ysIcon" v-if="items.is_artificial == 1"
                            :src="require('@/assets/images/ysicon.png')" fit="contain"></el-image>
                    </div>
                    <p>
                        <span class="bold">提示：</span>
                        {{ items.desc }}
                    </p>
                    <p>
                        <span class="bold">质控依据：</span>
                        {{ items.basis }}
                    </p>
                </div>
            </div>
            <div class="btn-content" v-if="$route.query.from == 'review'">
                <div class="btn-left" v-if="items.type == 2">
                    <div class="appeal_progress" style="cursor: pointer;" v-if="items.status == 0" @click="openAppealDialog('appeal_ing', items, 1)"></div>">
                        申诉中
                    </div>
                    <div class="appeal_yes" v-if="items.status == 1" @click="openAppealDialog('appeal_yes', items, 1)">
                        通过
                    </div>
                    <div class="appeal_no" v-if="items.status == 2" @click="openAppealDialog('appeal_no', items, 1)">
                        驳回
                    </div>
                </div>

                <div class="btn-right" v-if="items.type == 2 && items.status == 0">
                    <div class="appeal_in_yes" @click="openAppealDialog('appeal_in_yes', items, 1)">
                        通过
                    </div>
                    <div class="appeal_in_no" @click="openAppealDialog('appeal_in_no', items, 1)">
                        驳回
                    </div>
                </div>
            </div>
            <div class="hz"></div>
        </div>
    </div>
</template>
<script>
    export default {
        data() {
            return {
                responseData: {

                }
            }
        },
        mounted() {
            getData()
        },
        methods: {
            getData() {
                this.$axios.post('/home_quality/getQualityResult', {
                    id: this.MEDRECID
                }).then(res => {
                    this.responseData = res.data; // 清空结果 
                }).catch(e => {
                    console.log(e);
                });
            }
        }
    }
</script>
<style lang="scss" scoped>
@import './index.scss';
</style>