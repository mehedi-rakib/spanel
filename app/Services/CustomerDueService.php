<?php

namespace App\Services;

use App\Contracts\Repositories\CustomerDueTransactionRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\CustomerDueTransaction;
use Illuminate\Support\Facades\DB;

class CustomerDueService
{
    public function __construct(
        private readonly CustomerRepositoryInterface             $customerRepo,
        private readonly CustomerDueTransactionRepositoryInterface $dueRepo,
    )
    {
    }

    /**
     * Increases a customer's outstanding due (e.g. an unpaid/partially paid POS sale) and logs it.
     */
    public function addDue(int $userId, float $amount, ?int $orderId = null, ?string $note = null, ?int $adminId = null): ?CustomerDueTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($userId, $amount, $orderId, $note, $adminId) {
            $customer = $this->customerRepo->getFirstWhere(params: ['id' => $userId]);
            if (!$customer) {
                return null;
            }

            $newBalance = round((float)$customer->due_balance + $amount, 2);
            $this->customerRepo->update(id: $userId, data: ['due_balance' => $newBalance]);

            return $this->dueRepo->add(data: [
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => CustomerDueTransaction::TYPE_SALE_DUE,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'note' => $note,
                'admin_id' => $adminId,
            ]);
        });
    }

    /**
     * Records a customer paying back part or all of their outstanding due.
     *
     * @return array{success: bool, message: string, transaction?: CustomerDueTransaction}
     */
    public function recordPayment(int $userId, float $amount, ?string $note = null, ?int $adminId = null): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'invalid_amount'];
        }

        return DB::transaction(function () use ($userId, $amount, $note, $adminId) {
            $customer = $this->customerRepo->getFirstWhere(params: ['id' => $userId]);
            if (!$customer) {
                return ['success' => false, 'message' => 'customer_not_found'];
            }

            if ($amount > (float)$customer->due_balance) {
                return ['success' => false, 'message' => 'amount_exceeds_due'];
            }

            $newBalance = round((float)$customer->due_balance - $amount, 2);
            $this->customerRepo->update(id: $userId, data: ['due_balance' => $newBalance]);

            $transaction = $this->dueRepo->add(data: [
                'user_id' => $userId,
                'order_id' => null,
                'type' => CustomerDueTransaction::TYPE_PAYMENT,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'note' => $note,
                'admin_id' => $adminId,
            ]);

            return ['success' => true, 'message' => 'due_payment_recorded', 'transaction' => $transaction];
        });
    }
}
