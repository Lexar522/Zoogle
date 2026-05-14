<?php

namespace App\Filament\Admin\Concerns;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use App\Support\RichTextSanitizer;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HandlesListingVariantOptions
{
    /**
     * Товар (listing), з якого підтягуються варіанти для merge опцій.
     * На сторінках Create/Edit resource це getRecord(); getOwnerRecord() лише у RelationManager.
     */
    protected function listingForVariantOptionsMerge(): ?Product
    {
        if (! method_exists($this, 'getRecord')) {
            return null;
        }

        $record = $this->getRecord();

        return $record instanceof Product ? $record : null;
    }

    /**
     * Підготовка рядків repeater для форми: у БД лише option_group_id + option_value_ids,
     * без option_value_type та new_values — без цього Filament не відновлює стан після перезавантаження.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function enrichVariantOptionsRowsForForm(array $rows): array
    {
        return array_values(array_map(function (array $row): array {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid > 0) {
                $vt = OptionGroup::query()->whereKey($gid)->value('value_type');
                $row['option_value_type'] = in_array($vt, ['text', 'color'], true) ? $vt : 'text';
            } else {
                $row['option_value_type'] = $row['option_value_type'] ?? 'text';
            }

            if (! isset($row['new_values']) || ! is_array($row['new_values'])) {
                $row['new_values'] = [];
            }

            $row['option_value_ids'] = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            $row['value_photos'] = collect($row['value_photos'] ?? [])
                ->map(function ($entry): array {
                    return [
                        'option_value_id' => (int) ($entry['option_value_id'] ?? 0),
                        'photos' => collect($entry['photos'] ?? [])
                            ->map(fn ($path) => is_string($path) ? trim($path) : '')
                            ->filter(fn (string $path): bool => $path !== '')
                            ->values()
                            ->all(),
                    ];
                })
                ->filter(fn (array $entry): bool => $entry['option_value_id'] > 0 && $entry['photos'] !== [])
                ->values()
                ->all();

            $row['value_prices'] = $this->valuePricesMapToRepeater($row['value_prices'] ?? []);

            return $row;
        }, $rows));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeProductRichTextFields(array $data): array
    {
        if (array_key_exists('description', $data)) {
            $data['description'] = $this->normalizeRichEditorValueForDatabase($data['description']);
        }

        if (array_key_exists('short_description', $data)) {
            $data['short_description'] = $this->normalizeRichEditorValueForDatabase($data['short_description']);
        }

        if (array_key_exists('careArticles', $data) && is_array($data['careArticles'])) {
            foreach ($data['careArticles'] as $key => $row) {
                if (is_array($row) && array_key_exists('body', $row)) {
                    $row['body'] = ProductCareArticle::normalizeRichEditorValueForStorage($row['body']);
                    $data['careArticles'][$key] = $row;
                }
            }
        }

        return $data;
    }

    /**
     * Livewire часто передає Tiptap як array; RichEditorStateCast мав би дати HTML, але на save інколи лишається JSON.
     */
    protected function normalizeRichEditorValueForDatabase(mixed $value): ?string
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

