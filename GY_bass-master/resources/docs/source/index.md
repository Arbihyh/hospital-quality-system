---
title: API Reference

language_tabs:
- bash
- javascript

includes:

search: true

toc_footers:
- <a href='http://github.com/mpociot/documentarian'>Documentation Powered by Documentarian</a>
---
<!-- START_INFO -->
# Info

Welcome to the generated API reference.
[Get Postman Collection](http://localhost/docs/collection.json)

<!-- END_INFO -->

#Index


<!-- START_91c3c36e3b9219b370a769c294e6ed2c -->
## login

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"name":"ipsum","password":"consequatur"}'

```

```javascript
const url = new URL(
    "http://localhost/api/login"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "ipsum",
    "password": "consequatur"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "token": "jkjhdjkshkjsj"
    },
    "time": 123787842
}
```

### HTTP Request
`GET api/login`

`POST api/login`

`PUT api/login`

`PATCH api/login`

`DELETE api/login`

`OPTIONS api/login`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `name` | string |  required  | 用户名
        `password` | string |  required  | 密码
    
<!-- END_91c3c36e3b9219b370a769c294e6ed2c -->

#case-quality


<!-- START_bdc554ff702afb9fe1849199cc5d45f7 -->
## analysis
/api/case-quality/analysis
质量分析

> Example request:

```bash
curl -X POST \
    "http://localhost/api/case-quality/analysis" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"start_time":"porro","end_time":"saepe"}'

```

```javascript
const url = new URL(
    "http://localhost/api/case-quality/analysis"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "start_time": "porro",
    "end_time": "saepe"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
null
```

### HTTP Request
`POST api/case-quality/analysis`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `start_time` | string |  optional  | 开始时间  日期字符串格式
        `end_time` | string |  optional  | 结束时间  日期字符串格式
    
<!-- END_bdc554ff702afb9fe1849199cc5d45f7 -->

<!-- START_6c69f5dc8b24ac0a421ca8add8b30edd -->
## ranking_department
/api/case-quality/ranking_department
科室排名 前10条

> Example request:

```bash
curl -X POST \
    "http://localhost/api/case-quality/ranking_department" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"start_time":"laborum","end_time":"quibusdam"}'

```

```javascript
const url = new URL(
    "http://localhost/api/case-quality/ranking_department"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "start_time": "laborum",
    "end_time": "quibusdam"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
null
```

### HTTP Request
`POST api/case-quality/ranking_department`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `start_time` | string |  optional  | 开始时间  日期字符串格式
        `end_time` | string |  optional  | 结束时间  日期字符串格式
    
<!-- END_6c69f5dc8b24ac0a421ca8add8b30edd -->

<!-- START_09359f241ce93b4b49fb7514e8e06cc3 -->
## defect_issues
/api/case-quality/defect_issues
缺陷问题列表

> Example request:

```bash
curl -X POST \
    "http://localhost/api/case-quality/defect_issues" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"start_time":"labore","end_time":"et"}'

```

```javascript
const url = new URL(
    "http://localhost/api/case-quality/defect_issues"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "start_time": "labore",
    "end_time": "et"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
null
```

### HTTP Request
`POST api/case-quality/defect_issues`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `start_time` | string |  optional  | 开始时间  日期字符串格式
        `end_time` | string |  optional  | 结束时间  日期字符串格式
    
<!-- END_09359f241ce93b4b49fb7514e8e06cc3 -->

#general


<!-- START_02bc6ac7e701faed7fbe1fb3bdc15c15 -->
## api/test
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/test" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/test"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`GET api/test`

`POST api/test`

`PUT api/test`

`PATCH api/test`

`DELETE api/test`

`OPTIONS api/test`


<!-- END_02bc6ac7e701faed7fbe1fb3bdc15c15 -->

<!-- START_31e791b06fda2f45ec79be46da9a7565 -->
## api/user/info
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/user/info" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/user/info"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/user/info`

`POST api/user/info`

`PUT api/user/info`

`PATCH api/user/info`

`DELETE api/user/info`

`OPTIONS api/user/info`


<!-- END_31e791b06fda2f45ec79be46da9a7565 -->

<!-- START_efa27553e4123deff5b930255ed88e26 -->
## api/user/menus
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/user/menus" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/user/menus"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/user/menus`

`POST api/user/menus`

`PUT api/user/menus`

`PATCH api/user/menus`

`DELETE api/user/menus`

`OPTIONS api/user/menus`


<!-- END_efa27553e4123deff5b930255ed88e26 -->

<!-- START_cfedc82efac7c1adc103139be57dbcce -->
## api/feeDetail
> Example request:

```bash
curl -X POST \
    "http://localhost/api/feeDetail" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/feeDetail"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/feeDetail`


<!-- END_cfedc82efac7c1adc103139be57dbcce -->

<!-- START_9792377865465dfd12bebd73e7326925 -->
## field 参数
     select_type  0：and, 1:or, 2:must_not
     type 0为like  1为准确查找

> Example request:

```bash
curl -X POST \
    "http://localhost/api/search" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/search"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/search`


<!-- END_9792377865465dfd12bebd73e7326925 -->

<!-- START_d4ae133177bc07c3242ac779c856276a -->
## api/normalSearch
> Example request:

```bash
curl -X POST \
    "http://localhost/api/normalSearch" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/normalSearch"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/normalSearch`


<!-- END_d4ae133177bc07c3242ac779c856276a -->

<!-- START_6f08ed8499d297ce2e1fc5e76236d4d3 -->
## 导出

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/excel_error" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/excel_error"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/excel_error`

`POST api/excel_error`

`PUT api/excel_error`

`PATCH api/excel_error`

`DELETE api/excel_error`

`OPTIONS api/excel_error`


<!-- END_6f08ed8499d297ce2e1fc5e76236d4d3 -->

<!-- START_e8fec36f4b86dd3ae16672be2c17a276 -->
## homeCensus
首页统计

> Example request:

```bash
curl -X POST \
    "http://localhost/api/homeCensus" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"title":"amet","time":"quia","type":12,"type_id":9,"qa_status":15}'

