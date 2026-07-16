<template>
    <div class="title" :class="{ 'title-left': keyAlign === 'left', 'title-center': keyAlign === 'center' }" :style="{
        color: color,
        fontSize: fontSize,
    }">{{ title }}</div>
</template>

<script>
export default {
    name: 'H3Title',
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
            default: '14px'
        },
        keyAlign: {
            type: String,
            default: 'center',
            validator: (val) => ['left', 'center'].includes(val)
        }
    }
}
</script>

<style scoped>
.title {
    /* height: 23px;
    line-height: 23px; */
    font-size: 14px;
    color: rgba(16, 16, 16, 1);
    font-weight: bold;
    font-family: PingFangSC-bold;
    position: relative;
}
.title-center {
    text-align: center;
}
.title-left {
    text-align: left;
    /* text-indent: 2em; */
}
</style>
