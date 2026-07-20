<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait BuildsAuditTrailTables
{
    protected function ensureAuditTrailTables(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->bigIncrements('audit_id');
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->string('actor_type', 40)->nullable();
                $table->string('actor_key', 120)->nullable();
                $table->string('entity_type', 50);
                $table->string('entity_id', 64);
                $table->string('action', 50);
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->json('summary_json')->nullable();
                $table->json('meta_json')->nullable();
                $table->string('request_id', 64)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->dateTime('created_at');
            });
        }

        if (! Schema::hasTable('audit_log_subjects')) {
            Schema::create('audit_log_subjects', function (Blueprint $table): void {
                $table->bigIncrements('audit_subject_id');
                $table->unsignedBigInteger('audit_id');
                $table->string('subject_type', 50);
                $table->string('subject_id', 64);
                $table->string('subject_role', 32)->nullable();
                $table->dateTime('created_at');
            });
        }
    }
}
