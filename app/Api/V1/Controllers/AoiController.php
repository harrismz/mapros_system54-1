<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Http\Request;
use App\Api\V1\Requests\AoiRequest;
use App\AOI;

class AoiController extends Controller
{
	public function index(AoiRequest $request)
	{
		$aoi = AOI::select([
			'barcode',
			'userjudgment'
		])->where('barcode', $request->board_id);

		if ($aoi->first()) {
			return [
				'success' => true,
				'data' => $aoi->first(),
				'status' => 'OUT',
				'judge' => ($aoi->exists()) ? 'OK' : 'NG'
			];
		} else {
			$changeToMother = $this->changeToMotherCode($request->board_id);
			$aoi_convert = AOI::select([
				'barcode',
				'userjudgment'
			])->where('barcode', $changeToMother);

			if (!$aoi_convert->first()) {
				// "Data '{$request->board_id}' NG atau tidak ditemukan di SMT!!"
				throw new StoreResourceFailedException("Board '{$request->board_id}' belum inspect AOI atau NG AOI. Silahkan confirm SMT ", [
					'message' => 'data tidak ditemukan pada table AOI!'
				]);
			}

			return [
				'success' => true,
				'data' => $aoi_convert->first(),
				'status' => 'OUT',
				'judge' => ($aoi_convert->exists()) ? 'OK' : 'NG'
			];
		}

		// if (!$aoi->first()) {
		// 	// "Data '{$request->board_id}' NG atau tidak ditemukan di SMT!!"
		// 	throw new StoreResourceFailedException("Board '{$request->board_id}' belum inspect AOI atau NG AOI. Silahkan confirm SMT ", [
		// 		'message' => 'data tidak ditemukan pada table AOI!'
		// 	]);
		// }

		// return [
		// 	'success' => true,
		// 	'data' => $aoi->first(),
		// 	'status' => 'OUT',
		// 	'judge' => ($aoi->exists()) ? 'OK' : 'NG'
		// ];
	}

	public function changeToMotherCode($boardid)
	{
		return substr_replace($boardid, '00', 12, -10);
	}
}
