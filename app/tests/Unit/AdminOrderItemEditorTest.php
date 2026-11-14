<?php

namespace Tests\Unit;

use App\Services\AdminOrderItemEditor;
use Tests\TestCase;

class AdminOrderItemEditorTest extends TestCase
{
    public function test_flatten_option_picks_sorts_unique_ids(): void
    {
        $editor = app(AdminOrderItemEditor::class);

        $this->assertSame(
            [2, 5],
            $editor->flattenOptionPicks(['10' => 5, '11' => [2, 5]])
        );
    }
}
