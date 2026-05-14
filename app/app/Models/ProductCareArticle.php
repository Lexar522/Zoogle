<?php

namespace App\Models;

use App\Support\RichTextSanitizer;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Throwable;

class ProductCareArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'sort_order',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'sort_order' => 'integer',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $article): void {
            $article->slug = static::uniqueSlugFor($article);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $nested): void {
                $nested
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function safeBodyHtml(): string
    {
        return RichTextSanitizer::toCareArticleHtml($this->body);
    }

    public function hasDisplayableBody(): bool
    {
        return RichTextSanitizer::hasVisibleContent($this->body);
    }

    public static function normalizeRichEditorValueForStorage(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            if ($value === []) {
                return null;
            }

            try {
                $html = trim(RichContentRenderer::make($value)->toUnsafeHtml());
            } catch (Throwable) {
                return null;
            }

            return $html === '' ? null : $html;
        }

        return RichTextSanitizer::normalizeNullableStoredHtml($value);
    }

    private static function uniqueSlugFor(self $article): string
    {
        $base = Str::slug((string) ($article->slug ?: $article->title));
        if ($base === '') {
            $base = 'care-article';
        }

        $slug = $base;
        $i = 2;

        while (
            static::query()
                ->where('product_id', $article->product_id)
                ->where('slug', $slug)
                ->when($article->exists, fn (Builder $query): Builder => $query->whereKeyNot($article->getKey()))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
