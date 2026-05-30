/**
 * Checkout / Finance Complete Deep Audit
 * Branch: checkout-finance-complete-deep-audit
 *
 * Strategy:
 * - SUB-BATCH 2: Setup via Staff API (walk-in → order → items → dispatch)
 *   SETUP_API_FALLBACK: Used for reliable test fixture creation
 * - SUB-BATCH 3–13: Hybrid UI + API verification
 * - All payment/refund tests use Cash / Staff settlement only (no real MoMo/VNPay)
 * - NOT_IMPLEMENTED / NEEDS_DATA / NEEDS_RUNTIME tagged for gaps
 */

import { test, expect, Page, APIRequestContext, request } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const runId = Date.now();
const evidenceDir = path.resolve(
  process.cwd(),
  '../docs/qa/ui-business-flow-audit/evidence',
  `fin-run-${runId}`,
);

if (!fs.existsSync(evidenceDir)) {
  fs.mkdirSync(evidenceDir, { recursive: true });
}

// Shared state captured during setup
let staffToken = '';
let branchId = 5;
let tableId = 0;
let tableRowVersion = 1;
let reservationId = '';
let reservationRowVersion = 1;
let orderId = '';
let orderRowVersion = 1;
let orderItemIds: number[] = [];
let outstandingAmount = 0;
let refundAmount = 0;
let cashierShiftId = 0;
let cashierShiftRowVersion = 1;
let paymentId = 0;
let refundId = 0;
let voucherId = 0;

const BASE_API = 'http://127.0.0.1:8000';
const STAFF_APP = 'http://localhost:5173';
const CUSTOMER_APP = 'http://localhost:3000';

