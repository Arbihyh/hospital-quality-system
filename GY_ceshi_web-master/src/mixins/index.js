import Vue from 'vue';

Vue.mixin({
  methods: {
    // 金额
    Money(value) {
      return value.toFixed(2);
    },
    // 起购金额判断
    getMeyer(Max, Nin, amount) {
      if (Number(amount) >= Number(Max)) {
        // 大于剩余本金
        return 'F';
      }
      if (Number(amount) <= Number(Nin)) {
        // 小于起购金额
        return 'T';
      }
    },
    // 新对象用旧对象相同属性赋值
    getNewObj(newObj, oldObj) {
      let oldObjCopy = { ...oldObj };
      let newObjCopy = { ...newObj };
      Object.keys(newObjCopy).forEach(key => {
        newObjCopy[key] = oldObjCopy[key] || '';
      });
      return newObjCopy;
    },
    // 返回时间
    getNumTime(data) {
      return new Date(data).toJSON().slice(0, 10);
    },

    gotimeNumber(val, val2) {
      if (new Date(val).getTime() > new Date(val2).getTime()) {
        return true;
      } else {
        return false;
      }
    },

    bankcard(value) {
      // let a=[\u4E00-\u9FA5]
      if (!value) return '';
      if (value[0] == '零') {
        return value;
      }
      var data = value.substring(0, 4) + ' ****** ' + value.substring(value.length - 4);
      return data;
    },
    goTime(val) {
      return val.slice(0, 4) + '-' + val.slice(4, 6) + '-' + val.slice(6, 8);
    },
    // 日期时间
    goDateTime(val) {
      return val.slice(0, 4) + '-' + val.slice(4, 6) + '-' + val.slice(6, 8) + ' ' + val.slice(8, 10) + ':' + val.slice(10, 12) + ':' + val.slice(12, 14);
    },
    getTimefilter(val) {
      return val.slice(0, 4) + '-' + val.slice(4, 6);
    },
    amountFile(val) {
      console.log(val);
      let numval = Number(val);
      return String(numval.toFixed(2));
    },
    goTimeTwe(val) {
      return val.slice(0, 4) + val.slice(5, 7) + val.slice(8, 10);
    },
    goto(id) {
      this.$router.push(id);
    },
    storageSet(key, value) {
      sessionStorage.setItem(key, JSON.stringify(value));
    },
    // 取出
    storageGet(key) {
      return JSON.parse(sessionStorage.getItem(key) == "undefined" ? null : sessionStorage.getItem(key));
    },
    // 删除
    storageRemove(key) {
      sessionStorage.removeItem(key);
    },
    // 返回上个路由
    goBack() {
      // this.$router.back()
      this.$router.go(-1);
    },
    // 跳转并删除当前页面
    goreplace(id) {
      this.$router.replace(id);
      // this.$router.replace(id)
    },
    goBackward(val) {
      this.$router.go(val);
    },
    //   // 相当于定义全局方法
    //   $field: function (key) {
    //     return $locale.FIELDS[key]
    //   },
    //   $msg: function (key) {
    //     return $locale.MESSAGES[key]
    //   },
    // 重新获得验证码图片
    switchCode(verifyid) {
      document.getElementById(verifyid).setAttribute('src', 'eweb-common.GenTokenImg.do?timestrap=' + Date.now());
    },
    // 起止时间计算
    timesCalculation(time) {
      // 获取当前时间对象, 作为截止时间
      const endDateObj = new Date();
      // 将时间戳转换为年月日
      const endYear = endDateObj.getFullYear();
      var endMonth = endDateObj.getMonth() + 1;
      if (endMonth < 10) {
        endMonth = '0' + endMonth;
      }
      var endDay = endDateObj.getDate();
      if (endDay < 10) {
        endDay = '0' + endDay;
      }
      const endDate = endYear.toString() + endMonth.toString() + endDay.toString();
      const endDate2 = endYear.toString() + '-' + endMonth.toString() + '-' + endDay.toString();
      // 获取传入的天数, 转换为时间戳
      const timeStamp = Number(time) * 24 * 60 * 60 * 1000;
      // 获取结束时间戳
      const endStamp = endDateObj.getTime();
      // 计算出起始日期时间戳
      const startStamp = endStamp - timeStamp;
      // 将起始时间戳转化为时间对象
      const startDateObj = new Date(startStamp);
      // 将时间戳转换为年月日
      const startYear = startDateObj.getFullYear();
      var startMonth = startDateObj.getMonth() + 1;
      if (startMonth < 10) {
        startMonth = '0' + startMonth;
      }
      var startDay = startDateObj.getDate();
      if (startDay < 10) {
        startDay = '0' + startDay;
      }
      const startDate = startYear.toString() + startMonth.toString() + startDay.toString();
      const startDate2 = startYear.toString() + '-' + startMonth.toString() + '-' + startDay.toString();
      // 返回起始时间和结束时间, 格式为yyyy-mm-dd
      if (Number(time) === 1) {
        return [endDate, endDate, endDate2, endDate2];
      } else {
        return [startDate, endDate, startDate2, endDate2];
      }
    },
  },
});
