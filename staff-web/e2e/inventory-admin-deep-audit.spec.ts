import { test, expect, Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const runId = Date.now();
const evidenceDir = path.resolve(process.cwd(), '../docs/qa/ui-business-flow-audit/evidence', `inventory-run-${runId}`);

if (!fs.existsSync(evidenceDir)) {
  fs.mkdirSync(evidenceDir, { recursive: true });
}

let uniqueName = `Ingred_${runId}`;

test.describe('Inventory/Admin Deep Audit', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(240000); 

  let page: Page;
  let context: any;

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    page = await context.newPage();
  });

  test.afterAll(async () => {
    await page.close();
  });

  test('1. Login Admin', async () => {
    console.log(`Starting Inventory Audit... Evidence dir: ${evidenceDir}`);
    
    await page.goto('http://localhost:5173/login');
    await page.getByLabel(/Tài khoản \/ email \/ số điện thoại/i).fill('bootstrap-admin');
    await page.getByLabel('Mật khẩu').fill('password');
    const loginResPromise = page.waitForResponse(r => r.url().includes('/api/v1/auth/staff/login') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Đăng nhập' }).click();
    await loginResPromise;
    await page.waitForURL(url => url.pathname.includes('/access') || url.pathname.includes('/ops/') || url.pathname.includes('/admin/'), { timeout: 10000 });
    
    // Choose Admin / Inventory Workspace if present
    const adminWorkspaceBtn = page.getByText(/Quản trị|Kho|Admin|Inventory/i).first();
    await adminWorkspaceBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await adminWorkspaceBtn.count() > 0 && await adminWorkspaceBtn.isVisible()) {
        await adminWorkspaceBtn.click();
        await page.waitForTimeout(500);
    }
    
    console.log('INV_LOGIN_OK');
    await page.screenshot({ path: path.join(evidenceDir, '01-login-success.png') });
  });

  test('2. Inventory Navigation Baseline', async () => {
    // Navigate via the Dashboard's link or AccessGatePage
    // Because this QA session doesn't have an open cashier shift, it lands on /access (AccessGatePage).
    // On AccessGatePage, there is a list of workspaces with 'Open' buttons.
    const inventoryAccessBtn = page.locator('.staff-task-list-item')
        .filter({ hasText: 'Nguyên liệu, nhà cung cấp, đơn mua và phiếu nhận hàng.' })
        .getByRole('button', { name: 'Open' })
        .first();
        
    await inventoryAccessBtn.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    if (await inventoryAccessBtn.count() > 0 && await inventoryAccessBtn.isVisible()) {
        await inventoryAccessBtn.click();
    } else {
        // Fallback: If on another page, try switching workspace or using Command Palette
        const workspaceSelect = page.locator('#staff-shell-workspace-select');
        if (await workspaceSelect.count() > 0 && await workspaceSelect.isVisible()) {
            await workspaceSelect.selectOption('admin');
            await page.waitForTimeout(500);
            const menuLink = page.getByRole('button', { name: 'Kho' }).first();
            if (await menuLink.count() > 0 && await menuLink.isVisible()) {
                await menuLink.click();
            }
        }
    }
    
    await page.waitForURL('**/admin/inventory', { timeout: 5000 }).catch(() => {});
    
    const inventoryHeader = page.getByRole('heading', { name: /Kho và mua hàng/i }).first();
    await inventoryHeader.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    
    if (await inventoryHeader.count() > 0 && await inventoryHeader.isVisible()) {
        console.log('INV_NAVIGATION_FOUND');
    } else {
        console.log('INV_NAVIGATION_NOT_IMPLEMENTED');
    }
    
    await page.screenshot({ path: path.join(evidenceDir, '02-inventory-navigation.png') });
  });

  test('3. Ingredients CRUD', async () => { page.on('console', msg => console.log('BROWSER_CONSOLE:', msg.text()));
    const createBtn = page.getByTestId('inventory-ingredient-create-button');
    await createBtn.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    if (await createBtn.count() > 0 && await createBtn.isVisible()) {
        await createBtn.click();
        
        await page.getByTestId('inventory-ingredient-name-input').fill(`Auto Ingred ${uniqueName}`);
        
        await page.getByTestId('inventory-ingredient-unit-select').fill('kg');
        
        await page.getByTestId('inventory-ingredient-save-button').click();
        
        try {
            await page.waitForSelector('text=Tạo nguyên liệu thành công', { timeout: 5000 });
            console.log('INV_INGREDIENT_UI_FOUND');
        } catch {
            console.log('INV_INGREDIENT_CRUD_PARTIAL');
            await page.screenshot({ path: path.join(evidenceDir, '03-ingredient-error.png') });
            await page.keyboard.press('Escape'); // close modal
        }
    } else {
        const text = await page.evaluate(() => document.body.innerText); console.log('BODY_TEXT:', text); console.log('INV_INGREDIENT_CRUD_NOT_IMPLEMENTED'); await page.screenshot({ path: path.join(evidenceDir, '03-ingredient-missing.png') });
    }
  });

  test('4. Suppliers CRUD', async () => {
    const createBtn = page.getByTestId('inventory-supplier-create-button');
    await createBtn.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    if (await createBtn.count() > 0 && await createBtn.isVisible()) {
        await createBtn.click();
        
        await page.getByTestId('inventory-supplier-name-input').fill(`Auto Supplier ${uniqueName}`);
        await page.getByTestId('inventory-supplier-phone-input').fill('0901234567');
        await page.getByTestId('inventory-supplier-save-button').click();
        
        try {
            await page.waitForSelector('text=Tạo nhà cung cấp thành công', { timeout: 5000 });
            console.log('INV_SUPPLIER_CRUD_PARTIAL');
        } catch {
            console.log('INV_SUPPLIER_ERROR');
            await page.locator('.ant-modal-close').last().click().catch(() => {}); // close modal
        }
    } else {
        console.log('INV_SUPPLIER_CRUD_NOT_IMPLEMENTED');
    }
  });

  test('5. Purchase Orders', async () => {
    const createBtn = page.getByTestId('inventory-po-create-button');
    await createBtn.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    if (await createBtn.count() > 0 && await createBtn.isVisible()) {
        await createBtn.click();
        
        try {
            await page.getByTestId('inventory-po-supplier-select').click({ timeout: 2000 });
            await page.keyboard.press('ArrowDown');
            await page.keyboard.press('Enter');

            await page.getByTestId('inventory-po-line-ingredient-select').click({ timeout: 2000 });
            await page.keyboard.press('ArrowDown');
            await page.keyboard.press('Enter');

            await page.getByPlaceholder('SL').fill('10', { timeout: 2000 });
            await page.getByTestId('inventory-po-save-button').click({ timeout: 2000 });

            await page.waitForSelector('text=Tạo đơn mua hàng thành công', { timeout: 5000 });
            console.log('INV_PO_UI_FOUND');
        } catch (e) {
            console.error('PO_CREATE_ERROR:', e);
            console.log('INV_PO_ERROR');
            await page.locator('.ant-modal-close').last().click().catch(() => {}); // close modal
        }
    } else {
        console.log('INV_PO_NOT_IMPLEMENTED');
    }
  });

  test('6. Purchase Receipts', async () => {
    const prTab = page.getByText(/Phiếu nhập kho|Nhập kho|Receipts/i).first();
    await prTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await prTab.count() > 0 && await prTab.isVisible()) {
       await prTab.click({ force: true, timeout: 2000 }).catch(() => {});
       console.log('INV_RECEIPT_UI_FOUND');
    } else {
       console.log('INV_RECEIPT_NOT_IMPLEMENTED');
    }
  });

  test('7. Stock Movement & Recon', async () => {
    const stockTab = page.getByText(/Kiểm kê|Điều chỉnh|Movements/i).first();
    await stockTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await stockTab.count() > 0 && await stockTab.isVisible()) {
       await stockTab.click({ force: true, timeout: 2000 }).catch(() => {});
       console.log('INV_STOCK_MOVEMENT_UI_FOUND');
    } else {
       console.log('INV_STOCK_MOVEMENT_NOT_IMPLEMENTED');
    }
  });

  test('8. Row Version Conflict', async () => {
    // If not possible via UI, try to at least find if there is a conflict guard UI
    const ingredientRow = page.locator('.staff-admin-surface-item').filter({ hasText: 'Auto Ingred' }).first();
    await ingredientRow.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await ingredientRow.count() > 0 && await ingredientRow.isVisible()) {
       await ingredientRow.click({ force: true, timeout: 2000 }).catch(() => {});
       console.log('INV_ROW_VERSION_NOT_TESTABLE');
    } else {
       console.log('INV_ROW_VERSION_NOT_IMPLEMENTED');
    }
  });

  test('9. Negative Stock Guard', async () => {
    const deductBtn = page.getByText(/Xuất kho|Trừ kho|Giảm/i).first();
    await deductBtn.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await deductBtn.count() > 0 && await deductBtn.isVisible()) {
       await deductBtn.click({ force: true, timeout: 2000 }).catch(() => {});
       console.log('INV_NEGATIVE_STOCK_NOT_TESTABLE');
    } else {
       console.log('INV_NEGATIVE_STOCK_NOT_IMPLEMENTED');
    }
  });

  test('10. Recipe / Menu Link', async () => {
    const recipeTab = page.getByText(/Định mức|Công thức|Recipe/i).first();
    await recipeTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await recipeTab.count() > 0 && await recipeTab.isVisible()) {
       await recipeTab.click({ force: true, timeout: 2000 }).catch(() => {});
       console.log('INV_RECIPE_UI_FOUND');
    } else {
       console.log('INV_RECIPE_NOT_IMPLEMENTED');
    }
  });

  test('11. Import / Export Data', async () => {
    const exportBtn = page.getByText(/Xuất excel|Xuất file|Export/i).first();
    await exportBtn.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await exportBtn.count() > 0 && await exportBtn.isVisible()) {
       await exportBtn.click({ force: true, timeout: 2000 }).catch(() => {});
       console.log('INV_EXPORT_UI_FOUND');
    } else {
       console.log('INV_EXPORT_NOT_IMPLEMENTED');
    }
  });

  test('12. Permission Guard', async () => {
    console.log('INV_PERMISSION_GUARD_NEEDS_DATA');
  });
});
