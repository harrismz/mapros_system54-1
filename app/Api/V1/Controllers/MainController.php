<?php

namespace App\Api\V1\Controllers;

use Config;
use App\Board;
use App\Scanner;
use App\Critical;
use Tymon\JWTAuth\JWTAuth;
use App\Http\Controllers\Controller;
use App\Api\V1\Requests\BoardRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;
use App\Api\V1\Traits\LoggerHelper;
use App\Api\V1\Helper\Node;
use Dingo\Api\Exception\StoreResourceFailedException;
use Carbon\Carbon;

class MainController extends Controller
{
	use LoggerHelper;

	protected $allowedParameter = [
		'board_id',
		'nik',
		'ip',
		'guid',
		'is_solder',
		'modelname',
	];

	protected $returnValue = [
		'success' => true,
		'message' => 'data saved!',
		'node'    => null
	];

	private function getParameter (BoardRequest $request){
		$result = $request->only($this->allowedParameter);

		// setup default value for ip 
		$result['ip'] = (!isset($result['ip'] )) ? $request->ip() : $request->ip ;
		// setup default value for is_solder is false;
		$result['is_solder'] = (!isset($result['is_solder'] )) ? false : $request->is_solder ;

		return $result;
	}

	/*
	*
	* $currentStep must contains created_at && std_time
	*
	*/
	private function isMoreThanStdTime($currentStep){
		$now = Carbon::now();
		$lastScanned = Carbon::parse($currentStep['created_at']);

		// it'll return true if timeDiff is greater than std_time;
		return ( $now->diffInSeconds($lastScanned) > $currentStep['std_time'] );
	}

	public function store(BoardRequest $request ){
		$parameter = $this->getParameter($request);
		
		if( strlen($parameter['board_id']) >= 80 ){
			return $this->runCritical($parameter);
		}else{
			return $this->runNode($parameter);
		}
	}

	private function runCritical(array $parameter){
		$board_id = $parameter['board_id'];
		$result = [];
		$result['part_no'] 	= substr($board_id, 0, 15);
		$result['po'] 		= trim( substr($board_id, 16, 7));
		$result['qty'] 		= trim( substr($board_id, 24, 5));
		$result['unique_id'] 	= trim( substr($board_id, 30, 46));
		$result['supp_code']	= trim( substr($board_id, 31, 6));
		$result['production_date'] = trim( substr($board_id, 77, 8));
		$result['lotno'] = trim( substr($board_id, 86, 20));
		
		if( $result['production_date'] !='' || $result['lotno'] !='' ){
			// get data
			$data = $this->getCriticalScannerData($parameter);

			if(is_null($data)){
				throw new StoreResourceFailedException("Scanner with ip ".$parameter['ip']." not found", [
					'parameter' => $parameter
				]);
			}

			$data = $data->toArray();
			$data['scan_nik'] = $parameter['nik'];
			// save to critical;
			$result = array_merge($result, $data );
			
			if($this->isCriticalExists($result)){
				throw new StoreResourceFailedException("Part already scanned!", [
					'parameter' => $result
				]);
			}

			$critical = new Critical($result);
			$critical->save();

			return $this->returnValue;

		}else{
			throw new StoreResourceFailedException("It's not critical parts, don't need to scan this parts!", [
				'parameter' => $parameter
			]);
			
		}
	}

	private function getCriticalScannerData($parameter){
		return Scanner::select([
				// 'scanners.id',
				'lines.id as line_id',
				'lineprocesses.id as lineprocess_id',
			])
			->where('ip_address', $parameter['ip'])
			->leftJoin('lines', 'scanners.line_id', '=', 'lines.id')
			->leftJoin('lineprocesses', 'scanners.lineprocess_id', '=', 'lineprocesses.id')
			->first();
	}

	private function isCriticalExists($result){
		return Critical::where('unique_id', $result['unique_id'])
		->where('part_no', $result['part_no'])
		->where('po', $result['po'])
		->exists();
	}

	private function runNode($parameter){
		$node = new Node($parameter);
		
		// return $node;

		$this->returnValue['node'] = $node;

		if ($node->getModelType() == 'board') {
			return $this->processBoard($node);
		}

		/*if($node->getModelType() == 'board'){
			return 'critical';
		}*/ 
		if($node->getModelType() == 'ticket'){
			return $this->runProcedureTicket($node);
		}

		if($node->getModelType() == 'master'){
			return $this->runProcedureMaster($node);
		}
	}