```

```javascript
const url = new URL(
    "http://localhost/api/homeCensus"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "amet",
    "time": "quia",
    "type": 12,
    "type_id": 9,
    "qa_status": 15
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "结果",
    "data": {
        "total": "病案数量",
        "errorMedical": "总缺陷",
        "averageScore": "平均得分",
        "averageError": "平均缺陷",
        "highest_score": "优",
        "good": "良",
        "minimum_score": "差",
        "before": {
            "outstanding": "优秀率",
            "averageError": "平均缺陷",
            "averageScore": "平均分"
        },
        "last": {
            "outstanding": "优秀率",
            "averageError": "平均缺陷",
            "averageScore": "平均分"
        },
        "new": {
            "outstanding": "优秀率",
            "averageError": "平均缺陷",
            "averageScore": "平均分"
        }
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/homeCensus`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `title` | string |  optional  | 开始时间  时间戳or时间
        `time` | string |  optional  | 结束时间
        `type` | integer |  optional  | 按年(1)、季度(2)、月(3);
        `type_id` | integer |  optional  | type选项;
        `qa_status` | integer |  optional  | 1（质控后）
    
<!-- END_e8fec36f4b86dd3ae16672be2c17a276 -->

<!-- START_bb446f956cc8e68a840ebb89d50e4aa3 -->
## 上报历史

> Example request:

```bash
curl -X POST \
    "http://localhost/api/reportingHistory" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"string":"officia","int":"sint"}'

```

```javascript
const url = new URL(
    "http://localhost/api/reportingHistory"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "string": "officia",
    "int": "sint"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/reportingHistory`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `string` | end_time |  optional  | 结束时间
        `int` | type |  optional  | 上报平台（1：国考；2：卫统；3：医保）
    
<!-- END_bb446f956cc8e68a840ebb89d50e4aa3 -->

<!-- START_f0db7db91189dd25a747f26f21dad090 -->
## home
住院病案首页

> Example request:

```bash
curl -X POST \
    "http://localhost/api/medical_record" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"id":"illum"}'

```

```javascript
const url = new URL(
    "http://localhost/api/medical_record"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "id": "illum"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "结果",
    "data": {
        "AAA26C": "医疗付费方式代吗",
        "AAA29": "住院次数",
        "AAB06C": "入院途径代码",
        "AAA01": "姓名",
        "AAA02C": "性别",
        "AAA03": "出生日期",
        "AAA04": "年龄",
        "AAA05C": "国籍",
        "AAA06C": "民族",
        "AAA07": "身份证号",
        "AAA08C": "婚姻状况代码",
        "AAA40": "不足1周岁年龄",
        "AEN01": "新生儿出生体重",
        "AAA42": "新生儿入院体重",
        "AAC04": "实际住院(天)",
        "AAA09": "出生地省",
        "AAA10": "出生地市",
        "AAA11": "出生地县",
        "AAA43": "籍贯省",
        "AAA44": "籍贯市",
        "AAA48": "现居住地省",
        "AAA49": "现居住地市",
        "AAA50": "现居住地县",
        "AAA15": "现住址详细地址（居住半年以上）",
        "AAA51": "现住址电话",
        "AAA17C": "现住址邮政编码",
        "AAA45": "户籍省（区、市）",
        "AAA46": "户籍市",
        "AAA47": "户籍县",
        "AAA12": "户籍详细地址",
        "AAA14C": "户籍地址邮政编码",
        "AAA19": "工作单位及地址",
        "AAA20": "工作单位电话",
        "AAA21C": "工作单位邮政编码",
        "AAA18C": "职业代码(可能得换成职业名称)",
        "AAA22": "联系人姓名",
        "AAA24": "联系人地址",
        "AAA25": "联系人电话",
        "AAB01": "入院时间",
        "AAB02C": "入院科别代码",
        "AAB03": "入院病房",
        "AAC02C": "出院科别代码",
        "AAC03": "出院病房",
        "AAD01C": "转经科别代码",
        "ABA01N": "门（急）诊诊断名称",
        "ABA01C": "门(急)诊诊断编码-疾病编码",
        "diagnosis": {
            "list": [
                {
                    "id": "id",
                    "class": "主要诊断main,其他诊断other",
                    "ICD10_NAME": "出院诊断名称",
                    "ICD10_ID1": "诊断编码"
                }
            ]
        },
        "other_diagnosis": {
            "list": [
                {
                    "ICD10_NAME": "出院诊断名称",
                    "ICD10_ID1": "诊断编码"
                }
            ]
        },
        "ABC03C": "入院病情代码",
        "ABF01N": "病理诊断名称",
        "ABF04": "病理号",
        "ABF01C": "病理诊断编码(M码)ID",
        "AEB02C": "有无药物过敏",
        "AEB01": "过敏药物",
        "AEI01C": "是否尸检代码",
        "AEG01C": "血型代码",
        "AEG02C": "Rh 代码",
        "AEE01": "科主任姓名",
        "AEE02": "主(副主)任医师姓名",
        "AEE03": "主治医师姓名",
        "AEE04": "住院医师姓名",
        "AEE10": "责任护士姓名",
        "AEE05": "进修医师姓名",
        "AEE07": "实习医师姓名",
        "AEE08": "编码员姓名",
        "AED02": "质控医师姓名",
        "AED03": "质控护士姓名",
        "AED04": "病案质量检查日期",
        "AED01C": "病案质量代码",
        "main_operation": {
            "list": [
                {
                    "ICD9_ID1": "手术或操作ID",
                    "OPE_DATE": "手术或操作日期",
                    "OPE_LEVEL": "手术级别",
                    "ICD9_NAME": "手术或操作名称",
                    "OPE_MAN_NAME": "主刀医师姓名",
                    "FRIST_ASSISTANT_NAME": "一助医师姓名",
                    "SECOND_ASSISTANT_NAME": "二助医师姓名",
                    "INCISION_GRADE_ID": "切口愈合等级",
                    "HOCUS_WAY_ID": "麻醉方式",
                    "HOCUS_MAN_NAME": "麻醉医师名称"
                }
            ]
        },
        "secondary_operation": {
            "list": [
                {
                    "ICD9_ID1": "手术或操作ID",
                    "OPE_DATE": "手术或操作日期",
                    "OPE_LEVEL": "手术级别",
                    "ICD9_NAME": "手术或操作名称",
                    "OPE_MAN_NAME": "主刀医师姓名",
                    "FRIST_ASSISTANT_NAME": "一助医师姓名",
                    "SECOND_ASSISTANT_NAME": "二助医师姓名",
                    "INCISION_GRADE_ID": "切口愈合等级",
                    "HOCUS_WAY_ID": "麻醉方式",
                    "HOCUS_MAN_NAME": "麻醉医师名称"
                }
            ]
        },
        "operation": {
            "list": [
                {
                    "id": "id",
                    "class": "主要手术main,其他手术other",
                    "type": "手术操作类别。1，治疗性操作；2，诊断性操作；3，介入治疗；4，手术",
                    "ICD9_ID1": "手术或操作ID",
                    "OPE_DATE": "手术或操作日期",
                    "OPE_LEVEL": "手术级别",
                    "ICD9_NAME": "手术或操作名称",
                    "OPE_MAN_NAME": "主刀医师姓名",
                    "FRIST_ASSISTANT_NAME": "一助医师姓名",
                    "SECOND_ASSISTANT_NAME": "二助医师姓名",
                    "INCISION_GRADE_ID": "切口愈合等级",
                    "HOCUS_WAY_ID": "麻醉方式",
                    "HOCUS_MAN_NAME": "麻醉医师名称"
                }
            ]
        },
        "AEM01C": "离院方式代码",
        "AEM03C": "是否有出院31日内再住院计划",
        "AEM04": "31日内再住院目的",
        "AEJ01": "颅脑损伤患者入院前昏迷时间（天）",
        "AEJ02": "颅脑损伤患者入院前昏迷时间（小时）",
        "AEJ03": "颅脑损伤患者入院前昏迷时间（分钟）",
        "AEJ04": "颅脑损伤患者入院后昏迷时间（天）",
        "AEJ05": "颅脑损伤患者入院后昏迷时间（小时）",
        "AEJ06": "颅脑损伤患者入院后昏迷时间（分钟）",
        "ADA0101": "自付金额",
        "D11": "一般医疗服务费",
        "D12": "一般治疗操作费",
        "D13": "护理费",
        "D14": "综合医疗服务类其他费用",
        "D15": "病理诊断费",
        "D16": "实验室诊断费",
        "D17": "影像学诊断费",
        "D18": "临床诊断项目费",
        "D19": "非手术治疗项目费",
        "D19X01": "其中:临床物理治疗费",
        "D20": "手术治疗费",
        "D20X01": "其中：麻醉费",
        "D20X02": "其中：手术费",
        "D21": "康复费",
        "D22": "中医治疗费",
        "D23": "西药费",
        "D23X01": "其中：抗菌药物费",
        "D24": "中成药费",
        "D25": "中草药费",
        "D26": "血费",
        "D27": "白蛋白类制品费",
        "D28": "球蛋白类制品费",
        "D29": "凝血因子类制品费",
        "D30": "细胞因子类制品费",
        "D31": "检查用一次性医用材料费",
        "D32": "治疗用一次性医用材料费",
        "D33": "手术用一次性医用材料费",
        "D34": "其他费",
        "IS_MAIN_WAY": "重症监护室代码",
        "IN_TIME": "监护室进入日期时间",
        "OUT_TIME": "监护室退出日期时间",
        "AEL01": "呼吸机时间",
        "error": {
            "list": [
                {
                    "down": "扣分",
                    "desc": "提示",
                    "error_field": "缺陷字段",
                    "error_name": "缺陷字段名称"
                }
            ]
        }
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/medical_record`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `id` | string |  required  | 病案号
    
<!-- END_f0db7db91189dd25a747f26f21dad090 -->

<!-- START_4847a3fcacd221a7bb4655d6281d04a8 -->
## editHome
病案首页编辑

@response {
 "code":200,
	"msg":"结果",
 "data":{
}
}

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/medicalRecordEdit" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/medicalRecordEdit"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/medicalRecordEdit`

`POST api/medicalRecordEdit`

`PUT api/medicalRecordEdit`

`PATCH api/medicalRecordEdit`

`DELETE api/medicalRecordEdit`

`OPTIONS api/medicalRecordEdit`


<!-- END_4847a3fcacd221a7bb4655d6281d04a8 -->

<!-- START_054303e44890e5fec20f644e5f68b22b -->
## api/wtExport
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/wtExport" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/wtExport"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/wtExport`

`POST api/wtExport`

`PUT api/wtExport`

`PATCH api/wtExport`

`DELETE api/wtExport`

`OPTIONS api/wtExport`


<!-- END_054303e44890e5fec20f644e5f68b22b -->

<!-- START_3824bf288332d8e218b79e0a1d9ebfa1 -->
## api/gkExport
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/gkExport" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/gkExport"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/gkExport`

`POST api/gkExport`

`PUT api/gkExport`

`PATCH api/gkExport`

`DELETE api/gkExport`

`OPTIONS api/gkExport`


<!-- END_3824bf288332d8e218b79e0a1d9ebfa1 -->

<!-- START_7196f6591269bd2646ba10dd8f16d0a3 -->
## api/getTree
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/getTree" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/getTree"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/getTree`

`POST api/getTree`

`PUT api/getTree`

`PATCH api/getTree`

`DELETE api/getTree`

`OPTIONS api/getTree`


<!-- END_7196f6591269bd2646ba10dd8f16d0a3 -->

<!-- START_4bf97aa7a2b76426ef912bc7ee8bab95 -->
## api/getAllCase
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/getAllCase" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/getAllCase"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/getAllCase`

`POST api/getAllCase`

`PUT api/getAllCase`

`PATCH api/getAllCase`

`DELETE api/getAllCase`

`OPTIONS api/getAllCase`


<!-- END_4bf97aa7a2b76426ef912bc7ee8bab95 -->

<!-- START_84ba4902254851e6a7cf06efbc0349a1 -->
## api/get_assessment_indicators
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/get_assessment_indicators" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/get_assessment_indicators"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/get_assessment_indicators`

`POST api/get_assessment_indicators`

`PUT api/get_assessment_indicators`

`PATCH api/get_assessment_indicators`

`DELETE api/get_assessment_indicators`

`OPTIONS api/get_assessment_indicators`


<!-- END_84ba4902254851e6a7cf06efbc0349a1 -->

<!-- START_40dd85d4892bfb82c29a8a2a140af429 -->
## api/get_zhibiao_list
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/get_zhibiao_list" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/get_zhibiao_list"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/get_zhibiao_list`

`POST api/get_zhibiao_list`

`PUT api/get_zhibiao_list`

`PATCH api/get_zhibiao_list`

`DELETE api/get_zhibiao_list`

`OPTIONS api/get_zhibiao_list`


<!-- END_40dd85d4892bfb82c29a8a2a140af429 -->

<!-- START_8ef1e604cebb050353d5d8aa703bd7b0 -->
## api/get_illness_type
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/get_illness_type" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/get_illness_type"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/get_illness_type`

`POST api/get_illness_type`

`PUT api/get_illness_type`

`PATCH api/get_illness_type`

`DELETE api/get_illness_type`

`OPTIONS api/get_illness_type`


<!-- END_8ef1e604cebb050353d5d8aa703bd7b0 -->

<!-- START_7890d31d4decd57ec32618fd4b3bd675 -->
## api/get_case
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/get_case" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/get_case"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/get_case`

`POST api/get_case`

`PUT api/get_case`

`PATCH api/get_case`

`DELETE api/get_case`

`OPTIONS api/get_case`


<!-- END_7890d31d4decd57ec32618fd4b3bd675 -->

<!-- START_fba6d19b1b70b17720056abc6735f602 -->
## api/get_case_platform
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/get_case_platform" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/get_case_platform"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/get_case_platform`

`POST api/get_case_platform`

`PUT api/get_case_platform`

`PATCH api/get_case_platform`

`DELETE api/get_case_platform`

`OPTIONS api/get_case_platform`


<!-- END_fba6d19b1b70b17720056abc6735f602 -->

<!-- START_6e44409d419dc31f3c55ad5c02089e53 -->
## api/get_pacs_dir
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/get_pacs_dir" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/get_pacs_dir"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/get_pacs_dir`

`POST api/get_pacs_dir`

`PUT api/get_pacs_dir`

`PATCH api/get_pacs_dir`

`DELETE api/get_pacs_dir`

`OPTIONS api/get_pacs_dir`


<!-- END_6e44409d419dc31f3c55ad5c02089e53 -->

<!-- START_097a5398745e966d7736bcd00edca78f -->
## api/get_pacs_detail
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/get_pacs_detail" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/get_pacs_detail"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/get_pacs_detail`

`POST api/get_pacs_detail`

`PUT api/get_pacs_detail`

`PATCH api/get_pacs_detail`

`DELETE api/get_pacs_detail`

`OPTIONS api/get_pacs_detail`


<!-- END_097a5398745e966d7736bcd00edca78f -->

<!-- START_ea2881242dfafab1ee2e37b7bc238dde -->
## api/get_jmgs_detail
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/get_jmgs_detail" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/get_jmgs_detail"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/get_jmgs_detail`

`POST api/get_jmgs_detail`

`PUT api/get_jmgs_detail`

`PATCH api/get_jmgs_detail`

`DELETE api/get_jmgs_detail`

`OPTIONS api/get_jmgs_detail`


<!-- END_ea2881242dfafab1ee2e37b7bc238dde -->

<!-- START_c5d62b67b58999aa3531cd905ac7a4ec -->
## api/every
> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/every" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/every"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/every`

`POST api/every`

`PUT api/every`

`PATCH api/every`

`DELETE api/every`

`OPTIONS api/every`


<!-- END_c5d62b67b58999aa3531cd905ac7a4ec -->

<!-- START_fe5fe3a14f04e5648848f1a59ea3da82 -->
## admin/login
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/login"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/login`


<!-- END_fe5fe3a14f04e5648848f1a59ea3da82 -->

<!-- START_1418a859affee5de847e0a5e72884002 -->
## admin/admin/adminInfo
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/adminInfo" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/adminInfo"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/adminInfo`


<!-- END_1418a859affee5de847e0a5e72884002 -->

<!-- START_86b3ec944ae26069563e4ead18db8045 -->
## admin/admin/adminList
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/adminList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/adminList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/adminList`


<!-- END_86b3ec944ae26069563e4ead18db8045 -->

<!-- START_61721ffb8bd1bdd3a9077c99030be9cb -->
## admin/admin/addAdmin
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/addAdmin" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/addAdmin"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/addAdmin`


<!-- END_61721ffb8bd1bdd3a9077c99030be9cb -->

<!-- START_a36ac833a44f45180e6316bf4075d29c -->
## admin/admin/editAdmin
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/editAdmin" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/editAdmin"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/editAdmin`


<!-- END_a36ac833a44f45180e6316bf4075d29c -->

<!-- START_6e83b6f24517a57c1de8d8ec199c6df5 -->
## admin/admin/delAdmin
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/delAdmin" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/delAdmin"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/delAdmin`


<!-- END_6e83b6f24517a57c1de8d8ec199c6df5 -->

<!-- START_134ebfd1119b40d73ea996972c162b3c -->
## admin/admin/adminGroupList
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/adminGroupList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/adminGroupList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/adminGroupList`


<!-- END_134ebfd1119b40d73ea996972c162b3c -->

<!-- START_97735237bea404ad7434e710d2621131 -->
## admin/admin/editAdminGroup
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/editAdminGroup" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/editAdminGroup"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/editAdminGroup`


<!-- END_97735237bea404ad7434e710d2621131 -->

<!-- START_fc75fda542d81fb57427d2f9c38cdf78 -->
## admin/admin/addAdminGroup
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/addAdminGroup" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/addAdminGroup"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/addAdminGroup`


<!-- END_fc75fda542d81fb57427d2f9c38cdf78 -->

<!-- START_3ade81c0e843defdfa67ea1eaafc83de -->
## admin/admin/delAdminGroup
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/delAdminGroup" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/delAdminGroup"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/delAdminGroup`


<!-- END_3ade81c0e843defdfa67ea1eaafc83de -->

<!-- START_09dd4a252fb6649e2f5590781e415af5 -->
## admin/admin/adminGroup
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/adminGroup" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/adminGroup"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/adminGroup`


<!-- END_09dd4a252fb6649e2f5590781e415af5 -->

<!-- START_a6e4b214f0538e4d8576b86c3a2cb6fc -->
## admin/admin/groupList
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/groupList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/groupList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/groupList`


<!-- END_a6e4b214f0538e4d8576b86c3a2cb6fc -->

<!-- START_09eea9c2d4a0a8a928c94d305eb2c361 -->
## admin/admin/menuList
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/menuList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/menuList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/menuList`


<!-- END_09eea9c2d4a0a8a928c94d305eb2c361 -->

<!-- START_93a4ee04712eb78d2c93a425bda9490c -->
## admin/admin/rbacList
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/rbacList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/rbacList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/rbacList`


<!-- END_93a4ee04712eb78d2c93a425bda9490c -->

<!-- START_c773b37c20555890fb135abf75982018 -->
## admin/admin/adminLog
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/admin/adminLog" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/admin/adminLog"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/admin/adminLog`


<!-- END_c773b37c20555890fb135abf75982018 -->

<!-- START_a516e81535d4e43593b76bd4838db46d -->
## addSurgery
添加

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/admin/addSurgery" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"code":"quia","name":"quam","level":"et","type":"aut","option":"incidunt"}'

```

```javascript
const url = new URL(
    "http://localhost/admin/admin/addSurgery"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "code": "quia",
    "name": "quam",
    "level": "et",
    "type": "aut",
    "option": "incidunt"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果"
}
```

### HTTP Request
`GET admin/admin/addSurgery`

`POST admin/admin/addSurgery`

`PUT admin/admin/addSurgery`

`PATCH admin/admin/addSurgery`

`DELETE admin/admin/addSurgery`

`OPTIONS admin/admin/addSurgery`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `code` | string |  required  | 手术编码
        `name` | string |  required  | 手术名称
        `level` | string |  required  | 手术操作分级
        `type` | string |  required  | 类别
        `option` | string |  required  | 录入选项
    
<!-- END_a516e81535d4e43593b76bd4838db46d -->

<!-- START_f13b0f9613725ac38aa0c87a728a9d5d -->
## editSurgery
编辑

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/admin/editSurgery" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"id":15,"code":"sit","name":"reprehenderit","level":"fuga","type":"atque","option":"est"}'

```

```javascript
const url = new URL(
    "http://localhost/admin/admin/editSurgery"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "id": 15,
    "code": "sit",
    "name": "reprehenderit",
    "level": "fuga",
    "type": "atque",
    "option": "est"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果"
}
```

### HTTP Request
`GET admin/admin/editSurgery`

`POST admin/admin/editSurgery`

`PUT admin/admin/editSurgery`

`PATCH admin/admin/editSurgery`

`DELETE admin/admin/editSurgery`

`OPTIONS admin/admin/editSurgery`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `id` | integer |  required  | id
        `code` | string |  required  | 手术编码
        `name` | string |  required  | 手术名称
        `level` | string |  required  | 手术操作分级
        `type` | string |  required  | 类别
        `option` | string |  required  | 录入选项
    
<!-- END_f13b0f9613725ac38aa0c87a728a9d5d -->

<!-- START_97e133d41f288d5f6facd9a8175233ea -->
## delSurgery
删除

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/admin/delSurgery" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"id":2}'

```

```javascript
const url = new URL(
    "http://localhost/admin/admin/delSurgery"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "id": 2
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果",
    "data": {}
}
```

### HTTP Request
`GET admin/admin/delSurgery`

`POST admin/admin/delSurgery`

`PUT admin/admin/delSurgery`

`PATCH admin/admin/delSurgery`

`DELETE admin/admin/delSurgery`

`OPTIONS admin/admin/delSurgery`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `id` | integer |  required  | id
    
<!-- END_97e133d41f288d5f6facd9a8175233ea -->

<!-- START_159dde022608e821f753f6a51535c003 -->
## add
添加

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/admin/addSurgeryMapping" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"code":"facilis","name":"corrupti","three_code":"maiores","three_name":"sit","three_type":"numquam","three_option":"temporibus"}'

```

```javascript
const url = new URL(
    "http://localhost/admin/admin/addSurgeryMapping"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "code": "facilis",
    "name": "corrupti",
    "three_code": "maiores",
    "three_name": "sit",
    "three_type": "numquam",
    "three_option": "temporibus"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果"
}
```

### HTTP Request
`GET admin/admin/addSurgeryMapping`

`POST admin/admin/addSurgeryMapping`

`PUT admin/admin/addSurgeryMapping`

`PATCH admin/admin/addSurgeryMapping`

`DELETE admin/admin/addSurgeryMapping`

`OPTIONS admin/admin/addSurgeryMapping`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `code` | string |  required  | 手术编码
        `name` | string |  required  | 手术名称
        `three_code` | string |  required  | 映射到3.0手术编码
        `three_name` | string |  required  | 映射到3.0手术名称
        `three_type` | string |  required  | 映射到3.0类别
        `three_option` | string |  required  | 映射到3.0录入选项
    
<!-- END_159dde022608e821f753f6a51535c003 -->

<!-- START_4f1f1d655c981f8f28f3b62dc6d54f72 -->
## edit
编辑

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/admin/editSurgeryMapping" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"id":13,"code":"quaerat","name":"earum","three_code":"maxime","three_name":"distinctio","three_type":"doloribus","three_option":"vel"}'

```

```javascript
const url = new URL(
    "http://localhost/admin/admin/editSurgeryMapping"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "id": 13,
    "code": "quaerat",
    "name": "earum",
    "three_code": "maxime",
    "three_name": "distinctio",
    "three_type": "doloribus",
    "three_option": "vel"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果"
}
```

### HTTP Request
`GET admin/admin/editSurgeryMapping`

`POST admin/admin/editSurgeryMapping`

`PUT admin/admin/editSurgeryMapping`

`PATCH admin/admin/editSurgeryMapping`

`DELETE admin/admin/editSurgeryMapping`

`OPTIONS admin/admin/editSurgeryMapping`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `id` | integer |  required  | id
        `code` | string |  required  | 手术编码
        `name` | string |  required  | 手术名称
        `three_code` | string |  required  | 映射到3.0手术编码
        `three_name` | string |  required  | 映射到3.0手术名称
        `three_type` | string |  required  | 映射到3.0类别
        `three_option` | string |  required  | 映射到3.0录入选项
    
<!-- END_4f1f1d655c981f8f28f3b62dc6d54f72 -->

<!-- START_de567fb1cbcb406cb83dff1bff7654c1 -->
## admin/user/userList
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/userList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/userList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/userList`


<!-- END_de567fb1cbcb406cb83dff1bff7654c1 -->

<!-- START_2cb4b9a0fe417e55ae5d219014ff787e -->
## admin/user/addUser
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/addUser" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/addUser"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/addUser`


<!-- END_2cb4b9a0fe417e55ae5d219014ff787e -->

<!-- START_d9af25d96d12834368b9c68a1865d705 -->
## admin/user/editUser
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/editUser" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/editUser"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/editUser`


<!-- END_d9af25d96d12834368b9c68a1865d705 -->

<!-- START_e60cabff9a1ae51c95859df7b7a26d96 -->
## admin/user/delUser
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/delUser" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/delUser"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/delUser`


<!-- END_e60cabff9a1ae51c95859df7b7a26d96 -->

<!-- START_462bef852b742400265d353326abb5c2 -->
## admin/user/userGroup
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/userGroup" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/userGroup"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/userGroup`


<!-- END_462bef852b742400265d353326abb5c2 -->

<!-- START_ef0096598f5ae796c637e0b1220cc32e -->
## admin/user/userGroupList
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/userGroupList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/userGroupList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/userGroupList`


<!-- END_ef0096598f5ae796c637e0b1220cc32e -->

<!-- START_0e97652caa18a72f2f998d1a39f37081 -->
## admin/user/addUserGroup
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/addUserGroup" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/addUserGroup"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/addUserGroup`


<!-- END_0e97652caa18a72f2f998d1a39f37081 -->

<!-- START_17fa0d42eed5437f5c63ed4016c0beae -->
## admin/user/editUserGroup
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/editUserGroup" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/editUserGroup"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/editUserGroup`


<!-- END_17fa0d42eed5437f5c63ed4016c0beae -->

<!-- START_b83e8a37d7589fe57c0337f115f3f7ea -->
## admin/user/delUserGroup
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/delUserGroup" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/delUserGroup"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/delUserGroup`


<!-- END_b83e8a37d7589fe57c0337f115f3f7ea -->

<!-- START_8d4f1f951b5b11409c924f40be873def -->
## admin/user/rbacList
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/rbacList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/rbacList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/rbacList`


<!-- END_8d4f1f951b5b11409c924f40be873def -->

<!-- START_0b0d1e01d4ee0bb3c66adc5e48ba9c4b -->
## admin/user/userLog
> Example request:

```bash
curl -X POST \
    "http://localhost/admin/user/userLog" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/user/userLog"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST admin/user/userLog`


<!-- END_0b0d1e01d4ee0bb3c66adc5e48ba9c4b -->

<!-- START_00aa2f318619d7a17a65a146302c896a -->
## addErrorRule
添加规则

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/rule/addErrorRule" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"auth":"in","field":"molestias","rule":"rerum","relation":"dolore","relation_rule":"asperiores","level":"natus","desc":"iusto","down":8,"type":10,"error_type":18}'

```

```javascript
const url = new URL(
    "http://localhost/admin/rule/addErrorRule"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "auth": "in",
    "field": "molestias",
    "rule": "rerum",
    "relation": "dolore",
    "relation_rule": "asperiores",
    "level": "natus",
    "desc": "iusto",
    "down": 8,
    "type": 10,
    "error_type": 18
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果"
}
```

### HTTP Request
`GET admin/rule/addErrorRule`

`POST admin/rule/addErrorRule`

`PUT admin/rule/addErrorRule`

`PATCH admin/rule/addErrorRule`

`DELETE admin/rule/addErrorRule`

`OPTIONS admin/rule/addErrorRule`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `auth` | string |  required  | 验证字段
        `field` | string |  required  | 验证字段名称
        `rule` | string |  required  | 验证规则
        `relation` | string |  required  | 关联字段
        `relation_rule` | string |  required  | 关联规则
        `level` | string |  required  | 错误等级
        `desc` | string |  required  | 规则描述
        `down` | integer |  required  | 扣分
        `type` | integer |  required  | 缺陷分类0患者基本信息1诊疗信息2费用信息
        `error_type` | integer |  required  | 缺陷类型0逻辑性1规范性2编码
    
<!-- END_00aa2f318619d7a17a65a146302c896a -->

<!-- START_a6eb89f8adae9ae724209b49f69bc797 -->
## delErrorRule
删除

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/rule/delErrorRule" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"id":7}'

```

```javascript
const url = new URL(
    "http://localhost/admin/rule/delErrorRule"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "id": 7
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果",
    "data": {}
}
```

### HTTP Request
`GET admin/rule/delErrorRule`

`POST admin/rule/delErrorRule`

`PUT admin/rule/delErrorRule`

`PATCH admin/rule/delErrorRule`

`DELETE admin/rule/delErrorRule`

`OPTIONS admin/rule/delErrorRule`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `id` | integer |  required  | id
    
<!-- END_a6eb89f8adae9ae724209b49f69bc797 -->

<!-- START_97a3930167ce6ce943d9a0d56861962f -->
## editErrorRule
编辑规则

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/rule/editErrorRule" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"id":2,"auth":"repellendus","field":"laborum","rule":"alias","relation":"nobis","relation_rule":"aliquam","level":"rerum","desc":"unde","down":7,"type":5,"error_type":4}'

```

```javascript
const url = new URL(
    "http://localhost/admin/rule/editErrorRule"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "id": 2,
    "auth": "repellendus",
    "field": "laborum",
    "rule": "alias",
    "relation": "nobis",
    "relation_rule": "aliquam",
    "level": "rerum",
    "desc": "unde",
    "down": 7,
    "type": 5,
    "error_type": 4
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果"
}
```

### HTTP Request
`GET admin/rule/editErrorRule`

`POST admin/rule/editErrorRule`

`PUT admin/rule/editErrorRule`

`PATCH admin/rule/editErrorRule`

`DELETE admin/rule/editErrorRule`

`OPTIONS admin/rule/editErrorRule`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `id` | integer |  required  | id
        `auth` | string |  required  | 验证字段
        `field` | string |  required  | 验证字段名称
        `rule` | string |  required  | 验证规则
        `relation` | string |  required  | 关联字段
        `relation_rule` | string |  required  | 关联规则
        `level` | string |  required  | 错误等级
        `desc` | string |  required  | 规则描述
        `down` | integer |  required  | 扣分
        `type` | integer |  required  | 缺陷分类0患者基本信息1诊疗信息2费用信息
        `error_type` | integer |  required  | 缺陷类型0逻辑性1规范性2编码
    
<!-- END_97a3930167ce6ce943d9a0d56861962f -->

<!-- START_782d2d39e654ea4d70ba820a270f6ac9 -->
## admin/rule/updateStatus
> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/rule/updateStatus" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/rule/updateStatus"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "c": 1004,
    "d": false,
    "m": "无效token",
    "p": {},
    "t": 1679922581
}
```

### HTTP Request
`GET admin/rule/updateStatus`

`POST admin/rule/updateStatus`

`PUT admin/rule/updateStatus`

`PATCH admin/rule/updateStatus`

`DELETE admin/rule/updateStatus`

`OPTIONS admin/rule/updateStatus`


<!-- END_782d2d39e654ea4d70ba820a270f6ac9 -->

<!-- START_d6555ec15435391d24783e83471deb3f -->
## admin/operation/relationsList
> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/operation/relationsList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/operation/relationsList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "c": 1004,
    "d": false,
    "m": "无效token",
    "p": {},
    "t": 1679922581
}
```

### HTTP Request
`GET admin/operation/relationsList`

`POST admin/operation/relationsList`

`PUT admin/operation/relationsList`

`PATCH admin/operation/relationsList`

`DELETE admin/operation/relationsList`

`OPTIONS admin/operation/relationsList`


<!-- END_d6555ec15435391d24783e83471deb3f -->

<!-- START_bc8193ecd3a6b052fbe833aeb6cda2e9 -->
## admin/operation/addOperationRelation
> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/operation/addOperationRelation" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/operation/addOperationRelation"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "c": 1004,
    "d": false,
    "m": "无效token",
    "p": {},
    "t": 1679922581
}
```

### HTTP Request
`GET admin/operation/addOperationRelation`

`POST admin/operation/addOperationRelation`

`PUT admin/operation/addOperationRelation`

`PATCH admin/operation/addOperationRelation`

`DELETE admin/operation/addOperationRelation`

`OPTIONS admin/operation/addOperationRelation`


<!-- END_bc8193ecd3a6b052fbe833aeb6cda2e9 -->

<!-- START_09bafe674383eddb568d1fe595f50bb2 -->
## admin/operation/editOperationRelation
> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/operation/editOperationRelation" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/operation/editOperationRelation"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "c": 1004,
    "d": false,
    "m": "无效token",
    "p": {},
    "t": 1679922581
}
```

### HTTP Request
`GET admin/operation/editOperationRelation`

`POST admin/operation/editOperationRelation`

`PUT admin/operation/editOperationRelation`

`PATCH admin/operation/editOperationRelation`

`DELETE admin/operation/editOperationRelation`

`OPTIONS admin/operation/editOperationRelation`


<!-- END_09bafe674383eddb568d1fe595f50bb2 -->

<!-- START_b3dbecec12f760ac2eb512d945807892 -->
## admin/operation/delOperationRelation
> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/operation/delOperationRelation" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/operation/delOperationRelation"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "c": 1004,
    "d": false,
    "m": "无效token",
    "p": {},
    "t": 1679922581
}
```

### HTTP Request
`GET admin/operation/delOperationRelation`

`POST admin/operation/delOperationRelation`

`PUT admin/operation/delOperationRelation`

`PATCH admin/operation/delOperationRelation`

`DELETE admin/operation/delOperationRelation`

`OPTIONS admin/operation/delOperationRelation`


<!-- END_b3dbecec12f760ac2eb512d945807892 -->

<!-- START_8c2efe0952e79b776727e6d06420a16b -->
## admin/operation/updateStatus
> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/operation/updateStatus" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/operation/updateStatus"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "c": 1004,
    "d": false,
    "m": "无效token",
    "p": {},
    "t": 1679922581
}
```

### HTTP Request
`GET admin/operation/updateStatus`

`POST admin/operation/updateStatus`

`PUT admin/operation/updateStatus`

`PATCH admin/operation/updateStatus`

`DELETE admin/operation/updateStatus`

`OPTIONS admin/operation/updateStatus`


<!-- END_8c2efe0952e79b776727e6d06420a16b -->

<!-- START_7961b6f65bd8e99b1740a3066b2c4cf4 -->
## admin/operation/getCaseRule
> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/operation/getCaseRule" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/operation/getCaseRule"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "c": 1004,
    "d": false,
    "m": "无效token",
    "p": {},
    "t": 1679922581
}
```

