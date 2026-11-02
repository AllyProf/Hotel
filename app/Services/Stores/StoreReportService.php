<?php

namespace App\Services\Stores;

use App\Models\Hotel;
use App\Models\HotelStoreConsumptionItem;
use App\Models\HotelStoreIndent;
use App\Models\HotelStoreIndentItem;
use Illuminate\Support\Collection;

class StoreReportService
{
    /** @param  array<string, mixed>  $filters
     * @return array{total_value: float, groups: list<array<string, mixed>>}
     */
    public function build(Hotel $hotel, array $filters): array
    {
        $type = (string) ($filters['report_type'] ?? 'indent');
        $sort = (string) ($filters['report_sort'] ?? 'department');
        $from = trim((string) ($filters['from_date'] ?? ''));
        $to = trim((string) ($filters['to_date'] ?? ''));

        $lines = $type === 'consumption'
            ? $this->consumptionLines($hotel, $from, $to)
            : $this->indentLines($hotel, $from, $to);

        $groups = match ($sort) {
            'item' => $this->groupByItem($lines),
            'category' => $this->groupByCategory($lines),
            'sub_category' => $this->groupBySubCategory($lines),
            default => $this->groupByDepartment($lines),
        };

        $totalValue = round($lines->sum('value'), 2);

        return [
            'total_value' => $totalValue,
            'groups' => $groups,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function indentLines(Hotel $hotel, string $from, string $to): Collection
    {
        $query = HotelStoreIndentItem::query()
            ->whereHas('indent', function ($q) use ($hotel, $from, $to) {
                $q->where('hotel_id', $hotel->id)
                    ->where('status', HotelStoreIndent::STATUS_RECEIVED);
                if ($from !== '') {
                    $q->whereDate('received_at', '>=', $from);
                }
                if ($to !== '') {
                    $q->whereDate('received_at', '<=', $to);
                }
            })
            ->with([
                'indent.department',
                'masterItem.category',
                'masterItem.subCategory',
                'masterItem.vendor',
            ]);

        return $query->get()->map(fn (HotelStoreIndentItem $row) => $this->mapIndentRow($row));
    }

    /** @return array<string, mixed> */
    private function mapIndentRow(HotelStoreIndentItem $row): array
    {
        $master = $row->masterItem;
        $value = (float) $row->pre_tax;
        if ($value <= 0 && $master !== null) {
            $value = round((float) $row->quantity * (float) $master->rate, 2);
        }

        return $this->normalizeLine([
            'department_id' => (int) ($row->indent?->hotel_department_id ?? 0),
            'department_name' => (string) ($row->indent?->department?->name ?? '—'),
            'master_item_id' => (int) ($row->hotel_store_master_item_id ?? 0),
            'item_name' => (string) ($row->name ?: ($master?->name ?? '—')),
            'category_id' => (int) ($master?->hotel_store_category_id ?? 0),
            'category_name' => (string) ($master?->category?->name ?? '—'),
            'sub_category_id' => (int) ($master?->hotel_store_sub_category_id ?? 0),
            'sub_category_name' => (string) ($master?->subCategory?->name ?? '—'),
            'vendor_name' => (string) ($master?->vendor?->name ?? '—'),
            'quantity' => (float) $row->quantity,
            'unit' => trim((string) ($row->unit ?? $master?->unit ?? '')),
            'value' => $value,
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function consumptionLines(Hotel $hotel, string $from, string $to): Collection
    {
        $query = HotelStoreConsumptionItem::query()
            ->whereHas('consumption', function ($q) use ($hotel, $from, $to) {
                $q->where('hotel_id', $hotel->id);
                if ($from !== '') {
                    $q->whereDate('created_at', '>=', $from);
                }
                if ($to !== '') {
                    $q->whereDate('created_at', '<=', $to);
                }
            })
            ->with([
                'consumption.department',
                'masterItem.category',
                'masterItem.subCategory',
                'masterItem.vendor',
            ]);

        return $query->get()->map(fn (HotelStoreConsumptionItem $row) => $this->mapConsumptionRow($row));
    }

    /** @return array<string, mixed> */
    private function mapConsumptionRow(HotelStoreConsumptionItem $row): array
    {
        $master = $row->masterItem;
        $rate = $master !== null ? (float) $master->rate : 0.0;
        $value = round((float) $row->quantity * $rate, 2);

        return $this->normalizeLine([
            'department_id' => (int) ($row->consumption?->hotel_department_id ?? 0),
            'department_name' => (string) ($row->consumption?->department?->name ?? '—'),
            'master_item_id' => (int) ($row->hotel_store_master_item_id ?? 0),
            'item_name' => (string) ($row->name ?: ($master?->name ?? '—')),
            'category_id' => (int) ($master?->hotel_store_category_id ?? 0),
            'category_name' => (string) ($master?->category?->name ?? '—'),
            'sub_category_id' => (int) ($master?->hotel_store_sub_category_id ?? 0),
            'sub_category_name' => (string) ($master?->subCategory?->name ?? '—'),
            'vendor_name' => (string) ($master?->vendor?->name ?? '—'),
            'quantity' => (float) $row->quantity,
            'unit' => trim((string) ($row->unit ?? $master?->unit ?? '')),
            'value' => $value,
        ]);
    }

    /** @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function normalizeLine(array $line): array
    {
        if ($line['unit'] === '') {
            $line['unit'] = '—';
        }

        return $line;
    }

    /** @param  Collection<int, array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function groupByDepartment(Collection $lines): array
    {
        return $this->buildGroups($lines, 'department_id', 'department_name', function (Collection $bucket) {
            return $this->aggregateChildren($bucket, ['master_item_id', 'unit'], [
                'name' => 'item_name',
                'category' => 'category_name',
                'sub_category' => 'sub_category_name',
            ], false);
        }, false);
    }

    /** @param  Collection<int, array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function groupByItem(Collection $lines): array
    {
        return $this->buildGroups($lines, 'master_item_id', 'item_name', function (Collection $bucket) {
            return $this->aggregateChildren($bucket, ['department_id'], [
                'name' => 'department_name',
                'category' => 'category_name',
                'sub_category' => 'sub_category_name',
            ], true);
        }, true);
    }

    /** @param  Collection<int, array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function groupByCategory(Collection $lines): array
    {
        return $this->buildGroups($lines, 'category_id', 'category_name', function (Collection $bucket) {
            return $this->aggregateChildren($bucket, ['vendor_name', 'sub_category_id'], [
                'name' => 'vendor_name',
                'category' => 'category_name',
                'sub_category' => 'sub_category_name',
            ], true);
        }, true);
    }

    /** @param  Collection<int, array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function groupBySubCategory(Collection $lines): array
    {
        return $this->buildGroups($lines, 'sub_category_id', 'sub_category_name', function (Collection $bucket) {
            return $this->aggregateChildren($bucket, ['master_item_id', 'unit'], [
                'name' => 'item_name',
                'category' => 'category_name',
                'sub_category' => 'sub_category_name',
            ], true);
        }, true);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  callable(Collection): list<array<string, mixed>>  $childBuilder
     * @return list<array<string, mixed>>
     */
    private function buildGroups(
        Collection $lines,
        string $keyField,
        string $labelField,
        callable $childBuilder,
        bool $parentShowsQuantity,
    ): array {
        if ($lines->isEmpty()) {
            return [];
        }

        return $lines
            ->groupBy(fn (array $line) => (string) ($line[$keyField] ?: $line[$labelField]))
            ->map(function (Collection $bucket) use ($keyField, $labelField, $childBuilder, $parentShowsQuantity) {
                $first = $bucket->first();
                $children = $childBuilder($bucket);
                $parentQty = $parentShowsQuantity ? $this->sumQuantityLabel($bucket) : '';
                $parentCategory = '';
                $parentSubCategory = '';
                if ($parentShowsQuantity && $keyField === 'master_item_id') {
                    $parentCategory = (string) ($first['category_name'] ?? '');
                    $parentSubCategory = (string) ($first['sub_category_name'] ?? '');
                }

                return [
                    'name' => (string) ($first[$labelField] ?? '—'),
                    'category' => $parentCategory,
                    'sub_category' => $parentSubCategory,
                    'quantity_label' => $parentQty,
                    'value' => round($bucket->sum('value'), 2),
                    'children' => $children,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $bucket
     * @param  list<string>  $groupKeys
     * @param  array{name: string, category: string, sub_category: string}  $fieldMap
     * @return list<array<string, mixed>>
     */
    private function aggregateChildren(
        Collection $bucket,
        array $groupKeys,
        array $fieldMap,
        bool $fillCategoryOnParentStyle,
    ): array {
        return $bucket
            ->groupBy(function (array $line) use ($groupKeys) {
                return collect($groupKeys)->map(fn ($k) => (string) ($line[$k] ?? ''))->join('|');
            })
            ->map(function (Collection $group) use ($fieldMap, $fillCategoryOnParentStyle) {
                $first = $group->first();

                return [
                    'name' => (string) ($first[$fieldMap['name']] ?? '—'),
                    'category' => $fillCategoryOnParentStyle && $fieldMap['name'] === 'vendor_name'
                        ? ''
                        : (string) ($first[$fieldMap['category']] ?? ''),
                    'sub_category' => (string) ($first[$fieldMap['sub_category']] ?? ''),
                    'quantity_label' => $this->sumQuantityLabel($group),
                    'value' => round($group->sum('value'), 2),
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function sumQuantityLabel(Collection $rows): string
    {
        $units = $rows->pluck('unit')->unique()->filter(fn ($u) => $u !== '' && $u !== '—');
        $qty = round($rows->sum('quantity'), 2);
        if ($qty <= 0) {
            return '';
        }
        if ($units->count() === 1) {
            return $this->formatQty($qty).' '.$units->first();
        }

        return $this->formatQty($qty);
    }

    private function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }

    /** @param  array{total_value: float, groups: list<array<string, mixed>>}  $report */
    public function toCsvRows(array $report, string $reportType, string $sortLabel): array
    {
        $rows = [
            ['Report type', $reportType],
            ['Sort by', $sortLabel],
            ['Total value', number_format((float) $report['total_value'], 2, '.', '')],
            [],
            ['Name', 'Category', 'Sub Category', 'Quantity', 'Value'],
        ];

        foreach ($report['groups'] as $group) {
            $rows[] = [
                $group['name'],
                $group['category'] ?? '',
                $group['sub_category'] ?? '',
                $group['quantity_label'] ?? '',
                number_format((float) ($group['value'] ?? 0), 2, '.', ''),
            ];
            foreach ($group['children'] ?? [] as $child) {
                $rows[] = [
                    '  '.$child['name'],
                    $child['category'] ?? '',
                    $child['sub_category'] ?? '',
                    $child['quantity_label'] ?? '',
                    number_format((float) ($child['value'] ?? 0), 2, '.', ''),
                ];
            }
        }

        return $rows;
    }
}
