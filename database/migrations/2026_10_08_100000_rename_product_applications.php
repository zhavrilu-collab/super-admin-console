<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'udruga-saas' => 'SuperSkyClub',
            'hr-saas' => 'SuperSkyCrew',
            'legal-saas' => 'SuperSkyLaw',
        ] as $slug => $name) {
            DB::table('applications')->where('slug', $slug)->update([
                'name' => $name,
                'updated_at' => now(),
            ]);
        }

        foreach ([
            'https://app.superskytech.com' => 'https://club.superskytech.com',
            'http://app.superskytech.com' => 'https://club.superskytech.com',
            'https://hr.superskytech.com' => 'https://crew.superskytech.com',
            'http://hr.superskytech.com' => 'https://crew.superskytech.com',
        ] as $from => $to) {
            DB::table('applications')->where('api_base_url', $from)->update([
                'api_base_url' => $to,
                'updated_at' => now(),
            ]);
            DB::table('applications')->where('api_base_url', $from.'/')->update([
                'api_base_url' => $to,
                'updated_at' => now(),
            ]);
        }

        $opgId = DB::table('applications')->where('slug', 'opg-saas')->value('id');

        if ($opgId === null) {
            return;
        }

        DB::table('tenants')->where('application_id', $opgId)->delete();
        DB::table('applications')->where('id', $opgId)->delete();
    }

    public function down(): void
    {
    }
};