    /**
     * Під Filament RichEditor: у БД могли бути числа/JSON, які tiptap-php сприймає як JSON і падає.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function coerceProductRichTextFieldsForFill(array $data): array
    {
        foreach (['description', 'short_description'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            $raw = $data[$key];
            if (is_scalar($raw) || $raw instanceof \Stringable) {
                $data[$key] = RichTextSanitizer::coerceForFilamentRichEditor((string) $raw);
            }
        }

        if (array_key_exists('careArticles', $data) && is_array($data['careArticles'])) {
            foreach ($data['careArticles'] as $key => $row) {
                if (! is_array($row) || ! array_key_exists('body', $row) || $row['body'] === null) {
                    continue;
                }

                $raw = $row['body'];
                if (is_scalar($raw) || $raw instanceof \Stringable) {
                    $row['body'] = RichTextSanitizer::coerceForFilamentRichEditor((string) $raw);
                    $data['careArticles'][$key] = $row;
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeVariantOptions(array $data): array
    {
        $rows = $data['variant_options'] ?? [];

        if (! is_array($rows)) {
            $data['variant_options'] = [];

            return $data;
        }

        $normalized = [];

        foreach ($rows as $row) {
            $groupId = (int) ($row['option_group_id'] ?? 0);

            if ($groupId <= 0) {
                continue;
            }

            $group = OptionGroup::query()->whereKey($groupId)->first();

            if (! $group) {
                continue;
            }

            if ($group->slug === 'category') {
                $selectedValueIds = collect($row['option_value_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->values()
                    ->all();

                if (($group->selection_mode ?? 'single') === 'single' && ! empty($selectedValueIds)) {
                    $selectedValueIds = [reset($selectedValueIds)];
                }

                if (! empty($selectedValueIds)) {
                    $normalized[] = [
                        'option_group_id' => $groupId,
                        'option_value_ids' => $selectedValueIds,
                        'value_photos' => [],
                        'value_prices' => [],
                    ];
                }

                continue;
            }

            $selectedValueIds = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            $newValues = $row['new_values'] ?? [];
            $newValuePriceByCreatedId = [];

            if (is_array($newValues)) {
                foreach ($newValues as $newValue) {
                    $name = trim((string) ($newValue['name'] ?? ''));

                    if ($name === '') {
                        continue;
                    }

                    $existing = OptionValue::query()
                        ->where('option_group_id', $groupId)
                        ->where('name', $name)
                        ->first();

                    if ($existing) {
                        $selectedValueIds[] = (int) $existing->id;

                        continue;
                    }

                    $baseSlug = Str::slug($name);
                    $slug = $baseSlug !== '' ? $baseSlug : 'value';
                    $suffix = 1;

                    while (OptionValue::query()->where('option_group_id', $groupId)->where('slug', $slug)->exists()) {
                        $suffix++;
                        $slug = "{$baseSlug}-{$suffix}";
                    }

                    $maxSortOrder = (int) OptionValue::query()
                        ->where('option_group_id', $groupId)
                        ->max('sort_order');

                    $created = OptionValue::create([
                        'option_group_id' => $groupId,
                        'name' => $name,
                        'price' => null,
                        'color_hex' => filled($newValue['color_hex'] ?? null) ? (string) $newValue['color_hex'] : null,
                        'slug' => $slug,
                        'sort_order' => $maxSortOrder + 1,
                        'is_active' => true,
                    ]);

                    $selectedValueIds[] = (int) $created->id;
                    if (filled($newValue['price'] ?? null) && (float) $newValue['price'] > 0) {
                        $newValuePriceByCreatedId[(string) $created->id] = round((float) $newValue['price'], 2);
                    }
                    if (! empty($newValue['photos'] ?? [])) {
                        $row['value_photos'] = array_values(array_merge(
                            is_array($row['value_photos'] ?? null) ? $row['value_photos'] : [],
                            [[
                                'option_value_id' => (int) $created->id,
                                'photos' => collect($newValue['photos'] ?? [])
                                    ->map(fn ($path) => is_string($path) ? trim($path) : '')
                                    ->filter(fn (string $path): bool => $path !== '')
                                    ->values()
                                    ->all(),
                            ]]
                        ));
                    }
                }
            }

            $selectedValueIds = collect($selectedValueIds)
                ->unique()
                ->values()
                ->all();

            if ($group->slug !== 'category' && $selectedValueIds === []) {
                throw ValidationException::withMessages([
                    'variant_options' => 'У кожній доданій опції товару має бути вибране хоча б одне значення.',
                ]);
            }

            // Не скорочувати до одного id: у картці товару зберігаються усі відмічені «доступні»
            // значення групи. Режим single/multiple групи стосується вибору покупцем на вітрині,
            // а не того, скільки значень можна задати в оголошенні.

            // Порожній список теж зберігаємо — інакше група зникає з JSON і не з’являється у варіантах,
            // поки не відмітиш значення в картці.
            $valuePrices = $this->normalizeValuePricesRepeaterToMap($row['value_prices'] ?? [], $selectedValueIds);
            foreach ($newValuePriceByCreatedId as $k => $v) {
                $valuePrices[$k] = $v;
            }

            $normalized[] = [
                'option_group_id' => $groupId,
                'option_value_ids' => $selectedValueIds,
                'value_photos' => $this->normalizeValuePhotosBySelectedIds($row['value_photos'] ?? [], $selectedValueIds),
                'value_prices' => $valuePrices === [] ? [] : $valuePrices,
            ];
        }

        $normalized = $this->collapseVariantOptionRowsByGroupPreserveOrder($normalized);
        $normalized = $this->mergeVariantUsedOptionsIntoListingRows($normalized);

        // System group "category" must always exist in listing options.
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $categoryGroup = $categoryGroupId > 0
            ? OptionGroup::query()->whereKey($categoryGroupId)->first()
            : null;

        if ($categoryGroup) {
            $hasCategory = collect($normalized)->contains(
                fn (array $row): bool => (int) ($row['option_group_id'] ?? 0) === (int) $categoryGroup->id
            );

            // Do not override selected category from form.
            // If category is missing at all, add a sensible default once.
            if (! $hasCategory) {
                $fallbackCategoryValueId = OptionValue::query()
                    ->where('option_group_id', $categoryGroup->id)
                    ->where('is_active', true)
                    ->orderByRaw('case when slug = ? then 0 else 1 end', [OptionGroup::CATALOG_PRODUCT_TYPE])
                    ->orderBy('sort_order')
                    ->value('id');

                if ($fallbackCategoryValueId) {
                    array_unshift($normalized, [
                        'option_group_id' => (int) $categoryGroup->id,
                        'option_value_ids' => [(int) $fallbackCategoryValueId],
                        'value_prices' => [],
                    ]);
                }
            }
        }

        $data['variant_options'] = $normalized;

        return $data;
    }

    /**
     * Один рядок на групу (крім «категорії»), порядок груп — як перша поява в repeater.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function collapseVariantOptionRowsByGroupPreserveOrder(array $rows): array
    {
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();

        $categoryMergedIds = [];
        $order = [];
        $mergedIds = [];

        foreach ($rows as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }

            if ($categoryGroupId > 0 && $gid === $categoryGroupId) {
                $ids = collect($row['option_value_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->values()
                    ->all();
                $categoryMergedIds = array_values(array_unique(array_merge($categoryMergedIds, $ids)));

                continue;
            }

            if (! isset($order[$gid])) {
                $order[$gid] = count($order);
            }

            $ids = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            $mergedIds[$gid] = array_values(array_unique(array_merge($mergedIds[$gid] ?? [], $ids)));
        }

        $categoryRows = [];
        if ($categoryGroupId > 0 && $categoryMergedIds !== []) {
            $categoryGroup = OptionGroup::query()->whereKey($categoryGroupId)->first();
            if ($categoryGroup && ($categoryGroup->selection_mode ?? 'single') === 'single') {
                $categoryMergedIds = [reset($categoryMergedIds)];
            }
            $categoryRows[] = [
                'option_group_id' => $categoryGroupId,
                'option_value_ids' => $categoryMergedIds,
                'value_photos' => [],
                'value_prices' => [],
            ];
        }

        if ($order === []) {
            return $categoryRows;
        }

        asort($order);

        $out = $categoryRows;
        foreach (array_keys($order) as $gid) {
            $mergedValueIds = $mergedIds[$gid] ?? [];
            $out[] = [
                'option_group_id' => $gid,
                'option_value_ids' => $mergedValueIds,
                'value_photos' => $this->mergeValuePhotoRows($rows, $gid, $mergedValueIds),
                'value_prices' => $this->mergeValuePriceRows($rows, $gid, $mergedValueIds),
            ];
        }

        return $out;
    }

    /**
     * Підтягує в картку товару всі групи/значення, які реально використовуються у варіантах.
     * Це дозволяє тримати PDP-осі (колір/розмір тощо) на рівні товару й не губити перемикання фото.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mergeVariantUsedOptionsIntoListingRows(array $rows): array
    {
        $owner = $this->listingForVariantOptionsMerge();
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();

        /** @var array<int, list<int>> $variantUsed */
        $variantUsed = [];
        $variants = $owner instanceof Product ? $owner->variants()->get() : collect();

