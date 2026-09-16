<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\CustomerDueTransactionRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\CustomerDueService;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerDueController extends Controller
{
    public function __construct(
        private readonly CustomerRepositoryInterface             $customerRepo,
        private readonly CustomerDueTransactionRepositoryInterface $dueRepo,
        private readonly CustomerDueService                       $customerDueService,
    )
    {
    }

    /**
     * Due report: every customer who currently owes the shop money.
     */
    public function index(Request $request)
    {
        $customers = $this->customerRepo->getListWhere(
            orderBy: ['due_balance' => 'desc'],
            searchValue: $request['searchValue'],
            filters: [],
            dataLimit: $request['limit'] ?? DEFAULT_DATA_LIMIT
        );

        $totalDue = (float)DB::table('users')->sum('due_balance');

        return response()->json([
            'total_outstanding_due' => round($totalDue, 2),
            'customers' => $customers,
        ], 200);
    }

    /**
     * Full due ledger (sales-on-credit + payments) for a single customer.
     */
    public function show(string|int $customerId, Request $request)
    {
        $customer = $this->customerRepo->getFirstWhere(params: ['id' => $customerId]);
        if (!$customer) {
            return response()->json(['errors' => [['code' => 'customer-001', 'message' => translate('customer_not_found')]]], 404);
        }

        $transactions = $this->dueRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            filters: ['user_id' => $customerId],
            dataLimit: $request['limit'] ?? DEFAULT_DATA_LIMIT
        );

        return response()->json([
            'customer_id' => $customer->id,
            'due_balance' => (float)$customer->due_balance,
            'transactions' => $transactions,
        ], 200);
    }

    /**
     * Record a customer paying back some or all of their outstanding due.
     */
    public function store(Request $request, string|int $customerId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $result = $this->customerDueService->recordPayment(
            userId: (int)$customerId,
            amount: (float)$request['amount'],
            note: $request['note'] ?? null,
            adminId: $request['admin']->id,
        );

        if (!$result['success']) {
            $errorCode = $result['message'] === 'customer_not_found' ? 'customer-001' : 'customer-due-001';
            $statusCode = $result['message'] === 'customer_not_found' ? 404 : 422;
            return response()->json(['errors' => [['code' => $errorCode, 'message' => translate($result['message'])]]], $statusCode);
        }

        return response()->json($result['transaction'], 201);
    }
}
