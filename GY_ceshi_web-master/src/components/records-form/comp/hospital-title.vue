<!-- 医院名称 -->
<template>
    <div class="hospital-title" :style="{
        color: color,
        fontSize: fontSize,
    }">{{ title }}</div>
</template>

<script>
export default {
    name: 'HospitalTitle',
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
            default: '18px'
        }
    }
}
</script>

<style scoped>
.hospital-title {
    height: 25px;
    line-height: 25px;
    text-align: center;
    font-family: PingFangSC-regular;
    position: relative;
}
</style>