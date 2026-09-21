<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retires the "Emergency" assistance type.
 *
 * The rows go first: MySQL refuses to narrow an enum while a column still
 * holds a value the new definition does not list, so any Emergency record left
 * behind would fail the ALTER rather than be quietly converted.
 */
return new class extends Migration
{
    /** The four types that remain, in the order the forms list them. */
    private const KEPT = ['Medical', 'Burial', 'Educational', 'Livelihood'];

    private const WITH_EMERGENCY = ['Medical', 'Burial', 'Educational', 'Emergency', 'Livelihood'];

    public function up(): void
    {
        DB::table('assistance_requests')->where('assistance_type', 'Emergency')->delete();
        DB::table('budgets')->where('assistance_type', 'Emergency')->delete();

        $this->setEnum(self::KEPT);
    }

    /**
     * Restores the wider enum. The deleted rows are not recoverable here — a
     * rollback returns the column to its old shape, not the data to it.
     */
    public function down(): void
    {
        $this->setEnum(self::WITH_EMERGENCY);
    }

    /**
     * Raw SQL because Doctrine DBAL does not model MySQL enums, so the schema
     * builder cannot alter one in place.
     */
    private function setEnum(array $values): void
    {
        $list = implode(', ', array_map(fn ($value) => "'".$value."'", $values));

        foreach (['assistance_requests', 'budgets'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY COLUMN assistance_type ENUM({$list}) NOT NULL");
        }
    }
};
