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
        Schema::create('file_hosts', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->comment('Label atau deskripsi file');
            $table->string('original_name')->comment('Nama asli file saat diupload');
            $table->string('mime_type')->comment('MIME type file');
            $table->unsignedBigInteger('size')->comment('Ukuran file dalam bytes');
            $table->string('path')->comment('Path file di storage B2');
            $table->string('disk')->default('b2')->comment('Nama disk storage');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_hosts');
    }
};
