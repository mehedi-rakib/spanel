<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\BrandRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(private readonly BrandRepositoryInterface $brandRepo)
    {
    }

    public function index(Request $request)
    {
        $brands = $this->brandRepo->getListWhere(searchValue: $request['searchValue'], dataLimit: 'all');
        return response()->json($brands, 200);
    }
}
