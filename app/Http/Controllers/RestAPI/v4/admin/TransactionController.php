<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RestAPI\v4\admin\Concerns\QueriesPurchaseBills;
use App\Http\Controllers\RestAPI\v4\admin\Concerns\QueriesSales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    use QueriesPurchaseBills, QueriesSales;

    private const TYPES = ['sale', 'payment_in', 'purchase'];

    /**
     * One newest-first feed of every money movement the shop records:
     * POS/web sales (orders), customer due pay-backs (payment_in) and supplier
     * purchase bills. `?type=` narrows to one kind; `?from_date`/`?to_date`
     * (YYYY-MM-DD) power the Day Book report.
     */
    public function index(Request $request)
    {
        $fromDate = $request['from_date'];
        $toDate = $request['to_date'];
        $searchValue = $request['searchValue'];
        $types = in_array($request['type'], self::TYPES, true) ? [$request['type']] : self::TYPES;

        $queries = [];

        if (in_array('sale', $types, true)) {
            $queries[] = DB::table('orders')
                ->leftJoin('users', 'users.id', '=', 'orders.customer_id')
                ->when($fromDate, fn($query) => $query->whereDate('orders.created_at', '>=', $fromDate))
                ->when($toDate, fn($query) => $query->whereDate('orders.created_at', '<=', $toDate))
                ->when($searchValue, function ($query) use ($searchValue) {
                    $query->where(function ($query) use ($searchValue) {
                        $query->where('orders.id', 'like', "%{$searchValue}%")
                            ->orWhere('users.f_name', 'like', "%{$searchValue}%")
                            ->orWhere('users.l_name', 'like', "%{$searchValue}%")
                            ->orWhere('users.name', 'like', "%{$searchValue}%")
                            ->orWhere('users.phone', 'like', "%{$searchValue}%");
                    });
                })
                ->selectRaw("
                    'sale' as type,
                    CAST(orders.id AS CHAR) as reference,
                    orders.customer_id as party_id,
                    {$this->customerNameSql()} as party_name,
                    orders.order_amount as total,
                    GREATEST(orders.order_amount - orders.paid_amount, 0) as balance,
                    orders.payment_status as status,
                    orders.order_status as order_status,
                    orders.created_at as created_at
                ");
        }

        if (in_array('payment_in', $types, true)) {
            $queries[] = DB::table('customer_due_transactions')
                ->leftJoin('users', 'users.id', '=', 'customer_due_transactions.user_id')
                ->where('customer_due_transactions.type', 'payment')
                ->when($fromDate, fn($query) => $query->whereDate('customer_due_transactions.created_at', '>=', $fromDate))
                ->when($toDate, fn($query) => $query->whereDate('customer_due_transactions.created_at', '<=', $toDate))
                ->when($searchValue, function ($query) use ($searchValue) {
                    $query->where(function ($query) use ($searchValue) {
                        $query->where('users.f_name', 'like', "%{$searchValue}%")
                            ->orWhere('users.l_name', 'like', "%{$searchValue}%")
                            ->orWhere('users.name', 'like', "%{$searchValue}%")
                            ->orWhere('users.phone', 'like', "%{$searchValue}%");
                    });
                })
                ->selectRaw("
                    'payment_in' as type,
                    CAST(customer_due_transactions.id AS CHAR) as reference,
                    customer_due_transactions.user_id as party_id,
                    {$this->customerNameSql()} as party_name,
                    customer_due_transactions.amount as total,
                    customer_due_transactions.balance_after as balance,
                    'paid' as status,
                    NULL as order_status,
                    customer_due_transactions.created_at as created_at
                ");
        }

        if (in_array('purchase', $types, true)) {
            $bills = $this->purchaseBillsQuery($fromDate, $toDate, null, $searchValue);
            $queries[] = DB::query()->fromSub($bills, 'bills')->selectRaw("
                'purchase' as type,
                bills.reference_no as reference,
                bills.supplier_id as party_id,
                COALESCE(bills.supplier_name, '') as party_name,
                bills.total_amount as total,
                0 as balance,
                'paid' as status,
                NULL as order_status,
                bills.created_at as created_at
            ");
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $transactions = DB::query()
            ->fromSub($union, 'transactions')
            ->orderByDesc('created_at')
            ->paginate($request['limit'] ?? DEFAULT_DATA_LIMIT);

        $transactions->getCollection()->transform(function ($row) {
            $row->party_id = $row->party_id !== null ? (int)$row->party_id : null;
            $row->total = round((float)$row->total, 2);
            $row->balance = round((float)$row->balance, 2);
            if ($row->type === 'sale' && (!$row->party_name || $row->party_id === 0)) {
                $row->party_name = translate('walking_customer');
            }
            if ($row->type === 'purchase' && !$row->party_name) {
                $row->party_name = translate('unknown_supplier');
            }
            return $row;
        });

        return response()->json($transactions, 200);
    }
}
