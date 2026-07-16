<template>
    <div class="h2-title" :style="{
        color: color,
        fontSize: fontSize,
    }">{{ title }}</div>
</template>

<script>
export default {
    name: 'H2Title',
    props: {
        title: {
            type: String,
            required: true,
            default: ''
        },
        color: {
            type: String,
            default: 'rgba(16,16,16,1)'
        },
        fontSize: {
            type: [String, Number],
            default: '24px'
        }
    }
}
</script>

<style scoped>
.h2-title {
    height: 34px;
    line-height: 34px;
    text-align: center;
    color: #03101010;
    font-weight: bold;
    font-family: PingFangSC-bold;
    position: relative;
}
</style>
