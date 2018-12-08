<?php

namespace App\Api\V1\Controllers;

use Config;
use Tymon\JWTAuth\JWTAuth;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;
use App\Symptom;

class SymptomController extends Controller
{
    public function __construct(){
        $this->model = new Symptom;
    }

    public function all(Request $request){
    	$limit = (isset( $request->limit)) ? $request->limit : 25; //default value is 25;
    	$result = $this->model
    	->select(['code', 'category']);

    	if(isset($request->q)){
    		$result = $result->where('code',  'like', "%{$request->q}%" )
    		->orWhere('category', 'like', "%{$request->q}%" );
		}
		
		if ($request->has('category')) {
			$result = $result->where('category','like', "%{$request->category}%" );
		}

		if($request->has('include_symptom_id')) {
			$result = $result->whereIn('code', $request->include_symptom_id );
		}

    	return $result = $result->paginate($limit);
    }
}
