<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_otp_challenges', function (Blueprint $table) {
            $table->enum('sent_status', ['pending', 'sent', 'failed'])->default('pending')->after('max_resends');
            $table->text('send_error')->nullable()->after('sent_status');
            $table->index(['sent_status']);
        });
    }

    public function down(): void
    {
        Schema::table('login_otp_challenges', function (Blueprint $table) {
            $table->dropIndex(['sent_status']);
            $table->dropColumn(['sent_status', 'send_error']);
        });
    }
};
