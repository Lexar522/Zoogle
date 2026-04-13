<?php

namespace App\Filament\Admin\Concerns;

use App\Models\OptionGroup;

trait NormalizesVariantOptionsRepeaterForm
{
    protected function isVariantOptionGroupMultiple(int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }

        return (OptionGroup::query()->whereKey($groupId)->value('selection_mode') ?? 'single') === 'multiple';
    }

    /**
     * З JSON варіанта: плоский список пар → рядки repeater (для груп multiple — один ряд з option_value_ids).
     *
     * @param  list<array<string, mixed>>|null  $flat
     * @return list<array<string, mixed>>
     */
    protected function denormalizeVariantOptionsRepeaterForForm(?array $flat): array
    {
        if (! is_array($flat) || $flat === []) {
            return [];
        }

        /** @var array<int, list<int>> $byGid */
        $byGid = [];
        foreach ($flat as $pair) {
            $gid = (int) ($pair['option_group_id'] ?? 0);
            $vid = (int) ($pair['option_value_id'] ?? 0);
            if ($gid <= 0 || $vid <= 0) {
                continue;
            }
            $byGid[$gid][] = $vid;
        }

        $rows = [];
        foreach ($byGid as $gid => $vids) {
            $vids = array_values(array_unique($vids));
            sort($vids);
            if ($this->isVariantOptionGroupMultiple($gid)) {
                $rows[] = [
                    'option_group_id' => $gid,
                    'option_value_ids' => $vids,
                    'option_value_id' => null,
                ];
            } else {
                $rows[] = [
                    'option_group_id' => $gid,
                    'option_value_id' => $vids[0] ?? 0,
                    'option_value_ids' => [],
                ];
            }
        }

        return $rows;
    }

    /**
     * З repeater у плоский список пар для збереження в product_variants.options.
     *
     * @param  list<array<string, mixed>>|null  $rows
     * @return list<array{option_group_id: int, option_value_id: int}>
     */
    protected function normalizeVariantOptionsRepeaterForSave(?array $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $gid = (int) ($row['option_group_id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }

            if ($this->isVariantOptionGroupMultiple($gid)) {
                foreach ($row['option_value_ids'] ?? [] as $vid) {
                    $vid = (int) $vid;
                    if ($vid > 0) {
                        $out[] = ['option_group_id' => $gid, 'option_value_id' => $vid];
                    }
                }
            } else {
                $vid = (int) ($row['option_value_id'] ?? 0);
                if ($vid > 0) {
                    $out[] = ['option_group_id' => $gid, 'option_value_id' => $vid];
                }
            }
        }

        $seen = [];
        $unique = [];
        foreach ($out as $p) {
            $k = $p['option_group_id'].'-'.$p['option_value_id'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $unique[] = $p;
        }

        return $unique;
    }
}