        foreach ($variants as $variant) {
            $pairs = is_array($variant->options ?? null) ? $variant->options : [];
            foreach ($pairs as $pair) {
                $gid = (int) ($pair['option_group_id'] ?? 0);
                $vid = (int) ($pair['option_value_id'] ?? 0);
                if ($gid <= 0 || $vid <= 0 || ($categoryGroupId > 0 && $gid === $categoryGroupId)) {
                    continue;
                }
                $variantUsed[$gid][] = $vid;
            }
        }

        if ($variantUsed === []) {
            return $rows;
        }

        $byGroup = [];
        $order = [];
        foreach ($rows as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            if (! isset($order[$gid])) {
                $order[$gid] = count($order);
            }
            $ids = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
            $byGroup[$gid] = array_values(array_unique(array_merge($byGroup[$gid] ?? [], $ids)));
        }

        foreach ($variantUsed as $gid => $ids) {
            if (! isset($order[$gid])) {
                $order[$gid] = count($order);
            }
            $byGroup[$gid] = array_values(array_unique(array_merge(
                $byGroup[$gid] ?? [],
                array_values(array_unique(array_map('intval', $ids)))
            )));
            sort($byGroup[$gid]);
        }

        asort($order);
        $out = [];
        foreach (array_keys($order) as $gid) {
            $mergedIds = $byGroup[$gid] ?? [];
            $out[] = [
                'option_group_id' => $gid,
                'option_value_ids' => $mergedIds,
                'value_photos' => $this->mergeValuePhotoRows($rows, $gid, $mergedIds),
                'value_prices' => $this->mergeValuePriceRows($rows, $gid, $mergedIds),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $selectedValueIds
     * @return list<array{option_value_id: int, photos: list<string>}>
     */
    protected function normalizeValuePhotosBySelectedIds(mixed $rawRows, array $selectedValueIds): array
    {
        if (! is_array($rawRows) || $selectedValueIds === []) {
            return [];
        }

        $allowed = array_flip(array_map('intval', $selectedValueIds));
        $out = [];
        foreach ($rawRows as $row) {
            $valueId = (int) ($row['option_value_id'] ?? 0);
            if ($valueId <= 0 || ! isset($allowed[$valueId])) {
                continue;
            }
            $photos = collect($row['photos'] ?? [])
                ->map(fn ($path) => is_string($path) ? trim($path) : '')
                ->filter(fn (string $path): bool => $path !== '')
                ->values()
                ->all();
            if ($photos === []) {
                continue;
            }
            $out[] = [
                'option_value_id' => $valueId,
                'photos' => $photos,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $allowedIds
     * @return array<string, float>
     */
    protected function normalizeValuePricesRepeaterToMap(mixed $raw, array $allowedIds): array
    {
        if (! is_array($raw) || $allowedIds === []) {
            return [];
        }

        $flip = array_flip(array_map('intval', $allowedIds));
        $out = [];
        $keys = array_keys($raw);
        $isList = $keys === range(0, count($raw) - 1);

        if (! $isList) {
            foreach ($raw as $k => $v) {
                $id = (int) $k;
                if ($id <= 0 || ! isset($flip[$id])) {
                    continue;
                }
                if ($v === null || $v === '') {
                    continue;
                }
                $out[(string) $id] = round(max(0, (float) $v), 2);
            }

            return $out;
        }

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $id = (int) ($entry['option_value_id'] ?? 0);
            if ($id <= 0 || ! isset($flip[$id])) {
                continue;
            }
            $p = $entry['price_addon'] ?? $entry['price'] ?? null;
            if ($p === null || $p === '') {
                continue;
            }
            $out[(string) $id] = round(max(0, (float) $p), 2);
        }

        return $out;
    }

    /**
     * @return list<array{option_value_id: int, price_addon: float}>
     */
    protected function valuePricesMapToRepeater(mixed $map): array
    {
        if (! is_array($map) || $map === []) {
            return [];
        }

        $keys = array_keys($map);
        if ($keys === range(0, count($map) - 1)) {
            return $map;
        }

        $out = [];
        foreach ($map as $k => $v) {
            $id = (int) $k;
            if ($id <= 0) {
                continue;
            }
            if ($v === null || $v === '') {
                continue;
            }
            $out[] = [
                'option_value_id' => $id,
                'price_addon' => (float) $v,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $selectedValueIds
     * @return array<string, float>
     */
    protected function mergeValuePriceRows(array $rows, int $groupId, array $selectedValueIds): array
    {
        if ($selectedValueIds === []) {
            return [];
        }

        $flip = array_flip(array_map('intval', $selectedValueIds));
        $out = [];

        foreach ($rows as $row) {
            if ((int) ($row['option_group_id'] ?? 0) !== $groupId) {
                continue;
            }
            $m = $row['value_prices'] ?? [];
            if (! is_array($m)) {
                continue;
            }
            foreach ($m as $k => $v) {
                $id = (int) $k;
                if ($id <= 0 || ! isset($flip[$id])) {
                    continue;
                }
                if ($v === null || $v === '') {
                    continue;
                }
                $out[(string) $id] = round(max(0, (float) $v), 2);
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $selectedValueIds
     * @return list<array{option_value_id: int, photos: list<string>}>
     */
    protected function mergeValuePhotoRows(array $rows, int $groupId, array $selectedValueIds): array
    {
        if ($selectedValueIds === []) {
            return [];
        }

        $merged = [];
        foreach ($rows as $row) {
            if ((int) ($row['option_group_id'] ?? 0) !== $groupId) {
                continue;
            }
            foreach ($this->normalizeValuePhotosBySelectedIds($row['value_photos'] ?? [], $selectedValueIds) as $photoRow) {
                $valueId = (int) ($photoRow['option_value_id'] ?? 0);
                if ($valueId <= 0) {
                    continue;
                }
                $merged[$valueId] = array_values(array_unique(array_merge(
                    $merged[$valueId] ?? [],
                    $photoRow['photos'] ?? []
                )));
            }
        }

        $out = [];
        foreach ($merged as $valueId => $photos) {
            $out[] = [
                'option_value_id' => (int) $valueId,
                'photos' => $photos,
            ];
        }

        return $out;
    }
}
