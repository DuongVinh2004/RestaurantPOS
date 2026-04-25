<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Branch;

use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\IdentityAccess\Application\Queries\StaffCapabilityResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class BranchContextServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('branches');

        Schema::create('branches', function (Blueprint $table): void {
            $table->increments('branch_id');
            $table->string('branch_code')->unique();
            $table->string('branch_name');
            $table->string('description')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('currency', 10)->default('VND');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('row_version')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        DB::table('branches')->insert([
            [
                'branch_id' => 1,
                'branch_code' => 'MAIN',
                'branch_name' => 'Main',
                'timezone' => 'UTC',
                'currency' => 'VND',
                'is_active' => true,
                'is_default' => true,
                'row_version' => 1,
            ],
            [
                'branch_id' => 2,
                'branch_code' => 'ANNEX',
                'branch_name' => 'Annex',
                'timezone' => 'UTC',
                'currency' => 'VND',
                'is_active' => true,
                'is_default' => false,
                'row_version' => 1,
            ],
        ]);
    }

    public function test_assert_single_branch_returns_only_branch_id(): void
    {
        $service = new BranchContextService;

        self::assertSame(2, $service->assertSingleBranch([2, 2], activeOnly: false));
    }

    public function test_assert_single_branch_rejects_mixed_branch_ids(): void
    {
        $service = new BranchContextService;

        $this->expectException(ValidationException::class);
        $service->assertSingleBranch([1, 2], 'Resources must belong to a single branch.', 'branch_id', false);
    }

    public function test_assert_same_branch_rejects_mismatch(): void
    {
        $service = new BranchContextService;

        $this->expectException(ValidationException::class);
        $service->assertSameBranch(1, 2, 'Branch mismatch.', 'branch_id', false);
    }

    public function test_default_branch_read_path_does_not_create_branch_when_none_exists(): void
    {
        DB::table('branches')->delete();

        $service = new BranchContextService;

        $this->expectException(ModelNotFoundException::class);

        try {
            $service->defaultBranch();
        } finally {
            self::assertSame(0, (int) DB::table('branches')->count());
        }
    }

    public function test_default_branch_read_path_does_not_promote_active_branch(): void
    {
        DB::table('branches')->update(['is_default' => false]);

        $service = new BranchContextService;
        $branch = $service->defaultBranch();

        self::assertSame(1, (int) $branch->branch_id);
        self::assertFalse((bool) DB::table('branches')->where('branch_id', 1)->value('is_default'));
    }

    public function test_staff_branch_context_read_path_does_not_create_default_branch(): void
    {
        DB::table('branches')->delete();
        config()->set('staff_capabilities.fallback_branch_scopes', ['default']);

        $service = new StaffBranchContextService(
            new BranchContextService,
            new StaffCapabilityResolver,
        );

        self::assertSame([], $service->accessibleBranchIds());
        self::assertSame(0, (int) DB::table('branches')->count());
    }

    public function test_default_branch_bootstrap_path_can_still_seed_default_branch(): void
    {
        DB::table('branches')->delete();
        config()->set('booking.multi_branch.default_branch_code', 'MAIN');
        config()->set('booking.multi_branch.default_branch_name', 'Main');
        config()->set('booking.multi_branch.default_branch_timezone', 'UTC');
        config()->set('booking.multi_branch.default_branch_currency', 'VND');

        $service = new BranchContextService;
        $service->ensureDefaultBranchExists();

        self::assertSame(1, (int) DB::table('branches')->count());
        self::assertSame('MAIN', (string) DB::table('branches')->value('branch_code'));
        self::assertTrue((bool) DB::table('branches')->value('is_default'));
    }
}
