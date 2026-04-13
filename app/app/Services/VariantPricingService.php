<?php

namespace App\Services;

use App\Enums\PromotionDiscountMode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromotionTarget;
use App\Support\Pricing\VariantPriceQuote;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VariantPricingService
{
    public function quoteProductVariant(ProductVariant $variant, ?\DateTimeInterface $at = null): VariantPriceQuote
    {
        $at ??= now();

        $quote = $this->quoteFromRegularAndCompare(
            regular: round((float) $variant->price, 2),
            targets: $this->loadTargetsForProductVariantIds([(int) $variant->id], $at)->get((int) $variant->id, collect()),
            at: $at,
        );

        if ($variant->compare_at_price === null) {
            return $quote;
        }

        $compareAt = round((float) $variant->compare_at_price, 2);
        if ($compareAt <= $quote->effectivePrice + 0.0001) {
            return $quote;
        }

        $strike = max($quote->strikePrice ?? $quote->regularPrice, $compareAt);

        return new VariantPriceQuote(
            regularPrice: $quote->regularPrice,
            effectivePrice: $quote->effectivePrice,
            strikePrice: round($strike, 2),
        );
    }

    public function quoteProduct(Product $listing, ?\DateTimeInterface $at = null): VariantPriceQuote
    {
        $at ??= now();
        $id = (int) $listing->id;

        return $this->quoteFromRegularAndCompare(
            regular: round((float) ($listing->price ?? 0), 2),
            targets: $this->loadTargetsForProductIds([$id], $at)->get($id, collect()),
            at: $at,
        );
    }

    /**
     * @return array<int, VariantPriceQuote> keyed by listing id
     */
    public function quoteManyProducts(Collection $listings, ?\DateTimeInterface $at = null): array
    {
        $at ??= now();
        $ids = $listings->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $grouped = $this->loadTargetsForProductIds($ids, $at);
        $out = [];
        foreach ($listings as $l) {
            $id = (int) $l->id;
            $out[$id] = $this->quoteFromRegularAndCompare(
                regular: round((float) ($l->price ?? 0), 2),
                targets: $grouped->get($id, collect()),
                at: $at,
            );
        }

        return $out;
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<int, VariantPriceQuote>
     */
    public function quoteManyProductVariants(Collection $variants, ?\DateTimeInterface $at = null): array
    {
        $at ??= now();
        $ids = $variants->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $grouped = $this->loadTargetsForProductVariantIds($ids, $at);
        $out = [];
        foreach ($variants as $v) {
            $vid = (int) $v->id;
            $out[$vid] = $this->quoteFromRegularAndCompare(
                regular: round((float) $v->price, 2),
                targets: $grouped->get($vid, collect()),
                at: $at,
            );
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Collection<int, PromotionTarget>>
     */
    private function loadTargetsForProductVariantIds(array $ids, \DateTimeInterface $at): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $moment = Carbon::parse($at);

        return PromotionTarget::query()
            ->where('target_type', 'product_variant')
            ->whereIn('target_id', $ids)
            ->where(function ($q) use ($moment): void {
                $q->whereNull('promotion_targets.ends_at')
                    ->orWhere('promotion_targets.ends_at', '>=', $moment);
            })
            ->whereHas('promotion', fn ($q) => $q->activeAt($at))
            ->with('promotion')
            ->get()
            ->groupBy(fn (PromotionTarget $t) => (int) $t->target_id);
    }

    /**
     * @param  list<int>  $ids  products.id
     * @return Collection<int, Collection<int, PromotionTarget>>
     */
    private function loadTargetsForProductIds(array $ids, \DateTimeInterface $at): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $moment = Carbon::parse($at);

        return PromotionTarget::query()
            ->where('target_type', 'product')
            ->whereIn('target_id', $ids)
            ->where(function ($q) use ($moment): void {
                $q->whereNull('promotion_targets.ends_at')
                    ->orWhere('promotion_targets.ends_at', '>=', $moment);
            })
            ->whereHas('promotion', fn ($q) => $q->activeAt($at))
            ->with('promotion')
            ->get()
            ->groupBy(fn (PromotionTarget $t) => (int) $t->target_id);
    }

    /**
     * Застосувати набір promotion_targets до суми (наприклад, підсумок комплекту).
     *
     * @param  Collection<int, PromotionTarget>  $targets
     */
    public function quotePromotionTargetsOnAmount(
        float $regular,
        Collection $targets,
        ?\DateTimeInterface $at = null,
    ): VariantPriceQuote {
        $at ??= now();

        return $this->quoteFromRegularAndCompare($regular, $targets, $at);
    }

    /**
     * @param  Collection<int, PromotionTarget>  $targets
     */
    private function quoteFromRegularAndCompare(
        float $regular,
        Collection $targets,
        \DateTimeInterface $at,
    ): VariantPriceQuote {
        $effective = $regular;
        $winningPriority = \PHP_INT_MIN;

        $moment = Carbon::parse($at);

        foreach ($targets as $target) {
            if (! $target->promotion || ! $target->promotion->appliesAt($at)) {
                continue;
            }

            if ($target->ends_at && Carbon::parse($target->ends_at)->lt($moment)) {
                continue;
            }

            $candidate = $this->applyPromotionToRegular($regular, $target);
            $priority = (int) $target->promotion->priority;

            if ($candidate < $effective - 0.0001) {
                $effective = $candidate;
                $winningPriority = $priority;
            } elseif (abs($candidate - $effective) < 0.0001 && $priority > $winningPriority) {
                $winningPriority = $priority;
            }
        }

        $effective = round(max(0, $effective), 2);
        $strike = $this->resolveStrikePrice($regular, $effective);

        return new VariantPriceQuote(
            regularPrice: $regular,
            effectivePrice: $effective,
            strikePrice: $strike,
        );
    }

    private function applyPromotionToRegular(float $regular, PromotionTarget $target): float
    {
        $value = (float) $target->discount_value;
        $raw = match ($target->discount_mode) {
            PromotionDiscountMode::Percent => $regular * (1 - min(100, max(0, $value)) / 100),
            PromotionDiscountMode::AmountOff => max(0, $regular - $value),
            PromotionDiscountMode::FixedPrice => max(0, $value),
        };

        return round(min($regular, $raw), 2);
    }

    private function resolveStrikePrice(float $regular, float $effective): ?float
    {
        return $regular > $effective + 0.0001 ? round($regular, 2) : null;
    }

    /**
     * Підзапит для whereExists: активна акція на варіант (morph-ключ з morphMap).
     *
     * @param  string  $idColumnSql  напр. product_variants.id
     */
    public static function bindActivePromotionExists(QueryBuilder $sub, string $morphKey, string $idColumnSql): void
    {
        $now = now();
        $sub->selectRaw('1')
            ->from('promotion_targets')
            ->join('promotions', 'promotions.id', '=', 'promotion_targets.promotion_id')
            ->whereColumn('promotion_targets.target_id', $idColumnSql)
            ->where('promotion_targets.target_type', '=', $morphKey)
            ->where('promotions.is_active', true)
            ->where(function ($w) use ($now): void {
                $w->whereNull('promotions.starts_at')
                    ->orWhere('promotions.starts_at', '<=', $now);
            })
            ->where(function ($w) use ($now): void {
                $w->whereNull('promotions.ends_at')
                    ->orWhere('promotions.ends_at', '>=', $now);
            })
            ->where(function ($w) use ($now): void {
                $w->whereNull('promotion_targets.ends_at')
                    ->orWhere('promotion_targets.ends_at', '>=', $now);
            });
    }
}
