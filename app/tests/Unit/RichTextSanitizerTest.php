<?php

namespace Tests\Unit;

use App\Support\RichTextSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RichTextSanitizerTest extends TestCase
{
    #[Test]
    public function plain_text_is_preserved_with_line_breaks(): void
    {
        $html = RichTextSanitizer::toHtml("перший\nдругий");

        $this->assertStringContainsString('перший', $html);
        $this->assertMatchesRegularExpression('/<br\s*\/?>/i', $html);
    }

    #[Test]
    public function less_than_without_html_tag_is_escaped(): void
    {
        $html = RichTextSanitizer::toHtml('5 < 10');

        $this->assertStringContainsString('&lt;', $html);
        $this->assertStringNotContainsString('<10', $html);
    }

    #[Test]
    public function angle_bracket_without_tag_stays_plain(): void
    {
        $html = RichTextSanitizer::toHtml('<3 серця');

        $this->assertStringContainsString('&lt;3', $html);
    }

    #[Test]
    public function rich_html_is_sanitized_and_keeps_safe_markup(): void
    {
        $html = RichTextSanitizer::toHtml('<p><strong>Жирний</strong></p>');

        $this->assertStringContainsString('<strong>Жирний</strong>', $html);
    }

    #[Test]
    public function relative_links_are_preserved(): void
    {
        $html = RichTextSanitizer::toHtml('<p><a href="/catalog">Каталог</a></p>');

        $this->assertStringContainsString('href="/catalog"', $html);
    }

    #[Test]
    public function script_tags_are_stripped(): void
    {
        $html = RichTextSanitizer::toHtml('<p>Hi</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', strtolower($html));
    }

    #[Test]
    public function has_visible_content_detects_empty_rich_text(): void
    {
        $this->assertFalse(RichTextSanitizer::hasVisibleContent(null));
        $this->assertFalse(RichTextSanitizer::hasVisibleContent(''));
        $this->assertFalse(RichTextSanitizer::hasVisibleContent('<p></p>'));
        $this->assertFalse(RichTextSanitizer::hasVisibleContent('<p><br></p>'));
        $this->assertTrue(RichTextSanitizer::hasVisibleContent('<p>Текст</p>'));
        $this->assertTrue(RichTextSanitizer::hasVisibleContent('Просто текст'));
    }

    #[Test]
    public function normalize_nullable_stored_html_keeps_meaningful_markup(): void
    {
        $html = '<p><strong>Текст</strong></p>';

        $this->assertSame($html, RichTextSanitizer::normalizeNullableStoredHtml($html));
    }

    #[Test]
    public function normalize_nullable_stored_html_trims_only_preserves_markup(): void
    {
        $this->assertSame('<p></p>', RichTextSanitizer::normalizeNullableStoredHtml('<p></p>'));
        $this->assertNull(RichTextSanitizer::normalizeNullableStoredHtml([]));
        $this->assertNull(RichTextSanitizer::normalizeNullableStoredHtml('   '));
    }

    #[Test]
    public function coerce_wraps_json_scalar_strings_for_tiptap(): void
    {
        $this->assertSame('<p>123</p>', RichTextSanitizer::coerceForFilamentRichEditor('123'));
        $this->assertSame('<p>true</p>', RichTextSanitizer::coerceForFilamentRichEditor('true'));
    }

    #[Test]
    public function coerce_passes_through_valid_tiptap_doc_json_string(): void
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Hi"}]}]}';

        $this->assertSame($json, RichTextSanitizer::coerceForFilamentRichEditor($json));
    }

    #[Test]
    public function coerce_passes_through_html(): void
    {
        $html = '<p><strong>X</strong></p>';

        $this->assertSame($html, RichTextSanitizer::coerceForFilamentRichEditor($html));
    }
}