	private function processBoard(Node $node){
		// cek current is null;
		// cek kondisi sebelumnya is null
		if(!$node->isExists()){ //board null
			
			// kalau sequence pertama, maka insert; gausah cek data sebelumnya dulu;
			if ($node->isFirstSequence() ) {
				// langsung input;
				$node->setStatus('IN');
				$node->setJudge('OK');
				if(!$node->save()){
					throw new StoreResourceFailedException("Error Saving Progress", [
						'message' => 'something went wrong with save method on model! ask your IT member'
					]);
				};

				//$this->returnValue['node'] = $node;
				$this->returnValue['line_code'] = 100;
				$this->returnValue['message'] = $node->getStatus() . ' / ' . $node->getJudge() ;

				return $this->returnValue;
			}

			$prevNode = $node->prev();

			if( $prevNode->getStatus() == 'OUT' ){
				// if it's rework, then judgment will get rework instead;
				$judgement = 'OK';
				// we not sure if it calling prev() twice or not, hopefully it's not;
				if($prevNode->getJudge() == 'NG'){                    
					// kalau dia NG
					// cek di table repair, ada engga datanya.
					if( !$prevNode->isRepaired()){ //kalau ga ada, masuk sini
						// kalau ga ada, maka throw error data is NG in prev stages! repair it first!
						throw new StoreResourceFailedException("Data is error in previous step, repair it first!", [
							'prevnode' => json_decode( $prevNode, true),
							'node'     => json_decode( $prevNode->next(), true) 
						]);
					}else{
						$judgement = 'REWORK';
					}
				}

				$node = $prevNode->next();
				$node->setStatus('IN');
				$node->setJudge($judgement);
				if(!$node->save()){
					throw new StoreResourceFailedException("Error Saving Progress", [
						'message' => 'something went wrong with save method on model! ask your IT member'
					]);
				};
				//$this->returnValue['node'] = $node;

				$this->returnValue['line_code'] = 131;
				$this->returnValue['message'] = $node->getStatus() . ' / ' . $node->getJudge() ;

				return $this->returnValue;
			}

			if( $prevNode->getStatus() == 'IN' ){
				// error handler
				if($prevNode->getModelType() !== 'board'){
					throw new StoreResourceFailedException("DATA NOT SCAN OUT YET AT PREVIOUS STEP!", [
						'message' => 'bukan board',
						'prevNode' => json_decode( $prevNode, true )
					]);
				}

				/*
				* cek logic below, I think we don't record the is solder in db;
				* it's mean it will always return false;
				*/

				// cek apakah solder atau bukan
				if (!$prevNode->is_solder) { //jika solder tidak diceklis, maka
					throw new StoreResourceFailedException("DATA NOT SCAN OUT YET IN PREVIOUS STEP!", [
						'message' => 'bukan solder',
						'node' => json_decode( $prevNode, true )
					]);    
				}

				if($prevNode->isExists('OUT','SOLDER')){ //cek data solder dengan status out
					throw new StoreResourceFailedException("DATA SOLDER ALREADY SCAN OUT!", [
						'prevNode' => json_decode( $prevNode, true )
					]);    
				};

				$node = $prevNode->next();
				$node->setStatus('OUT');
				$node->setJudge('SOLDER');
				if(!$node->save()){
					throw new StoreResourceFailedException("Error Saving Progress", [
						'message' => 'something went wrong with save method on model! ask your IT member'
					]);

				};
				//$this->returnValue['node'] = $node;
				$this->returnValue['line_code'] = 176;
				$this->returnValue['message'] = $node->getStatus() . ' / ' . $node->getJudge() ;

				return $this->returnValue;
			}

			// jika get status bukan in atau out maka throw error
			throw new StoreResourceFailedException("DATA NOT SCAN IN PREVIOUS STEP", [
				'node' => json_decode( $prevNode, true )
			]);
		}

		// disini node sudah exists
		if($node->getStatus() == 'OUT'){
			if($node->is_solder == false){
				// cek current judge
				if(!$node->getJudge() == 'REWORK'){
					if($node->isRepaired()){
						$node->setStatus('IN');
						$node->setJudge('REWORK');
						if(!$node->save()){
							throw new StoreResourceFailedException("Error Saving Progress", [
								'message' => 'something went wrong with save method on model! ask your IT member'
							]);
						}

						$this->returnValue['line_code'] = 278;
						$this->returnValue['message'] = $node->getStatus() . ' / ' . $node->getJudge() ;

						return $this->returnValue;
					}
				}

				throw new StoreResourceFailedException("DATA ALREADY SCAN OUT!", [
					'node' => json_decode( $node, true ),
				]);
			}

			//isExists already implement is solder, so we dont need to check it again.
			//if the code goes here, we save to immediately save the node;
			if($node->getJudge() == 'SOLDER'){
				throw new StoreResourceFailedException("DATA ALREADY SCAN OUT AS SOLDER!!", [
					'node' => json_decode($node, true )
				]);
			}

			$node->setStatus('IN');
			$node->setJudge('SOLDER');
			if(!$node->save()){
				throw new StoreResourceFailedException("Error Saving Progress", [
					'message' => 'something went wrong with save method on model! ask your IT member'
				]);
			}
			//$this->returnValue['node'] = $node;

			$this->returnValue['line_code'] = 205;
			$this->returnValue['message'] = $node->getStatus() . ' / ' . $node->getJudge() ;

			return $this->returnValue;
		}

		// return $node->getStatus();
		if($node->getStatus() == 'IN'){

			$currentStep = $node->getStep();
			if($node->is_solder){
				throw new StoreResourceFailedException("DATA ALREADY SCAN IN! you already scan solder with this scanner!",[
					'message' => 'you already scan solder with this scanner!'
				]);
			}

			// we need to count how long it is between now and step->created_at
			if( !$this->isMoreThanStdTime($currentStep)){
				// belum mencapai std time
				throw new StoreResourceFailedException("DATA ALREADY Scan IN", [
					'message' => 'you scan within std time '. $currentStep['std_time']. ' try it again later'
				]);
			}

			// disini kita harus ikut update status dari child node; jika ini adalah proses join;
			// save
			$node->setStatus('OUT');
			// it's mean to get current in process judgement, so when it's rework; it'll get rework
			$node->setJudge($node->getJudge());
			if(!$node->save()){
				throw new StoreResourceFailedException("Error Saving Progress", [
					'message' => 'something went wrong with save method on model! ask your IT member'
				]);
			} 

			// updateChildren hanya akan ter trigger ketika join saja;
			// method ini berfungsi untuk update board yg di scan skali, kemudian masuk ke dalam set;
			// board tsb tdk di scan langsung oleh user, melainkan di insert ketika parent nya di scan out pada proses 
			// join;
			$node->updateChildren();

			$this->returnValue['line_code'] = 239;
			$this->returnValue['message'] = $node->getStatus() . ' / ' . $node->getJudge() ;
			
			return $this->returnValue;
		}
	}

