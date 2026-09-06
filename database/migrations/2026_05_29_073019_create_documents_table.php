<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            if (! $this->foreignKeyExists('documents', 'documents_request_id_foreign')) {
                Schema::table('documents', function (Blueprint $table) {
                    $table->foreign('request_id')
                        ->references('id')
                        ->on('assistance_requests')
                        ->cascadeOnDelete();
                });
            }

            return;
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('assistance_requests')->cascadeOnDelete();
            $table->string('document_type', 100);
            $table->string('document_status', 50)->default('submitted');
            $table->string('file_path', 255)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return (bool) DB::selectOne(
            'select constraint_name from information_schema.table_constraints where table_schema = database() and table_name = ? and constraint_name = ? and constraint_type = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );
    }
};
