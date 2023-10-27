<?php

namespace App\Api\V1\Controllers;

use Config;
use App\Board;
use App\Scanner;
use App\Critical;
use App\qrPanel;
use App\AdditionalLabel;
use Tymon\JWTAuth\JWTAuth;
use App\Http\Controllers\Controller;
use App\Api\V1\Requests\BoardRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;
use App\Api\V1\Traits\LoggerHelper;
use App\Api\V1\Helper\Node;
use Dingo\Api\Exception\StoreResourceFailedException;
use Carbon\Carbon;
use App\lineprocess;
use App\MasterManualInstruction;

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
		'judge',
		'symptom',
		'critical_parts', //new due to critical part scan;
		'locations', //added for touch up process
		'isRework',
		'manual_content', // optional
		'carton',
		'serial_number', //optional and cannot be null
		'fifoMode', // true or false;
		'qrPanel', // add from isQrPanel for Inspect 7
		'sirius', // add from isSirius for Inspect 7
		'pet', // add from isLdpePet for packing 1
		'ldpe' // add from isLdpePet for packing 1
	];

	protected $judge; // OK/NG only except from SOLDER;

	protected $returnValue = [
		'success' => true,
		// 'message' => 'data saved!',
		'message' => ' 35 data tersimpan!', //Ario 20180915
		'node'    => null
	];

	private function getParameter (BoardRequest $request){
		$result = $request->only($this->allowedParameter);
		// set judge based on user parameter on front end;
		$this->judge = $request->judge;
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
	private function isMoreThanStdTime($currentStep, $lineprocess ){
		if( !isset( $currentStep['created_at']) ){
			//kalau currentStep nya ga contain created_at, return true untuk handle type node baru, lcd part
			return true; 
		}

		$now = Carbon::now();
		$lastScanned = Carbon::parse($currentStep['created_at']);

		// it'll return true if timeDiff is greater than std_time;
		$result = ( $now->diffInSeconds($lastScanned) > $lineprocess['std_time'])?true:$now->diffInSeconds($lastScanned);
		return $result;
	}

	public function store(BoardRequest $request ){
		$parameter = $this->getParameter($request);
	
		if( setting("admin.check_serial_number", true ) ){
			if($request->has('serial_number')) {
				$regex = setting('admin.serial_number_regex', '/(\w+)? ?(\d+[A-Za-z]\d+)/' );
				if( preg_match( $regex , $request->serial_number )) {
					// DO NOTHING
				} else {
					throw new StoreResourceFailedException("'{$request->serial_number}' bukan serial number. tolong check ulang.", [
						"serial_number" => $request->serial_number,
						"regex" => $regex,
						'parameter' => $parameter
					]);
				}
			}
		}

		/*isset($parameter['critical_part'])*/
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
				throw new StoreResourceFailedException("SCANNER DENGAN IP ".$parameter['ip']." TIDAK DITEMUKAN. CEK PENGATURAN ADMIN!", [
					'parameter' => $parameter
				]);
			}

			$data = $data->toArray();
			$data['scan_nik'] = $parameter['nik'];
			// save to critical;
			$result = array_merge($result, $data );

			if($this->isCriticalExists($result)){
				throw new StoreResourceFailedException("PART SUDAH DI SCAN!", [
					'parameter' => $result
				]);
			}

			$critical = new Critical($result);
			$critical->save();

			return $this->returnValue;

		}else{
			throw new StoreResourceFailedException("BUKAN CRITICAL PART. TIDAK USAH SCAN", [
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

		if($node->getModelType() == 'ticket'){
			return $this->runProcedureTicket($node);
		}

		if($node->getModelType() == 'master'){
			return $this->runProcedureMaster($node);
		}

		return $this->processBoard($node); //lcd atau parts
	}

	private function processBoard(Node $node){

		// cek current is null;
		// cek kondisi sebelumnya is null
		if(!$node->isExists()){ //board null
			// kalau sequence pertama atau pengaturan prev_node_checking false,
			// maka insert; gausah cek data sebelumnya dulu;
			if ($node->isFirstSequence() || (setting('admin.prev_node_checking') == false) || $node->isRework() ) {
				// langsung input;
				$node->setStatus('IN');
				$node->setJudge('OK'); //kalau IN mah ga bisa NG
				if(!$node->save()){
					// throw new StoreResourceFailedException("Error Saving Progress", [
					throw new StoreResourceFailedException("Terjadi Error Ketika Menyimpan Progress", [ //Ario 20180915
						'message' => '172 : something went wrong with save method on model! ask your IT member'
					]);
				};

				//$this->returnValue['node'] = $node;
				$this->returnValue['line_code'] = 100;
				$this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus() . ' / ' . $node->getJudge() ;

				return $this->returnValue;
			}

			$prevNode = $node->prev();

			if( $prevNode->getStatus() == 'OUT' ){
				// if it's rework, then judgment will get rework instead;

				if(($prevNode->getJudge() != 'SOLDER') && ($node->is_solder == true)){
					throw new StoreResourceFailedException("HAPUS CHECKLIST SOLDER !!!", [
						// 'message' => 'Scan in this process',
						'message' => '192 Scan in this process',
						'prevNode' => json_decode( $prevNode, true )
					]);
				}

				$judgement = 'OK'; //kalau IN mah ga bisa NG;
				// we not sure if it calling prev() twice or not, hopefully it's not;
				if($prevNode->getJudge() == 'NG'){ //kita bisa tambah or $prevNode->getJudge() == 'REWORK'                   
					// kalau dia NG
					// cek di table repair, ada engga datanya.
					// if( !$prevNode->isRepaired()){ //kalau ga ada, masuk sini
						
					// kalau ga ada, maka throw error data is NG in prev stages! repair it first!
					
					/*
						kalau prev node is NG, throw the NG walaupun sudah di input oleh teknisi.
						karena kebutuhan sekarang (2018-11-19), minimal, set harus balik lagi ke proses terakhir.
						ini mencegah set di alirkan ke proses setelahnya jika muncul pesan error "data belum di scan rework diproses sebelumnya. 

					*/

					$step = ( is_null($prevNode->getLineprocess()) ) ? '': $prevNode->getLineprocess()['name'];
					throw new StoreResourceFailedException("DATA ". $prevNode->getDummyId() ." ERROR DI PROSES SEBELUMNYA ( {$step} ) & BELUM DIPERBAIKI! klik see details untuk info lebih lanjut", [
						'message' => 'perbaiki dengan cara input data repair oleh teknisi & scan rework',
					]);

					// }else{
					// 	$judgement = 'REWORK';
					// }
				}

				$node = $prevNode->next();
				$node->setStatus('IN');
				$node->setJudge($judgement);
				if(!$node->save()){
					// throw new StoreResourceFailedException("Error Saving Progress", [
					throw new StoreResourceFailedException("Terjadi Error Ketika Menyimpan Progress", [ //Ario 20180915	
						'message' => '219 something went wrong with save method on model! ask your IT member'
					]);
				};
				//$this->returnValue['node'] = $node;

				$this->returnValue['line_code'] = 131;
				$this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus() . ' / ' . $node->getJudge() ;

				return $this->returnValue;
			}

			if( $prevNode->getStatus() == 'IN' ){
				// error handler
				if($prevNode->getModelType() !== 'board'){
					$step = ( is_null($prevNode->getLineprocess()) ) ? '': $prevNode->getLineprocess()['name'];
					throw new StoreResourceFailedException("DATA BELUM DI SCAN OUT DI PROSES SEBELUMNYA. ( ".$step." )", [
						'message' => '235 bukan board',
						'prevNode' => json_decode( $prevNode, true )
					]);
				}

				/*
				* cek logic below, I think we don't record the is solder in db;
				* it's mean it will always return false;
				*/


				if(($prevNode->isExists('IN','SOLDER')) && ( $node->is_solder == false)){ //cek data solder dengan status out
					throw new StoreResourceFailedException("DATA BELUM DI SCAN OUT DI PROSES SEBELUMNYA. ( SOLDER )", [
						'message' => '248 SCAN OUT SOLDER',
						'prevNode' => json_decode( $prevNode, true )
					]);  
				};

				// cek apakah solder atau bukan
				if (!$prevNode->is_solder) { //jika solder tidak diceklis, maka
					$step = ( is_null($prevNode->getLineprocess()) ) ? '': $prevNode->getLineprocess()['name'];
					throw new StoreResourceFailedException("DATA BELUM DI SCAN OUT DI PROSES SEBELUMNYA. ( ".$step." )", [
						'message' => '257 bukan solder',
						'node' => json_decode( $prevNode, true )
					]);    
				}

				if($prevNode->isExists('OUT','SOLDER')){ //cek data solder dengan status out
					throw new StoreResourceFailedException("DATA SOLDER SUDAH SCAN OUT!", [
						'prevNode' => json_decode( $prevNode, true )
					]);    
				};



				$node = $prevNode->next();
				$node->setStatus('OUT');
				$node->setJudge('SOLDER');
				if(!$node->save()){
					// throw new StoreResourceFailedException("Error Saving Progress", [
					throw new StoreResourceFailedException("Terjadi Error Ketika Menyimpan Progress", [ //Ario 20180915
						'message' => '276 something went wrong with save method on model! ask your IT member'
					]);

				};
				//$this->returnValue['node'] = $node;
				$this->returnValue['line_code'] = 176;
				$this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus() . ' / ' . $node->getJudge() ;

				return $this->returnValue;
			}

			// jika get status bukan in atau out maka throw error
			$step = ( is_null($prevNode->getLineprocess()) ) ? '': $prevNode->getLineprocess()['name'];
			throw new StoreResourceFailedException("DATA BELUM DI SCAN IN DI PROSES SEBELUMNYA. ( ".$step." )", [
				'node' => json_decode( $prevNode, true )
			]);
		}

		// disini node sudah exists
		if($node->getStatus() == 'OUT'){
			if($node->is_solder == false){
				// cek current judge
				// if($node->getJudge() != 'REWORK'){
				if ($node->hasRework() === false ) {
					#jika belum rework masuk sini;
					/*
						isRepaired harusnya verify juga bahwa current node punya record NG.
						untuk pengamanan in case someone input into table repair without input 
						record NG. that would be huge pain. --> Done!! (2018-11-19)
					*/
					if($node->isRepaired()){
						// cek apakah Start Id == Current lineprocess;
						/*
							getStartId return start_id dari lineprocessNg, bukan lagi dari 
							current lineprocess;
						*/
						if ($node->getLineprocess()['id'] == $node->getStartId() ) {
							# code...
							$node->setStatus('IN');
							$node->setJudge('REWORK');
							if(!$node->save()){
								// throw new StoreResourceFailedException("Error Saving Progress", [
								throw new StoreResourceFailedException("Terjadi Error Ketika Menyimpan Progress", [ //Ario 20180915	
									'message' => '305 something went wrong with save method on model! ask your IT member'
								]);
							}

							$this->returnValue['line_code'] = 278;
							$this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus().' / '. $node->getJudge();

							return $this->returnValue;
						}else{
							// add more if statement here.
							/*
								check if current process is after ng_lineprocess_id;
								if False, run current code.
								if not, let it to shows error 'data sudah di scan out di proses ini.'
							*/
							if ($node->isAfterNgProcess() == false ) {
								# code...
								$isNodeBeforeStartId = $node->isBeforeStartId();
								if ($isNodeBeforeStartId) {
									# kalau node adalah first sequences, kita harus ambil NG nya dimana.
									# untuk memunculkan pesan error yg benar.
									$step = $node->getLineprocessNgName();
									$errorMessages = "DATA HARUS SCAN REWORK MULAI DARI PROSES ( ".$step." )";
								}
								// cek prev node nya;
								$prevNode = $node->prev();
								$prevIsExists = $prevNode->hasRework(); //$prevNode->isExists('OUT', 'REWORK');
								// untuk rework kedua jadi error. gimana caranya biar isExists bisa return false based on 
								// repair ke berapa. itu kan data repair pertama, sedangkan yg kita check repair kedua;
								// how ??
								
								if ($prevIsExists) {
									# kalau prev node nya exists, boleh input di current positions;
									$node->next(); // maju
									$node->setStatus('IN');
									$node->setJudge('REWORK');
									if(!$node->save()){
										// throw new StoreResourceFailedException("Error Saving Progress", [
										throw new StoreResourceFailedException("Terjadi Error Ketika Menyimpan Progress", [ //Ario 20180915	
											'message' => '305 something went wrong with save method on model! ask your IT member'
										]);
									}

									$this->returnValue['line_code'] = 352;
									$this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus().' / '. $node->getJudge();

									return $this->returnValue;
								}else{
									// selain itu error data belum di scan di process sebelumnya;
									if (!$isNodeBeforeStartId) {
										#jika isNodeFirstSequence false, maka error message nya masuk sini.
										# selain itu, $step & $errorMessages sudah di declare sebelumnya.
										$step = ( is_null($prevNode->getLineprocess()) ) ? '': $prevNode->getLineprocess()['name'];
										$errorMessages = "DATA BELUM DI SCAN REWORK DI PROSES SEBELUMNYA. ( ".$step." )";
									}

									throw new StoreResourceFailedException( $errorMessages , [
										// 'message' => 'bukan board',
										'prevNode' => json_decode( $prevNode, true )
									]);
								}
							}
						}
					}
				}
				// }

				$getJudge = $node->getJudge();
				if( ( $getJudge == 'OK') || ($getJudge == 'NG') || ( $getJudge == 'REWORK') ){
					try {
						$lineprocessNgName = $node->getLineprocessNgName();
					} catch (StoreResourceFailedException $e) {
						$lineprocessNgName = null;
					}

					throw new StoreResourceFailedException("DATA '".$node->getDummyId()."' SUDAH DI SCAN OUT {$getJudge} DI PROSES INI! klik see details", [
						'message' => 'jika ini NG untuk ke sekian kali. mohon pastikan anda sudah insert data repair ulang!',
						'sudah_repair' => $node->isRepaired(),
						'jumlah_repair' => $node->repairCount(),
						'ng_terakhir'=> $lineprocessNgName,
						'has_rework' => $node->hasRework(),
						'proses NG' => $node->getAllNgRecord(),
						'node' => json_decode( $node, true ),
					]);
				}

				// kalau status skrg out & judge solder, itu artinya blm scan in proses terkini;
				if($node->getJudge() == 'SOLDER'){
					$node->setStatus('IN');
					$node->setJudge($this->judge);
					if(!$node->save()){
						// throw new StoreResourceFailedException("Error Saving Progress", [
						throw new StoreResourceFailedException("Terjadi Error Ketika Menyimpan Progress", [	//Ario 20180915
							'message' => '329 something went wrong with save method on model! ask your IT member'
						]);
					}

					$this->returnValue['line_code'] = 307;
					$this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus() . ' / ' . $node->getJudge() ;

					return $this->returnValue;
				}
			}

			//isExists already implement is solder, so we dont need to check it again.
			//if the code goes here, we save to immediately save the node;
			if($node->getJudge() == 'SOLDER'){
				throw new StoreResourceFailedException("DATA '".$node->getDummyId()."' SUDAH SCAN OUT SOLDER!!", [
					'node' => json_decode($node, true )
				]);
			}

			if($node->isExists('OUT', 'SOLDER')){
				throw new StoreResourceFailedException("DATA '".$node->getDummyId()."' SUDAH SCAN OUT SOLDER!!", [
					'MESSAGE' => 'DATA SUDAH SCAN OUT SOLDER DISINI DAN SUDAH IN OUT OK ',
					'node' => json_decode($node, true ),

				]);
			}

			$node->setStatus('IN');
			$node->setJudge('SOLDER');
			if(!$node->save()){
				// throw new StoreResourceFailedException("Error Saving Progress", [
				throw new StoreResourceFailedException("Error Saving Progress", [ //Ario 20180915 
					'message' => '361 something went wrong with save method on model! ask your IT member'
				]);
			}
			//$this->returnValue['node'] = $node;

			$this->returnValue['line_code'] = 205;
			$this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus() . ' / ' . $node->getJudge() ;

			return $this->returnValue;
		}

		// return $node->getStatus();
		if($node->getStatus() == 'IN'){

			// if( ($node->is_solder == true) && ($node->getJudge() == 'SOLDER' ) ){
			// 	throw new StoreResourceFailedException("DATA '".$node->getDummyId()."' SUDAH SCAN IN SOLDER! SCAN OUT SOLDER DENGAN SCANNER BERIKUTNYA!",[
			// 		'message' => 'SCAN SOLDER DENGAN PROSES BERIKUTNYA'
			// 	]);
			// }

			if( ($node->is_solder) ){

				if($node->getJudge() == 'SOLDER'){
					throw new StoreResourceFailedException("DATA '".$node->getDummyId()."' SUDAH SCAN IN SOLDER! SCAN OUT SOLDER DENGAN SCANNER BERIKUTNYA!",[
						'message' => 'SCAN SOLDER DENGAN PROSES BERIKUTNYA'
					]);
				}
				if( $node->getJudge() != 'SOLDER' ){
					throw new StoreResourceFailedException("HAPUS CHECKLIST SOLDER UNTUK SCAN OUT PROSES SAAT INI !!",[
						'message' => 'SCAN OUT PROCESS SAAT INI'
					]);
				}
			}
			if($node->getJudge() == 'SOLDER'){
				throw new StoreResourceFailedException("DATA '".$node->getDummyId()."' SUDAH SCAN OUT, LANJUTKAN PROSES BERIKUTNYA.",[
					'message' => 'NEXT PROSES'
				]);
			}

			$currentStep = $node->getStep();
			$lineprocess = $node->getLineprocess();
			$isMoreThanStdTime = $this->isMoreThanStdTime($currentStep, $lineprocess );
			$selisih = ( (int) $lineprocess['std_time'] - (int) $isMoreThanStdTime);

			/*return [
				'currentStep' => $currentStep,
				'lineprocess' => $lineprocess,
				'isMoreThanStdTime' => $isMoreThanStdTime,
				'selisih' => $selisih,
			];*/

			// we need to count how long it is between now and step->created_at
			if( $isMoreThanStdTime !== true ){

				// belum mencapai std time
				throw new StoreResourceFailedException("DATA {$node->getDummyId()} SUDAH SCAN IN ! COBA {$selisih} DETIK LAGI", [
					'message' => 'you scan within std time '. $lineprocess['std_time']. ' try it again later'
				]);
			}

			// checking inspection log before out
			if(setting('admin.check_inspection_log')){
				if($node->hasInspect()){
					$node->InspectionLogOk(); //if not it will throw exception;
				}
			}

			$alreadyCheckInstructionManual = false;
			if(setting('admin.check_instruction_manual') && ($node->checkInstructionManual()) ) {
				/* cek apakah front end kirm manual instruction as parameter */
				if(!$node->hasInstructionManual()) {
					throw new StoreResourceFailedException('TOLONG SCAN MANUAL INSTRUCTIONS QRCODE !', [
						'message' => 'tolong klik button show button Manual instruction field untuk memunculkan textfieldnya'
					]);
				}

				/* check the setting value */
				if( setting('admin.compare_manual_instruction_to_modelname') ) {
					$alreadyCheckInstructionManual = true;
					$content  = $node->getParameter()['manual_content'];

					if(!$node->CompareModelname($content)) {
						$node->checkContentWithModelname($content);
					}
				}
				
			}

			/* 
				untuk mencegah pengecekan manual instruction dilakuan dua kali, maka yang dibawah ini akan di check ketika 
				pengaturan check_instruction_manual di non aktifkan.
			*/
			if(!$alreadyCheckInstructionManual){
				// check setting compare manual instruction to modelname
				if( setting('admin.compare_manual_instruction_to_modelname') ) {
					/* check apakah users kirim instruction manual content */
					if(($node->checkInstructionManual())) {
						$content  = $node->getParameter()['manual_content'];

						if(!$node->CompareModelname($content)) {
							$node->checkContentWithModelname($content);
						}
					}
				}
			}

			if(setting('admin.check_carton') && ($node->checkCarton()) ) {
				if(!$node->hasCarton()) {
					throw new StoreResourceFailedException('TOLONG SCAN KARTON QRCODE !', [
						'message' => 'tolong klik button show button Carton field untuk memunculkan textfieldnya',
						'node' => \json_decode( $node ,  true )
					]);
				}
			}
			// disini kita harus ikut update status dari child node; jika ini adalah proses join;
			// save
			$node->setStatus('OUT');
			// it's mean to get current in process judgement, so when it's rework; it'll get rework
			// also, if its OK, it's get the users judge parameter;
			// $judge = ($node->getJudge() == 'OK')? $this->judge : $node->getJudge();
			// kalau dari parameter nya NG, maka set NG. selain itu, ambil dari judge node;
			$judge = ($this->judge == 'NG')? 'NG' : $node->getJudge(); 
			$node->setJudge($judge);
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
			$this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus() . ' / ' . $node->getJudge() ;

			return $this->returnValue;
		}
	}

	private function runProcedureTicket(Node $node, $isRunningMaster=false ){
		// cek current node exists atau ngga 
		if( $node->isExists() === false && $isRunningMaster === false ){
			// kalau node ini first sequences, gausah cek ke belakang.
			if( ($node->isFirstSequence() == false) ){
				if( setting('admin.prev_node_checking') )
				{
					/* kalau bukan rework, baru check ke belakang. kalau rework, gausah. */
					if( $node->isRework() == false ){
						$prevNode = $node->prev();
						// cek prev node sudah out atau belum
						if ($prevNode->getStatus() !== 'OUT') {
							// jika get status bukan in atau out maka throw error
							$step = ( is_null($prevNode->getLineprocess()) ) ? '': $prevNode->getLineprocess()['name'];
							throw new StoreResourceFailedException("DATA BELUM DI SCAN DI PROSES SEBELUMNYA. ( ".$step." )", [
								'node' => json_decode( $prevNode, true )
							]);
						}
						// go back to where it is;
						$node = $node->next();
					}
				}
			}
		}

		// memastikan proses ini belum In && join proses
		if( ($node->isJoin()) && ( $node->isIn() == false ) && ($node->isSettingContainChildrenOf('ticket')) && ($node->isSettingContain('ticket')) ){
			// lcd in nya dari sini, that's why ketika masuk processBoard, dia langsung out;
			$node->setStatus('IN');
			$node->setJudge("OK"); //in harus selalu OK, no matter what;
			if(!$node->save()){
				// throw new StoreResourceFailedException("Error Saving Progress", [
				throw new StoreResourceFailedException("Terjadi Error Ketika Menyimpan Progress", [ //Ario 20180915
					'message' => '442 something went wrong with save method on model! ask your IT member'
				]);
			}
		};

		//cek apakah ticket sudah punya anak;
		if(!$node->hasChildren() && ($node->isSettingContainChildrenOf('ticket')) && ($node->isSettingContain('ticket')) ){ 
			//kalau belum, return view lagi;
			throw new StoreResourceFailedException("view", [
				'node' => json_decode($node, true ),
				'nik' => $node->getNik(),
				'ip' => $node->getScanner()['ip_address'],
				'dummy_id' => $node->dummy_id, 
				'guid'=> ( $isRunningMaster == false ) ? $node->getGuidTicket() : $node->getGuidMaster(),
				'join_times_left' => $node->getJoinTimesLeft(),
				'message' => 'runProcedureTicket',
			]);
		}

		// kalau bukan rework, baru check mecha counter
		if($node->isRework() == false){
			// check if mecha counter is hit the maximum scan yet
			$node->checkMechaCounter();
		}

		return $this->processBoard($node);

	}

	private function runProcedureMaster(Node $node){
		// cek prev node on master
		if( $node->isExists() === false ){
			// kalau node ini first sequences, gausah cek ke belakang.
			if( ($node->isFirstSequence() == false) ){
				if( setting('admin.prev_node_checking') )
				{
					/* kalau bukan rework, baru check ke belakang. kalau rework, gausah. */
					if( $node->isRework() == false ){
						$prevNode = $node->prev();
						// cek prev node sudah out atau belum
						if ($prevNode->getStatus() !== 'OUT') {
							// jika get status bukan in atau out maka throw error
							$step = ( is_null($prevNode->getLineprocess()) ) ? '': $prevNode->getLineprocess()['name'];
							throw new StoreResourceFailedException("DATA BELUM DI SCAN DI PROSES SEBELUMNYA. ( ".$step." )", [
								'node' => json_decode( $prevNode, true )
							]);
						}
						// go back to where it is;
						$node = $node->next();
					}
				}
			}
		}

		if( ($node->isJoin()) && ( $node->isIn() == false ) && $node->isSettingContainChildrenOf('master') && $node->isSettingContain('master') ){

			$node->setStatus('IN');
			$node->setJudge("OK"); //in harus selalu ok, no matter what;
			if(!$node->save()){
				// throw new StoreResourceFailedException("Error Saving Progress", [
				throw new StoreResourceFailedException("Terjadi Error Ketika Menyimpan Progress", [ //Ario 20180915
					'message' => '468 something went wrong with save method on model! ask your IT member'
				]);
			}
		};

		// cek master sudah punya anak atau belum, kalau belum, return view lagi;
		if( (!$node->hasChildren()) && ( $node->isSettingContainChildrenOf('master') ) && ($node->isSettingContain('master')) ){
			throw new StoreResourceFailedException("view", [
				'node' => json_decode($node, true ),
				'nik' => $node->getNik(),
				'ip' => $node->getScanner()['ip_address'],
				'dummy_id' => $node->dummy_id, 
				'guid'=>    $node->getGuidMaster(),
				'join_times_left' => $node->getJoinTimesLeft(),
				'message' => 'master procedures',
			]);
		}

		return $this->runProcedureTicket($node , true );

		// $this->returnValue['message'] = $node->getDummyId() .' : '. $node->getStatus() . ' / ' . $node->getJudge() ;
		// return $this->returnValue;
	}

	public function destroy(BoardRequest $request){
		$parameter = $this->getParameter($request);
		$node = new Node($parameter);

		// $this->returnValue['message'] = $node->delete() .' Data Deleted!!';
		$this->returnValue['message'] = $node->delete() .' Data Terhapus!!'; //Ario 20180915
		return $this->returnValue;
	}

}
