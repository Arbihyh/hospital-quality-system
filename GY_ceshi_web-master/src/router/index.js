import Vue from 'vue';
import Router from 'vue-router';
import store from '../store'
import menu from '../menu/menu.js'
import { getToken } from '@/utils/auth'; // get token from cookie
Vue.use(Router);

/* Layout */
import Layout from '@/layout';
import AppMain from '@/layout/components/AppMain.vue';

/**
 * Note: sub-menu only appear when route children.length >= 1
 * Detail see: https://panjiachen.github.io/vue-element-admin-site/guide/essentials/router-and-nav.html
 *
 * hidden: true                   if set true, item will not show in the sidebar(default is false)
 * alwaysShow: true               if set true, will always show the root menu
 *                                if not set alwaysShow, when item has more than one children route,
 *                                it will becomes nested mode, otherwise not show the root menu
 * redirect: noRedirect           if set noRedirect will no redirect in the breadcrumb
 * name:'router-name'             the name is used by <keep-alive> (must set!!!)
 * meta : {
    roles: ['admin','editor']    control the page roles (you can set multiple roles)
    title: 'title'               the name show in sidebar and breadcrumb (recommend set)
    icon: 'svg-name'/'el-icon-x' the icon show in the sidebar
    breadcrumb: false            if set false, the item will hidden in breadcrumb(default is true)
    activeMenu: '/example/list'  if set path, the sidebar will highlight the path you set
  }
 */

/**
 * constantRoutes
 * a base page that does not have permission requirements
 * all roles can be accessed
 */
