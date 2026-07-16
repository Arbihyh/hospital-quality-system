import defaultSettings from '@/settings';

const title = defaultSettings.title || '病案数据治理与服务平台';

export default function getPageTitle(pageTitle) {
  if (pageTitle) {
    return `${pageTitle} - ${title}`;
  }
  return `${title}`;
}
