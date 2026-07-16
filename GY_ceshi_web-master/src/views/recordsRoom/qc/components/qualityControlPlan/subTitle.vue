<template>
    <div class="subTitleBox">
        <div class="guide"></div>
        <span>{{title}}</span>
    </div>
</template>
<script>
export default {
    props: ['title']
}
</script>
<style lang="scss" scoped>
.subTitleBox {
    height: 58px;
    font-weight: 400;
    font-style: normal;
    color: rgba(0, 0, 0, 0);
    text-decoration: none;
    background: rgb(245, 247, 248);
    display: flex;
    align-items: center;
    margin-bottom: 14px;
    & .guide {
        width: 6px;
        height: 17px;
        background: -webkit-gradient(linear, left top, left bottom, from(#185da6), to(#3195ff));
        background: linear-gradient(180deg, #185da6 0%, #3195ff 100%);
        border-radius: 3px;
        margin-left: 14px
    }
    & span {
        color: rgba(29, 33, 41, 1);
        font-size: 16px;
        font-weight: 700;
        line-height: 23px;
        margin-left: 14px
    }
}

</style>