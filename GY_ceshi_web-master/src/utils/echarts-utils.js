export function download(
    chartInstance,
    chartName = 'echarts图表',
    options = {}
) {
    return new Promise((resolve, reject) => {
        const defaultOptions = {
            type: 'png',
            pixelRatio: 2,
            backgroundColor: '#fff',
            excludeComponents: ['toolbox']
        };
        const finalOptions = { ...defaultOptions, ...options };

        if (!chartInstance || typeof chartInstance.getDataURL !== 'function') {
            console.error('ECharts 实例无效或未加载完成');
            reject(new Error('图表未加载完成，无法下载'));
            return;
        }

        try {
            const base64 = chartInstance.getDataURL(finalOptions);
            const timestamp = new Date().getTime();
            const fileName = `${chartName}_${timestamp}.${finalOptions.type}`;
            const link = document.createElement('a');
            link.href = base64;
            link.download = fileName;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();

            setTimeout(() => {
                document.body.removeChild(link);
                resolve(true);
            }, 100);
        } catch (error) {
            console.error('图表下载失败：', error);
            reject(new Error('图表下载失败，请重试'));
        }
    });
}
