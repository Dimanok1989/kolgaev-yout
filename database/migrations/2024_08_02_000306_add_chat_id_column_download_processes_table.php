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
        Schema::table('download_processes', function (Blueprint $table) {
            $table->bigInteger('chat_id')->nullable()->comment('Идентификатор чата для отправки ссылки')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('download_processes', function (Blueprint $table) {
            $table->dropColumn('chat_id');
        });
    }
};
