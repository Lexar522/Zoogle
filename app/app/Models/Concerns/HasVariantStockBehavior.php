<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Єдина логіка «Закінчується» / наявність для варіантів товару.
 */
trait HasVariantStockBehavior
{
    protected static function bootHasVariantStockBehavior(): void
    {
        static::saving(function ($model): void {
            if ($model->allows_preorder) {
                return;
            }

            // «Закінчується» завжди продається як наявний зарезервований стан
            if ($model->is_low_stock) {
                $model->is_available = true;
            }

            // Якщо з адмінки не передали видимість — показуємо на вітрині
            if ($model->is_visible === null) {
                $model->is_visible = true;
            }

            if ($model->getTable() === 'product_variants' && $model->is_sold === null) {
                $model->is_sold = false;
            }
        });
    }

    /**
     * Варіанти, які мають потрапляти в каталог / на сторінку товару.
     * NULL у is_visible трактуємо як «показувати» (старі записи без поля у формі).
     */
    public function scopeVisibleOnStorefront(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where('is_visible', true)->orWhereNull('is_visible');
        });

        if ($query->getModel()->getTable() === 'product_variants') {
            $query->where(function (Builder $q): void {
                $q->where('is_sold', false)->orWhereNull('is_sold');
            });
        }
    }

    public function isVisibleOnStorefront(): bool
    {
        if ($this->getTable() === 'product_variants' && ($this->is_sold ?? false)) {
            return false;
        }

        return $this->is_visible !== false;
    }

    /**
     * Чи можна купити / показувати як доступний до покупки (не «немає в наявності»).
     */
    public function isSellable(): bool
    {
        return $this->allows_preorder
            || (bool) ($this->is_low_stock ?? false)
            || (bool) ($this->is_available ?? false);
    }
}
