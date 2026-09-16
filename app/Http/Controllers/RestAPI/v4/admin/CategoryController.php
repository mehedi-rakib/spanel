<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryRepositoryInterface $categoryRepo)
    {
    }

    public function index(Request $request)
    {
        $filters = [];
        if ($request->filled('parent_id')) {
            $filters['parent_id'] = $request['parent_id'];
        } else {
            $filters['position'] = 0;
        }

        $categories = $this->categoryRepo->getListWhere(filters: $filters, dataLimit: 'all');
        return response()->json($categories, 200);
    }
}
