<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductRecommendationsService
{
    /** Макс. кандидатів за глобальним збігом (теги в JSON / шаблон по назві). */
    private const GLOBAL_MATCH_LIMIT = 200;

    /**
     * Підбір з урахуванням **спільних тегів** (однакові після нормалізації) та того, що теги можуть
     * збігатися з **назвою** іншого товару: тег ↔ тег, слово назви ↔ слова назви, тег ↔ підрядок у назві.
     *
     * @return Collection<int, Product>
     */
    public function forProductPage(Product $current, int $limit = 8): Collection
    {
        $limit = max(1, $limit);

        $currentSignals = $this->signalSetForProduct($current);

        if ($this->isSignalSetEmpty($currentSignals)) {
            return $this->rankByMatchScoreAndDate(
                $this->candidatePoolQuery($current)->withCount('favoritedByUsers')->get(),
                $current,
                $limit
            );
        }

        $literalTags = $this->rawTrimmedTags($current->search_tags ?? []);
        $titleWordsLower = array_keys($this->titleWordSet($current->title));
        $normalizedTagKeys = array_keys($this->normalizedTagSetKeyIndex($current->search_tags ?? []));

        /** За назвою шукаємо і значущі слова заголовка, і рядки з тегів (тег як частина назви). */
        $titleLikeFragments = array_values(array_unique(array_merge(
            $titleWordsLower,
            $normalizedTagKeys,
        )));

        $fromGlobal = collect();
        $hasTagConditions = $literalTags !== [];
        $hasTitleConditions = $titleLikeFragments !== [];

        if ($hasTagConditions || $hasTitleConditions) {
            $fromGlobal = $this->baseCatalogQuery($current)
                ->where(function (Builder $q) use (
                    $literalTags,
                    $normalizedTagKeys,
                    $titleLikeFragments,
                    $hasTagConditions,
                    $hasTitleConditions,
                    $current
                ): void {
                    if ($hasTagConditions) {
                        foreach ($literalTags as $tag) {
                            $q->orWhereJsonContains('search_tags', $tag);
                            $lower = mb_strtolower($tag, 'UTF-8');
                            if ($lower !== $tag) {
                                $q->orWhereJsonContains('search_tags', $lower);
                            }
                            $upper = mb_strtoupper($tag, 'UTF-8');
                            if ($upper !== $tag && $upper !== $lower) {
                                $q->orWhereJsonContains('search_tags', $upper);
                            }
                        }
                    }

                    foreach ($normalizedTagKeys as $normTag) {
                        if ($normTag === '') {
                            continue;
                        }
                        $q->orWhereJsonContains('search_tags', $normTag);
                    }

                    if ($hasTitleConditions) {
                        $table = $current->getTable();
                        foreach ($titleLikeFragments as $word) {
                            if (mb_strlen($word, 'UTF-8') < 2) {
                                continue;
                            }
                            $q->orWhereRaw(
                                'LOWER(`'.$table.'`.`title`) LIKE ?',
                                ['%'.$this->likeLiteral($word).'%']
                            );
                        }
                    }
                })
                ->withCount('favoritedByUsers')
                ->limit(self::GLOBAL_MATCH_LIMIT)
                ->get();
        }

        $pickedIds = $fromGlobal->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $categoryQuery = $this->candidatePoolQuery($current)->withCount('favoritedByUsers');
        if ($pickedIds !== []) {
            $categoryQuery->whereNotIn('id', $pickedIds);
        }
        $fromCategory = $categoryQuery->get();

        $candidates = $fromGlobal->merge($fromCategory);

        if ($candidates->isEmpty()) {
            return collect();
        }

        return $this->rankByMatchScoreAndDate($candidates, $current, $limit);
    }

    /**
     * Теги (lower) ∪ значущі слова назви (lower) — лише щоб зрозуміти, чи є що порівнювати.
     *
     * @return array<string, true>
     */
    private function signalSetForProduct(Product $p): array
    {
        $tags = $this->normalizedTagSetKeyIndex($p->search_tags ?? []);
        $words = $this->titleWordSet($p->title);

        foreach ($words as $k => $_) {
            $tags[$k] = true;
        }

        return $tags;
    }

    /**
     * @param  array<string, true>  $set
     */
    private function isSignalSetEmpty(array $set): bool
    {
        return $set === [];
    }

    /**
     * Значущі слова з назви; порівняння без урахування регістру.
     *
     * @return array<string, true>
     */
    private function titleWordSet(?string $title): array
    {
        $title = trim((string) $title);
        if ($title === '') {
            return [];
        }

        $lower = mb_strtolower($title, 'UTF-8');
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $out = [];
        foreach ($parts as $p) {
            $len = mb_strlen($p, 'UTF-8');
            if ($len >= 3 || ($len >= 2 && ctype_digit($p))) {
                $out[$p] = true;
            }
        }

        return $out;
    }

    /**
     * @return Collection<int, Product>
     */
    private function rankByMatchScoreAndDate(Collection $candidates, Product $current, int $limit): Collection
    {
        $scored = $candidates->map(function (Product $p) use ($current) {
            $sharedTags = $this->sharedNormalizedTagsCount($current, $p);
            $score = $this->matchScoreBetween($current, $p);

            return [
                'product' => $p,
                'sharedTags' => $sharedTags,
                'score' => $score,
                'sortDate' => $p->published_at ?? $p->created_at,
            ];
        });

        $ordered = $scored->sort(function (array $a, array $b): int {
            if ($a['sharedTags'] !== $b['sharedTags']) {
                return $b['sharedTags'] <=> $a['sharedTags'];
            }
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            $ta = $a['sortDate']?->getTimestamp() ?? 0;
            $tb = $b['sortDate']?->getTimestamp() ?? 0;

            return $tb <=> $ta;
        })->values();

        return $ordered->pluck('product')->take($limit)->values();
    }

    /** Скільки **однакових** тегів (після mb_strtolower) у обох товарів. */
    private function sharedNormalizedTagsCount(Product $a, Product $b): int
    {
        $aTags = $this->normalizedTagSetKeyIndex($a->search_tags ?? []);
        $bTags = $this->normalizedTagSetKeyIndex($b->search_tags ?? []);
        if ($aTags === [] || $bTags === []) {
            return 0;
        }

        $n = 0;
        foreach ($aTags as $k => $_) {
            if (isset($bTags[$k])) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Підрахунок збігів: тег–тег, слово назви–слова назви, **тег ↔ підрядок/слова в назві** іншого товару.
     * Кожен ключ (нормалізований рядок) лічиться не більше одного разу.
     */
    private function matchScoreBetween(Product $current, Product $candidate): int
    {
        $aTags = $this->normalizedTagSetKeyIndex($current->search_tags ?? []);
        $bTags = $this->normalizedTagSetKeyIndex($candidate->search_tags ?? []);
        $aTit = $this->titleWordSet($current->title);
        $bTit = $this->titleWordSet($candidate->title);

        $matched = [];

        foreach ($aTags as $k => $_) {
            if (isset($bTags[$k])) {
                $matched[$k] = true;
            }
            if (isset($bTit[$k])) {
                $matched[$k] = true;
            }
            if ($this->normalizedNeedleInText($candidate->title ?? '', $k)) {
                $matched[$k] = true;
            }
        }

        foreach ($bTags as $k => $_) {
            if (isset($aTit[$k])) {
                $matched[$k] = true;
            }
            if ($this->normalizedNeedleInText($current->title ?? '', $k)) {
                $matched[$k] = true;
            }
        }

        foreach ($aTit as $k => $_) {
            if (isset($bTit[$k])) {
                $matched[$k] = true;
            }
        }

        return count($matched);
    }

    /** Підрядок у тексті (обидва в нижньому регістрі). */
    private function normalizedNeedleInText(string $text, string $normalizedNeedle): bool
    {
        if ($normalizedNeedle === '' || mb_strlen($normalizedNeedle, 'UTF-8') < 2) {
            return false;
        }

        return mb_strpos(mb_strtolower($text, 'UTF-8'), $normalizedNeedle) !== false;
    }

    private function candidatePoolQuery(Product $current): Builder
    {
        $q = $this->baseCatalogQuery($current);
        $this->applyCategoryBranchScope($q, $current);

        return $q;
    }

    private function baseCatalogQuery(Product $current): Builder
    {
        return Product::query()
            ->whereKeyNot((int) $current->id)
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true);
    }

    private function applyCategoryBranchScope(Builder $q, Product $current): void
    {
        $leaf = (int) ($current->category_value_id ?? 0);
        $parent = (int) ($current->category_parent_value_id ?? 0);

        if ($leaf <= 0 && $parent <= 0) {
            return;
        }

        $q->where(function (Builder $inner) use ($leaf, $parent): void {
            if ($leaf > 0) {
                $inner->where('category_value_id', $leaf);
                if ($parent > 0) {
                    $inner->orWhere('category_parent_value_id', $parent);
                }
            } else {
                $inner->where('category_parent_value_id', $parent)
                    ->orWhere('category_value_id', $parent);
            }
        });
    }

    /**
     * @param  list<string>|null  $raw
     * @return list<string>
     */
    private function rawTrimmedTags(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        $out = [];
        foreach ($raw as $t) {
            $s = trim((string) $t);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $raw
     * @return array<string, true>
     */
    private function normalizedTagSetKeyIndex(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        $out = [];
        foreach ($raw as $t) {
            $s = mb_strtolower(trim((string) $t), 'UTF-8');
            if ($s !== '') {
                $out[$s] = true;
            }
        }

        return $out;
    }

    private function likeLiteral(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
