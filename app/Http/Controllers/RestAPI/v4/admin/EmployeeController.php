<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\AdminRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private readonly AdminRepositoryInterface $adminRepo)
    {
    }

    public function index(Request $request)
    {
        $employees = $this->adminRepo->getEmployeeListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $request['searchValue'],
            filters: ['admin_role_id' => $request['admin_role_id'] ?? 'all'],
            relations: ['role'],
            dataLimit: $request['limit'] ?? DEFAULT_DATA_LIMIT
        );
        return response()->json($employees, 200);
    }

    public function show(string|int $id)
    {
        $employee = $this->adminRepo->getFirstWhere(params: ['id' => $id], relations: ['role']);
        if (!$employee) {
            return response()->json(['errors' => [['code' => 'employee-001', 'message' => translate('employee_not_found')]]], 404);
        }
        return response()->json($employee, 200);
    }
}
