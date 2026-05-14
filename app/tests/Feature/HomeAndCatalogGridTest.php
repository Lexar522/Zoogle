<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeAndCatalogGridTest extends TestCase
{
    public function test_catalog_without_filters_shows_prompt_instead_of_product_grid(): void
    {
        $response = $this->get(route('catalog.index'));

        $response->assertOk();
        $response->assertViewHas('showCatalogGrid', false);
        $response->assertSee('Оберіть категорію в меню вище', false);
    }
}