### HTTP Request
`GET admin/operation/getCaseRule`

`POST admin/operation/getCaseRule`

`PUT admin/operation/getCaseRule`

`PATCH admin/operation/getCaseRule`

`DELETE admin/operation/getCaseRule`

`OPTIONS admin/operation/getCaseRule`


<!-- END_7961b6f65bd8e99b1740a3066b2c4cf4 -->

<!-- START_e9eba73c33e3226beb7e96df664460ca -->
## admin/operation/addCaseRule
> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/operation/addCaseRule" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/admin/operation/addCaseRule"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "c": 1004,
    "d": false,
    "m": "无效token",
    "p": {},
    "t": 1679922581
}
```

### HTTP Request
`GET admin/operation/addCaseRule`

`POST admin/operation/addCaseRule`

`PUT admin/operation/addCaseRule`

`PATCH admin/operation/addCaseRule`

`DELETE admin/operation/addCaseRule`

`OPTIONS admin/operation/addCaseRule`


<!-- END_e9eba73c33e3226beb7e96df664460ca -->

<!-- START_a5778bb5c331653fce61aa0335ffddc8 -->
## jmjk/submit
> Example request:

```bash
curl -X POST \
    "http://localhost/jmjk/submit" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/jmjk/submit"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST jmjk/submit`


