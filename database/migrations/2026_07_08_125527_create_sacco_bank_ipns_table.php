<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_bank_ipns', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Bank / endpoint identity
            |--------------------------------------------------------------------------
            */
            $table->string('bank_code', 30)->index('idx_sbi_bank_code'); // SBM, KCB, NCBA
            $table->string('endpoint', 120)->nullable();                 // /sbm, /kcb, /ncba
            $table->string('ipn_batch_id', 120)->nullable()->index('idx_sbi_batch_id');

            /*
            |--------------------------------------------------------------------------
            | Source / request trace
            |--------------------------------------------------------------------------
            */
            $table->string('source_ip', 45)->nullable()->index('idx_sbi_source_ip');
            $table->string('remote_addr', 45)->nullable()->index('idx_sbi_remote_addr');
            $table->string('cf_connecting_ip', 45)->nullable()->index('idx_sbi_cf_ip');
            $table->string('x_forwarded_for', 500)->nullable();
            $table->string('x_real_ip', 45)->nullable();

            $table->string('request_host', 255)->nullable();        // domain hit on your server
            $table->string('request_scheme', 20)->nullable();       // http / https
            $table->string('request_url', 1000)->nullable();        // full URL
            $table->string('request_path', 255)->nullable();        // /sbm
            $table->string('http_method', 10)->default('POST');

            $table->string('origin_header', 500)->nullable();
            $table->string('referer_header', 1000)->nullable();
            $table->string('possible_sender_domain', 255)->nullable()->index('idx_sbi_sender_domain');
            $table->string('reverse_dns', 255)->nullable();

            $table->string('user_agent', 500)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Full payload storage
            |--------------------------------------------------------------------------
            | Always store the full original request. For SBM, also store encrypted
            | and decrypted payloads separately.
            */
            $table->longText('headers_json')->nullable();
            $table->longText('query_json')->nullable();
            $table->longText('form_payload_json')->nullable();

            $table->longText('raw_body')->nullable();
            $table->string('raw_body_hash', 64)->nullable()->index('idx_sbi_body_hash');

            $table->string('payload_format', 40)->nullable(); // encrypted_base64, json, form, xml, text
            $table->longText('encrypted_payload')->nullable();
            $table->longText('decrypted_payload')->nullable();
            $table->longText('full_payload_json')->nullable();
            $table->longText('item_payload_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Extracted bank transaction fields
            |--------------------------------------------------------------------------
            */
            $table->string('transaction_reference', 150)->nullable();
            $table->string('bank_account', 100)->nullable()->index('idx_sbi_bank_account');
            $table->string('transaction_type', 40)->nullable(); // Credit, Debit
            $table->decimal('transaction_amount', 18, 2)->default(0.00);
            $table->string('currency', 10)->default('KES');
            $table->dateTime('transaction_date')->nullable()->index('idx_sbi_txn_date');

            $table->text('narration')->nullable();
            $table->string('customer_unique_id', 150)->nullable()->index('idx_sbi_customer_id');
            $table->string('customer_name', 255)->nullable();
            $table->decimal('account_balance', 18, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | SACCO reference extracted from narration/customerUniqueId
            |--------------------------------------------------------------------------
            */
            $table->string('member_reference', 150)->nullable()->index('idx_sbi_member_ref');
            $table->string('normalized_reference', 150)->nullable()->index('idx_sbi_norm_ref');

            /*
            |--------------------------------------------------------------------------
            | Validation state
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_valid')->default(false)->index('idx_sbi_is_valid');
            $table->string('validation_status', 40)->default('received')->index('idx_sbi_validation');
            $table->text('validation_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Picking / processing state
            |--------------------------------------------------------------------------
            | is_processed = true means this IPN has successfully updated the
            | correct SACCO/member records.
            */
            $table->boolean('is_picked')->default(false)->index('idx_sbi_is_picked');
            $table->timestamp('picked_at')->nullable();

            $table->boolean('is_processed')->default(false)->index('idx_sbi_is_processed');
            $table->timestamp('processed_at')->nullable();

            $table->string('processing_status', 40)->default('pending')->index('idx_sbi_processing');
            $table->text('processing_message')->nullable();

            $table->unsignedInteger('processing_attempts')->default(0);
            $table->text('last_error')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Allocation trace
            |--------------------------------------------------------------------------
            | No foreign keys. Just store where the money was posted.
            */
            $table->unsignedBigInteger('allocated_member_id')->nullable()->index('idx_sbi_member_id');
            $table->string('allocated_module', 80)->nullable()->index('idx_sbi_module');
            $table->unsignedBigInteger('allocated_record_id')->nullable();
            $table->string('allocated_doc_no', 150)->nullable()->index('idx_sbi_doc_no');

            /*
            |--------------------------------------------------------------------------
            | Bank response trace
            |--------------------------------------------------------------------------
            */
            $table->string('response_status', 40)->nullable();
            $table->unsignedSmallInteger('http_status_returned')->nullable();
            $table->longText('response_body')->nullable();
            $table->longText('encrypted_response')->nullable();

            $table->timestamp('received_at')->nullable()->index('idx_sbi_received_at');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Duplicate prevention / job indexes
            |--------------------------------------------------------------------------
            */
            $table->unique(
                ['bank_code', 'transaction_reference'],
                'uq_sbi_bank_reference'
            );

            $table->index(
                ['bank_code', 'is_valid', 'is_picked', 'is_processed'],
                'idx_sbi_job_pick'
            );

            $table->index(
                ['bank_code', 'processing_status'],
                'idx_sbi_bank_processing'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_bank_ipns');
    }
};