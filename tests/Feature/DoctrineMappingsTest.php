<?php

namespace Itemvirtual\Laravel2fa\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Itemvirtual\Laravel2fa\Tests\TestCase;

class DoctrineMappingsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/laravel-2fa-mappings-' . uniqid();
        File::copyDirectory(__DIR__ . '/../Fixtures/xml-mappings', $this->path);
        config()->set('laravel-2fa.xml_mappings_path', $this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    public function test_adds_fields_to_every_guard_mapping()
    {
        $this->artisan('laravel-2fa:doctrine-mappings')->assertExitCode(0);

        foreach (['Users', 'Customers'] as $entity) {
            $columns = $this->columns($this->path . '/' . $entity . '.dcm.xml');

            foreach (['two_factor_enabled', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_timestamp', 'two_factor_confirmed_at'] as $column) {
                $this->assertSame(1, count(array_keys($columns, $column)), $entity . ' ' . $column);
            }
        }
    }

    public function test_inserts_with_the_mapping_formatting()
    {
        $original = File::get($this->path . '/Users.dcm.xml');

        $this->artisan('laravel-2fa:doctrine-mappings');

        $expected = str_replace('    </entity>', implode("\n", [
            '        <field name="twoFactorEnabled" type="boolean" column="two_factor_enabled" nullable="false">',
            '            <options>',
            '                <option name="default">0</option>',
            '            </options>',
            '        </field>',
            '        <field name="twoFactorSecret" type="text" column="two_factor_secret" nullable="true"/>',
            '        <field name="twoFactorRecoveryCodes" type="text" column="two_factor_recovery_codes" nullable="true"/>',
            '        <field name="twoFactorLastTimestamp" type="bigint" column="two_factor_last_timestamp" nullable="true">',
            '            <options>',
            '                <option name="unsigned"/>',
            '            </options>',
            '        </field>',
            '        <field name="twoFactorConfirmedAt" type="datetime" column="two_factor_confirmed_at" nullable="true"/>',
            '    </entity>',
        ]), $original);

        $this->assertSame($expected, File::get($this->path . '/Users.dcm.xml'));
    }

    public function test_second_run_changes_nothing()
    {
        $this->artisan('laravel-2fa:doctrine-mappings');
        $users = File::get($this->path . '/Users.dcm.xml');
        $customers = File::get($this->path . '/Customers.dcm.xml');

        $this->artisan('laravel-2fa:doctrine-mappings')
            ->expectsOutputToContain('users: up to date')
            ->assertExitCode(0);

        $this->assertSame($users, File::get($this->path . '/Users.dcm.xml'));
        $this->assertSame($customers, File::get($this->path . '/Customers.dcm.xml'));
    }

    public function test_dry_run_does_not_write()
    {
        $original = File::get($this->path . '/Users.dcm.xml');

        $this->artisan('laravel-2fa:doctrine-mappings', ['--dry-run' => true])
            ->expectsOutputToContain('users: would add two_factor_enabled, two_factor_secret')
            ->assertExitCode(0);

        $this->assertSame($original, File::get($this->path . '/Users.dcm.xml'));
    }

    public function test_leaves_other_mappings_untouched()
    {
        $original = File::get($this->path . '/Pages.dcm.xml');

        $this->artisan('laravel-2fa:doctrine-mappings');

        $this->assertSame($original, File::get($this->path . '/Pages.dcm.xml'));
    }

    public function test_fails_without_changes_when_a_table_has_no_mapping()
    {
        File::delete($this->path . '/Customers.dcm.xml');
        $original = File::get($this->path . '/Users.dcm.xml');

        $this->artisan('laravel-2fa:doctrine-mappings')
            ->expectsOutputToContain('No xml-mapping found for table [customers]')
            ->assertExitCode(1);

        $this->assertSame($original, File::get($this->path . '/Users.dcm.xml'));
    }

    public function test_fails_when_the_path_does_not_exist()
    {
        config()->set('laravel-2fa.xml_mappings_path', $this->path . '/missing');

        $this->artisan('laravel-2fa:doctrine-mappings')
            ->expectsOutputToContain('The xml-mappings path does not exist')
            ->assertExitCode(1);
    }

    public function test_fails_when_the_path_is_not_configured()
    {
        config()->set('laravel-2fa.xml_mappings_path', null);

        $this->artisan('laravel-2fa:doctrine-mappings')
            ->expectsOutputToContain('The xml-mappings path does not exist')
            ->assertExitCode(1);
    }

    public function test_fails_when_the_path_has_no_mappings()
    {
        File::cleanDirectory($this->path);

        $this->artisan('laravel-2fa:doctrine-mappings')
            ->expectsOutputToContain('No xml-mapping found for table [users]')
            ->expectsOutputToContain('No xml-mapping found for table [customers]')
            ->assertExitCode(1);
    }

    public function test_ignores_unrelated_invalid_mappings()
    {
        File::put($this->path . '/Broken.dcm.xml', '<doctrine-mapping><entity');

        $this->artisan('laravel-2fa:doctrine-mappings')->assertExitCode(0);

        $this->assertSame('<doctrine-mapping><entity', File::get($this->path . '/Broken.dcm.xml'));
        $this->assertContains('two_factor_secret', $this->columns($this->path . '/Users.dcm.xml'));
    }

    private function columns(string $file): array
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->load($file), 'Invalid XML in ' . $file);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('orm', 'http://doctrine-project.org/schemas/orm/doctrine-mapping');

        $columns = [];
        foreach ($xpath->query('//orm:entity/orm:field') as $node) {
            $columns[] = $node->getAttribute('column');
        }

        return $columns;
    }
}
