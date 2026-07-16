<!-- 基础信息 -->
<template>
    <div class="content">
        <div class="basic-info-section">
            <div class="basic-info">
                <KeyValueItem v-for="(item, index) in patientGeneralInfoList" :key="index" :keyName="item.title"
                    :valueName="item.value" class="info-item" />
            </div>
        </div>
    </div>
</template>