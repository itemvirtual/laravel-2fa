<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class MigrationTest extends TestCase
{
    private array $columns = [
        'two_factor_enabled',
        'two_factor_method',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_last_timestamp',
        'two_factor_confirmed_at',
    ];

    public function test_adds_columns_to_every_guard_table()
    {
        $this->setUpTwoFactorTables();

        $this->assertTrue(Schema::hasColumns('users', $this->columns));
        $this->assertTrue(Schema::hasColumns('customers', $this->columns));
    }

    public function test_existing_rows_get_two_factor_disabled()
    {
        $this->createGuardTables();
        DB::table('users')->insert(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);

        $this->twoFactorMigration()->up();

        $this->assertEquals(0, DB::table('users')->value('two_factor_enabled'));
    }

    public function test_is_idempotent()
    {
        $this->setUpTwoFactorTables();
        $this->twoFactorMigration()->up();

        $this->assertTrue(Schema::hasColumns('users', $this->columns));
    }

    public function test_only_adds_missing_columns()
    {
        $this->createGuardTables();
        Schema::table('customers', function (Blueprint $blueprint) {
            $blueprint->text('two_factor_secret')->nullable();
        });

        $this->twoFactorMigration()->up();

        $this->assertTrue(Schema::hasColumns('customers', $this->columns));
    }

    public function test_skips_missing_tables()
    {
        $this->twoFactorMigration()->up();

        $this->assertFalse(Schema::hasTable('users'));
    }

    public function test_down_removes_the_columns()
    {
        $this->setUpTwoFactorTables();
        $this->twoFactorMigration()->down();

        foreach ($this->columns as $column) {
            $this->assertFalse(Schema::hasColumn('users', $column));
            $this->assertFalse(Schema::hasColumn('customers', $column));
        }

        $this->assertTrue(Schema::hasColumn('users', 'email'));
    }
}
