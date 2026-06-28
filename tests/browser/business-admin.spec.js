const { test, expect } = require('playwright/test');

const password = process.env.QXADMIN_TEST_PASSWORD;

if (!password) {
    throw new Error('QXADMIN_TEST_PASSWORD is required for browser verification.');
}

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('邮箱', { exact: true }).fill('test@qq.com');
    await page.getByLabel('密码', { exact: true }).fill(password);
    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.getByRole('button', { name: '登录', exact: true }).click(),
    ]);
});

test('业务后台页面与弹窗可用', async ({ page }) => {
    const pages = [
        ['/dashboard', '选择店铺'],
        ['/data-processing', '数据处理'],
        ['/sku-product-types', 'SKU 与产品类型'],
        ['/order-processing', '订单处理配置'],
        ['/processing-crafts', '工艺管理'],
        ['/employees', '员工与职位'],
        ['/admins', '管理员账号'],
    ];

    for (const [path, heading] of pages) {
        await page.goto(path);
        await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
        const hasBodyOverflow = await page.evaluate(() => (
            document.body.scrollWidth > document.documentElement.clientWidth + 1
        ));
        expect(hasBodyOverflow).toBe(false);
    }

    await page.goto('/order-processing/create');
    await page.getByRole('button', { name: '新建工艺', exact: true }).click();
    await expect(page.getByRole('dialog', { name: '新建工艺', exact: true })).toBeVisible();
    await page.getByRole('button', { name: '取消', exact: true }).click();

    await page.goto('/sku-product-types');
    const deleteButtons = page.locator('button[data-delete-trigger]');
    expect(await deleteButtons.count()).toBeGreaterThan(0);
    await deleteButtons.first().click();
    await expect(page.getByRole('dialog', { name: '删除 SKU 映射', exact: true })).toBeVisible();
    await page.getByRole('button', { name: '取消', exact: true }).click();
});

test('移动端宽表在容器内滚动', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile');

    await page.goto('/sku-product-types');
    const dimensions = await page.evaluate(() => {
        const wrapper = document.querySelector('.overflow-x-auto');
        const table = wrapper ? wrapper.querySelector('table') : null;

        return {
            bodyOverflow: document.body.scrollWidth > document.documentElement.clientWidth + 1,
            wrapperWidth: wrapper ? wrapper.clientWidth : 0,
            wrapperScrollWidth: wrapper ? wrapper.scrollWidth : 0,
            tableMinWidth: table ? window.getComputedStyle(table).minWidth : '0px',
        };
    });

    expect(dimensions.bodyOverflow).toBe(false);
    expect(dimensions.wrapperScrollWidth).toBeGreaterThan(dimensions.wrapperWidth);
    expect(dimensions.tableMinWidth).toBe('720px');
});
