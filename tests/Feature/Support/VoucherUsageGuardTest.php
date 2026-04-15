<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\BenefitsLoyalty\Domain\Models\UserVoucher;
use App\Modules\BenefitsLoyalty\Domain\Models\Voucher;
use App\Modules\BenefitsLoyalty\Domain\Guards\VoucherUsageGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class VoucherUsageGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('user_vouchers');
        Schema::dropIfExists('vouchers');

        Schema::create('vouchers', function (Blueprint $table): void {
            $table->increments('voucher_id');
            $table->string('code');
            $table->string('discount_type');
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->unsignedInteger('free_item_id')->nullable();
            $table->unsignedInteger('free_item_qty')->nullable();
            $table->unsignedInteger('max_usage')->nullable();
            $table->unsignedInteger('max_usage_per_user')->nullable();
            $table->decimal('min_spend', 14, 2)->default(0);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        Schema::create('user_vouchers', function (Blueprint $table): void {
            $table->increments('user_voucher_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('voucher_id');
            $table->dateTime('assigned_date')->nullable();
            $table->boolean('is_used')->default(false);
            $table->dateTime('used_date')->nullable();
            $table->unsignedInteger('used_reservation_id')->nullable();
            $table->decimal('used_amount', 14, 2)->nullable();
            $table->string('lock_token')->nullable();
            $table->dateTime('locked_until')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('row_version')->default(1);
        });
    }

    public function test_it_rejects_total_usage_above_voucher_cap(): void
    {
        $voucher = $this->createVoucher(maxUsage: 1, maxUsagePerUser: null);

        UserVoucher::query()->create([
            'user_id' => 1,
            'voucher_id' => (int) $voucher->voucher_id,
            'assigned_date' => Carbon::now('UTC'),
            'is_used' => true,
            'used_date' => Carbon::now('UTC'),
            'used_reservation_id' => 501,
            'used_amount' => 25.00,
        ]);

        $this->expectException(ValidationException::class);

        VoucherUsageGuard::lockVoucherAndAssertCanConsume($voucher, 2);
    }

    public function test_it_allows_revalidating_the_same_used_row_when_excluded(): void
    {
        $voucher = $this->createVoucher(maxUsage: 1, maxUsagePerUser: null);

        $used = UserVoucher::query()->create([
            'user_id' => 1,
            'voucher_id' => (int) $voucher->voucher_id,
            'assigned_date' => Carbon::now('UTC'),
            'is_used' => true,
            'used_date' => Carbon::now('UTC'),
            'used_reservation_id' => 601,
            'used_amount' => 25.00,
        ]);

        $lockedVoucher = VoucherUsageGuard::lockVoucherAndAssertCanConsume($voucher, 1, (int) $used->user_voucher_id);

        self::assertSame((int) $voucher->voucher_id, (int) $lockedVoucher->voucher_id);
    }

    public function test_it_rejects_per_user_usage_above_cap(): void
    {
        $voucher = $this->createVoucher(maxUsage: null, maxUsagePerUser: 1);

        UserVoucher::query()->create([
            'user_id' => 44,
            'voucher_id' => (int) $voucher->voucher_id,
            'assigned_date' => Carbon::now('UTC'),
            'is_used' => true,
            'used_date' => Carbon::now('UTC'),
            'used_reservation_id' => 701,
            'used_amount' => 30.00,
        ]);

        $this->expectException(ValidationException::class);

        VoucherUsageGuard::lockVoucherAndAssertCanConsume($voucher, 44);
    }

    private function createVoucher(?int $maxUsage, ?int $maxUsagePerUser): Voucher
    {
        /** @var Voucher $voucher */
        $voucher = Voucher::query()->create([
            'code' => 'T-'.$this->randomCode(),
            'discount_type' => 'Fixed',
            'discount_value' => 25.00,
            'max_usage' => $maxUsage,
            'max_usage_per_user' => $maxUsagePerUser,
            'min_spend' => 0,
            'is_active' => true,
        ]);

        return $voucher;
    }

    private function randomCode(): string
    {
        return strtoupper(substr(sha1((string) microtime(true).random_int(1, PHP_INT_MAX)), 0, 8));
    }
}