<!-- END_a5778bb5c331653fce61aa0335ffddc8 -->

#quality


<!-- START_b6b4a74f82321068f39f53220d85d9b4 -->
## selectInfo
筛选数据

> Example request:

```bash
curl -X POST \
    "http://localhost/api/selectInfo" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/selectInfo"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "coder": [
            {
                "id": "1",
                "name": "text"
            }
        ],
        "pay": [
            {
                "id": "1.1",
                "name": "1.1 - 本市城镇职工基本医疗保险"
            }
        ],
        "editStatus": [
            {
                "id": "all",
                "name": "全部"
            },
            {
                "id": "0",
                "name": "未编辑"
            },
            {
                "id": "1",
                "name": "已编辑"
            }
        ],
        "department": [
            {
                "id": "2",
                "name": "text"
            }
        ],
        "level": [
            {
                "id": "all",
                "name": "全部"
            },
            {
                "id": "0",
                "name": "强制"
            },
            {
                "id": "1",
                "name": "建议"
            }
        ],
        "IN_STATUS": [
            {
                "id": "all",
                "name": "全部"
            }
        ],
        "field": [
            {
                "id": "1",
                "name": "text"
            }
        ]
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/selectInfo`


<!-- END_b6b4a74f82321068f39f53220d85d9b4 -->

