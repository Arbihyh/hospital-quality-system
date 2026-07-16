import router from './router';
import store from './store';
import { Message } from 'element-ui';
import NProgress from 'nprogress'; // progress bar
import 'nprogress/nprogress.css'; // progress bar style
import { getToken } from '@/utils/auth'; // get token from cookie
import getPageTitle from '@/utils/get-page-title';

NProgress.configure({ showSpinner: false }); // NProgress Configuration

const whiteList = [
  '/login',
  // '/whitelist-qualityUnreadResults',
  // '/StatementList',
  // '/middleCaseNumber',
  // '/middleDefectNumber',
  // '/whitelist-search-specialty', 
  '/whitelist-search', 
  // '/whitelist-caseViews', 
  // '/whitelist-chargeDetails', 
  // '/whitelist-details', 
  '/whitelist-caseControl', 
  // '/whitelist-outpatient', 
  '/whitelist-qualityResults', 
  // '/whitelist-generate-case', 
  // '/empty-quality-results', 
  '/whitelist-bmyQualityResult', 
  // '/whitelist-outpatientMedicalRecordDetail', 
  // '/searchSystem', 
  // '/whitelist-message-center', 
  // '/caseViews', 
  // '/medicalRecordNew', 
  // '/outpatientMedicalRecordDetail'
]; // no redirect whitelist

router.beforeEach(async (to, from, next) => {
  // start progress bar
  NProgress.start();

  // set page title
  // document.title = getPageTitle(to.meta.title);
  document.title = getPageTitle('');

  // determine whether the user has logged in
  const hasToken = getToken();

  if (hasToken) {
    if (to.path === '/login') {
      // if is logged in, redirect to the home page
      next({ path: '/' });
      NProgress.done();
    } else {
      const hasGetUserInfo = store.getters.name;
      if (hasGetUserInfo) {
        next();
      } else {
        try {
          // get user info
          // await store.dispatch('user/getInfo');

          next();
        } catch (error) {
          // remove token and go to login page to re-login
          // await store.dispatch('user/resetToken');
          Message.error(error || 'Has Error');
          next(`/login`);
          NProgress.done();
        }
      }
    }
  } else {
    /* has no token*/
    if (whiteList.indexOf(to.path) !== -1) {
      // in the free login whitelist, go directly
      next();
    } else {
      // other pages that do not have permission to access are redirected to the login page.
      next(`/login`);
      NProgress.done();
    }
  }
});

router.afterEach(() => {
  // finish progress bar
  NProgress.done();
});
