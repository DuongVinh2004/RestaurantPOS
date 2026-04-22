<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Branch;

use App\Modules\BranchScheduling\Application\Services\BranchContextService;
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
}