<!-- START_42b8343b1d87edbe9cc517364e34bbee -->
## qualityList
病案列表

> Example request:

```bash
curl -X POST \
    "http://localhost/api/qualityList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"bllb":"perspiciatis","search_keyword":"mollitia","AAA28":"itaque","AAC11N":"dolore","AAA26C":"nobis","status":"ut","level":"recusandae","AAC01":"omnis","AAA04":"omnis","AAA40":"quod","coder_id":"qui","field":{},"page":"perspiciatis","limit":"aut"}'

```

```javascript
const url = new URL(
    "http://localhost/api/qualityList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "bllb": "perspiciatis",
    "search_keyword": "mollitia",
    "AAA28": "itaque",
    "AAC11N": "dolore",
    "AAA26C": "nobis",
    "status": "ut",
    "level": "recusandae",
    "AAC01": "omnis",
    "AAA04": "omnis",
    "AAA40": "quod",
    "coder_id": "qui",
    "field": {},
    "page": "perspiciatis",
    "limit": "aut"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "AAA28": "",
                "AAA01": "",
                "AAA02C": "",
                "AAA04": "",
                "AAA29": "",
                "AAC11N": "",
                "AAC01": "",
                "ADA01": "",
                "F_D": "",
                "J": "",
                "ABC01N": "",
                "ICD9_NAME": "",
                "AAC04": "",
                "ATTEND_GRP_NAME": "",
                "AAB06C": ""
            }
        ],
        "count": 100
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/qualityList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `bllb` | string |  optional  | 病例名称
        `search_keyword` | string |  optional  | 关键词搜索
        `AAA28` | string |  optional  | 病案号
        `AAC11N` | string |  optional  | 出院科室
        `AAA26C` | string |  optional  | 付款方式
        `status` | string |  optional  | 编辑状态
        `level` | string |  optional  | 编辑状态
        `AAC01` | string |  optional  | 出院时间
        `AAA04` | string |  optional  | 年龄
        `AAA40` | string |  optional  | 不足一周岁年龄
        `coder_id` | string |  optional  | 编码员ID
        `field` | object |  optional  | 字段条件
        `page` | string |  required  | 页码
        `limit` | string |  required  | 条数
    
<!-- END_42b8343b1d87edbe9cc517364e34bbee -->

<!-- START_1d63a46064d7e574414a8ad6125a5c53 -->
## getHomeQualityList
病案列表

> Example request:

```bash
curl -X POST \
    "http://localhost/api/getHomeQualityList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAA28":"mollitia","AAC11N":"asperiores","AAA26C":"omnis","status":"harum","level":"cupiditate","AAC01":"repellendus","AAA04":"dolorem","AAA40":"voluptate","coder_id":"iusto","field":{},"page":"voluptatem","limit":"voluptas"}'

