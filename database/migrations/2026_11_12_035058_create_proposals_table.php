<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('owner_claimed_at')->nullable();
            $table->foreignId('root_id')->nullable()->constrained('proposals')->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('superseded_at')->nullable();
            $table->string('number')->unique();
            $table->string('type', 20);
            $table->string('status', 20)->default('draft');
            $table->string('title');
            $table->string('client_name');
            $table->string('client_email');
            $table->string('currency', 3);
            $table->json('items');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedSmallInteger('tax_rate_basis_points')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->date('issued_on');
            $table->date('valid_until');
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->string('signer_name')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->json('recipient_verification')->nullable();
            $table->timestamp('recipient_verified_at')->nullable();
            $table->json('accepted_snapshot')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->string('invitation_status', 20)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
