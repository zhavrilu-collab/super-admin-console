<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RenameProductApplicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_renames_products_rewrites_hosts_and_removes_opg(): void
    {
        $clubId = DB::table('applications')->insertGetId([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'api_base_url' => 'https://app.superskytech.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $crewId = DB::table('applications')->insertGetId([
            'name' => 'HR SaaS',
            'slug' => 'hr-saas',
            'api_base_url' => 'https://hr.superskytech.com/',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('applications')->insert([
            'name' => 'Local',
            'slug' => 'local-saas',
            'api_base_url' => 'http://127.0.0.1:8000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opgId = DB::table('applications')->insertGetId([
            'name' => 'OPG SaaS',
            'slug' => 'opg-saas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tenants')->insert([
            'application_id' => $opgId,
            'external_id' => 'opg-1',
            'name' => 'OPG test',
            'slug' => 'opg-test',
            'status' => 'active',
            'plan' => 'basic',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_08_100000_rename_product_applications.php');
        $migration->up();

        $this->assertSame('SuperSkyClub', DB::table('applications')->where('id', $clubId)->value('name'));
        $this->assertSame('https://club.superskytech.com', DB::table('applications')->where('id', $clubId)->value('api_base_url'));
        $this->assertSame('SuperSkyCrew', DB::table('applications')->where('id', $crewId)->value('name'));
        $this->assertSame('https://crew.superskytech.com', DB::table('applications')->where('id', $crewId)->value('api_base_url'));
        $this->assertSame('http://127.0.0.1:8000', DB::table('applications')->where('slug', 'local-saas')->value('api_base_url'));
        $this->assertNull(DB::table('applications')->where('slug', 'opg-saas')->value('id'));
        $this->assertSame(0, DB::table('tenants')->where('slug', 'opg-test')->count());
    }
}