```

```javascript
const url = new URL(
    "http://localhost/api/getHomeQualityList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAA28": "mollitia",
    "AAC11N": "asperiores",
    "AAA26C": "omnis",
    "status": "harum",
    "level": "cupiditate",
    "AAC01": "repellendus",
    "AAA04": "dolorem",
    "AAA40": "voluptate",
    "coder_id": "iusto",
    "field": {},
    "page": "voluptatem",
    "limit": "voluptas"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "AAA28": "",
                "AAA01": "",
                "AAA02C": "",
                "AAA04": "",
                "AAA29": "",
                "AAC11N": "",
                "AAC01": "",
                "ADA01": "",
                "F_D": "",
                "J": "",
                "ABC01N": "",
                "ICD9_NAME": "",
                "AAC04": "",
                "ATTEND_GRP_NAME": "",
                "AAB06C": ""
            }
        ],
        "count": 100
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/getHomeQualityList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAA28` | string |  optional  | 病案号
        `AAC11N` | string |  optional  | 出院科室
        `AAA26C` | string |  optional  | 付款方式
        `status` | string |  optional  | 编辑状态
        `level` | string |  optional  | 编辑状态
        `AAC01` | string |  optional  | 出院时间
        `AAA04` | string |  optional  | 年龄
        `AAA40` | string |  optional  | 不足一周岁年龄
        `coder_id` | string |  optional  | 编码员ID
        `field` | object |  optional  | 字段条件
        `page` | string |  required  | 页码
        `limit` | string |  required  | 条数
    
<!-- END_1d63a46064d7e574414a8ad6125a5c53 -->

<!-- START_9082fbee5f0f448483716dc434a4c852 -->
## ruleList
规则列表

> Example request:

```bash
curl -X POST \
    "http://localhost/api/ruleList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"text":"esse"}'

```

```javascript
const url = new URL(
    "http://localhost/api/ruleList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "text": "esse"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": [
        {
            "id": 1,
            "field": "",
            "desc": ""
        }
    ],
    "time": 123787842
}
```

### HTTP Request
`POST api/ruleList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `text` | string |  optional  | 缺陷项目
    
<!-- END_9082fbee5f0f448483716dc434a4c852 -->

<!-- START_aaac44c5472266ad87c158b1b3a3125b -->
## errorList
缺陷分析列表

> Example request:

```bash
curl -X POST \
    "http://localhost/api/errorList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"title":"et","time":"consequuntur","type":11,"error_id":17,"coder":14,"page":"officia","limit":"deserunt"}'

```

```javascript
const url = new URL(
    "http://localhost/api/errorList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "et",
    "time": "consequuntur",
    "type": 11,
    "error_id": 17,
    "coder": 14,
    "page": "officia",
    "limit": "deserunt"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "AAA28": "",
                "AAB01": "",
                "AAC11N": "",
                "AAC01": "",
                "AAA01": "",
                "desc": "",
                "error_name": ""
            }
        ],
        "count": 0
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/errorList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `title` | string |  optional  | start_time 开始时间  时间戳or时间
        `time` | string |  optional  | end_time 结束时间
        `type` | integer |  optional  | 按年(1)、季度(2)、月(3);
        `error_id` | integer |  required  | 错误规则ID
        `coder` | integer |  required  | 编码员ID
        `page` | string |  required  | 页码
        `limit` | string |  required  | 条数
    
<!-- END_aaac44c5472266ad87c158b1b3a3125b -->

<!-- START_a1f75356a5dcff4686480bf21fb1985d -->
## errorData
缺陷问题

> Example request:

```bash
curl -X POST \
    "http://localhost/api/errorData" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAC11C":"culpa","level":"est","start_time":"neque","end_time":"sed","type":17}'

```

```javascript
const url = new URL(
    "http://localhost/api/errorData"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAC11C": "culpa",
    "level": "est",
    "start_time": "neque",
    "end_time": "sed",
    "type": 17
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "count": "缺陷数量",
                "field": "缺陷字段",
                "desc": "缺陷描述",
                "level": "缺陷分级",
                "error_rule": "缺陷id,点击查询缺陷列表用的id"
            }
        ],
        "count": {
            "base": "患者基本信息",
            "diagnosis": "诊疗信息",
            "cost": "费用信息"
        }
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/errorData`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAC11C` | string |  optional  | 科室编码 all:全部
        `level` | string |  optional  | 错误等级 all:全部0:强制1:建议
        `start_time` | string |  optional  | 开始时间 时间戳
        `end_time` | string |  optional  | 结束时间
        `type` | integer |  optional  | 按年(1)、季度(2)、月(3)
    
<!-- END_a1f75356a5dcff4686480bf21fb1985d -->

<!-- START_a72f66401a55f57bbce9c6fa2cc6950d -->
## errorDataList
缺陷列表

> Example request:

```bash
curl -X POST \
    "http://localhost/api/errorDataList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAC11C":"odio","level":2,"error_type":3,"hospital_name":"voluptatem","department_name":"et","coder_name":"quod","page":"consequatur","limit":"veritatis"}'

```

```javascript
const url = new URL(
    "http://localhost/api/errorDataList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAC11C": "odio",
    "level": 2,
    "error_type": 3,
    "hospital_name": "voluptatem",
    "department_name": "et",
    "coder_name": "quod",
    "page": "consequatur",
    "limit": "veritatis"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "AAA28": "病案号",
                "AAA01": "患者姓名",
                "AAC11N": "出院科室",
                "AAC01": "出院时间",
                "error_field": "缺陷项",
                "level": "缺陷级别",
                "desc": "修订建议",
                "AAC03": "出院病房",
                "AEE04": "住院医师"
            }
        ],
        "count": 0
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/errorDataList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAC11C` | string |  optional  | 科室ID
        `level` | integer |  optional  | 错误等级
        `error_type` | integer |  optional  | 错误类型0：逻辑性1：规范性2：编码
        `hospital_name` | string |  optional  | 住院医师姓名
        `department_name` | string |  optional  | 主治医师姓名
        `coder_name` | string |  optional  | 编码员姓名
        `page` | string |  required  | 页码
        `limit` | string |  required  | 条数
    
<!-- END_a72f66401a55f57bbce9c6fa2cc6950d -->

<!-- START_f1dfef71ff21edfc6f7a796b5b5751bd -->
## errorDataList
缺陷列表

> Example request:

```bash
curl -X POST \
    "http://localhost/api/homeErrorDataList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAC11C":"quo","level":19,"error_type":20,"hospital_name":"veniam","department_name":"voluptas","coder_name":"perferendis","page":"qui","limit":"unde"}'

```

```javascript
const url = new URL(
    "http://localhost/api/homeErrorDataList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAC11C": "quo",
    "level": 19,
    "error_type": 20,
    "hospital_name": "veniam",
    "department_name": "voluptas",
    "coder_name": "perferendis",
    "page": "qui",
    "limit": "unde"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "AAA28": "病案号",
                "AAA01": "患者姓名",
                "AAC11N": "出院科室",
                "AAC01": "出院时间",
                "error_field": "缺陷项",
                "level": "缺陷级别",
                "desc": "修订建议",
                "AAC03": "出院病房",
                "AEE04": "住院医师"
            }
        ],
        "count": 0
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/homeErrorDataList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAC11C` | string |  optional  | 科室ID
        `level` | integer |  optional  | 错误等级
        `error_type` | integer |  optional  | 错误类型0：逻辑性1：规范性2：编码
        `hospital_name` | string |  optional  | 住院医师姓名
        `department_name` | string |  optional  | 主治医师姓名
        `coder_name` | string |  optional  | 编码员姓名
        `page` | string |  required  | 页码
        `limit` | string |  required  | 条数
    
<!-- END_f1dfef71ff21edfc6f7a796b5b5751bd -->

<!-- START_3086df182f3ea77bae37af6d45ce56bf -->
## errorCount
缺陷统计

> Example request:

