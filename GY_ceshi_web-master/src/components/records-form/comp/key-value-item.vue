<template>
    <div class="key-value-item">
        <span class="key">{{ keyName }}：</span>
        <span class="value" v-html="valueName">
        </span>
    </div>
</template>

<script>
export default {
    name: 'KeyValueItem',
    props: {
        keyName: {
            type: String,
            required: true
        },
        valueName: {
            type: [String, Number],
            default: ''
        },

    }
}
</script>

<style scoped>
.key-value-item {
    /* height: 20px;
    line-height: 20px; */
    color: rgba(16, 16, 16, 1);
    padding: 4px 0;
    font-size: 14px;
    font-family: PingFangSC-regular;
}

.key {
    font-weight: bold;
}

.value {
    line-height: 1.8;

}
</style>