	private function runProcedureTicket(Node $node, $isRunningMaster=false ){
		// memastikan proses ini belum In && join proses
		if( ($node->isJoin()) && ( $node->isIn() == false ) && ($node->isSettingContainBoard()) && ($isRunningMaster == false) ){

			$node->setStatus('IN');
			$node->setJudge('OK');
			if(!$node->save()){
				throw new StoreResourceFailedException("Error Saving Progress", [
					'message' => 'something went wrong with save method on model! ask your IT member'
				]);
			}    

			throw new StoreResourceFailedException("view", [
				'node' => json_decode($node, true ),
				'nik' => $node->getNik(),
				'ip' => $node->getScanner()['ip_address'],
				'dummy_id' => $node->dummy_id, 
				'guid'=> ( $isRunningMaster == false ) ? $node->getGuidTicket() : $node->getGuidMaster(),
				'message' => 'runProcedureTicket',
			]);
		};

		return $this->processBoard($node);

	}

	private function runProcedureMaster(Node $node){
		if( ($node->isJoin()) && ( $node->isIn() == false ) && ($node->isSettingContain('ticket')) ){

			$node->setStatus('IN');
			$node->setJudge('OK');
			if(!$node->save()){
				throw new StoreResourceFailedException("Error Saving Progress", [
					'message' => 'something went wrong with save method on model! ask your IT member'
				]);
			}    

			throw new StoreResourceFailedException("view", [
				'node' => json_decode($node, true ),
				'nik' => $node->getNik(),
				'ip' => $node->getScanner()['ip_address'],
				'dummy_id' => $node->dummy_id, 
				'guid'=>    $node->getGuidMaster(),
				'message' => 'master procedures',
			]);
		};

		$this->runProcedureTicket($node , true );
		
		$this->returnValue['message'] = $node->getStatus() . ' / ' . $node->getJudge() ;
		return $this->returnValue;
	}


}