```bash
curl -X POST \
    "http://localhost/api/errorCount" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAC01":{}}'

```

```javascript
const url = new URL(
    "http://localhost/api/errorCount"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAC01": {}
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "total": 20,
        "total_error": 0,
        "avg": 95
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/errorCount`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAC01` | object |  optional  | 出院时间
    
<!-- END_3086df182f3ea77bae37af6d45ce56bf -->

#ranking


<!-- START_ffa4ac1c1ebf239e7335b07ed224f2c9 -->
## hospital
住院医师排名 前5条

> Example request:

```bash
curl -X POST \
    "http://localhost/api/ranking_hospital" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"title":"distinctio","time":"est","type":18,"num":5}'

```

```javascript
const url = new URL(
    "http://localhost/api/ranking_hospital"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "distinctio",
    "time": "est",
    "type": 18,
    "num": 5
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "结果",
    "data": {
        "list": [
            {
                "total_medical": "总病案数量",
                "total_error_medical": "累计有问题病案数",
                "control_medical": "质控病案数?",
                "name": "科室",
                "hospital_name": "住院医师",
                "total_error_proportion": "问题比例",
                "complete_error_medical": "完整性问题-病案数",
                "complete_error_proportion": "完整性问题-病案比例",
                "logic_error_medical": "逻辑性",
                "standard_error_medical": "规范性",
                "code_error_medical": "编码错误?"
            }
        ]
    }
}
```

### HTTP Request
`POST api/ranking_hospital`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `title` | string |  optional  | start_time 开始时间  时间戳or时间
        `time` | string |  optional  | end_time 结束时间
        `type` | integer |  optional  | 按年(1)、季度(2)、月(3);
        `num` | integer |  optional  | 几条数据
    
<!-- END_ffa4ac1c1ebf239e7335b07ed224f2c9 -->

<!-- START_e525f58e4fe8afbbe9c5554da1536072 -->
## department
/api/ranking_department
科室排名 前10条

> Example request:

```bash
curl -X POST \
    "http://localhost/api/ranking_department" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"title":"repellat","time":"voluptatem","type":"blanditiis"}'

