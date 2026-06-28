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
        Schema::create('wargas', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 16)->unique()->comment('Nomor Induk Kependudukan');
            $table->string('nama');
            $table->text('alamat');
            $table->string('pas_foto')->comment('Path pas foto warga');
            $table->string('dokumen')->nullable()->comment('Path dokumen pendukung (opsional)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wargas');
    }
};
