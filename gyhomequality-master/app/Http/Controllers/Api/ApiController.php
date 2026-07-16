<?php
/**
 * 空构造，预留
 */
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function predict(Request $request)
    {

        $content = $request->post("content");
        try{
            $res = requestPost(env("BIG_MODEL_APIURL"), ['query' => $content]);
            $response = $res->result->response ?? "";
            $code = 0;
        } catch (\Exception $e) {
            $code = 401;
            $msg = $e->getMessage();
        }
        return ToolsService::returnAdmin($code, $response, $msg ?? '');
    }

}
