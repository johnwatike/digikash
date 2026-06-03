<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('pi_id', 64)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('trx_id', 64)->nullable()->index();
            $table->string('status', 40)->default('requires_payment_method');
            $table->decimal('amount', 18, 8);
            $table->decimal('fee', 18, 8)->default(0);
            $table->decimal('net_amount', 18, 8)->default(0);
            $table->string('currency', 10);
            $table->string('client_secret', 128)->unique();
            $table->string('idempotency_key', 128)->nullable();
            $table->string('ref_trx', 128)->nullable();
            $table->string('environment', 20)->default('production');
            $table->json('metadata')->nullable();
            $table->json('payment_method_data')->nullable();
            $table->string('next_action_type', 40)->nullable();
            $table->json('next_action_data')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key'], 'payment_intents_merchant_idempotency_unique');
            $table->index(['merchant_id', 'ref_trx']);
            $table->index(['merchant_id', 'status']);
        });

        Schema::create('payment_intent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('reason', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('secret', 128);
            $table->json('events')->nullable();
            $table->string('api_version', 20)->default('2026-06-01');
            $table->string('status', 20)->default('active');
            $table->boolean('is_legacy_ipn')->default(false);
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('resource_type', 40)->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->unsignedBigInteger('sequence')->default(0);
            $table->json('payload');
            $table->string('environment', 20)->default('production');
            $table->timestamps();

            $table->index(['merchant_id', 'resource_id', 'sequence']);
            $table->index(['merchant_id', 'type', 'created_at']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
        });

        Schema::create('mpesa_shortcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('shortcode', 20);
            $table->string('label', 120)->nullable();
            $table->string('nominated_phone', 20)->nullable();
            $table->text('credentials')->nullable();
            $table->boolean('callbacks_registered')->default(false);
            $table->string('environment', 20)->default('sandbox');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shortcode', 'type', 'environment']);
        });

        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mpesa_shortcode_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trans_id', 64)->nullable()->unique();
            $table->string('bill_ref_number', 64)->nullable()->index();
            $table->string('msisdn', 20)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('transaction_type', 40)->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('type', 40);
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_account_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 10);
            $table->decimal('amount', 18, 8);
            $table->string('currency', 10);
            $table->string('reference_type', 40)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('merchant_settlement_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 20)->default('daily');
            $table->unsignedTinyInteger('settlement_delay_days')->default(2);
            $table->decimal('minimum_payout', 18, 8)->default(0);
            $table->string('currency', 10)->default('KES');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('merchant_reserves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->decimal('percent', 8, 4)->default(0);
            $table->unsignedInteger('hold_days')->default(0);
            $table->decimal('cap_amount', 18, 8)->nullable();
            $table->string('currency', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_intent_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->string('recipient_label', 120)->nullable();
            $table->decimal('amount', 18, 8);
            $table->decimal('percent', 8, 4)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('merchant_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40)->default('support');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_id', 'user_id']);
        });

        Schema::create('integration_handlers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('type', 40);
            $table->boolean('is_enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('fraud_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('rule_type', 40);
            $table->json('conditions');
            $table->string('action', 40)->default('block');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();
        });

        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 128);
            $table->string('request_hash', 64)->nullable();
            $table->unsignedSmallInteger('response_status')->default(200);
            $table->json('response_body');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key']);
            $table->index('expires_at');
        });

        Schema::table('merchants', function (Blueprint $table) {
            if (! Schema::hasColumn('merchants', 'enforce_unique_ref_trx')) {
                $table->boolean('enforce_unique_ref_trx')->default(false)->after('webhook_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (Schema::hasColumn('merchants', 'enforce_unique_ref_trx')) {
                $table->dropColumn('enforce_unique_ref_trx');
            }
        });

        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('fraud_rules');
        Schema::dropIfExists('integration_handlers');
        Schema::dropIfExists('merchant_team_members');
        Schema::dropIfExists('payment_intent_splits');
        Schema::dropIfExists('merchant_reserves');
        Schema::dropIfExists('merchant_settlement_schedules');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('mpesa_transactions');
        Schema::dropIfExists('mpesa_shortcodes');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('payment_intent_events');
        Schema::dropIfExists('payment_intents');
    }
};
