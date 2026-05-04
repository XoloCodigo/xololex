<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->text('company_name')->change();
        });

        Schema::table('sharepoint_forms', function (Blueprint $table) {
            $table->text('sharepoint_url')->nullable()->change();
            $table->text('pdf_path')->nullable()->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->text('address')->nullable()->change();
            $table->text('name')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('company_name')->change();
        });

        Schema::table('sharepoint_forms', function (Blueprint $table) {
            $table->string('sharepoint_url')->nullable()->change();
            $table->string('pdf_path')->nullable()->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
            $table->string('name')->change();
        });
    }
};
