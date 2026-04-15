<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Uat\UatScenarioPackService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class UatScenarioPackConsoleCommandTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        $this->manifestPath = storage_path('framework/testing/uat-scenario-pack-test.json');

        if (File::exists($this->manifestPath)) {
            File::delete($this->manifestPath);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->manifestPath)) {
            File::delete($this->manifestPath);
        }

        parent::tearDown();
    }

    #[Group('booking-ops')]
    public function test_bootstrap_and_reset_commands_manage_canonical_uat_pack_data_and_manifest(): void
    {
        $bootstrapExit = Artisan::call('booking:uat-pack:bootstrap', [
            '--base-url' => 'http://127.0.0.1:8000',
            '--manifest-path' => $this->manifestPath,
            '--json' => true,
        ]);

        self::assertSame(0, $bootstrapExit);
        $bootstrap = $this->decodeArtisanOutput();

        self::assertTrue((bool) $bootstrap['ok']);
        self::assertSame('UATDEMO', (string) data_get($bootstrap, 'data.summary.branch.branch_code'));
        self::assertSame('restaurantpos-uat-demo', (string) data_get($bootstrap, 'data.manifest.pack.name'));
        self::assertNotEmpty((string) data_get($bootstrap, 'data.manifest.auth.admin.api_key'));
        self::assertSame('UatDemo!123', (string) data_get($bootstrap, 'data.manifest.auth.customer_primary.password'));
        self::assertCount(9, (array) data_get($bootstrap, 'data.summary.supported_scenarios', []));
        self::assertTrue(File::exists($this->manifestPath));

        /** @var array<string,mixed> $manifest */
        $manifest = json_decode((string) File::get($this->manifestPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('UATDEMO', (string) data_get($manifest, 'branch.branch_code'));
        self::assertSame('Asia/Ho_Chi_Minh', (string) data_get($manifest, 'branch.timezone'));
        self::assertSame('UAT-VOUCHER-50', (string) data_get($manifest, 'benefits.voucher.voucher_code'));
        self::assertSame('Open', (string) data_get($manifest, 'conversation.status'));
        self::assertSame('customer.bill_self_payment', (string) data_get($manifest, 'feature_flags.0.feature_key'));

        self::assertSame(1, (int) DB::table('branches')->where('branch_code', 'UATDEMO')->count());
        self::assertSame(4, (int) DB::table('users')->whereIn('username', [
            'uat.admin',
            'uat.staff',
            'uat.customer.primary',
            'uat.customer.secondary',
        ])->count());
        self::assertSame(5, (int) DB::table('reservations')->whereIn('reservation_code', [
            'UAT-DEP-001',
            'UAT-DINE-001',
            'UAT-BEN-001',
            'UAT-RF-001',
            'UAT-RFC-001',
        ])->count());
        self::assertSame(5, (int) DB::table('feature_flags')
            ->where('branch_id', (int) data_get($manifest, 'branch.branch_id'))
            ->count());
        self::assertSame(1, (int) DB::table('kitchen_stations')->where('code', 'UAT-HOT-PASS')->count());
        self::assertSame(1, (int) DB::table('kitchen_stations')->where('code', 'UAT-DRINK-BAR')->count());
        self::assertSame(1, (int) DB::table('kitchen_station_category_routes')
            ->where('category_id', (int) data_get($manifest, 'menu.categories.signatures.category_id'))
            ->count());
        self::assertSame(1, (int) DB::table('kitchen_station_category_routes')
            ->where('category_id', (int) data_get($manifest, 'menu.categories.drinks.category_id'))
            ->count());

        $branchId = (int) data_get($manifest, 'branch.branch_id');
        $steakItemId = (int) data_get($manifest, 'menu.items.steak.item_id');
        $businessDate = now('UTC')->toDateString();
        $ingredientId = $this->createIngredient();
        $unitCode = (string) (DB::table('ingredients')->where('ingredient_id', $ingredientId)->value('unit_code') ?? 'unit');
        $externalOrderItemId = $this->createOrderItem([
            'item_id' => $steakItemId,
        ]);
        $this->createKitchenOrderTicket([
            'order_item_id' => $externalOrderItemId,
        ]);

        DB::table('reporting_daily_sales_snapshots')->insert([
            'branch_id' => $branchId,
            'business_date' => $businessDate,
            'currency' => 'VND',
            'billed_reservation_count' => 1,
            'billed_guest_count' => 2,
            'gross_bill_amount' => '180000.00',
            'discount_amount' => '0.00',
            'billed_total_amount' => '180000.00',
            'invoice_issued_count' => 0,
            'invoiced_total_amount' => '0.00',
            'invoiced_tax_amount' => '0.00',
            'payment_row_count' => 1,
            'refund_row_count' => 0,
            'captured_amount' => '180000.00',
            'refunded_amount' => '0.00',
            'net_paid_amount' => '180000.00',
            'deposit_net_amount' => '0.00',
            'final_net_amount' => '180000.00',
            'cashier_shift_closed_count' => 0,
            'refreshed_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('reporting_daily_operation_snapshots')->insert([
            'branch_id' => $branchId,
            'business_date' => $businessDate,
            'scheduled_reservation_count' => 1,
            'scheduled_guest_count' => 2,
            'scheduled_minutes_total' => 120,
            'checked_in_count' => 0,
            'completed_count' => 0,
            'cancelled_count' => 0,
            'no_show_count' => 0,
            'turn_count' => 0,
            'turn_minutes_total' => 0,
            'waiting_list_created_count' => 0,
            'waiting_list_notified_count' => 0,
            'waiting_list_seated_count' => 0,
            'waiting_list_cancelled_count' => 0,
            'waiting_list_confirmed_arrival_count' => 0,
            'refreshed_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('reporting_daily_inventory_movement_snapshots')->insert([
            'branch_id' => $branchId,
            'business_date' => $businessDate,
            'ingredient_id' => $ingredientId,
            'unit_code' => $unitCode,
            'movement_count' => 1,
            'purchase_receipt_movement_count' => 0,
            'stock_in_quantity' => '1.000',
            'stock_out_quantity' => '0.000',
            'adjustment_increase_quantity' => '0.000',
            'adjustment_decrease_quantity' => '0.000',
            'wastage_quantity' => '0.000',
            'net_quantity_delta' => '1.000',
            'last_movement_at' => now('UTC'),
            'refreshed_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $invoiceReservationId = (int) data_get($manifest, 'reservations.dine_in_checkin.reservation_id');
        DB::table('billing_invoices')->insert([
            'reservation_id' => $invoiceReservationId,
            'invoice_number' => 'UAT-INV-0001',
            'invoice_status' => 'Issued',
            'subtotal_amount' => '210000.00',
            'discount_amount' => '0.00',
            'total_amount' => '210000.00',
            'currency' => 'VND',
            'tax_rate_percentage' => '0.000',
            'prices_include_tax' => 1,
            'taxable_amount' => '210000.00',
            'tax_amount' => '0.00',
            'seller_name' => 'UAT Demo Branch',
            'seller_tax_id' => null,
            'seller_address' => '123 UAT Street',
            'issued_at' => now('UTC'),
            'issued_by' => null,
            'voided_at' => null,
            'voided_by' => null,
            'metadata_json' => null,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $resetExit = Artisan::call('booking:uat-pack:reset', [
            '--manifest-path' => $this->manifestPath,
            '--json' => true,
        ]);

        self::assertSame(0, $resetExit);
        $reset = $this->decodeArtisanOutput();

        self::assertTrue((bool) $reset['ok']);
        self::assertTrue((bool) data_get($reset, 'data.manifest_deleted'));
        self::assertFalse(File::exists($this->manifestPath));
        self::assertSame(0, (int) DB::table('branches')->where('branch_code', 'UATDEMO')->count());
        self::assertSame(0, (int) DB::table('users')->whereIn('username', [
            'uat.admin',
            'uat.staff',
            'uat.customer.primary',
            'uat.customer.secondary',
        ])->count());
        self::assertSame(0, (int) DB::table('reservation_order_items')->where('order_item_id', $externalOrderItemId)->count());
        self::assertSame(0, (int) DB::table('kitchen_order_item_tickets')->where('order_item_id', $externalOrderItemId)->count());
        self::assertSame(0, (int) DB::table('billing_invoices')->where('reservation_id', $invoiceReservationId)->count());
        self::assertSame(0, (int) DB::table('kitchen_station_category_routes')
            ->where('category_id', (int) data_get($manifest, 'menu.categories.signatures.category_id'))
            ->count());
        self::assertSame(0, (int) DB::table('kitchen_stations')->whereIn('code', ['UAT-HOT-PASS', 'UAT-DRINK-BAR'])->count());
        self::assertSame(0, (int) DB::table('reporting_daily_sales_snapshots')->where('branch_id', $branchId)->count());
        self::assertSame(0, (int) DB::table('reporting_daily_operation_snapshots')->where('branch_id', $branchId)->count());
        self::assertSame(0, (int) DB::table('reporting_daily_inventory_movement_snapshots')->where('branch_id', $branchId)->count());
    }

    #[Group('booking-ops')]
    public function test_bootstrap_command_returns_structured_validation_errors_when_bootstrap_validation_fails(): void
    {
        $this->app->instance(UatScenarioPackService::class, new class extends UatScenarioPackService
        {
            public function __construct() {}

            public function bootstrap(?string $baseUrl = null, ?string $manifestPath = null): array
            {
                throw ValidationException::withMessages([
                    'base_url' => ['The base_url must use https.'],
                ]);
            }
        });

        $exitCode = Artisan::call('booking:uat-pack:bootstrap', [
            '--base-url' => 'http://127.0.0.1:8000',
            '--json' => true,
        ]);

        self::assertSame(1, $exitCode);

        $payload = $this->decodeArtisanOutput();

        self::assertSame('validation_error', $payload['error'] ?? null);
        self::assertSame(['The base_url must use https.'], $payload['errors']['base_url'] ?? null);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeArtisanOutput(): array
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