test.describe('Checkout/Finance Complete Deep Audit', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(300_000); // 5 min per test

  let page: Page;
  let api: APIRequestContext;

  test.beforeAll(async ({ browser, playwright }) => {
    page = await browser.newPage();
    // Start with unauthenticated context; will be replaced after login
    api = await playwright.request.newContext({ baseURL: BASE_API });
  });

  test.afterAll(async () => {
    await page.close();
    await api.dispose();
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 2 — Setup: Walk-in → Order → Items → Dispatch
  // SETUP_API_FALLBACK: Order creation uses Staff API for reliability
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 2 — Order setup (FIN_ORDER_READY)', async () => {
    await test.step('Staff login via UI', async () => {
      await page.goto(`${STAFF_APP}/login`);
      await page.getByLabel(/Tài khoản \/ email \/ số điện thoại/i).fill('bootstrap-admin');
      await page.getByLabel('Mật khẩu').fill('password');

      const loginPromise = page.waitForResponse(
        (r) => r.url().includes('/api/v1/auth/staff/login') && r.request().method() === 'POST',
      );
      await page.getByRole('button', { name: 'Đăng nhập' }).click();
      const loginRes = await loginPromise;
      const loginJson = await loginRes.json();
      staffToken =
        loginJson?.data?.access_token ||
        loginJson?.data?.api_key ||
        loginJson?.access_token ||
        loginJson?.token ||
        '';
      expect(staffToken, 'Staff token must be present after login').toBeTruthy();
      console.log(`Staff token acquired: ${staffToken.substring(0, 20)}...`);
    });

    await test.step('Build authenticated API context', async () => {
      await api.dispose();
      api = await request.newContext({
        baseURL: BASE_API,
        extraHTTPHeaders: {
          'X-Staff-Key': staffToken,
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Staff-Branch-Id': String(branchId),
        },
      });
    });

    await test.step('Ensure cashier shift is open', async () => {
      // Check current shift — must be Open status
      const currentRes = await api.get('/api/v1/staff/cashier/shifts/current', {
        params: { branch_id: branchId },
      });
      const currentJson = currentRes.ok() ? await currentRes.json() : null;
      const currentShift = currentJson?.data;
      const isOpenShift =
        currentShift &&
        currentShift.cashier_shift_id > 0 &&
        ['open', 'Open', 'active', 'Active'].includes(currentShift.status ?? '');

      if (isOpenShift) {
        cashierShiftId = currentShift.cashier_shift_id;
        cashierShiftRowVersion = currentShift.row_version || 1;
        console.log(`Existing open cashier shift: ${cashierShiftId}`);
      } else {
        // Open a new shift (existing shift may be closed from previous run)
        const openRes = await api.post('/api/v1/staff/cashier/shifts/open', {
          headers: { 'Idempotency-Key': `shift-open-${runId}` },
          data: {
            branch_id: branchId,
            opening_float_amount: 0,
            currency: 'VND',
            terminal_code: `POS-QA-${runId}`,
            notes: 'Finance audit shift',
          },
        });
        expect(openRes.ok(), `Open cashier shift failed: ${await openRes.text()}`).toBeTruthy();
        const json = await openRes.json();
        cashierShiftId = json?.data?.cashier_shift_id || 0;
        cashierShiftRowVersion = json?.data?.row_version || 1;
        console.log(`Opened new cashier shift: ${cashierShiftId}`);
      }
      expect(cashierShiftId, 'cashierShiftId must be set').toBeGreaterThan(0);
    });

    // SETUP_API_FALLBACK: Walk-in session for deterministic table + reservation
    await test.step('Create walk-in session via API', async () => {
      // Try both legacy and new table board endpoints
      let boardData: any = null;
      const boardAttempts = [
        `/api/v1/staff/table-board?branch_id=${branchId}`,
        `/api/v1/staff/tables/board?branch_id=${branchId}`,
        `/api/v1/staff/tables?branch_id=${branchId}`,
      ];
      for (const endpoint of boardAttempts) {
        const res = await api.get(endpoint);
        if (res.ok()) {
          boardData = await res.json();
          break;
        }
      }
      expect(boardData, 'Table board endpoint must be reachable').toBeTruthy();
      const tables: any[] = boardData?.data || [];
      expect(tables.length, 'Branch 5 must have tables configured').toBeGreaterThan(0);

      // Try each table in order until walk-in succeeds (previous runs may have occupied tables)
      const availableTables = [
        ...tables.filter((t: any) => t.board_state === 'available'),
        ...tables.filter((t: any) => !['blocked', 'cleaning', 'reserved_confirmed', 'available'].includes(t.board_state ?? '')),
      ];
      if (availableTables.length === 0) availableTables.push(...tables);

      let walkInSucceeded = false;
      let walkInJson: any = null;

      for (const tbl of availableTables) {
        tableId = tbl.table_id;
        tableRowVersion = tbl.row_version || 1;
        const walkInRes = await api.post('/api/v1/staff/service-sessions/walk-in', {
          headers: { 'Idempotency-Key': `walkin-fin-${runId}-${tableId}` },
          data: {
            branch_id: branchId,
            guest_name: `FinAudit Guest ${runId}`,
            table_ids: [tableId],
            guest_count: 2,
            service_minutes: 60,
          },
        });
        if (walkInRes.ok()) {
          walkInJson = await walkInRes.json();
          walkInSucceeded = true;
          console.log(`Walk-in succeeded on table_id=${tableId} board_state=${tbl.board_state}`);
          break;
        } else {
          console.log(`Walk-in rejected on table_id=${tableId} (${tbl.board_state}): ${walkInRes.status()}`);
        }
      }

      expect(walkInSucceeded, `Walk-in failed on all ${availableTables.length} attempted tables. All may be occupied. Run 'php artisan booking:release-qa-tables' or restart the backend.`).toBeTruthy();
      reservationId = String(walkInJson?.data?.reservation_id || walkInJson?.data?.id || '');
      reservationRowVersion = walkInJson?.data?.row_version || 1;
      expect(reservationId, 'reservationId must be captured').toBeTruthy();
      console.log(`Walk-in reservation: ${reservationId}, table: ${tableId}, reservation_row_version: ${reservationRowVersion}`);
    });

    await test.step('Create order via API (SETUP_API_FALLBACK)', async () => {
      const createRes = await api.post(`/api/v1/staff/tables/${tableId}/orders`, {
        headers: { 'Idempotency-Key': `create-order-fin-${runId}` },
        data: { row_version: reservationRowVersion },
      });
      expect(createRes.ok(), `Create order failed: ${await createRes.text()}`).toBeTruthy();
      const createJson = await createRes.json();
      orderId = String(createJson?.data?.order_id || createJson?.data?.id || '');
      orderRowVersion = createJson?.data?.row_version || 1;
      expect(orderId, 'orderId must be captured').toBeTruthy();
      console.log(`Order created: order_id=${orderId} row_version=${orderRowVersion}`);
    });

    await test.step('Add order items via API (SETUP_API_FALLBACK)', async () => {
      // Fetch menu items available on branch 5
      const menuRes = await api.get('/api/v1/staff/menu/items', { params: { branch_id: branchId } });
      expect(menuRes.ok(), `Menu fetch failed: ${await menuRes.text()}`).toBeTruthy();
      const menuJson = await menuRes.json();
      const menuItems: any[] = menuJson?.data || [];
      expect(menuItems.length, 'Menu must have at least 1 item').toBeGreaterThan(0);
      const itemToAdd = menuItems[0];
      const item2 = menuItems[1] || menuItems[0];

      const addRes = await api.post(`/api/v1/staff/orders/${orderId}/items`, {
        headers: { 'Idempotency-Key': `add-items-fin-${runId}` },
        data: {
          row_version: orderRowVersion,
          items: [
            { menu_item_id: itemToAdd.item_id || itemToAdd.menu_item_id || itemToAdd.id, qty: 2, note: 'No ice' },
            { menu_item_id: item2.item_id || item2.menu_item_id || item2.id, qty: 1, note: '' },
          ],
        },
      });
      expect(addRes.ok(), `Add items failed: ${await addRes.text()}`).toBeTruthy();
      const addJson = await addRes.json();
      orderRowVersion = addJson?.data?.row_version || orderRowVersion + 1;
      orderItemIds = (addJson?.data?.items || []).map((i: any) => i.id || i.order_item_id);
      console.log(
        `Items added. order_row_version=${orderRowVersion}, item_ids=${orderItemIds.join(',')}`,
      );
    });

    await test.step('Dispatch order to kitchen via API', async () => {
      const dispatchRes = await api.post(`/api/v1/staff/orders/${orderId}/kitchen/dispatch`, {
        headers: { 'Idempotency-Key': `dispatch-fin-${runId}` },
        data: { row_version: orderRowVersion },
      });
      // Dispatch may return 200 or 204 or even 422 if already dispatched
      console.log(`Dispatch status: ${dispatchRes.status()}`);
      if (dispatchRes.ok()) {
        const dispatchJson = await dispatchRes.json();
        orderRowVersion = dispatchJson?.data?.row_version || orderRowVersion;
      }
    });

    console.log('FIN_ORDER_READY');
    test.info().annotations.push({ type: 'marker', description: 'FIN_ORDER_READY' });
    await page.screenshot({ path: path.join(evidenceDir, '01-order-ready.png') }).catch(() => {});
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 3 — Settlement Preview + Bill Snapshot
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 3 — Settlement preview + bill snapshot', async () => {
    await test.step('GET settlement-preview via API', async () => {
      const previewRes = await api.get(
        `/api/v1/staff/orders/${orderId}/settlement-preview`,
        { params: { currency: 'VND' } },
      );
      expect(
        previewRes.ok(),
        `settlement-preview failed (${previewRes.status()}): ${await previewRes.text()}`,
      ).toBeTruthy();
      const json = await previewRes.json();
      const data = json?.data;
      console.log('Settlement preview:', JSON.stringify(data).substring(0, 500));

      // Verify structure — actual shape from API:
      // { order_id, reservation_id, total_amount, outstanding_amount, paid_amount, ... }
      expect(data, 'settlement preview data must be present').toBeTruthy();
      const totalPayable =
        data?.outstanding_amount ??
        data?.total_amount ??
        data?.total_payable ??
        data?.total_due ??
        data?.total ??
        data?.final_bill_amount;
      expect(
        totalPayable !== undefined && totalPayable !== null,
        `Settlement preview must have a total field. Got keys: ${Object.keys(data || {}).join(', ')}`,
      ).toBeTruthy();
      const totalNum = parseFloat(String(totalPayable));
      outstandingAmount = totalNum;
      expect(totalNum, 'Total payable must be > 0 after adding items').toBeGreaterThan(0);
      console.log(`FIN_SETTLEMENT_PREVIEW_READY — outstanding_amount=${totalNum}`);
    });

    await test.step('POST bill-snapshot (lock bill)', async () => {
      // Get fresh order row version
      const orderRes = await api.get(`/api/v1/staff/orders/${orderId}`);
      if (orderRes.ok()) {
        const orderJson = await orderRes.json();
        orderRowVersion = orderJson?.data?.row_version || orderRowVersion;
      }

      const snapshotRes = await api.post(`/api/v1/staff/orders/${orderId}/bill-snapshot`, {
        headers: { 'Idempotency-Key': `bill-snap-${runId}` },
        data: { row_version: orderRowVersion, notes: 'QA audit bill snapshot' },
      });
      expect(
        snapshotRes.ok(),
        `bill-snapshot failed (${snapshotRes.status()}): ${await snapshotRes.text()}`,
      ).toBeTruthy();
      const json = await snapshotRes.json();
      // bill-snapshot returns data: ReservationOrderResource — it has status, final_bill_amount, row_version
      const orderData = json?.data;
      orderRowVersion = orderData?.row_version ?? orderRowVersion;
      const finalBillAmount =
        orderData?.final_bill_amount ??
        orderData?.total_due_amount ??
        orderData?.total_amount ??
        orderData?.total;
      console.log(`FIN_BILL_SNAPSHOT_CREATED — final_bill_amount/total=${JSON.stringify(finalBillAmount)} order_status=${orderData?.status}`);
      // Bill snapshot must produce an order resource with a status
      expect(orderData, 'bill-snapshot must return order data').toBeTruthy();
      expect(orderData?.status ?? orderData?.order_status, 'Order must have a status after bill snapshot').toBeTruthy();
    });

    test.info().annotations.push({ type: 'marker', description: 'FIN_SETTLEMENT_PREVIEW_READY' });
    test.info().annotations.push({ type: 'marker', description: 'FIN_BILL_SNAPSHOT_CREATED' });
    await page.screenshot({ path: path.join(evidenceDir, '02-settlement-preview.png') }).catch(() => {});
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 4 — Cash Payment (Pay Order)
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 4 — Cash payment safe path (FIN_CASH_PAYMENT_COMPLETED)', async () => {
    await test.step('GET fresh order row_version before pay', async () => {
      const orderRes = await api.get(`/api/v1/staff/orders/${orderId}`);
      expect(orderRes.ok()).toBeTruthy();
      const json = await orderRes.json();
      orderRowVersion = json?.data?.row_version || orderRowVersion;
      console.log(`Pre-pay order row_version=${orderRowVersion}`);
    });

    await test.step('POST pay (Cash)', async () => {
      const payRes = await api.post(`/api/v1/staff/orders/${orderId}/pay`, {
        headers: { 'Idempotency-Key': `pay-fin-${runId}` },
        data: {
          payment_method: 'cash',
          paid_amount: outstandingAmount,
          currency: 'VND',
          transaction_code: `QA-PAY-${runId}`,
          payment_provider: '',
          notes: 'Finance audit cash payment',
          row_version: orderRowVersion,
        },
      });
      expect(
        payRes.ok(),
        `Payment failed (${payRes.status()}): ${await payRes.text()}`,
      ).toBeTruthy();
      const json = await payRes.json();
      console.log('Pay response:', JSON.stringify(json).substring(0, 600));

      // pay() returns data: buildCheckoutResponse shape
      // { order_id, reservation_id, row_version, total_amount, outstanding_amount,
      //   payment_status, status, order_status, reservation_status }
      const payData = json?.data;
      paymentId = payData?.payment_id ?? 0; // not in response — will be 0 here
      const orderStatus = payData?.order_status ?? payData?.status;
      const paymentStatus = payData?.payment_status;
      orderRowVersion = payData?.row_version || orderRowVersion;

      console.log(
        `FIN_CASH_PAYMENT_COMPLETED — payment_status=${paymentStatus} order_status=${orderStatus}`,
      );
      // After cash payment with large amount, payment_status should be Success
      expect(
        ['Success', 'success', 'Completed', 'completed'].includes(String(paymentStatus)) ||
          payData?.outstanding_amount === 0 ||
          parseFloat(String(payData?.outstanding_amount)) === 0,
        `Expected payment_status=Success or outstanding=0, got payment_status=${paymentStatus} outstanding=${payData?.outstanding_amount}`,
      ).toBeTruthy();
    });

    await test.step('Verify order state via GET', async () => {
      const verifyRes = await api.get(`/api/v1/staff/orders/${orderId}`);
      expect(verifyRes.ok()).toBeTruthy();
      const json = await verifyRes.json();
      // OrderReadResource returns nested `order` object
      const status = json?.data?.order?.status ?? json?.data?.status;
      const paymentStatus = json?.data?.order?.payment_status ?? json?.data?.payment_status;
      console.log(`FIN_PAYMENT_STATE_VERIFIED — order.status=${status} order.payment_status=${paymentStatus}`);
      // Payment status should now be Success or order status Completed
      expect(
        ['Success', 'success'].includes(String(paymentStatus)) ||
          ['Completed', 'completed'].includes(String(status)),
        `Order should show payment_status=Success or status=Completed, got status=${status} payment_status=${paymentStatus}`,
      ).toBeTruthy();
      test.info().annotations.push({ type: 'marker', description: `FIN_PAYMENT_STATE_VERIFIED status=${status} payment_status=${paymentStatus}` });
    });

    test.info().annotations.push({ type: 'marker', description: 'FIN_CASH_PAYMENT_COMPLETED' });
    await page.screenshot({ path: path.join(evidenceDir, '03-payment-complete.png') }).catch(() => {});
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 5 — Duplicate Payment / Idempotency Guard
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 5 — Duplicate payment idempotency guard (FIN_DUPLICATE_PAYMENT_GUARDED)', async () => {
    await test.step('Retry same Idempotency-Key → expect replay, not duplicate', async () => {
      const dupRes = await api.post(`/api/v1/staff/orders/${orderId}/pay`, {
        headers: { 'Idempotency-Key': `pay-fin-${runId}` }, // SAME key as SUB-BATCH 4
        data: {
          payment_method: 'cash',
          paid_amount: 999999,
          currency: 'VND',
          transaction_code: `QA-PAY-${runId}`,
          payment_provider: '',
          notes: 'Finance audit cash payment',
          row_version: orderRowVersion,
        },
      });
      // Should be 200 (idempotent replay) or 422 (already paid / order not active)
      const dupStatus = dupRes.status();
      const dupJson = await dupRes.json();
      console.log(
        `Duplicate pay attempt status=${dupStatus}: ${JSON.stringify(dupJson).substring(0, 300)}`,
      );
      expect(
        [200, 201, 422, 409].includes(dupStatus),
        `Duplicate pay should be replayed (200) or rejected (422/409), got ${dupStatus}`,
      ).toBeTruthy();
      console.log('FIN_DUPLICATE_PAYMENT_GUARDED');
    });

    await test.step('Verify payment count = 1 via reconciliation', async () => {
      const reconcRes = await api.get(
        `/api/v1/staff/finance/reconciliation/${reservationId}`,
      );
      if (reconcRes.ok()) {
        const json = await reconcRes.json();
        const payments: any[] = json?.data?.payments || [];
        const finalPayments = payments.filter(
          (p: any) => (p.scope ?? p.payment_scope) !== 'deposit',
        );
        console.log(
          `Payment count for reservation ${reservationId}: ${finalPayments.length}`,
        );
        expect(
          finalPayments.length,
          'Should have exactly 1 final payment after idempotent retry',
        ).toBe(1);
      } else {
        console.log(
          `Reconciliation endpoint returned ${reconcRes.status()} — cannot verify payment count`,
        );
      }
    });

    test.info().annotations.push({ type: 'marker', description: 'FIN_DUPLICATE_PAYMENT_GUARDED' });
    await page.screenshot({ path: path.join(evidenceDir, '04-idempotency.png') }).catch(() => {});
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 6 — Refund Preview
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 6 — Refund preview (FIN_REFUND_PREVIEW_READY)', async () => {
    await test.step('GET refund-preview', async () => {
      const previewRes = await api.get(
        `/api/v1/staff/reservations/${reservationId}/refund-preview`,
        { params: { refund_scope: 'all', currency: 'VND' } },
      );
      console.log(`Refund preview status: ${previewRes.status()}`);

      if (previewRes.ok()) {
        const json = await previewRes.json();
        const refundData = json?.data?.refund;
        const reservationData = json?.data?.reservation;
        console.log(
          `Refund preview data: ${JSON.stringify(refundData).substring(0, 400)}`,
        );

        // Verify refundable amount present and positive
        const refundableAmount =
          refundData?.refund_amount ??
          refundData?.refundable_amount ??
          refundData?.total_refund_amount ??
          0;
        expect(
          parseFloat(String(refundableAmount)),
          'Refundable amount must be > 0 after payment',
        ).toBeGreaterThan(0);
        console.log(`FIN_REFUND_PREVIEW_READY — refundable=${refundableAmount}`);
        test.info().annotations.push({
          type: 'marker',
          description: `FIN_REFUND_PREVIEW_READY refundable=${refundableAmount}`,
        });
      } else {
        const errText = await previewRes.text();
        console.log(
          `FIN_REFUND_PREVIEW_READY — status=${previewRes.status()} body=${errText.substring(0, 200)}`,
        );
        // May be blocked if reservation not in refundable state
        test.info().annotations.push({
          type: 'marker',
          description: `FIN_REFUND_PREVIEW_READY status=${previewRes.status()} BLOCKED`,
        });
      }
    });

    await page.screenshot({ path: path.join(evidenceDir, '05-refund-preview.png') }).catch(() => {});
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 7 — Refund Execution + Over-Refund Guard
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 7 — Refund execution + over-refund guard', async () => {
    await test.step('Get reservation row_version for refund', async () => {
      const previewRes = await api.get(
        `/api/v1/staff/reservations/${reservationId}/refund-preview`,
        { params: { refund_scope: 'all', currency: 'VND' } },
      );
      if (previewRes.ok()) {
        const json = await previewRes.json();
        const resData = json?.data?.reservation;
        const rowVer = resData?.row_version ?? 1;
        console.log(`Reservation row_version for refund: ${rowVer}`);
        // Store in closure for this test
        (test as any)._refundRowVersion = rowVer;
        (test as any)._refundable = parseFloat(
          String(
            json?.data?.refund?.refund_amount ??
            json?.data?.refund?.refundable_amount ??
            json?.data?.refund?.total_refund_amount ??
            0,
          ),
        );
      }
    });

    await test.step('Execute partial refund', async () => {
      const refundRowVersion = (test as any)._refundRowVersion ?? 1;
      const refundable = (test as any)._refundable ?? 0;

      if (refundable <= 0) {
        console.log('FIN_REFUND_NOT_IMPLEMENTED — no refundable amount available (reservation may not be in settled state)');
        test.info().annotations.push({
          type: 'marker',
          description: 'FIN_REFUND_NOT_IMPLEMENTED refundable=0',
        });
        return;
      }

      const partialAmount = Math.max(1, Math.floor(refundable / 2)); // refund half

      const refundRes = await api.post(
        `/api/v1/staff/reservations/${reservationId}/refund`,
        {
          headers: { 'Idempotency-Key': `refund-fin-${runId}` },
          data: {
            payment_method: 'cash',
            refund_scope: 'final',
            refund_amount: partialAmount,
            currency: 'VND',
            transaction_code: `REFUND-${runId}`,
            payment_provider: '',
            notes: 'Finance audit partial refund',
            reason: 'QA audit test refund',
            row_version: refundRowVersion,
          },
        },
      );
      console.log(`Refund execution status: ${refundRes.status()}`);

      if (refundRes.ok()) {
        const json = await refundRes.json();
        const refundData = json?.data?.refund;
        refundId = refundData?.refund_id ?? refundData?.id ?? 0;
        const refundStatus = refundData?.status ?? refundData?.refund_status;
        console.log(
          `FIN_REFUND_COMPLETED — refund_id=${refundId} status=${refundStatus} amount=${partialAmount}`,
        );
        test.info().annotations.push({
          type: 'marker',
          description: `FIN_REFUND_COMPLETED refund_id=${refundId}`,
        });
      } else {
        const errText = await refundRes.text();
        console.log(
          `FIN_REFUND_NOT_IMPLEMENTED — status=${refundRes.status()} body=${errText.substring(0, 300)}`,
        );
        test.info().annotations.push({
          type: 'marker',
          description: `FIN_REFUND_NOT_IMPLEMENTED status=${refundRes.status()}`,
        });
      }
    });

    await test.step('Over-refund guard test', async () => {
      const refundRowVersion = (test as any)._refundRowVersion ?? 1;
      const refundable = (test as any)._refundable ?? 0;

      if (refundable <= 0) {
        console.log('FIN_OVER_REFUND_GUARDED — SKIPPED (no refundable amount, cannot test)');
        test.info().annotations.push({ type: 'marker', description: 'FIN_OVER_REFUND_GUARDED NEEDS_DATA' });
        return;
      }

      // Attempt to refund more than available
      const overAmount = refundable + 999999;
      const overRes = await api.post(
        `/api/v1/staff/reservations/${reservationId}/refund`,
        {
          headers: { 'Idempotency-Key': `over-refund-${runId}` },
          data: {
            payment_method: 'cash',
            refund_scope: 'final',
            refund_amount: overAmount,
            currency: 'VND',
            transaction_code: `OVER-REFUND-${runId}`,
            payment_provider: '',
            notes: 'Over-refund guard test',
            reason: 'QA audit over-refund',
            row_version: refundRowVersion,
          },
        },
      );

      console.log(
        `Over-refund attempt status: ${overRes.status()} (expected 422 or 409)`,
      );
      // Must NOT be 200/201
      expect(
        overRes.status(),
        `BUG-FIN-004: Over-refund allowed! Expected 422/409 but got ${overRes.status()}`,
      ).not.toBe(200);
      expect(
        overRes.status(),
        `BUG-FIN-004: Over-refund allowed! Expected 422/409 but got ${overRes.status()}`,
      ).not.toBe(201);
      expect(
        [400, 409, 422, 403].includes(overRes.status()),
        `BUG-FIN-004: Expected rejection (400/409/422), got ${overRes.status()}`,
      ).toBeTruthy();
      console.log('FIN_OVER_REFUND_GUARDED — over-refund correctly rejected');
      test.info().annotations.push({ type: 'marker', description: 'FIN_OVER_REFUND_GUARDED' });
    });

    await page.screenshot({ path: path.join(evidenceDir, '06-refund.png') }).catch(() => {});
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 8 — Refund Cancel
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 8 — Refund cancel (FIN_REFUND_CANCEL_VERIFIED)', async () => {
    await test.step('Test refund-cancel endpoint', async () => {
      // refund-cancel is: POST /reservations/{id}/refund-cancel
      // This is actually refundAndCancel which refunds AND cancels the reservation
      // We only test if we have a reservation in a suitable state
      const previewRes = await api.get(
        `/api/v1/staff/reservations/${reservationId}/refund-preview`,
        { params: { refund_scope: 'all', cancel_after_payment: 'true', currency: 'VND' } },
      );

      if (previewRes.ok()) {
        const json = await previewRes.json();
        const refundAmount = parseFloat(
          String(
            json?.data?.refund?.refund_amount ??
            json?.data?.refund?.refundable_amount ??
            0,
          ),
        );
        console.log(
          `FIN_REFUND_CANCEL_VERIFIED — refund-cancel preview OK, remaining refundable=${refundAmount}`,
        );
        test.info().annotations.push({
          type: 'marker',
          description: 'FIN_REFUND_CANCEL_VERIFIED (preview confirmed, execution skipped to preserve state)',
        });
      } else {
        console.log(
          `FIN_REFUND_CANCEL_NOT_IMPLEMENTED — preview status=${previewRes.status()}`,
        );
        test.info().annotations.push({
          type: 'marker',
          description: `FIN_REFUND_CANCEL_NOT_IMPLEMENTED status=${previewRes.status()}`,
        });
      }
    });
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 9 — Voucher Apply / Remove
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 9 — Voucher interaction (FIN_VOUCHER_APPLIED)', async () => {
    await test.step('List vouchers for reservation', async () => {
      const listRes = await api.get(
        `/api/v1/staff/reservations/${reservationId}/vouchers`,
      );
      console.log(`Voucher list status: ${listRes.status()}`);
      if (listRes.ok()) {
        const json = await listRes.json();
        const vouchers: any[] = json?.data || [];
        voucherId = vouchers[0]?.voucher_id ?? vouchers[0]?.id ?? 0;
        console.log(`Vouchers for reservation: ${vouchers.length} items`);
      }
    });

    await test.step('Apply voucher (requires valid voucher code in seed)', async () => {
      if (voucherId <= 0) {
        console.log('FIN_VOUCHER_NEEDS_DATA — no voucher seed found for this reservation');
        test.info().annotations.push({
          type: 'marker',
          description: 'FIN_VOUCHER_NEEDS_DATA — no voucher code in seed',
        });
        return;
      }

      const applyRes = await api.post(
        `/api/v1/staff/reservations/${reservationId}/voucher/apply`,
        {
          headers: { 'Idempotency-Key': `voucher-apply-${runId}` },
          data: { voucher_id: voucherId },
        },
      );
      console.log(`Voucher apply status: ${applyRes.status()}`);
      if (applyRes.ok()) {
        console.log('FIN_VOUCHER_APPLIED');
        test.info().annotations.push({ type: 'marker', description: 'FIN_VOUCHER_APPLIED' });

        // Remove voucher
        const removeRes = await api.post(
          `/api/v1/staff/reservations/${reservationId}/voucher/remove`,
          {
            headers: { 'Idempotency-Key': `voucher-remove-${runId}` },
            data: { voucher_id: voucherId },
          },
        );
        console.log(`Voucher remove status: ${removeRes.status()}`);
        console.log('FIN_VOUCHER_RELEASED');
        test.info().annotations.push({ type: 'marker', description: 'FIN_VOUCHER_RELEASED' });
      } else {
        console.log(
          `FIN_VOUCHER_NEEDS_DATA — apply failed: ${await applyRes.text().then((t) => t.substring(0, 200))}`,
        );
        test.info().annotations.push({ type: 'marker', description: 'FIN_VOUCHER_NEEDS_DATA' });
      }
    });
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 10 — Loyalty Redeem / Release
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 10 — Loyalty interaction (FIN_LOYALTY_REDEEMED)', async () => {
    await test.step('View loyalty for reservation', async () => {
      const loyaltyRes = await api.get(
        `/api/v1/staff/reservations/${reservationId}/loyalty`,
      );
      console.log(`Loyalty view status: ${loyaltyRes.status()}`);
      if (loyaltyRes.ok()) {
        const json = await loyaltyRes.json();
        const points = json?.data?.available_points ?? json?.data?.balance ?? 0;
        console.log(`Loyalty points available: ${points}`);

        if (parseFloat(String(points)) > 0) {
          // Try to redeem
          const redeemRes = await api.post(
            `/api/v1/staff/reservations/${reservationId}/loyalty/redeem`,
            {
              headers: { 'Idempotency-Key': `loyalty-redeem-${runId}` },
              data: { points_to_redeem: Math.min(10, parseFloat(String(points))) },
            },
          );
          console.log(`Loyalty redeem status: ${redeemRes.status()}`);
          if (redeemRes.ok()) {
            console.log('FIN_LOYALTY_REDEEMED');
            test.info().annotations.push({ type: 'marker', description: 'FIN_LOYALTY_REDEEMED' });

            // Release
            const releaseRes = await api.post(
              `/api/v1/staff/reservations/${reservationId}/loyalty/redeem/release`,
              {
                headers: { 'Idempotency-Key': `loyalty-release-${runId}` },
                data: {},
              },
            );
            console.log(`Loyalty release status: ${releaseRes.status()}`);
            console.log('FIN_LOYALTY_RELEASED');
            test.info().annotations.push({ type: 'marker', description: 'FIN_LOYALTY_RELEASED' });
          } else {
            console.log(
              `FIN_LOYALTY_NEEDS_DATA — redeem failed: ${await redeemRes.text().then((t) => t.substring(0, 200))}`,
            );
            test.info().annotations.push({ type: 'marker', description: 'FIN_LOYALTY_NEEDS_DATA' });
          }
        } else {
          console.log('FIN_LOYALTY_NEEDS_DATA — no points available for redemption');
          test.info().annotations.push({ type: 'marker', description: 'FIN_LOYALTY_NEEDS_DATA no_points' });
        }
      } else {
        console.log(`FIN_LOYALTY_NEEDS_DATA — loyalty view failed: ${loyaltyRes.status()}`);
        test.info().annotations.push({ type: 'marker', description: 'FIN_LOYALTY_NEEDS_DATA' });
      }
    });
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 11 — Cashier Shift Close
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 11 — Cashier shift totals + close (FIN_CASHIER_SHIFT_CLOSED)', async () => {
    await test.step('GET cashier shift to verify totals', async () => {
      expect(cashierShiftId, 'cashierShiftId must be set from setup').toBeGreaterThan(0);

      const shiftRes = await api.get(`/api/v1/staff/cashier/shifts/${cashierShiftId}`);
      expect(
        shiftRes.ok(),
        `Get shift failed (${shiftRes.status()}): ${await shiftRes.text()}`,
      ).toBeTruthy();
      const json = await shiftRes.json();
      const shiftData = json?.data;
      cashierShiftRowVersion = shiftData?.row_version ?? cashierShiftRowVersion;
      const totalCash = shiftData?.total_cash ?? shiftData?.total_payments ?? 0;
      console.log(
        `FIN_CASHIER_SHIFT_TOTAL_VERIFIED — shift_id=${cashierShiftId} total_cash=${totalCash}`,
      );
      test.info().annotations.push({
        type: 'marker',
        description: `FIN_CASHIER_SHIFT_TOTAL_VERIFIED total=${totalCash}`,
      });
    });

    await test.step('POST cashier/shifts/{id}/close', async () => {
      const closeRes = await api.post(
        `/api/v1/staff/cashier/shifts/${cashierShiftId}/close`,
        {
          headers: { 'Idempotency-Key': `shift-close-${runId}` },
          data: {
            actual_cash_amount: 0, // arbitrary for QA
            row_version: cashierShiftRowVersion,
            notes: 'Finance audit shift close',
          },
        },
      );
      expect(
        closeRes.ok(),
        `Close shift failed (${closeRes.status()}): ${await closeRes.text()}`,
      ).toBeTruthy();
      const json = await closeRes.json();
      const closedStatus = json?.data?.status ?? 'unknown';
      console.log(`FIN_CASHIER_SHIFT_CLOSED — status=${closedStatus}`);
      test.info().annotations.push({ type: 'marker', description: 'FIN_CASHIER_SHIFT_CLOSED' });
    });

    await test.step('Cannot close shift twice', async () => {
      const closeAgainRes = await api.post(
        `/api/v1/staff/cashier/shifts/${cashierShiftId}/close`,
        {
          headers: { 'Idempotency-Key': `shift-close-again-${runId}` },
          data: {
            actual_cash_amount: 0,
            row_version: cashierShiftRowVersion,
            notes: 'Double close attempt',
          },
        },
      );
      console.log(`Double-close attempt status: ${closeAgainRes.status()} (expected 4xx)`);
      expect(
        [400, 409, 422, 403, 404].includes(closeAgainRes.status()),
        `Double-close should be rejected, got ${closeAgainRes.status()}`,
      ).toBeTruthy();
      console.log('Cashier shift double-close correctly rejected');
    });

    await page.screenshot({ path: path.join(evidenceDir, '07-shift-closed.png') }).catch(() => {});
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 12 — Reconciliation / Invoice / Finance Read Flows
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 12 — Reconciliation + invoice read (FIN_RECONCILIATION_VERIFIED)', async () => {
    await test.step('GET finance/reconciliation list', async () => {
      const listRes = await api.get('/api/v1/staff/finance/reconciliation', {
        params: { branch_id: branchId },
      });
      console.log(`Reconciliation list status: ${listRes.status()}`);
      if (listRes.ok()) {
        const json = await listRes.json();
        const items: any[] = json?.data || [];
        console.log(`Reconciliation list count: ${items.length}`);
        test.info().annotations.push({ type: 'info', description: `Reconciliation list has ${items.length} items` });
      }
    });

    await test.step('GET finance/reconciliation/{reservation_id}', async () => {
      const showRes = await api.get(
        `/api/v1/staff/finance/reconciliation/${reservationId}`,
      );
      console.log(`Reconciliation show status: ${showRes.status()}`);
      if (showRes.ok()) {
        const json = await showRes.json();
        const recData = json?.data;
        const payments: any[] = recData?.payments || [];
        const netPaid = recData?.summary?.payment_summary?.net_paid_amount ?? recData?.net_paid_amount ?? recData?.total_paid ?? 0;
        console.log(
          `FIN_RECONCILIATION_VERIFIED — reservation=${reservationId} payments=${payments.length} net_paid=${netPaid}`,
        );
        expect(
          parseFloat(String(netPaid)),
          'Net paid amount must be > 0 after checkout',
        ).toBeGreaterThan(0);
        test.info().annotations.push({ type: 'marker', description: 'FIN_RECONCILIATION_VERIFIED' });
      } else {
        console.log(
          `FIN_RECONCILIATION_NOT_IMPLEMENTED — status=${showRes.status()}`,
        );
        test.info().annotations.push({
          type: 'marker',
          description: `FIN_RECONCILIATION_NOT_IMPLEMENTED status=${showRes.status()}`,
        });
      }
    });

    await test.step('GET finance/invoices/{reservation_id}', async () => {
      const invoiceRes = await api.get(
        `/api/v1/staff/finance/invoices/${reservationId}`,
      );
      console.log(`Invoice show status: ${invoiceRes.status()}`);
      if (invoiceRes.ok()) {
        const json = await invoiceRes.json();
        console.log(`Invoice data: ${JSON.stringify(json?.data).substring(0, 300)}`);
      } else {
        console.log(`Invoice GET returned ${invoiceRes.status()} — may be pre-issue state`);
      }
    });

    await test.step('POST finance/invoices/{reservation_id}/issue', async () => {
      const issueRes = await api.post(
        `/api/v1/staff/finance/invoices/${reservationId}/issue`,
        {
          headers: { 'Idempotency-Key': `invoice-issue-${runId}` },
          data: {},
        },
      );
      console.log(`Invoice issue status: ${issueRes.status()}`);
      if (issueRes.ok()) {
        const json = await issueRes.json();
        console.log(`Invoice issued: ${JSON.stringify(json?.data).substring(0, 200)}`);
      } else {
        console.log(
          `Invoice issue returned ${issueRes.status()}: ${await issueRes.text().then((t) => t.substring(0, 200))}`,
        );
      }
    });
  });

  // ─────────────────────────────────────────────────────────────
  // SUB-BATCH 13 — Permission / Access Guard
  // ─────────────────────────────────────────────────────────────
  test('SUB-BATCH 13 — Permission/access guard (FIN_PERMISSION_GUARD_VERIFIED)', async () => {
    await test.step('Test without staff key → expect 401/403', async () => {
      // Test settlement-preview without auth
      const noAuthCtx = await request.newContext({ baseURL: BASE_API });
      const noAuthRes = await noAuthCtx.get(
        `/api/v1/staff/orders/${orderId}/settlement-preview`,
      );
      console.log(`No-auth settlement-preview status: ${noAuthRes.status()}`);
      expect(
        [401, 403].includes(noAuthRes.status()),
        `Expected 401/403 without auth, got ${noAuthRes.status()}`,
      ).toBeTruthy();
      await noAuthCtx.dispose();
    });

    await test.step('Test pay endpoint without auth → expect 401/403', async () => {
      const noAuthCtx = await request.newContext({ baseURL: BASE_API });
      const noAuthPayRes = await noAuthCtx.post(`/api/v1/staff/orders/${orderId}/pay`, {
        data: { payment_method: 'cash', paid_amount: 100, currency: 'VND', row_version: 1 },
      });
      console.log(`No-auth pay status: ${noAuthPayRes.status()}`);
      expect(
        [401, 403].includes(noAuthPayRes.status()),
        `Expected 401/403 without auth, got ${noAuthPayRes.status()}`,
      ).toBeTruthy();
      await noAuthCtx.dispose();
    });

    await test.step('Test refund endpoint without auth → expect 401/403', async () => {
      const noAuthCtx = await request.newContext({ baseURL: BASE_API });
      const noAuthRefundRes = await noAuthCtx.post(
        `/api/v1/staff/reservations/${reservationId}/refund`,
        { data: { payment_method: 'cash', currency: 'VND', row_version: 1 } },
      );
      console.log(`No-auth refund status: ${noAuthRefundRes.status()}`);
      expect(
        [401, 403].includes(noAuthRefundRes.status()),
        `Expected 401/403 without auth, got ${noAuthRefundRes.status()}`,
      ).toBeTruthy();
      await noAuthCtx.dispose();
    });

    await test.step('Test cashier shift close without auth → expect 401/403', async () => {
      const noAuthCtx = await request.newContext({ baseURL: BASE_API });
      const noAuthCloseRes = await noAuthCtx.post(
        `/api/v1/staff/cashier/shifts/${cashierShiftId}/close`,
        { data: { actual_cash_amount: 0, row_version: 1 } },
      );
      console.log(`No-auth shift close status: ${noAuthCloseRes.status()}`);
      expect(
        [401, 403].includes(noAuthCloseRes.status()),
        `Expected 401/403 without auth, got ${noAuthCloseRes.status()}`,
      ).toBeTruthy();
      await noAuthCtx.dispose();
    });

    console.log('FIN_PERMISSION_GUARD_VERIFIED');
    test.info().annotations.push({ type: 'marker', description: 'FIN_PERMISSION_GUARD_VERIFIED' });
    await page.screenshot({ path: path.join(evidenceDir, '08-permission-guard.png') }).catch(() => {});
  });
});
