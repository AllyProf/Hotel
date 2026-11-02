<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\User;
use App\Services\Bar\BarPurchaseListService;
use App\Support\DepartmentProcurement;

class DepartmentProcurementIndexData
{
    public function __construct(
        private BarPurchaseListService $purchaseLists,
    ) {}

    /** @return array<string, mixed> */
    public function payload(Hotel $hotel, User $user, string $workflowKey, ?int $departmentId = null): array
    {
        $workflow = DepartmentProcurement::workflow($workflowKey);
        $departmentLinesOnly = ! empty($workflow['stock_receive_department_only']);

        return [
            'procWorkflow' => $workflowKey,
            'procLabel' => $workflow['label'],
            'procSettingsTab' => $workflow['settings_tab'],
            'purchaseLists' => $this->purchaseLists->recentLists($hotel, 30, $workflowKey),
            'receivedLists' => $this->purchaseLists->receivedListsForUser($hotel, $user, $departmentId, 50, $workflowKey),
            'receivedDepartments' => $this->purchaseLists->departmentsWithSentListsForUser($hotel, $user, $workflowKey),
            'isPurchaseListRecipient' => $this->purchaseLists->userShouldSeeDepartmentReceivedTab($hotel, $user, $workflowKey),
            'purchaseListRecipientsConfigured' => $this->purchaseLists->hasRecipients($hotel, $workflowKey),
            'marketLists' => $this->purchaseLists->listsForMarket($hotel, 100, $workflowKey),
            'stockReceiveLists' => $this->purchaseLists->listsPendingMarketStockReceive(
                $hotel,
                $departmentLinesOnly,
                100,
                $workflowKey,
            ),
            'stockReceivePendingCount' => $this->purchaseLists->pendingMarketStockReceiveCount(
                $hotel,
                $departmentLinesOnly,
                $workflowKey,
            ),
            'budgetCurrency' => $this->purchaseLists->budgetCurrencyForHotel($hotel, $workflowKey),
        ];
    }
}
