<template>
    <div class="page-header">
        <HospitalTitle :title="hospitalName" />
        <div class="patient-basic-info">
            <div class="info-row">
                <KeyValueItem v-for="(item, index) in basicInfoList" :key="index" :keyName="item.title"
                    :valueName="item.value" class="info-item" />
            </div>
        </div>
        <H2Title :title="pageTitle" />
    </div>
</template>

<script>
import HospitalTitle from '../comp/hospital-title.vue'
import H2Title from '../comp/h2-title.vue'
import KeyValueItem from '../comp/key-value-item.vue'
import DividerLine from '../comp/divider-line.vue'

export default {
    name: 'PageHeader',
    components: {
        HospitalTitle,
        H2Title,
        KeyValueItem,
        DividerLine
    },
    props: {
        basicInfoList: {
            type: Array,
            default: () => ([])
        },
        hospitalName: {
            type: String,
            default: ''
        },
        pageTitle: {
            type: String,
            default: ''
        }
    },
    mounted() {}
}
</script>

<style lang="scss" scoped>
.page-header {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 0;

    .patient-basic-info {
        padding: 10px 0;
        border-bottom: 1.5px solid #e2dfdf;
        margin-bottom: 28px;
        margin-top: 15px;
        width: 100%;

        .info-row {
            display: flex;
            flex-wrap: nowrap;
            justify-content: space-around;
            align-items: center;
            width: 100%;

            .info-item {
                flex: 1;
                text-align: center;
                // white-space: nowrap;
                padding: 0 2px;
                margin: 0 1px;
                font-size: 14px;
                max-width: calc(100% / 6);
            }
        }
    }
}

// @media (max-width: 1200px) {
//     .info-item {
//         font-size: 13px;
//         padding: 0 1px;
//         margin: 0;
//     }
// }

// @media (max-width: 768px) {
//     .info-item {
//         font-size: 12px;
//         padding: 0;
//         margin: 0;
//     }
// }

:deep(.key-value-item) {
    display: inline-flex;
    align-items: center;
    width: 100%;
    justify-content: center;
}
</style>