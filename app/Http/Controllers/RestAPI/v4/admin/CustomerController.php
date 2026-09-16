<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerRepositoryInterface $customerRepo)
    {
    }

    public function index(Request $request)
    {
        $filters = array_filter([
            'is_active' => $request['is_active'],
        ], fn($value) => $value !== null && $value !== '');

        $customers = $this->customerRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $request['searchValue'],
            filters: $filters,
            dataLimit: $request['limit'] ?? DEFAULT_DATA_LIMIT
        );

        return response()->json($customers, 200);
    }

    public function show(string|int $id)
    {
        $customer = $this->customerRepo->getFirstWhere(
            params: ['id' => $id],
            relations: ['orders' => function ($query) {
                $query->orderBy('id', 'desc')->limit(10);
            }, 'dueTransactions' => function ($query) {
                $query->orderBy('id', 'desc')->limit(10);
            }]
        );

        if (!$customer) {
            return response()->json(['errors' => [['code' => 'customer-001', 'message' => translate('customer_not_found')]]], 404);
        }

        return response()->json($customer, 200);
    }
}
