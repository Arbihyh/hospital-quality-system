<?php
/**
 * 空构造，预留
 */
namespace App\Http\Controllers\Api;

use App\Model\Department;
use Illuminate\Http\Request;
use App\Services\ToolsService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class DepartmentController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAllDepartment(Request $request)
    {
        $code = 200;
        $data = Department::query()->where('type_id', '=', 2)->get()->toArray();
        $data = array_map(function ($item) {
            return [
                'id' => $item['dep_id'],
                'name' => $item['dep_name'],
            ];
        }, $data);
        return ToolsService::returnAdmin($code, $data, '');
    }

}
