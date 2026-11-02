<?php

namespace App\Support;

use App\Models\HotelBarPurchaseList;
use Illuminate\Support\Facades\Route;

final class DepartmentProcurement
{
    /** @return array<string, mixed> */
    public static function workflow(string $key): array
    {
        $workflows = config('hotel_department_procurement.workflows', []);
        if (! isset($workflows[$key])) {
            throw new \InvalidArgumentException('Unknown procurement workflow: '.$key);
        }

        return $workflows[$key];
    }

    /** @return list<string> */
    public static function workflowKeys(): array
    {
        return array_keys(config('hotel_department_procurement.workflows', []));
    }

    public static function route(string $workflowKey, string $nameSuffix, mixed ...$parameters): string
    {
        $prefix = self::workflow($workflowKey)['route_name_prefix'];

        return route($prefix.'.'.$nameSuffix, $parameters);
    }

    public static function signedSharedRouteName(string $workflowKey): string
    {
        return self::workflow($workflowKey)['route_name_prefix'].'.purchase-lists.shared';
    }

    public static function signedSharedSubmitRouteName(string $workflowKey): string
    {
        return self::workflow($workflowKey)['route_name_prefix'].'.purchase-lists.shared.submit';
    }

    public static function assertListWorkflow(HotelBarPurchaseList $list, string $expectedWorkflow): void
    {
        $actual = $list->workflow_key ?: 'bar';
        abort_unless($actual === $expectedWorkflow, 404);
    }

    public static function routeWorkflowFromRequest(): string
    {
        $workflow = request()->route('procurement_workflow');
        if (is_string($workflow) && $workflow !== '') {
            return $workflow;
        }

        return 'bar';
    }

    /** @return array<string, string> */
    public static function statusLabels(string $group): array
    {
        return config('hotel_department_procurement.status_labels.'.$group, []);
    }

    public static function marketStockDestinations(string $workflowKey): array
    {
        return self::workflow($workflowKey)['stock_destinations'] ?? [];
    }

    /** @return list<string> */
    public static function allowedStockDestinationKeys(string $workflowKey): array
    {
        return array_keys(self::marketStockDestinations($workflowKey));
    }

    public static function departmentStockDestKey(string $workflowKey): string
    {
        return (string) (self::workflow($workflowKey)['department_stock_dest'] ?? 'department');
    }

    public static function label(string $workflowKey): string
    {
        return (string) self::workflow($workflowKey)['label'];
    }

    public static function indexUrl(string $workflowKey, string $tab, array $extra = []): string
    {
        $route = self::workflow($workflowKey)['index_route'];

        return route($route, array_merge(['tab' => $tab], $extra));
    }

    public static function routeExists(string $workflowKey, string $nameSuffix): bool
    {
        $name = self::workflow($workflowKey)['route_name_prefix'].'.'.$nameSuffix;

        return Route::has($name);
    }
}
