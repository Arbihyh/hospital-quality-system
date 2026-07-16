<!-- 组件置顶左侧标题 如 门诊病历质控统计分析 页面 -->
<template>
    <div class="custom-title">
        <slot></slot>
    </div>
</template>

<script>
export default {
    name: 'H1Title',
    props: {
        title: {
            type: String,
            default: ''
        },
        fontSize: {
            type: [Number, String],
            default: 20
        },
        color: {
            type: String,
            default: '#03101010'
        },
        fontFamily: {
            type: String,
            default: 'PingFangSC-bold'
        }
    }
}
</script>

<style lang="scss" scoped>
.custom-title {
    // left: 21px;
    // top: 23px;
    width: 200px;
    height: 29px;
    line-height: 28px;
    font-weight: bold;
    font-size: 20px;
    text-align: center;
    font-family: PingFangSC-bold;
    margin: 10px 0;
    font-size: v-bind(fontSize + 'px');
    color: v-bind(color);
    font-family: v-bind(fontFamily);
    margin: 10px auto;

}
</style>