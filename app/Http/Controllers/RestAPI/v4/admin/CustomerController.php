<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerRepositoryInterface $customerRepo)
    {
    }

    /**
     * Excludes the built-in walking customer (id 0), which can't carry a due balance.
     * `?has_due=1` narrows to customers who currently owe money.
     */
    public function index(Request $request)
    {
        $searchValue = $request['searchValue'];

        $customers = User::query()
            ->where('id', '!=', 0)
            ->when($request['is_active'] !== null && $request['is_active'] !== '', fn($query) => $query->where('is_active', (int)$request['is_active']))
            ->when($request['has_due'], fn($query) => $query->where('due_balance', '>', 0))
            ->when($searchValue, function ($query) use ($searchValue) {
                $query->where(function ($query) use ($searchValue) {
                    $query->where('name', 'like', "%{$searchValue}%")
                        ->orWhere('f_name', 'like', "%{$searchValue}%")
                        ->orWhere('l_name', 'like', "%{$searchValue}%")
                        ->orWhere('phone', 'like', "%{$searchValue}%")
                        ->orWhere('email', 'like', "%{$searchValue}%");
                });
            })
            ->orderBy($request['has_due'] ? 'due_balance' : 'id', 'desc')
            ->paginate($request['limit'] ?? DEFAULT_DATA_LIMIT);

        $customers->getCollection()->transform(fn($customer) => $this->withDisplayName($customer));

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

        return response()->json($this->withDisplayName($customer), 200);
    }

    /**
     * Quick-add a customer from the mobile POS: only name and phone are required.
     * The account gets a random password; the customer can claim it later through
     * the storefront's forgot-password flow using this phone number.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:80',
            'phone' => 'required|string|min:4|max:20|unique:users,phone',
            'email' => 'nullable|email|max:255|unique:users,email',
            'address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $customer = User::create([
            ...$this->splitName($request['name']),
            'phone' => $request['phone'],
            'email' => $request['email'] ?: null,
            'street_address' => $request['address'] ?: null,
            'password' => bcrypt(Str::random(16)),
            'is_active' => 1,
        ]);

        return response()->json($this->withDisplayName($customer->fresh()), 201);
    }

    public function update(Request $request, string|int $id)
    {
        $customer = User::where('id', '!=', 0)->find($id);
        if (!$customer) {
            return response()->json(['errors' => [['code' => 'customer-001', 'message' => translate('customer_not_found')]]], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:80',
            'phone' => ['required', 'string', 'min:4', 'max:20', Rule::unique('users', 'phone')->ignore($customer->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $customer->update([
            ...$this->splitName($request['name']),
            'phone' => $request['phone'],
            'email' => $request['email'] ?: null,
            'street_address' => $request['address'] ?: null,
        ]);

        return response()->json($this->withDisplayName($customer->fresh()), 200);
    }

    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $parts = explode(' ', $name, 2);

        return [
            'name' => $name,
            'f_name' => $parts[0],
            'l_name' => $parts[1] ?? '',
        ];
    }

    /**
     * Customers added from the web panel only have f_name/l_name, so `name` is
     * often null; the app always shows `name`.
     */
    private function withDisplayName(User $customer): User
    {
        if (!$customer->name) {
            $customer->name = trim($customer->f_name . ' ' . $customer->l_name) ?: $customer->phone;
        }
        return $customer;
    }
}
