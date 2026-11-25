<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Внизу головної видно версію сайту й дату останньої заливки — щоб одразу бачити, чи оновлення доїхало на хостинг.
 */
class SiteReleaseFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_footer_shows_version_and_deploy_date(): void
    {
        $this->assertFileDoesNotExist(base_path('bootstrap/cache/config.php'), 'Локально конфіг не кешовано: дата береться з VERSION');
        $version = trim((string) file_get_contents(base_path('VERSION')));
        $deployedOn = date('d.m.Y', (int) filemtime(base_path('VERSION')));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Версія '.$version.' · оновлено '.$deployedOn);
    }

    public function test_other_pages_do_not_show_version(): void
    {
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('оновлено', false);
    }
}
