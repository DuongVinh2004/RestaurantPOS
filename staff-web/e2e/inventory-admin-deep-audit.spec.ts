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
    // Try to find the Inventory/Menu items
    const inventoryMenu = page.getByText(/Tồn kho|Nguyên liệu|Inventory|Ingredients/i).first();
    await inventoryMenu.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
    
    if (await inventoryMenu.count() > 0 && await inventoryMenu.isVisible()) {
        await inventoryMenu.click();
        await page.waitForTimeout(1000);
        console.log('INV_NAVIGATION_FOUND');
    } else {
        console.log('INV_NAVIGATION_NOT_IMPLEMENTED');
    }
    await page.screenshot({ path: path.join(evidenceDir, '02-inventory-navigation.png') });
  });

  test('3. Ingredients CRUD', async () => {
    const ingredientsTab = page.getByText(/Nguyên liệu|Ingredients/i).first();
    await ingredientsTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await ingredientsTab.count() > 0 && await ingredientsTab.isVisible()) {
       await ingredientsTab.click();
       const createBtn = page.getByRole('button', { name: /Thêm|Tạo|Create|Add/i }).first();
       if (await createBtn.count() > 0 && await createBtn.isVisible()) {
           console.log('INV_INGREDIENT_UI_FOUND');
       } else {
           console.log('INV_INGREDIENT_CRUD_PARTIAL');
       }
    } else {
       console.log('INV_INGREDIENT_CRUD_NOT_IMPLEMENTED');
    }
  });

  test('4. Suppliers CRUD', async () => {
    const suppliersTab = page.getByText(/Nhà cung cấp|Suppliers/i).first();
    await suppliersTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await suppliersTab.count() > 0 && await suppliersTab.isVisible()) {
       await suppliersTab.click();
       console.log('INV_SUPPLIER_CRUD_PARTIAL');
    } else {
       console.log('INV_SUPPLIER_CRUD_NOT_IMPLEMENTED');
    }
  });

  test('5. Purchase Orders', async () => {
    const poTab = page.getByText(/Đơn mua hàng|Purchase Orders/i).first();
    await poTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await poTab.count() > 0 && await poTab.isVisible()) {
       await poTab.click();
       console.log('INV_PO_UI_FOUND');
    } else {
       console.log('INV_PO_NOT_IMPLEMENTED');
    }
  });

  test('6. Purchase Receipts', async () => {
    const prTab = page.getByText(/Phiếu nhập kho|Nhập kho|Receipts/i).first();
    await prTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await prTab.count() > 0 && await prTab.isVisible()) {
       await prTab.click();
       console.log('INV_RECEIPT_UI_FOUND');
    } else {
       console.log('INV_RECEIPT_NOT_IMPLEMENTED');
    }
  });

  test('7. Stock Movement & Recon', async () => {
    const stockTab = page.getByText(/Kiểm kê|Điều chỉnh|Movements/i).first();
    await stockTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await stockTab.count() > 0 && await stockTab.isVisible()) {
       await stockTab.click();
       console.log('INV_STOCK_MOVEMENT_UI_FOUND');
    } else {
       console.log('INV_STOCK_MOVEMENT_NOT_IMPLEMENTED');
    }
  });

  test('8. Recipe Management', async () => {
    const recipeTab = page.getByText(/Định lượng|Công thức|Recipe/i).first();
    await recipeTab.waitFor({ state: 'visible', timeout: 2000 }).catch(() => {});
    if (await recipeTab.count() > 0 && await recipeTab.isVisible()) {
       await recipeTab.click();
       console.log('INV_RECIPE_UI_FOUND');
    } else {
       console.log('INV_RECIPE_NOT_IMPLEMENTED');
    }
  });

  test('9. Row Version Conflict', async () => {
    console.log('INV_ROW_VERSION_CONFLICT_NOT_TESTABLE_UI');
  });

  test('10. Import/Export', async () => {
    console.log('INV_IMPORT_EXPORT_NOT_IMPLEMENTED');
  });

  test('11. Permission Guard', async () => {
    console.log('INV_PERMISSION_GUARD_NEEDS_DATA');
  });
});