export const constantRoutes = [
  {
    path: '/login',
    component: () => import('@/views/login/index'),
    hidden: true,
  },

  {
    path: '/404',
    component: () => import('@/views/404'),
    hidden: true,
  },

  {
    path: '/',
    component: Layout,
    redirect: '/allcase/index',
    children: [
      // {
      //   path: 'dashboard',
      //   name: 'Dashboard',
      //   component: () => import('@/views/dashboard/index'),
      //   meta: {
      //     title: '病案首页质量分析',
      //     icon: 'dashboard',
      //     keepAlive: 1,
      //     canMultipleOpen: true
      //   },
      // },

      //报表-病案数量
      {
        path: '/reportMedicalRecords',
        name: 'reportMedicalRecords',
        component: () => import('@/views/report/table/medical-records.vue'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '病案数量',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },

      {
        path: '/reportDoctorAppeal',
        name: 'reportDoctorAppeal',
        component: () => import('@/views/report/table/doctor-appeal.vue'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '病案数量',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },

      //全病历质控
      {
        path: '/caseNumber',
        name: 'caseNumber',
        component: () => import('@/views/allcase/caseNumber'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '病案数量',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/defectNumber',
        name: 'defectNumber',
        component: () => import('@/views/allcase/defectNumber'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '缺陷病案',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/homePage',
        name: 'homePage',
        component: () => import('@/views/allcase/homePage'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '出院记录',
          keepAlive: 0,
          canMultipleOpen: true //支持根据参数不同多开不同页签，如果你需要/a跟/a?v=123都分别打开两个页签，请设置为true，否则就只会显示一个页签，后打开的会替换到前打开的页签
        },
      },
      {
        path: '/search',
        name: 'Search',
        component: () => import('@/views/search/index'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '住院病历查询',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/caseViews',
        name: 'caseViews',
        component: () => import('@/views/allcase/caseViews'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '病案详情',
          keepAlive: 0,
          canMultipleOpen: true
        },
      },
      //首页数据质控
      {
        path: '/defectList',
        name: 'defectList',
        component: () => import('@/views/data/medicalRecords/defectList'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '质量分析缺陷病案',
          keepAlive: 1,
          canMultipleOpen: true
        }
      },
      {
        path: '/medicalRecords',
        name: 'medicalRecords',
        component: () => import('@/views/data/medicalRecords'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '质量分析病案数量',
          keepAlive: 1,
          canMultipleOpen: true
        }
      },
      {
        path: '/errorList',
        name: 'errorList',
        component: () => import('@/views/data/medicalRecords/errorList'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '病案数',
          keepAlive: 1,
          canMultipleOpen: true
        }
      },
      {
        path: '/department',
        name: 'department',
        component: () => import('@/views/data/medicalRecords/department'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '总缺陷',
          keepAlive: 1,
          canMultipleOpen: true
        }
      },
      {
        path: '/TotalRankingList',
        name: 'TotalRankingList',
        component: () => import('@/views/data/frontHome/TotalRankingList'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '总排名',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/defectProblem',
        name: 'DefectProblem',
        component: () => import('@/views/data/frontHome/defectProblem'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '缺陷问题',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/defectRuleProblem',
        name: 'DefectRuleProblem',
        component: () => import('@/views/data/frontHome/defectRuleProblem'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '缺陷问题详情',
          keepAlive: 0,
          canMultipleOpen: true
        },
      },
      {
        path: '/defectProblemList',
        name: 'DefectProblemList',
        component: () => import('@/views/data/frontHome/defectProblemList'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '缺陷问题列表',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/data/front',
        name: 'Fornt',
        component: () => import('@/views/data/frontHome/index'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '首页质控（病案室）',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/codeList',
        name: 'codeList',
        component: () => import('@/views/data/medicalRecords/codeList'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '编码员',
          keepAlive: 1,
          canMultipleOpen: true
        }
      },
      {
        path: '/details',
        name: 'details',
        component: () => import('@/views/data/query/details'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '病案首页质控详情',
          keepAlive: 0,
          canMultipleOpen: true
        }
      },
      {
        path: '/forewarning',
        name: 'forewarning',
        component: () => import('@/views/forewarning/index'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '预警信息查询',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/unlockRecords',
        name: 'unlockRecords',
        component: () => import('@/views/record/unlock-records/index'),
        hidden: true, 
        meta: {
          title: '质控解锁记录',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/issueReport',
        name: 'issueReport',
        component: () => import('@/views/report/issue-report/index.vue'),
        hidden: true,
        meta: {
          title: '质控明细',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/ChargeDetails',
        name: 'ChargeDetails',
        hidden: true, //不在导航栏展示
        component: () => import('@/views/data/query/ChargeDetails'),
        meta: {
          title: '费用明细',
          keepAlive: 0,
          canMultipleOpen: true
        }
      },
      {
        path: '/StatementList',
        name: 'StatementList',
        component: () => import('@/views/SettlementList/StatementList'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '结算清单数量',
          keepAlive: 0,
          canMultipleOpen: true
        }
      },
      // 医保结算清单
      {
        path: '/SetDetails',
        name: 'SetDetails',
        component: () => import('@/views/SettlementList/SetDetails'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '医保结算单病案数量',
          keepAlive: 0,
          canMultipleOpen: true
        }
      },
      {
        path: '/StatementListquery',
        name: 'StatementListquery',
        component: () => import('@/views/SettlementList/StatementListquery'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '医保结算单病案数量',
          keepAlive: 1,
          canMultipleOpen: true
        }
      },
      {
        path: '/defectStatementList',
        name: 'defectStatementList',
        hidden: true,//不在导航栏展示
        component: () => import('@/views/SettlementList/defectStatementList'),
        meta: {
          title: '缺陷结算清单数量',
          keepAlive: 1,
          canMultipleOpen: true
        }
      },
      {
        path: '/otherStatisticsData',
        name: 'OtherStatisticsData',
        component: () => import('@/views/otherStatisticsData/index'),
        hidden: true,
        meta: {
          title: '其他统计数据',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/otherStatisticsList',
        name: 'OtherStatisticsList',
        component: () => import('@/views/otherStatisticsData/list'),
        hidden: true,
        meta: {
          title: '其他统计数据详情',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/majorIndex',
        name: 'majorIndex',
        component: () => import('@/views/allcase/majorIndex'),
        hidden: true,
        meta: {
          title: '重点专业指标',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/core-indicator/index-analysis/index',
        name: 'index-analysis',
        component: () => import('@/views/core-indicator/index-analysis/index'),
        hidden: true,
        meta: {
          title: '指标分析',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/core-indicator/index-summary/index',
        name: 'index-summary',
        component: () => import('@/views/core-indicator/index-summary/index'),
        hidden: true,
        meta: {
          title: '指标概括',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/majorIndexDetail',
        name: 'majorIndexDetail',
        component: () => import('@/views/allcase/majorIndexDetail'),
        hidden: true,
        meta: {
          title: '重点专业指标详情',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/caseIndex',
        name: 'caseIndex',
        component: () => import('@/views/allcase/caseIndex'),
        hidden: true,
        meta: {
          title: '重点专业质量控制指标',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/caseIndexList',
        name: 'caseIndexList',
        component: () => import('@/views/allcase/caseIndexList'),
        hidden: true,
        meta: {
          title: '重点专业质量控制指标列表',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/caseIndexAnalysis',
        name: 'CaseIndexAnalysis',
        component: () => import('@/views/allcase/caseIndexAnalysis'),
        hidden: true,
        meta: {
          title: '评审评价指标分析',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/caseIndexAnalysisList',
        name: 'CaseIndexAnalysisList',
        component: () => import('@/views/allcase/caseIndexAnalysisList'),
        hidden: true,
        meta: {
          title: '评审评价指标分析-科室病案',
          keepAlive: 1,
          canMultipleOpen: true
        }
      },
      {
        path: '/searchSystem',
        name: 'SearchSystem',
        component: () => import('@/views/searchSystem/index'),
        hidden: true,
        meta: {
          title: '医院大数据自助查询系统',
          icon: 'dashboard',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/outpatientCase',
        name: 'OutpatientCase',
        component: () => import('@/views/outpatient/case'),
        hidden: true,
        meta: {
          title: '门诊病历查询',
          keepAlive: 1,
          canMultipleOpen: true
        },

      },
      {
        path: '/outpatientControl',
        name: 'OutpatientControl',
        component: () => import('@/views/outpatient/control/index-new.vue'),
        meta: {
          title: '门诊病历质控',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/college-level',
        name: 'collegeLevel',
        component: () => import('@/views/rectify/college-level/index.vue'),
        meta: {
          title: '院级整改追踪',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/coreIndicatorReport',
        name: 'coreIndicatorReport',
        component: () => import('@/views/core-indicator/report/index.vue'),
        meta: {
          title: '核心制度指标',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/dhy',
        name: 'dhy',
        component: () => import('@/views/home/index'),
        meta: {
          title: '导航页',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/outpatientMedicalRetrialNumber',
        name: 'outpatientMedicalRetrialNumber',
        component: () => import('@/views/outpatient/retrial/index.vue'),
        meta: {
          title: '复审问题',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/outpatientMedicalSummaryDefectNumber',
        name: 'outpatientMedicalSummaryDefectNumber',
        component: () => import('@/views/outpatient/control/summaryDefectNumber'),
        meta: {
          title: '汇总门诊病历',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/outpatientMedicalRecordDefectNumber',
        name: 'OutpatientMedicalRecordDefectNumber',
        component: () => import('@/views/outpatient/control/defectNumber'),
        meta: {
          title: '门诊病历',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/outpatientMedicalRecordIssNumber',
        name: 'OutpatientMedicalRecordIssNumber',
        component: () => import('@/views/outpatient/control/issonNumber'),
        meta: {
          title: '存在问题',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/outpatientMedicalShouldDefectNumber',
        name: 'OutpatientMedicalShouldDefectNumber',
        component: () => import('@/views/outpatient/control/shouldDefectNumber'),
        meta: {
          title: '门诊应有病历',
          keepAlive: 1,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/outpatientMedicalRecordDetail',
        name: 'OutpatientMedicalRecordDetail',
        component: () => import('@/views/outpatient/control/detail'),
        meta: {
          title: '门诊病历详情',
          keepAlive: 0,
          canMultipleOpen: true
        },
        hidden: true,
      },
      {
        path: '/reviewIndicators',
        name: 'reviewIndicators',
        component: () => import('@/views/allcase/reviewIndicators.vue'),
        hidden: true,
        meta: {
          title: '评审指标',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/reviewIndicatorsList',
        name: 'reviewIndicatorsList',
        component: () => import('@/views/allcase/reviewIndicatorsList.vue'),
        hidden: true,
        meta: {
          title: '指标列表',
          keepAlive: 1,
        },
      },
      {
        path: '/yypsIndexAnalysis',
        name: 'YypsIndexAnalysis',
        component: () => import('@/views/yyps/analysis/index.vue'),
        hidden: true,
        meta: {
          title: '指标分析',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/yypsIndexAnalysisList',
        name: 'YypsIndexAnalysisList',
        component: () => import('@/views/yyps/analysis/list.vue'),
        hidden: true,
        meta: {
          title: '指标分析-科室病案',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/yypsIndex',
        name: 'yypsIndex',
        component: () => import('@/views/yyps/index/index.vue'),
        hidden: true,
        meta: {
          title: '评审指标',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/middleCaseControl',
        name: 'middle-case-control',
        component: () => import('@/views/middleCaseControl/index.vue'),
        hidden: true,
        meta: {
          title: '运行病历',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/middleCaseNumber',
        name: 'MiddleCaseNumber',
        component: () => import('@/views/middleCaseControl/caseNumber'),
        hidden: true, //不在导航栏展示
        meta: {
          title: '病案数量',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/middleDefectNumber',
        name: 'MiddleDefectNumber',
        component: () => import('@/views/middleCaseControl/defectNumber'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '缺陷病案',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/encoder/errors',
        name: 'EncoderErrors',
        component: () => import('@/views/encoder/errors'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '编码员缺陷病案',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/doctor/bl',
        name: 'DoctorBl',
        component: () => import('@/views/encoder/doctorBl'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '医生病历总数',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/doctor/blkf',
        name: 'DoctorBlKf',
        component: () => import('@/views/encoder/doctorBlKf'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '医生病历扣分',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/medicalRecordNew',
        name: 'MedicalRecordNew',
        component: () => import('@/views/medicalRecord/index'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '病案详情',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/cost',
        name: 'Cost',
        component: () => import('@/views/medicalRecord/cost'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '费用详情',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
      {
        path: '/evaluateIndex',
        name: 'EvaluateIndex',
        component: () => import('@/views/evaluate/index/index'),
        hidden: true,//不在导航栏展示
        meta: {
          title: '评审评价指标',
          keepAlive: 1,
          canMultipleOpen: true
        },
      },
    ],
  },
  // 医院大数据自助查询系统
  {
    path: '/hospital',
    component: AppMain,
    redirect: '/hospital-search',
    children: [
      {
        path: '/hospital-search',
        name: 'HospitalSearch',
        component: () => import('@/views/searchSystem/index'),
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
        hidden: true
      },
      {
        path: '/hospital-caseViews',
        name: 'HospitalCaseViews',
        component: () => import('@/views/allcase/caseViews'),
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
        hidden: true
      },
      {
        path: '/hospital-details',
        name: 'HospitalDetails',
        component: () => import('@/views/data/query/details'),
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
        hidden: true
      },
      {
        path: '/hospital-chargeDetails',
        name: 'HospitalChargeDetails',
        hidden: true,
        component: () => import('@/views/data/query/ChargeDetails'),
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
      },
    ]
  },

  {
    path: '/embedIndex',
    component: AppMain,
    redirect: '/embedIndex-home',
    children: [
      {
        path: '/embedIndex-home',
        name: 'EmbedIndexHome',
        component: () => import('@/views/embedIndex/index'),
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
        hidden: true
      },
      {
        path: '/embedIndex-caseIndexAnalysisList',
        name: 'EmbedIndexCaseIndexAnalysisList',
        component: () => import('@/views/allcase/caseIndexAnalysisList'),
        hidden: true,
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/embedIndex-caseIndexList',
        name: 'EmbedIndexCaseIndexList',
        component: () => import('@/views/allcase/caseIndexList'),
        hidden: true,
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/embedIndex-caseViews',
        name: 'EmbedIndexCaseViews',
        component: () => import('@/views/allcase/caseViews'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/embedIndex-chargeDetails',
        name: 'EmbedIndexChargeDetails',
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
        component: () => import('@/views/data/query/ChargeDetails')
      }
    ]
  },

  {
    path: '/reviewIndex',
    component: AppMain,
    redirect: '/reviewIndex-home',
    children: [
      {
        path: '/reviewIndex-home',
        name: 'ReviewIndex',
        component: () => import('@/views/reviewIndex/index'),
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
        hidden: true
      },
      {
        path: '/reviewIndex-caseIndexList',
        name: 'ReviewIndexCaseIndexList',
        component: () => import('@/views/allcase/caseIndexList'),
        hidden: true,
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/reviewIndex-caseViews',
        name: 'ReviewIndexCaseViews',
        component: () => import('@/views/allcase/caseViews'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/reviewIndex-yypsIndexAnalysisList',
        name: 'ReviewYypsIndexAnalysisList',
        component: () => import('@/views/yyps/analysis/list.vue'),
        hidden: true,
        meta: {
          title: '指标分析-科室病案',
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/reviewIndex-chargeDetails',
        name: 'ReviewIndexChargeDetails',
        hidden: true,
        component: () => import('@/views/data/query/ChargeDetails'),
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
      }
    ]
  },
  // {
  //   path: '/login',
  //   component: Layout,
  //    redirect: '/login',
  //   keepAlive: 1,
  //   canMultipleOpen: true,
  //   meta: { title: '病案分析' },
  //   children: [
  //     {
  //       path: 'login',
  //       name: 'login',
  //       component: () => import('@/views/caseAnalysis/login.vue'),
  //       meta: { title: '病案分析' }
  //     },

  //   ]
  // },
  // {
  //   path: '/caseAnalysis',
  //   component: Layout,
  //    redirect: '/caseAnalysis',
  //   keepAlive: 1,
  //   canMultipleOpen: true,
  //   meta: { title: '病案分析' },
  //   children: [
  //     {
  //       path: 'caseAnalysis',
  //       name: 'caseAnalysis',
  //       component: () => import('@/views/caseAnalysis/caseAnalysis.vue'),
  //       meta: { title: '病案分析' }
  //     },
  //
  //   ]
  // },
  {
    path: '/whitelist',
    component: AppMain,
    redirect: '/whitelist-search',
    children: [
      {
        path: '/whitelist-search-specialty',
        name: 'WhitelistSearchSpecialty',
        component: () => import('@/views/search/index'),
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
        hidden: true
      },
      {
        path: '/whitelist-search',
        name: 'WhitelistSearch',
        component: () => import('@/views/searchSystem/index'),
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true
        },
        hidden: true
      },
      {
        path: '/whitelist-caseViews',
        name: 'WhitelistCaseViews',
        component: () => import('@/views/allcase/caseViews'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/whitelist-chargeDetails',
        name: 'WhitelistChargeDetails',
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
        component: () => import('@/views/data/query/ChargeDetails')
      },
      {
        path: '/whitelist-details',
        name: 'WhitelistDetails',
        component: () => import('@/views/data/query/details'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/whitelist-outpatientMedicalRecordDetail',
        name: 'WhitelistOutpatientMedicalRecordDetail',
        component: () => import('@/views/outpatient/control/detail'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true
        },
      },
      {
        path: '/whitelist-caseControl',
        name: 'WhitelistCaseControl',
        component: () => import('@/views/allcase/caseControl'),
        hidden: true,
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true,
          nocrumb: true, // 不展示多页签tab栏
        },
      },
      //门诊病历单连接
      {
        path: '/whitelist-outpatient',
        name: '/whitelistOutpatient',
        component: () => import('@/views/single-conn/outpatient/whitelist-outpatient.vue'),
        hidden: true,
        meta: {
          keepAlive: 1,
          canMultipleOpen: true,
          nocopy: true,
          nocrumb: true,
        },
      },
      {
        path: '/whitelist-qualityResults',
        name: 'WhitelistQualityResults',
        component: () => import('@/views/data/query/qualityResults'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true,
          nocrumb: true, // 不展示多页签tab栏
        },
      },

      // 生成病例
      {
        path: '/whitelist-generate-case',
        name: 'whitelistGenerateCase',
        component: () => import('@/views/single-conn/medical-v2/index.vue'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true,
          nocrumb: true, // 不展示多页签tab栏
        },
      },

      {
        path: '/empty-quality-results',
        name: 'emptyQualityResults',
        component: () => import('@/views/single-conn/empty-index.vue'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true,
          nocrumb: true, // 不展示多页签tab栏
        },
      },
      {
        path: '/whitelist-message-center',
        name: 'MessageCenterSingle',
        component: () => import('@/views/single-conn/message-center'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true,
          nocrumb: true, // 不展示多页签tab栏
        },
      },
      // 
      {
        path: '/whitelist-qualityUnreadResults',
        name: 'WhitelistQualityUnreadResults',
        component: () => import('@/views/data/query/quality-unread-results'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true,
          nocrumb: true, // 不展示多页签tab栏
        },
      },
      {
        path: '/whitelist-bmyQualityResult',
        name: 'WhitelistBmyQualityResult',
        component: () => import('@/views/data/query/bmyQualityResult'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true,
          nocrumb: true, // 不展示多页签tab栏
        },
      },
    ]
  },
  {
    path: '/ccyxy',
    redirect: '/ccyxy/index',
    component: AppMain,
    children: [
      {
        path: 'index',
        name: 'CcyxyIndex',
        component: () => import('@/views/changchun/index'),
        meta: {
          keepAlive: 1,
          canMultipleOpen: false,
          nocopy: true,
          // nocrumb: true
        },
        hidden: true
      },
      {
        path: 'defectNumber',
        name: 'CcyxyDefectNumber',
        component: () => import('@/views/changchun/defectNumber'),
        hidden: true,
        meta: {
          title: '缺陷病案',
          keepAlive: 0,
          canMultipleOpen: true,
          // nocrumb: true
        },
      },
      {
        path: 'caseViews',
        name: 'CcyxyCaseViews',
        component: () => import('@/views/allcase/caseViews'),
        hidden: true,
        meta: {
          keepAlive: 0,
          canMultipleOpen: true,
          nocopy: true,
          // nocrumb: true
        },
      },
      {
        path: '/test',
        name: 'test',
        component: () => import('@/views/test/test.vue'),
        hidden: true,
        meta: {
          title: '测试',
          keepAlive: 1,
          canMultipleOpen: true
        },
      }
    ]
  }
];

const createRouter = () =>
  new Router({
    // mode: 'history', // require service support
    scrollBehavior: () => ({ y: 0 }),
    routes: constantRoutes,
  });

const router = createRouter();

const whiteList = [
  '/login',
  // '/defectProblemList',
  '/404',
  '/whitelist-search',
  // '/whitelist-caseViews',
  // '/whitelist-chargeDetails',
  // '/whitelist-details',
  '/whitelist-caseControl',
  // '/whitelist-outpatient',
  '/whitelist-qualityResults',
  // '/whitelist-generate-case',
  // '/empty-quality-results',
  // '/whitelist-message-center',
  // '/whitelist-qualityUnreadResults',
  // '/middleCaseNumber',
  // '/middleDefectNumber',
  '/whitelist-bmyQualityResult',
  // '/whitelist-outpatientMedicalRecordDetail',
  // '/ccyxy/index',
  // '/ccyxy/defectNumber',
  // '/ccyxy/caseViews',
  // '/searchSystem',
  // '/caseViews',
  // '/medicalRecordNew',
  // '/outpatientMedicalRecordDetail',
  // '/StatementList'
]; // 添加路由白名单
//路由判断
router.beforeEach(async (to,
  from, next) => {
  const hasToken = getToken();
  if (hasToken) {
    if (!store.state.user.menu.length) {
      // 判断当前用户是否已拉取完权限菜单信息
      // 如果本地不存在权限菜单，则获取权限菜单，生成菜单列表
      // if (!sessionStorage.getItem("route")) {
      //获取路由菜单
      menu.getMenu().then(response => {
        //保险起见，组装一次数据
        menu.parseRoute(store.state.user.menu, []).then(res => {
          //添加路由并进行跳转
          menu.addMenu(res).then(e => {
            next({ ...to, replace: true }) // hack方法 确保addRoutes已完成
          })
        })
      }).catch(err => {//失败则直接跳转登录页面
        next(`/login`);
      })
      // } else {//从缓存中读取用户权限列表，并添加菜单到侧边栏和路由元
      //   menu.parseRoute(JSON.parse(sessionStorage.getItem("route")), []).then(res => {
      //     menu.addMenu(res).then(e => {
      //       next({ ...to, replace: true }) // hack方法 确保addRoutes已完成
      //     })
      //   })
      // }
    } else {
      // 有路由表直接放行
      next();
    }
  } else {
    if (whiteList.indexOf(to.path) !== -1) {
      // 在白名单内直接放行
      next();
    } else {
      // 其他没有访问权限的页面将被重定向到登录页面。
      next(`/login`);
    }
  }

  // if (to.path == from.path) {
  //   // 让 列表页 即不缓存，刷新
  //   to.meta.keepAlive = false;
  // }
  if (to.path == '/allcase/index' || to.path == '/encoder/index' || to.path == '/qc/index') {
    to.meta.keepAlive = 1;
  }

  if (to.path == '/qc/caseViews') {
    if (to.query && to.query.from == 'review') {
      to.meta.title = '申诉详情';
    } else {
      to.meta.title = '质控详情';
    }
  }

  next()

})


// Detail see: https://github.com/vuejs/vue-router/issues/1234#issuecomment-357941465
export function resetRouter() {
  const newRouter = createRouter();
  router.matcher = newRouter.matcher; // reset router
}

export default router;