```

```javascript
const url = new URL(
    "http://localhost/api/ranking_department"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "repellat",
    "time": "voluptatem",
    "type": "blanditiis"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "结果",
    "data": {
        "list": [
            {
                "average_score": "平均分",
                "name": "科室名称",
                "total_medical": "病案数",
                "total_error_medical": "缺陷病案数",
                "total_error": "总缺陷",
                "average_error": "平均缺陷",
                "max_score": "最高分",
                "min_score": "最低分",
                "outstanding": "优秀率"
            }
        ]
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/ranking_department`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `title` | string |  optional  | start_time 开始时间  时间戳or时间
        `time` | string |  optional  | end_time 结束时间
        `type` | string |  optional  | 按年(1)、季度(2)、月(3);
    
<!-- END_e525f58e4fe8afbbe9c5554da1536072 -->

<!-- START_aaa33891dedade23f90934e3ee5a52e2 -->
## attendingGroup
主诊组排名 前10条

> Example request:

```bash
curl -X POST \
    "http://localhost/api/ranking_attending_group" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"title":"provident","time":"aspernatur","type":"voluptatem"}'

```

```javascript
const url = new URL(
    "http://localhost/api/ranking_attending_group"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "provident",
    "time": "aspernatur",
    "type": "voluptatem"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "结果",
    "data": {
        "list": [
            {
                "total_medical": "总病案数量",
                "total_error_medical": "累计有问题病案数",
                "control_medical": "质控病案数",
                "name": "科室",
                "department_name": "主诊组",
                "total_error_proportion": "问题比例",
                "complete_error_medical": "完整性问题-病案数",
                "complete_error_proportion": "完整性问题-病案比例",
                "logic_error_medical": "逻辑性",
                "standard_error_medical": "规范性",
                "code_error_medical": "编码错误"
            }
        ]
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/ranking_attending_group`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `title` | string |  optional  | start_time 开始时间  时间戳or时间
        `time` | string |  optional  | end_time 结束时间
        `type` | string |  optional  | 按年(1)、季度(2)、月(3);
    
<!-- END_aaa33891dedade23f90934e3ee5a52e2 -->

<!-- START_96d6acf2fb66ed5a9dcfce36808be323 -->
## indications
主治医师排名 前5条

> Example request:

```bash
curl -X POST \
    "http://localhost/api/ranking_indications" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"start_time":"facilis","end_time":"aut","type":8,"num":7}'

```

```javascript
const url = new URL(
    "http://localhost/api/ranking_indications"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "start_time": "facilis",
    "end_time": "aut",
    "type": 8,
    "num": 7
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "结果",
    "data": {
        "list": [
            {
                "total_medical": "总病案数量",
                "total_error_medical": "累计有问题病案数",
                "control_medical": "质控病案数",
                "name": "科室",
                "department_name": "主治医师姓名",
                "total_error_proportion": "问题比例",
                "complete_error_medical": "完整性问题-病案数",
                "complete_error_proportion": "完整性问题-病案比例",
                "logic_error_medical": "逻辑性",
                "standard_error_medical": "规范性",
                "code_error_medical": "编码错误"
            }
        ]
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/ranking_indications`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `start_time` | string |  optional  | 开始时间  时间戳or时间
        `end_time` | string |  optional  | 结束时间
        `type` | integer |  optional  | 按年(1)、季度(2)、月(3);
        `num` | integer |  optional  | 5条/10条
    
<!-- END_96d6acf2fb66ed5a9dcfce36808be323 -->

<!-- START_f0b4607e755b697aa4c22227ab016a78 -->
## coder
编码员排名 前5条

> Example request:

```bash
curl -X POST \
    "http://localhost/api/ranking_coder" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"title":"doloribus","time":"ut","type":"dolores","num":9}'

```

```javascript
const url = new URL(
    "http://localhost/api/ranking_coder"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "doloribus",
    "time": "ut",
    "type": "dolores",
    "num": 9
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "结果",
    "data": {
        "list": [
            {
                "total_error_medical": "累计有问题病案数",
                "scores": "分数",
                "name": "姓名",
                "error_proportion": "处理病案占比",
                "total_error": "处理病案数",
                "total_medical": "问题比例",
                "code_proportion": "编码问题占比",
                "code_error_medical": "编码问题病案数"
            }
        ]
    },
    "time": 123787842
}
```

### HTTP Request
`POST api/ranking_coder`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `title` | string |  optional  | 开始时间  时间戳or时间
        `time` | string |  optional  | 结束时间
        `type` | string |  optional  | 按年(1)、季度(2)、月(3);
        `num` | integer |  optional  | 几条数据
    
<!-- END_f0b4607e755b697aa4c22227ab016a78 -->

#temporary


<!-- START_084d659e47b48383bf85167705d538d9 -->
## long
长期医嘱

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/long" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAA28":"ratione","page":"rerum","limit":"corrupti"}'

```

```javascript
const url = new URL(
    "http://localhost/api/long"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAA28": "ratione",
    "page": "rerum",
    "limit": "corrupti"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "YZMC": "医嘱名称",
                "KZSJ": "2020-12-28 08:12:58",
                "TZSJ": "2020-12-29 08:04:00",
                "KZYS": "开嘱医师",
                "TZYS": "停嘱医师",
                "XZJDGH": "护士",
                "KSDATE": "开始日期",
                "KSTIME": "开始时间",
                "TZDATE": "停止日期",
                "TZTIME": "停止时间"
            }
        ],
        "info": {
            "AAA28": "住院号",
            "AAA01": "姓名",
            "AAA02C": "性别",
            "AAA04": "年龄",
            "AAB02C": "科别"
        }
    },
    "time": 123787842
}
```

### HTTP Request
`GET api/long`

`POST api/long`

`PUT api/long`

`PATCH api/long`

`DELETE api/long`

`OPTIONS api/long`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAA28` | string |  required  | 病案号
        `page` | string |  optional  | 页码
        `limit` | string |  optional  | 条数
    
<!-- END_084d659e47b48383bf85167705d538d9 -->

<!-- START_a51f70de948956c70c350fdd9d2b017f -->
## temporary
临时医嘱

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/temporary" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAA28":"earum","page":"sit","limit":"est"}'

```

```javascript
const url = new URL(
    "http://localhost/api/temporary"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAA28": "earum",
    "page": "sit",
    "limit": "est"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "YZMC": "医嘱名称",
                "KZSJ": "2020-12-31 10:10:28",
                "TZSJ": "2020-12-31 10:10:28",
                "KZYS": "开嘱医师",
                "XZJDGH": "护士",
                "XZJDSJ": "执行时间",
                "DATE": "开嘱日期",
                "TIME": "开嘱时间"
            }
        ],
        "info": {
            "AAA28": "住院号",
            "AAA01": "姓名",
            "AAA02C": "性别",
            "AAA04": "年龄",
            "AAB02C": "科别"
        }
    },
    "time": 123787842
}
```

### HTTP Request
`GET api/temporary`

`POST api/temporary`

`PUT api/temporary`

`PATCH api/temporary`

`DELETE api/temporary`

`OPTIONS api/temporary`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAA28` | string |  required  | 病案号
        `page` | string |  optional  | 页码
        `limit` | string |  optional  | 条数
    
<!-- END_a51f70de948956c70c350fdd9d2b017f -->

<!-- START_bef4ce12038d915e145d7223f0826f00 -->
## getDoctorAdvice
医嘱查询

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/getDoctorAdvice" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAA28":"quae","start":"vel","end":"consequatur","YZQX":20,"BRKS":12,"KZKS":7,"YZMC":"et","page":"porro","limit":"pariatur"}'

```

```javascript
const url = new URL(
    "http://localhost/api/getDoctorAdvice"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAA28": "quae",
    "start": "vel",
    "end": "consequatur",
    "YZQX": 20,
    "BRKS": 12,
    "KZKS": 7,
    "YZMC": "et",
    "page": "porro",
    "limit": "pariatur"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": {
        "list": [
            {
                "ZYH": "住院号",
                "YZMC": "医嘱名称",
                "BRKS": "病人科室",
                "KZKS": "开嘱科室",
                "YZQX": "医嘱期效",
                "KZSJ": "开嘱时间",
                "AAA28": "病案号",
                "AAC01": "出院时间",
                "AAB01": "入院时间"
            }
        ],
        "total": 200
    },
    "time": 123787842
}
```

### HTTP Request
`GET api/getDoctorAdvice`

`POST api/getDoctorAdvice`

`PUT api/getDoctorAdvice`

`PATCH api/getDoctorAdvice`

`DELETE api/getDoctorAdvice`

`OPTIONS api/getDoctorAdvice`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAA28` | string |  optional  | 病案号
        `start` | string |  optional  | 开始时间
        `end` | string |  optional  | 结束时间
        `YZQX` | integer |  optional  | 医嘱期效
        `BRKS` | integer |  optional  | 病人科室
        `KZKS` | integer |  optional  | 开嘱科室
        `YZMC` | string |  optional  | 医嘱名称
        `page` | string |  optional  | 页码
        `limit` | string |  optional  | 条数
    
<!-- END_bef4ce12038d915e145d7223f0826f00 -->

<!-- START_8a4ce03fec4d1cca9e2f2d2ae93812c3 -->
## doctorAdviceSelect
医嘱查询条件

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/doctorAdviceSelect" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/doctorAdviceSelect"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "",
    "data": [
        {
            "key": "YZMC",
            "name": "医嘱名称",
            "type": "input",
            "value": ""
        },
        {
            "key": "AAA28",
            "name": "病案号",
            "type": "input",
            "value": ""
        }
    ],
    "time": 123787842
}
```

### HTTP Request
`GET api/doctorAdviceSelect`

`POST api/doctorAdviceSelect`

`PUT api/doctorAdviceSelect`

`PATCH api/doctorAdviceSelect`

`DELETE api/doctorAdviceSelect`

`OPTIONS api/doctorAdviceSelect`


<!-- END_8a4ce03fec4d1cca9e2f2d2ae93812c3 -->

<!-- START_86412abc065fecd30806e517ee1351cc -->
## doctorAdviceExport
医嘱查询

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/doctorAdviceExport" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"AAA28":"nam","start":"ea","end":"maiores","YZQX":6,"BRKS":1,"KZKS":11,"YZMC":"illum"}'

```

```javascript
const url = new URL(
    "http://localhost/api/doctorAdviceExport"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "AAA28": "nam",
    "start": "ea",
    "end": "maiores",
    "YZQX": 6,
    "BRKS": 1,
    "KZKS": 11,
    "YZMC": "illum"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 4001,
    "msg": "请先登录",
    "data": {},
    "time": 1679922581
}
```

### HTTP Request
`GET api/doctorAdviceExport`

`POST api/doctorAdviceExport`

`PUT api/doctorAdviceExport`

`PATCH api/doctorAdviceExport`

`DELETE api/doctorAdviceExport`

`OPTIONS api/doctorAdviceExport`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `AAA28` | string |  optional  | 病案号
        `start` | string |  optional  | 开始时间
        `end` | string |  optional  | 结束时间
        `YZQX` | integer |  optional  | 医嘱期效
        `BRKS` | integer |  optional  | 病人科室
        `KZKS` | integer |  optional  | 开嘱科室
        `YZMC` | string |  optional  | 医嘱名称
    
<!-- END_86412abc065fecd30806e517ee1351cc -->

#work


<!-- START_bb1626d1c85f50b655a2c505954da8fd -->
## record

> Example request:

```bash
curl -X POST \
    "http://localhost/api/workrecord" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"title":"est","time":"odit","limit":"autem","page":"molestiae"}'

```

```javascript
const url = new URL(
    "http://localhost/api/workrecord"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "est",
    "time": "odit",
    "limit": "autem",
    "page": "molestiae"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 200,
    "msg": "结果",
    "data": {
        "list": [
            {
                "id": "",
                "AAA28": "病案号",
                "time": "业务时间",
                "desc": "业务操作详情",
                "operator": "业务操作人"
            }
        ],
        "count": 1
    }
}
```

### HTTP Request
`POST api/workrecord`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `title` | string |  optional  | start_time 业务时间-开始时间  时间戳or时间
        `time` | string |  optional  | end_time 业务实践-结束时间
        `limit` | string |  optional  | end_time 每页的数据条数
        `page` | string |  optional  | end_time 当前第几页
    
<!-- END_bb1626d1c85f50b655a2c505954da8fd -->

<!-- START_cb6cfc3c462f0beca6c91a32835c8645 -->
## repoet

> Example request:

```bash
curl -X POST \
    "http://localhost/api/workrepoet" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"title":"velit","time":"consequatur","limit":"dignissimos","page":"deserunt"}'

```

```javascript
const url = new URL(
    "http://localhost/api/workrepoet"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "velit",
    "time": "consequatur",
    "limit": "dignissimos",
    "page": "deserunt"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
null
```

### HTTP Request
`POST api/workrepoet`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `title` | string |  optional  | start_time 业务时间-开始时间  时间戳or时间
        `time` | string |  optional  | end_time 业务实践-结束时间
        `limit` | string |  optional  | end_time 每页的数据条数
        `page` | string |  optional  | end_time 当前第几页
    
<!-- END_cb6cfc3c462f0beca6c91a32835c8645 -->

<!-- START_75e8485b9e82d197932ce97c278f0116 -->
## getList
获取列表

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/admin/getSurgeryList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"limit":"ad","page":"quia"}'

```

```javascript
const url = new URL(
    "http://localhost/admin/admin/getSurgeryList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "limit": "ad",
    "page": "quia"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果",
    "data": {
        "list": [
            {
                "id": ""
            }
        ],
        "count": 1
    }
}
```

### HTTP Request
`GET admin/admin/getSurgeryList`

`POST admin/admin/getSurgeryList`

`PUT admin/admin/getSurgeryList`

`PATCH admin/admin/getSurgeryList`

`DELETE admin/admin/getSurgeryList`

`OPTIONS admin/admin/getSurgeryList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `limit` | string |  optional  | end_time 每页的数据条数
        `page` | string |  optional  | end_time 当前第几页
    
<!-- END_75e8485b9e82d197932ce97c278f0116 -->

<!-- START_4da9771856b7893cfd97b1e1b89aaf40 -->
## getList
获取列表

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/admin/getSurgeryMappingList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"limit":"consequatur","page":"vel"}'

```

```javascript
const url = new URL(
    "http://localhost/admin/admin/getSurgeryMappingList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "limit": "consequatur",
    "page": "vel"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果",
    "data": {
        "list": [
            {
                "id": ""
            }
        ],
        "count": 1
    }
}
```

### HTTP Request
`GET admin/admin/getSurgeryMappingList`

`POST admin/admin/getSurgeryMappingList`

`PUT admin/admin/getSurgeryMappingList`

`PATCH admin/admin/getSurgeryMappingList`

`DELETE admin/admin/getSurgeryMappingList`

`OPTIONS admin/admin/getSurgeryMappingList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `limit` | string |  optional  | end_time 每页的数据条数
        `page` | string |  optional  | end_time 当前第几页
    
<!-- END_4da9771856b7893cfd97b1e1b89aaf40 -->

<!-- START_53ae0fef2a9db7600fbd0a99cd3b2583 -->
## getList
获取规则列表

> Example request:

```bash
curl -X GET \
    -G "http://localhost/admin/rule/errorList" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"limit":"provident","page":"consequatur"}'

```

```javascript
const url = new URL(
    "http://localhost/admin/rule/errorList"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "limit": "provident",
    "page": "consequatur"
}

fetch(url, {
    method: "GET",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "code": 0,
    "msg": "结果",
    "data": {
        "list": [
            {
                "id": ""
            }
        ],
        "count": 1
    }
}
```

### HTTP Request
`GET admin/rule/errorList`

`POST admin/rule/errorList`

`PUT admin/rule/errorList`

`PATCH admin/rule/errorList`

`DELETE admin/rule/errorList`

`OPTIONS admin/rule/errorList`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `limit` | string |  optional  | end_time 每页的数据条数
        `page` | string |  optional  | end_time 当前第几页
    
<!-- END_53ae0fef2a9db7600fbd0a99cd3b2583 -->


