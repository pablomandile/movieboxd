<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_expired_entries()
    {
        config(['cache.default' => 'database']);

        DB::table('cache')->insert([
            ['key' => 'vencida', 'value' => 'x', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'vigente', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()],
            ['key' => 'forever', 'value' => 'x', 'expiration' => 9999999999],
        ]);

        $this->artisan('movieboxd:prune-cache')->assertSuccessful();

        $this->assertEqualsCanonicalizing(['vigente', 'forever'], DB::table('cache')->pluck('key')->all());
    }

    public function test_it_does_nothing_when_the_store_is_not_database()
    {
        config(['cache.default' => 'array']);

        DB::table('cache')->insert(['key' => 'vencida', 'value' => 'x', 'expiration' => 1]);

        $this->artisan('movieboxd:prune-cache')->assertSuccessful();

        $this->assertSame(1, DB::table('cache')->count());
    }
}
