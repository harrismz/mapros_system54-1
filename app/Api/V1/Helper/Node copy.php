<?php

/**
 * 
 */

namespace App\Api\V1\Helper;

use App\Model;
use App\Board;
use App\Part;
use App\Ticket;
use App\Critical;
use App\Scanner;
use App\Sequence;
use App\Mastermodel;
use App\Repair;
use App\Lineprocess;
use App\ColumnSetting;
use App\InspectionLog;
use App\lineprocessInspect;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Guid;
use App\Master;
use GuzzleHttp\Client;
use Guzzle\Http\Exception\BadResponseException;
use App\Endpoint;
use App\Symptom;
use App\Http\Controllers\Controller;
use App\Api\V1\Interfaces\ColumnSettingInterface;
use App\Api\V1\Interfaces\CriticalPartInterface;
use App\Api\V1\Interfaces\RepairableInterface;
use App\Api\V1\Interfaces\LocationInterface;
use App\Api\V1\Interfaces\CheckBoardDupplicationInterface;
use App\Api\V1\Traits\ColumnSettingTrait;
use App\Api\V1\Traits\CriticalPartTrait;
use App\Api\V1\Traits\RepairableTrait;
use App\Api\V1\Traits\LocationTrait;
use App\Api\V1\Traits\CheckBoardDupplicationTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Api\V1\Interfaces\ManualInstructionInterface;
use App\Api\V1\Traits\ManualInstructionTrait;
use App\Api\V1\Interfaces\CartonInterface;
use App\Api\V1\Traits\CartonTrait;

class Node implements
	ColumnSettingInterface,
	CriticalPartInterface,
	RepairableInterface,
	LocationInterface,
	ManualInstructionInterface,
	CartonInterface
{
	use ColumnSettingTrait,
		CriticalPartTrait,
		RepairableTrait,
		LocationTrait,
		CheckBoardDupplicationTrait,
		ManualInstructionTrait,
		CartonTrait;

	protected $model; // App\Board, App\Master , App\Ticket, or App\Part;
	protected $model_code; // 5 char atau 11 char awal
	protected $allowedStatus = [
		'IN',
		'OUT'
	];
	protected $ticketCriteria = [
		'__MST', '__PNL', '__MCH'
	];
	/* protected $allowedParameter = [
		'board_id',
        'nik',
        'ip',
        'is_solder',
        'guid',
        'locations', // for touch up process;
	]; */
	public $scanner_id; //contain scanner id;
	public $scanner; //contain scanner object (App\Scanner)
	public $dummy_id; //it could be ticket_no, board_id, ticket_no_master based on the model
	public $guid_master;
	public $guid_ticket;
	public $status;
	public $judge = 'OK';
	public $nik;
	public $board;
	public $modelname;
	public $lotno;
	public $lineprocess;
	public $is_solder; // is solder is flag to determine wheter it is solder process or not;
	public $process; // process is contain data from lineprocess.process exp : 1, 2, 3, 5, 4 dst
	protected $dummy_column; // it's board_id, ticket_no, or ticket_no_master based on model_type
	protected $model_type; // it's board, ticket, or master
	protected $id_type; //board, panel, master or mecha;
	protected $step; // step is contain data in table boads, tickets, or master based on modeltype
	//protected $column_setting; //move to column setting trait
	public $unique_column; // its contain board id, guid_ticket, or guid_master based model type
	public $unique_id; // its contain board id, guid_ticket, or guid_master based model type for repair purposes
	protected $parameter;
	protected $key; //array index of sequence
	protected $symptom;
	protected $joinTimesLeft = 0; //jumlah sisa join, always update when hasChildren triggered
	// for conditional error view;
	protected $confirmation_view_error = 'confirmation-view';
	// protected $criticalParts; //dari CriticalPartTrait
	protected $firstSequence = false;
	protected $locations; // new comer dari trait LocationTrait. (implementasi touch up)

	function __construct($parameter, $debug = false)
	{
		// kalau sedang debugging, maka gausah run construct
		if (!$debug) {
			$this->parameter = $parameter;
			//get first 5 or 11 character of this dummy id / parameter['board_id']
			$this->initModelCode(); //dependence to $this->parameter;
			// setup model (board, ticket, or master)
			$this->setModel($parameter);
			// setup lot no
			$this->setLotno($parameter['board_id']);
			// setup scanner_id;
			$ip = $parameter['ip'];
			$this->setScannerId($ip);

			// setup is_solder
			$this->is_solder = $parameter['is_solder'];
			// setup nik
			$this->nik = $parameter['nik'];
			// setup board_id
			$this->dummy_id = $parameter['board_id'];

			// set lineprocess
			$this->setLineprocess($this->scanner['lineprocess_id']);
			// set column setting;
			// dependence to lineprocess;
			$this->initColumnSetting();

			// get prev guid id; this initGuid need to be called after initColumnSetting
			// dependence to initColumnSetting;
			$guidId = (isset($parameter['guid'])) ? $parameter['guid'] : null;
			$this->initGuid($guidId);

			// get board type from big & set into board properties
			// dependence to initGuid
			$this->getBoardType();
			// run to get sequence and set to process attribute
			// dependence to setScannerId, setModel, getBoardType()
			$this->getSequence();
			// set status & judge
			//dependence to setLineprocess;
			$this->loadStep();
			// set $key as current node positions
			$this->initCurrentPosition();
			// set the critical parts;
			$this->initCriticalPart();
			// set the the locations data
			$this->initLocations();
			// cek board dupplication if setting('admin.check_board_dupplication') is true
			if ($this->isRework() == false) {
				$this->checkBoardDupplication();
			}
		}
	}

	public function __toString()
	{
		/*
		* for every properties that return here, need to be define earlier, not define in method,
		* so even when error occures, those attributes still exists as null;
		* Exp : you add $this->age properties in method, but doesn't declare those var in class, 
		* it'll show error without proper info what is the error; 
		*/
		return json_encode([
			'scanner_id' 	=> $this->scanner_id,
			'scanner'		=> $this->scanner,
			'id_type'		=> $this->id_type,
			'unique_column'	=> $this->unique_column,
			'unique_id'		=> $this->unique_id,
			'dummy_column'	=> $this->dummy_column,
			'dummy_id' 		=> $this->dummy_id,
			'guid_master' 	=> $this->guid_master,
			'guid_ticket' 	=> $this->guid_ticket,
			'status' 		=> $this->status,
			'judge' 		=> $this->judge,
			'nik' 			=> $this->nik,
			'board'			=> $this->board,
			'process'		=> $this->process,
			'lineprocess'	=> $this->lineprocess,
			'step'			=> $this->step,
			'model'			=> $this->model,
			'column_setting' => $this->column_setting,
			'modelname'		=> $this->modelname,
			'lotno'			=> $this->lotno,
			'critical_parts' => $this->criticalParts,
			'parameter'		=> $this->parameter,
		]);
	}

	public function getNik()
	{
		return $this->nik;
	}

	// automaticly triggered on instantiate
	public function setScannerId($scanner_ip)
	{
		$scanner = Scanner::select([
			'id',
			'line_id',
			'lineprocess_id',
			'name',
			'mac_address',
			'ip_address',
		])->where('ip_address', $scanner_ip)->first();

		if (is_null($scanner)) {
			throw new StoreResourceFailedException("Scanner dengan ip=" . $scanner_ip . " tidak ditemukan. Mungkin scanner belum di register oleh admin", [
				'ip_address' => $scanner_ip,
				'message' => 'scanner not registered yet'
			]);
		}

		$this->scanner = $scanner;
		$this->scanner_id = $scanner['id'];
	}

	public function initColumnSetting()
	{
		if ($this->lineprocess == null) {
			throw new StoreResourceFailedException("Lineprocess Tidak Ditemukan", [
				'node' => $this
			]);
		}

		$this->setColumnSetting($this->lineprocess->columnSettings);
	}

	public function initCriticalPart()
	{
		$critical = (isset($this->parameter['critical_parts'])) ? $this->parameter['critical_parts'] : null;
		if (!is_null($critical)) {
			$this->setCriticalPart($critical);
		}
	}

	public function setDummyId($dummy_id)
	{
		$this->dummy_id = $dummy_id;
	}

	// dipanggil di setmodel
	public function setIdType($type)
	{
		$this->id_type = $type;
	}
	// dipanggil di getSequence untuk determine ini id type apa;
	public function getIdType()
	{
		return $this->id_type;
	}

	public function initModelCode()
	{
		if (strlen($this->parameter['board_id']) == 24) {
			$this->model_code = substr($this->parameter['board_id'], 0, 11);
		} else if (strlen($this->parameter['board_id']) <= 23) { //ini 23 untuk akomodir mecha serial yg 24 character
			$this->model_code = substr($this->parameter['board_id'], 0, 5);
		} else {
			//ini untuk critical parts; gausah di substr dulu;
			$this->model_code = $this->parameter['board_id'];
		}
	}

	public function getModelCode()
	{
		return $this->model_code;
	}

	public function setModelCode($code)
	{
		$this->model_code = $code;
	}
	//triggered on instantiate 
	public function setModel($parameter)
	{


		$setting = $this->getColumnSettingWhereCodePrefix($this->model_code);
		// ColumnSetting::where('code_prefix', $this->model_code )->first();
		if (!is_null($setting)) {
			$className = 'App\\' . studly_case(str_singular($setting->table_name));

			if (class_exists($className)) {
				$model = new $className;
				$dummy_column = $setting->dummy_column;
				$name = str_singular($setting->table_name);
				$idType = $setting->name;
				$unique_column = 'guid_' . $name; //guid_ticket or guid_master
				if ($name == 'part') {
					$unique_column = 'guid_ticket'; //avoid error on part
				}
			}
		} else {
			// $setting = ColumnSetting::where('code_prefix', null )->first();
			// fwrite(STDOUT, print_r($setting));
			$model = new Board;
			$dummy_column = 'board_id';
			$name = 'board';
			$idType = 'board';
			$unique_column = 'board_id';
		}

		$this->model = $model;
		$this->dummy_column = $dummy_column;
		$this->model_type = $name;
		$this->unique_column = $unique_column;
		$this->setIdType($idType);
	}

	public function getParent()
	{
		$columnSettings = $this->getColumnSetting();
		$tmpLevel = 999999; //max int harusnya
		$result = null;
		foreach ($columnSettings as $key => $setting) {
			if ($tmpLevel > $setting['level']) {
				$tmpLevel = $setting['level'];
				$result = $setting;
			}
		}
		return $result;
	}

	// method init guid di triggere dari constructor;
	private function initGuid($guidParam)
	{
		// cek apakah ticket guid sudah di generate sebelumnya;
		$guid = $this->getLastGuid(); //this method need update to acomodate master
		$type = $this->getModelType();
		$uniqueColumn = $this->getUniqueColumn();
		// $this->settingContain($type) tidak usah karena relasi antara
		//  proses dengan columnSetting harusnya lineprocess_id-> column_settings->name,
		// tapi aktual implementasi adalah lineprocess_id -> column_Settings_id
		// we need to check it again later, if it causing bug or not;
		if ($this->isJoin() && $this->isSettingContainParentOf($type) /* && $this->isSettingContain($type) */) {
			$parentName = (is_null($this->getParent())) ? '' : $this->getParent()['name'];
			$parentName = strtoupper($parentName);
			if ($guidParam == null) {
				throw new StoreResourceFailedException("INI PROSES JOIN, TOLONG SCAN {$parentName} TERLEBIH DULU!", [
					'note' => 'need guid !!',
					'node' => json_decode($this, true),
				]);
			}
		}

		if ($this->isGuidGenerated()) {
			if ($this->getModelType() == 'board' || $this->getModelType() == 'part') {
				$parent = $this->getParent();
				$tableName = $parent['table_name'];
				$uniqueColumn = 'guid_' . str_singular($tableName); //guid_ticket or guid_master
			}
			$guid = (!is_null($guid)) ? $guid[$uniqueColumn] : null;
		} else {
			$guid = ($guidParam == null) ?  $this->generateGuid() : $guidParam;
		}
		// it can triggered after scanner & model has been set; 
		if ($this->getModelType() == 'ticket') {
			if ($guidParam != null) {
				// verify that if guidParam is also guid_ticket, throw exception
				$this->isGuidTicket($guidParam);

				$this->setGuidMaster($guidParam);
				// ini terpanggil ketika join master dengan ticket
				// untuk verifikasi guid master & guid ticket
				$this->verifyModelnameAndLotnoTicketMaster($guid /*guidTicket*/, $guidParam /*guidMaster*/);

				if ($this->isRework()) {
					$this->storeSerialNumberRework($guidParam);
					// only mecha that we need to back up, dummy panel mah gausah;
					$this->backupMechaToHistory();
				}
			}

			$this->setGuidTicket($guid);
		} else if ($this->getModelType() == 'master') {
			$this->verifyGuidMaster($guid);
			$this->setGuidMaster($guid);
		} else /*($this->getModelType() == 'board')*/ {
			// cek column setting, this step is join atau bkn,
			// yg masuk kesini pasti board, atau part
			if ($this->isJoin()) {
				/*this changes is to avoid same guid_master & guid_ticket in input 1 audio which is obiously data anomaly*/
				$parent = $this->getParent();
				// ini untuk memastikan, satu lcd tidak di scan dengan lebih dari satu dummy panel
				if ($guidParam != null) {
					// jika lastGuid != currentGuid
					$lastGuid = $guid;
					$currentGuid = $guidParam;
					$parentName = $parent['table_name'];
					$parentDummyColumn = $parent['dummy_column'];
					$parentUniqueColumn = 'guid_' . str_singular($parentName); //guid_master or guid_ticket
					$className = 'App\\' . studly_case(str_singular($parentName));
					$idType = $this->getIdType();
					$idType = strtoupper($idType);
					// $this->wawa = [$lastGuid, $currentGuid ];
					if (class_exists($className)) {
						$className = new $className;
						// jika last guid & current guid beda, berarti ada yang salah.
						if ($lastGuid != $currentGuid) {
							$lastDummy = $className->distinct($parentDummyColumn)
								->where($parentUniqueColumn, $lastGuid)
								->orderBy('id', 'desc')
								->first();

							if ($lastDummy) {
								$lastDummy = $lastDummy[$parentDummyColumn];
							}

							$currDummy = $className->distinct($parentDummyColumn)
								->where($parentUniqueColumn, $currentGuid)
								->orderBy('id', 'desc')
								->first();

							if ($currDummy) {
								$currDummy = $currDummy[$parentDummyColumn];
							}

							if ($this->isRework()) {
								$this->storeSerialNumberRework($lastGuid);
								// backup current guid to board or part history,
								$this->backupToHistory($parentUniqueColumn, $lastGuid);
								// update old guid to new guid
								$this->changesGuid($parentUniqueColumn, $lastGuid, $currentGuid);
								// delete current data 
								$this->deleteCurrentTransaction($parentUniqueColumn, $currentGuid);

								$guid = $currentGuid; //untuk mencegah data baru, diambil dari getLastGuid
							} else {
								throw new StoreResourceFailedException("{$idType} {$this->getDummyId()} sudah join dengan dummy {$lastDummy}!!! dummy {$currDummy} harus lanjut dengan {$idType} lain!!", [
									'last_dummy' => $lastDummy,
									'current_dummy' => $currDummy,
									'parentDummyColumn' => $parentDummyColumn,
									'parentUniqueColumn' => $parentUniqueColumn,
									'last_guid' => $lastGuid,
									'current_guid' => $currentGuid
								]);
							}
						}
					}
				}


				if ($this->isSettingContain('master')) {
					$this->setGuidMaster($guid);
				} else {
					$this->setGuidTicket($guid);
				}
			} else {
				$guid = $this->dummy_id; //untuk board yg NG sebelum join; guidnya kita ganti dummy id
				// supaya bisa dapet ketika dicari di table repair;
			}
		}

		$this->setUniqueId($guid);
	}

	public function backupMechaToHistory() {
		
		if($this->getIdType() == 'mecha'){
			// get mecha, yang ticket_no = $this->dummy_id;
			$guid = $this->model
			->where( $this->dummy_column , $this->dummy_id )
			->where('guid_master', '!=', null )
			->where('guid_ticket', '!=', null )
			->orderBy('id', 'desc')
			->first();

			if(!$guid){
				return 0;
			}

			
			$historyTable = $this->model->getTable() . "_history";
			
			$history = DB::table($historyTable)->where($this->dummy_column, $this->dummy_id )
			->where('guid_master', $guid->guid_master )
			->first();
			
			$backup = false;
			$mecha = [];
			$backupCount = 0;
			if(!$history) {
				$backup = true;
				// backup
				$mecha = $this->model->where('guid_master', $guid->guid_master )->get();

				$backupCount = DB::table($historyTable)->insert($mecha->toArray());
			}

			return $backupCount;

			/* throw new StoreResourceFailedException("mecha back up", [
				'backup' => $backup,
				'mecha' => $mecha,
				'history_table' => $historyTable,
				'backup_count' => $backupCount,
			]); */
		}

	}

	public function storeSerialNumberRework($guid) {
		// get serial number of specific guid
		$master = Master::select('serial_no')	
			->where('guid_master', $guid)
			->where('serial_no', '!=', null )
			->orderBy('id', 'desc')
			->distinct()
			->first();
		
		if(!$master) {
			return false;
		}

		$serialNo = (!$master) ? null : $master->serial_no;

		if($serialNo == null){
			return false;
		}

		$rework = DB::table('rework')->where('barcode', $serialNo )
			->first();

		$modelname = null;
		$newRework = null;
		$insert    = null;
		$serialno  = null;
		if(!$rework){
			$tmp = explode(' ', $serialNo);
			$modelname = isset( $tmp[0]) ? $tmp[0] : null; //index pertama 
			$serialno = isset( $tmp[1]) ? $tmp[1] : null ;
			$newRework = [
				'barcode' => $serialNo,
				'model' => $modelname,
				'serialno' => $serialno,
				'categorynm' => "-",
				'input_user' => isset($this->nik) ? $this->nik : null ,
				'input_date' => date('Y-m-d H:m:s')
			];

			throw new StoreResourceFailedException("kajsdf", compact('rework', 'serialNo', 'master', 'modelname', 'newRework', 'insert', 'serialno') );

			$insert = DB::table('rework')->insert($newRework);
		}
		
		return compact('rework', 'serialNo', 'master', 'modelname', 'newRework', 'insert', 'serialno');


	}

	public function backupToHistory($uniqueColumn, $lastGuid)
	{

		$datas = $this->model
			->where($uniqueColumn, $lastGuid)
			->where($this->dummy_column, $this->dummy_id)
			->get();

		$modelname = $this->model->getTable();

		// insert data to table boards_history
		$exists = DB::table($modelname . '_history')->where($uniqueColumn, $lastGuid)
			->exists();

		$insert = false;
		if (!$exists) {
			$insert = DB::table($modelname . '_history')->insert($datas->toArray());
		}

		return [
			'success' => true,
			'exists' => $exists,
			'insert' => $insert,
			'datas' => $datas->toArray(),
			'modelname' => $modelname
		];
	}

	public function changesGuid($uniqueColumn, $oldGuid, $newGuid)
	{
		$updated = $this->model
			->where($uniqueColumn, $oldGuid)
			->where($this->dummy_column, $this->dummy_id )
			->update([
				$uniqueColumn => $newGuid
			]);

		/* throw new StoreResourceFailedException("updated {$updated}", [
			'unique_column' => $uniqueColumn,
			'old_guid' => $oldGuid,
			'new_guid' => $newGuid
		]); */

		return $updated;
	}

	public function deleteCurrentTransaction($uniqueColumn, $guid)
	{
		$scannerId = $this->getScanner()['id'];

		$deleted = $this->model
			->where($uniqueColumn, $guid)
			->where('scanner_id', $scannerId )
			->delete();

		/* throw new StoreResourceFailedException("delete current trans", [
			'current_process' => $deleted,
			'scanner_id' => $scannerId,
			'unique_column' => $uniqueColumn,
			'last_guid' => $guid
		]); */

		return $deleted;
	}

	public function verifyGuidMaster($guidParam = null)
	{
		$guid = (is_null($guidParam)) ? $this->getUniqueId() : $guidParam;
		// get master yg guid nya sama, tp dummy_master nya beda. 
		$notQualify = Master::where($this->getUniqueColumn(), $guid)
			->where('ticket_no_master', '!=', $this->parameter['board_id'])
			->orderBy('id', 'desc')
			->first();
		// kalau ada, maka guid tersebut ga boleh digunakan lagi seharusnya; maka throw error
		if ($notQualify) {
			throw new StoreResourceFailedException('Tolong Scan Board, Panel, Atau Mecha. Jangan Master lagi.', [
				'guid' => $guid,
				'prev_dummy' => $notQualify->ticket_no_master,
				'current_dummy' => $this->parameter['board_id']
			]);
		}
	}

	public function isGuidTicket($guidParam)
	{

		$uniqueColumn = $this->getUniqueColumn();
		$uniqueId = (is_null($guidParam)) ? $this->getUniqueId() : $guidParam;

		$result = Ticket::select('id')
			->where('guid_ticket', $uniqueId)
			->where('guid_master', '=', null) ///ini urgent, mohon review lagi nanti; untuk handle Audio mecha
			->first();

		if ($result) {
			throw new StoreResourceFailedException("Tolong Scan LCD atau BOARD, Jangan Dummy Panel lagi. atau matikan Is join Active dengan cara klik tombolnya", [
				'message' => 'tolong ulangi scan LCD atau Boardnya sampai berhasil!',
				'dev_message' => 'ini terjadi karena guid master yg dikirim front end, telah digunakan sbg guid ticket dummy panel lain',
				'guid' => $uniqueId
			]);
		};
		// if ( ( $result && ($guidParam !== '') ) );
	}

	private function setUniqueId($guid)
	{
		$this->unique_id = $guid;
	}

	public function getUniqueId()
	{
		return $this->unique_id;
	}

	public function getUniqueColumn()
	{
		return $this->unique_column;
	}

	public function getDummyParent()
	{
		$tmp = str_split($this->dummy_id);

		if (strlen($this->dummy_id) == 16) {
			$tmp[7] = 0;
			$tmp[8] = 0;
		} else if (strlen($this->dummy_id) == 24) {
			$tmp[12] = 0;
			$tmp[13] = 0;
		}

		return implode('', $tmp);
	}

	public function getDummyId()
	{
		return $this->dummy_id;
	}

	public function ignoreSideQuery($query)
	{
		if ($this->getModelType() == 'board') {

			$tmp = $this->getDummyParent();

			if (strlen($this->dummy_id) == 16) {
				$sideIndex = 6;
			} else if (strlen($this->dummy_id) == 24) {
				$sideIndex = 14;
			} else {
				// default side index yg sekarang adalah 14; karena ini yg berlaku;
				$sideIndex = 14;
			}

			$parentA = $tmp;
			$parentA[$sideIndex] = 'A';

			$parentB = $tmp;
			$parentB[$sideIndex] = 'B';

			$a = $this->dummy_id;
			$a[$sideIndex] = 'A';
			$b = $this->dummy_id;
			$b[$sideIndex] = 'B';

			$query->where($this->dummy_column, $a)
				->orWhere($this->dummy_column, $b)
				->orWhere($this->dummy_column, $parentA)
				->orWhere($this->dummy_column, $parentB);
		} else {
			// ticket & master
			$query->where($this->dummy_column, $this->dummy_id);
		}
	}

	// we need to changes this method to acomodate the masters 
	public function getLastGuid()
	{
		if (is_null($this->dummy_column)) {
			throw new StoreResourceFailedException("dummy_column id is null", [
				'node' => json_decode($this, true),
			]);
		}

		if (is_null($this->dummy_id)) {
			throw new StoreResourceFailedException("dummy_id id is null", [
				'node' => json_decode($this, true),
			]);
		}

		$guid = $this->model
			->where(function ($query) {
				$this->ignoreSideQuery($query);
			})
			->orderBy('id', 'desc');

		if ($this->getModelType() == 'ticket') {
			$guid = $guid->select([
				'guid_ticket'
			])->where('guid_master', null)
				->where('guid_ticket', '!=', null);
		} else if ($this->getModelType() == 'master') {
			$guid = $guid->select([
				'guid_master'
			])->where('serial_no', null)
				->where('guid_master', '!=', null);
		}

		return $guid = $guid->first();
	}

	public function getGuidTicket()
	{
		return $this->guid_ticket;
	}

	public function setGuidTicket($guid)
	{
		if ($this->getModelType() == 'board') {
			// if it has a sibling, then
			if ($this->hasTicketSibling($guid)) {
				//verify it has same modelname and lotno
				$this->verifyModelnameAndLotno('ticket', $guid);
				// if failed, throw error that the previous board has different modelname & lotno
			}

			if ($this->hasMasterSibling($guid)) {
				// verify it has same modelname and lotno
				$this->verifyModelnameAndLotno('master', $guid);
				// if failed, throw error that the previous board has different modelname & lotno
			}
		}

		$this->guid_ticket = $guid;
	}

	public function setGuidMaster($guid)
	{
		if ($this->getModelType() == 'board') {

			// if it has a sibling, then
			if ($this->hasTicketSibling($guid)) {
				//verify it has same modelname and lotno
				$this->verifyModelnameAndLotno('ticket', $guid);
				// if failed, throw error that the previous board has different modelname & lotno
			}

			if ($this->hasMasterSibling($guid)) {
				// verify it has same modelname and lotno
				$this->verifyModelnameAndLotno('master', $guid);
				// if failed, throw error that the previous board has different modelname & lotno
			}
		}

		$this->guid_master = $guid;
	}

	public function getGuidMaster()
	{
		return $this->guid_master;
	}


	/*
	* @parameter = 'ticket' or 'master'
	* this method called in setGuidMaster & setGuidTicket for verification
	* guid parameter is a must since this method called before initGuid finish
	*/
	public function verifyModelnameAndLotno($type = 'ticket', $guid = null)
	{
		// return setting('admin.strict_checking');
		if (function_exists('setting')) {
			// jika pengaturan admin.strict_checking == false, maka method ini langsung return saja, gausah dilanjut.
			// atau dengan kata lain, jangan test
			if (setting('admin.strict_checking')) {
				// get board based on guid; wheter it is 
				if ($type == 'ticket') {
					$prevBoard = Board::select(['id', 'modelname', 'lotno'])->where('guid_ticket', $guid)
						->orderBy('id', 'desc')
						->first();

					// we need to add checking boards from master here;
				}

				if ($type == 'master') {
					$prevBoard = Board::select(['id', 'modelname', 'lotno'])->where('guid_master', $guid)
						->orderBy('id', 'desc')
						->first();
				}

				// due to circular dependencies, we cannot use $this->modelname here, instead, we use user parameter;
				$modelname = (isset($this->modelname)) ? $this->modelname : $this->parameter['modelname'];

				if ($prevBoard->modelname != $modelname) {
					// if current model sent by user is different from previous insalled board model, return confirmation view
					throw new StoreResourceFailedException($this->confirmation_view_error, [
						'message' => "BOARD MODEL YANG ANDA SCAN BERBEDA DENGAN BOARD MODEL SEBELUMNYA. BOARD MODEL SEKARANG = '{$modelname}' , BOARD MODEL SEBELUMNYA '{$prevBoard->modelname}'!",
						'node' => json_decode($this, true),
						'prevBoard' => $prevBoard,
						'server-modelname' => $prevBoard->modelname,
					]);
				}

				if (setting('admin.check_lot_no')) {
					if ($prevBoard->lotno != $this->lotno) {
						throw new StoreResourceFailedException("LOT NUMBER BOARD YG ANDA SCAN BERBEDA DENGAN LOT NUMBER BOARD SEBELUMNYA. LOT NUMBER SEKARANG '{$this->lotno}' , LOT NUMBER SEBELUMNYA '{$prevBoard->lotno}'", [
							'message' => 'for jein developer : due to circular dependencies, we cannot use current node modelname. instead we use user parameter',
							'node' => json_decode($this, true),
							'prevBoard' => $prevBoard,
						]);
					}
				}
			}
		}
	}

	public function verifyModelnameAndLotnoTicketMaster($guidTicket, $guidMaster, $isTesting = false)
	{
		if (function_exists('setting')) {
			// jika pengaturan admin.strict_checking == false, maka method ini langsung return saja, gausah dilanjut.
			// atau dengan kata lain, jangan test
			if (setting('admin.strict_checking') || $isTesting) {
				if ($this->getIdType() == 'mecha') {
					return; //stop untill here;
					// mecha will not have boards
				}

				$boardTicket =  Board::select(['modelname', 'lotno'])
					->where('guid_ticket', $guidTicket)
					->orderBy('id', 'desc')
					->first();

				if (!$boardTicket) {
					// jika tidak punya boards, artinya mingkin, ini belum sampai ke panel 3.
					// jadi kita return saja, biar error nya ga miss leading
					return;
				}

				$boardMaster = Board::select(['modelname', 'lotno'])
					->where('guid_master', $guidMaster)
					->orderBy('id', 'desc')
					->first();

				if (!$boardMaster) {
					// we should think if we can return instead of throw exception.
					// supaya lebih konsisten;
					return; //biar ga missleading error.
					// ketika seharusnya blm scan process sblmnya, malah jadi tidak memiliki board
				}

				if ($boardTicket->modelname != $boardMaster->modelname) {
					# code...
					throw new StoreResourceFailedException("BOARD MODEL TICKET & MASTER BERBEDA! BOARD MODEL TICKET = {$boardTicket->modelname}. BOARD MODEL MASTER = {$boardMaster->modelname}.", [
						'board_model_ticket' => $boardTicket->modelname,
						'board_model_master' => $boardMaster->modelname,
					]);
				}

				if (setting('admin.check_lot_no')) {
					if ($boardTicket->lotno != $boardMaster->lotno) {
						# code...
						throw new StoreResourceFailedException("BOARD LOT NO TICKET & MASTER BERBEDA! BOARD MODEL TICKET = {$boardTicket->lotno}. BOARD MODEL MASTER = {$boardMaster->lotno}", [
							'board_model_ticket' => $boardTicket->lotno,
							'board_model_master' => $boardMaster->lotno,
						]);
					}
				}
			}
		}
	}

	/*
		@void, to verify if the parameter modelname sent by front end is correct by comparing the data with previous children board;
	*/
	public function verifyParameterModelname()
	{
		$boardChildren = $this->getBoardChildren(); //ambil board anak;

		if ($boardChildren !== null) {
			// compare antara pengaturan dengan board anak;
			if ($boardChildren['modelname'] != $this->parameter['modelname']) {
				// get the board children to compare the model
				throw new StoreResourceFailedException($this->confirmation_view_error, [
					'node' => json_decode($this, true),
					'server-modelname' => $boardChildren['modelname']
				]);
			}
		}
	}

	// only work for board because only 
	public function hasTicketSibling($guidParam = null)
	{
		$guid = (!is_null($guidParam)) ? $guidParam : $this->getGuidTicket();
		if ($this->getModelType() == 'board') {
			return Board::where('guid_ticket', '!=', null)
				->where('guid_ticket', $guid)
				->exists();
		}
	}
	// only work for board
	public function hasMasterSibling($guidParam = null)
	{
		$guid = (!is_null($guidParam)) ? $guidParam : $this->getGuidMaster();

		if ($this->getModelType() == 'board') {
			return Board::where('guid_master', '!=', null)
				->where('guid_master', $guid)
				->exists();
		}
	}

	/*
		bool @hasChildren() 
		comparing current children qty with $this->lineprocess->joinQty
		@20190206 this method is rewrited to efisiensi and become configurable 
		table hierarcy;
	*/
	public function hasChildren()
	{
		if ($this->getModelType() == 'board' || $this->getModelType() == 'part') {
			return false;
		}

		$joinQty = $this->getLineprocess()->join_qty;
		$totalChildren = 0; //default value of total children
		$children = $this->getChildren();

		if ($this->getModelType() == 'master' || $this->isSettingContain('master')) {
			$uniqueColumn = 'guid_master';
			$uniqueId = $this->getGuidMaster();
		}

		if ($this->getModelType() == 'ticket') {
			$uniqueColumn = 'guid_ticket';
			$uniqueId = $this->getGuidTicket();
		}

		foreach ($children as $key => $child) {
			$table = $child['table_name'];
			try {
				//code...
				$query = DB::table($table)
					->where('scanner_id', $this->scanner_id)
					->where($uniqueColumn, $uniqueId)
					->count('id');
			} catch (QueryException $th) {
				$query = 0; //if query error, i'll just get 0
			}

			$totalChildren += $query;
		}

		$this->joinTimesLeft = $joinQty - $totalChildren;
		return ($totalChildren >= $joinQty);
	}

	public function getJoinTimesLeft()
	{
		return $this->joinTimesLeft;
	}

	/*
		board getBoardChildren() will return boards with the same guid with current node;
		it is required guid in order to run
	*/
	public function getBoardChildren()
	{
		if ($this->getModelType() == 'board') {
			return null; // required due to caller if statement
		}

		if ($this->getModelType() == 'master') {

			$board = Board::where('guid_master', $this->getGuidMaster())
				->first();

			return $board;
		}

		if ($this->getModelType() == 'ticket') {
			return Board::where('guid_ticket', $this->getGuidTicket())
				->first();
		}
	}

	public function isGuidGenerated($paramType = null)
	{
		if (is_null($this->model)) {
			throw new StoreResourceFailedException("node model is null", [
				'node' => json_decode($this, true),
			]);
		}

		if (is_null($this->dummy_column)) {
			throw new StoreResourceFailedException("node dummy_column is null", [
				'node' => json_decode($this, true),
			]);
		}

		if (is_null($this->dummy_id)) {
			throw new StoreResourceFailedException("node dummy_id is null", [
				'node' => json_decode($this, true),
			]);
		}

		if (is_null($paramType)) {
			$paramType = $this->getModelType();
		}

		if ($paramType  == 'ticket') {
			return $this->model
				// ->where( 'scanner_id' , $this->scanner_id  )
				->where($this->dummy_column, $this->dummy_id)
				->where('guid_master', null)
				->exists();
		} else if ($paramType == 'master') {
			return $this->model
				->where($this->dummy_column, $this->dummy_id)
				->where('serial_no', null)
				->exists();
		} else { //untuk mengakomodir board & parts, yg parent nya bs ticket atau master;
			return $this->model
				->where($this->dummy_column, $this->dummy_id)
				->where(function ($q) {
					$q->where('guid_master', '!=', null)
						->orWhere('guid_ticket', '!=', null);
				})
				->exists();
		}
	}


	public function generateGuid($guidParam = null)
	{
		// cek apakah php punya com_create_guid
		if (function_exists('com_create_guid') === true) {
			$guid = trim(com_create_guid(), '{}');
		} else {
			$guid = sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
		}

		if (!is_null($guidParam)) {
			# code...
			$guid = $guidParam;
		}

		$guidExists = Guid::where('guid', $guid)->exists();
		// return ['result' => $guidExists ];
		if ($guidExists) {
			return $this->generateGuid(); //recursive calling
		}

		// save to table guid master;
		$newGuid = new Guid(['guid' => $guid]);
		$newGuid->save();

		return $guid;
	}

	public function getModel()
	{
		return $this->model;
	}

	public function getModelType()
	{
		return $this->model_type;
	}

	public function isExists($status = null, $judge = null, $is_solder = null)
	{
		if (is_null($this->lineprocess)) {
			throw new StoreResourceFailedException("this lineprocess is not set", [
				'message' => $this->lineprocess
			]);
		}

		if ($this->lineprocess['type'] == 1) {
			// masuk kesini jika internal;
			$model = $this->model
				->where('scanner_id', $this->scanner_id)
				->where(function ($query) {
					$this->ignoreSideQuery($query);
				});
			// ->where( $this->dummy_column, $this->dummy_id );

			if (!is_null($status)) {
				$model = $model->where('status', 'like', $status . '%');
			}

			if (!is_null($judge)) {
				$model = $model->where('judge', 'like', $judge . '%');
			}

			// $is_solder is parameter, if it refer to $this->is_solder, it broke the logic in mainController;
			if (!is_null($is_solder)) {
				$model = $model->where('judge', 'like', 'SOLDER%');
			}

			if ($this->getModelType() == 'master') {
				// make sure the finished dummy can be reuse;
				$model = $model->where('serial_no', null);
			} else if ($this->getModelType() == 'ticket') {
				$model = $model->where('guid_master', null);
			}

			return $model->exists();
		} else if ($this->lineprocess['type'] == 2) {
			// send cURL here;
			$endpoint = Endpoint::select()->find($this->lineprocess['endpoint_id']);
			if (is_null($endpoint)) {
				throw new StoreResourceFailedException("endpoint with id " . $this->lineprocess['endpoint_id'] . " is not found", [
					'lineprocess' => $this->lineprocess,
				]);
			}

			$url = $endpoint->url; //'http://localhost/mapros_system54/public/api/aoies';
			$client = new Client();
			// $url = "https://api.github.com/repos/guzzle/guzzle";
			$res = $client->get($url, [
				'query' => [
					'board_id'	=> $this->parameter['board_id']
				],
				'headers' => ['Content-type' => 'application/json'],
			]);

			$result = json_decode($res->getBody(), true);
			// it's should return boolean
			return ($result['success'] && $result['data']['userjudgment'] != 'NG');
		}
	}

	public function isIn()
	{
		return $this->isExists('IN');
	}

	public function isOut()
	{
		return $this->isExists('OUT');
	}

	public function isOk()
	{
		return $this->isExists(null, 'OK');
	}

	public function isInOk()
	{
		return $this->isExists('IN', 'OK');
	}

	public function isOutOK()
	{
		return $this->isExists('OUT', 'OK');
	}

	public function save()
	{
		$model = $this->model;
		$model[$this->dummy_column] = $this->dummy_id;
		$model->guid_master = $this->guid_master;

		if ($this->getModelType() != 'master') {
			$model->guid_ticket = $this->guid_ticket;
		}

		$model->scanner_id = $this->scanner_id;
		$model->status = $this->status;
		$model->judge = $this->judge;
		$model->scan_nik = $this->nik;

		if ($this->getModelType() == 'board') {
			$model->modelname = $this->modelname;
			$model->lotno = $this->lotno;
		}


		# insert the to critical parts jika critical parts tidak null;
		if (!is_null($this->getCriticalPart())) {
			# code...
			$criticalParts = $this->getExtractedCriticalParts();
			if (method_exists($this, 'insertIntoCritical')) {
				if ($this->getStatus() == 'IN') {
					# only run this method when it's IN. // if it's OUT, gausah.
					$this->insertIntoCritical($criticalParts, $this->getUniqueId());
				}
			}
		}

		/* check it fifo */
		$this->checkFifo();

		$isSaveSuccess = $model->save();

		$this->updateGuidSibling(); //move updateGuidSibling after save method;

		if ($isSaveSuccess && ($this->getModelType() == 'master') && ($this->getJudge() == 'NG')) {
			$this->insertSymptom($model);
		}

		/*kalau locations tidak null dan yg diproses itu board, maka save into locations*/
		if ((empty($this->getLocations()) === false) && ($this->getModelType() == 'board')) {
			# save to locations;
			$this->insertLocation($model);
		}
		/* insert ke manual_intructions kalau manual content ada di paramete  */
		if (isset($this->parameter['manual_content'])) {
			if (method_exists($this, 'storeManualContent')) {
				$this->storeManualContent($this->parameter['manual_content']);
			}
		}

		if (isset($this->parameter['carton'])) {
			if (method_exists($this, 'storeCarton')) {
				$this->storeCarton($this->parameter['carton']);
			}
		}

		$this->finishMaster();

		return $isSaveSuccess; //true or false;
	}

	public function delete()
	{
		if ($this->hasChildren() == false) {

			$model =  $this->model->where($this->dummy_column, $this->dummy_id)
				->where('scanner_id', $this->scanner_id);

			if ($this->getModelType() == 'master') {
				// make sure the finished dummy can be reuse;
				$model = $model->where('serial_no', null);
			} else if ($this->getModelType() == 'ticket') {
				$model = $model->where('guid_master', null);
			}

			$model = $model->delete();
			return $model;
		}
	}

	public function insertSymptom($model)
	{
		$symptom = Symptom::select(['id'])
			->whereIn('code', $this->parameter['symptom'])
			->get();

		if ($symptom->isEmpty()) {
			return true; // kalau empty data symptom yg di pass nya, langsung return saja;
		}

		$symptoms = [];
		foreach ($symptom as $key => $value) {
			$symptoms[] = $value['id'];
		}

		$model->symptoms()->sync($symptoms);
	}

	/*
	* it's search board id type from table in big system 
	* based on code = board_id;
	*/
	public function getBoardType($board_id = null)
	{
		throw new StoreResourceFailedException("model is not found", [
			'node' => json_decode($this, true),
		]);
		
		// it's can be triggered if model & guid has been set;
		if (is_null($this->model)) {
			throw new StoreResourceFailedException("model is not found", [
				'node' => json_decode($this, true),
			]);
		}

		if (is_null($board_id)) {
			// it'll need to be changed due to changes in big system
			$board_id = $this->getModelCode();
		}

		$model = Mastermodel::select([
			'id',
			'name',
			'pwbno',
			'pwbname',
			'process',
			'cavity',
			'code',
			'side',
		]);

		/*if($this->getModelType() == 'ticket'){
			if (is_null($this->guid_ticket)) {
				throw new StoreResourceFailedException("guid ticket is null", [
					'node' => json_decode($this, true),
				]);
			}
	
			// kalau belum, kita setup model based on user parameter;
			// ini untuk meng akomodir kebutuhan scan panel sebelumn proses join dengan board;
			// detect model from dummy card;
			$this->verifyParameterModelname();	

			$model = $model->where('name', $this->parameter['modelname'] );

		} else if($this->getModelType() == 'master') {
			// detect model from dummy card;
			$this->verifyParameterModelname();

			$model = $model->where('name', $this->parameter['modelname'] );
		}else{
			// this is from bigs db
			$model = $model->where('code', $board_id );
		}*/
		if ($this->getModelType() == 'board') {
			$assy_model = setting('admin.assy_model');
			$model = $model->where('code', $board_id);
			
			// $model = $this->getWhereNotLikeIn($model,$assy_model,'name');
			
			// foreach ($model as $value) {
			// 	$model_select[] =$value;
			// }

			// $countModel = $model->distinct()->count();

			// $model = $model->select('name','pwbname','pwbno','cavity','code');
			// $model = $model->pluck('name')->toArray();

			throw new StoreResourceFailedException($this->confirmation_view_error, [
				'message' => "MODELNAME BANYAK DI BIGS",
				'node' => json_decode($this, true),
				// 'prevBoard' => $prevBoard,
				'server-modelname' => Controller::getEloquentSqlWithBindings($model)
				// 'server-modelname' => $model
				// 'server-modelname' => $model
			]);
			

		} else {
			if (is_null($this->guid_ticket) && ($this->getModelType() == 'ticket')) {
				throw new StoreResourceFailedException("guid ticket is null", [
					'node' => json_decode($this, true),
				]);
			}

			$this->verifyParameterModelname();
			$model = $model->where('name', $this->parameter['modelname']);
		}

		$model = $model->first();

		if ($model == null) {
			throw new StoreResourceFailedException("ANDA SCAN '{$board_id}'. PENGATURAN DATA DENGAN NAMA MODEL '{$this->parameter['modelname']}' TIDAK DITEMUKAN DI BOARD ID GENERATOR SYSTEM! PASTIKAN CURRENT MODEL CONFIG BENAR!", [
				'node' => json_decode($this, true),
				'model_type' => $this->getModelType(),
				'scanned_value' => $board_id,
			]);
		}

		$this->setBoard($model);
		$this->setModelname($model->name);

		return $this;
	}

	public function setBoard($model = null)
	{
		// $this->board['name'] = $model['name'];
		// $this->board['pwbname'] = $model['pwbname'];
		$this->board = $model;
	}

	// @ return boolean; indicate that the column setting is contain board
	public function isSettingContainBoard()
	{
		return $this->isSettingContain('board');
	}

	public function isSettingContain($modelType = 'board')
	{
		$result = false;
		foreach ($this->column_setting as $key => $setting) {
			$settingTable = str_singular($setting['table_name']);
			if ($settingTable == $modelType) {
				return true;
			}
		}
		return $result;
	}

	public function setModelname($modelname)
	{
		$this->modelname = $modelname;
	}

	public function getModelname()
	{
		return $this->modelname;
	}

	public function setLotno($parameterBoardId)
	{
		if ($this->getModelType() != 'board') {
			// kalau bukan board, gausah set lot no
			return;
		}

		if (strlen($parameterBoardId) <= 24) {
			$lotno = substr($parameterBoardId, 16, 4);
		} else {
			// untuk 24 char
			$lotno = substr($parameterBoardId, 9, 3);
		}
		// kalau hasil substr ga ketemu, dia bakal return false;
		// untuk mengatasi itu, maka simpan saja empty string instead of 0;
		$lotno = (!$lotno) ? '' : $lotno;

		$this->lotno = $lotno;
	}

	public function getLotno()
	{
		return $this->lotno;
	}

	public function getBoard()
	{
		return $this->board;
	}

	public function getScanner()
	{
		return $this->scanner;
	}

	/*
	* @loadStep is method to init current step;
	* step == current status & current judge;
	* we need to becarefull here since we had more than lineprocess type;
	*/
	public function loadStep()
	{

		$lineprocess = $this->getLineprocess();

		if (is_null($lineprocess)) {
			throw new StoreResourceFailedException("Lineprocess is null", [
				'node' => $this,
			]);
		}

		if ($lineprocess['type'] == 1) { //internal

			$model = $this->model
				->where('scanner_id', $this->scanner_id)
				->where(function ($query) {
					$this->ignoreSideQuery($query);
				})
				->orderBy('id', 'desc'); //order menurun

			if ($this->getModelType() == 'master') {
				// make sure the finished dummy can be reuse;
				$model = $model->where('serial_no', null);
			} else if ($this->getModelType() == 'ticket') {
				$model = $model->where('guid_master', null);
			}

			$model = $model->first();

			if ($model !== null) {
				$this->setStatus($model->status);
				$this->setJudge($model->judge);
				$this->setStep($model);
			}
		} else {
			$this->procedureGetStepExternal();
		}
	}

	// called in loadStep 
	public function procedureGetStepExternal()
	{
		// send ajax into end point;
		$endpoint = Endpoint::select()->find($this->lineprocess['endpoint_id']);
		if (is_null($endpoint)) {
			throw new StoreResourceFailedException("endpoint with id " . $this->lineprocess['endpoint_id'] . " is not found", [
				'lineprocess' => $this->lineprocess,
			]);
		}

		$url = $endpoint->url; //'http://localhost/mapros_system54/public/api/aoies';
		$client = new Client();
		// $url = "https://api.github.com/repos/guzzle/guzzle";
		try {
			$res = $client->get($url, [
				'query' => [
					'board_id'	=> $this->parameter['board_id']
				],
				'headers' => ['Content-type' => 'application/json'],
				// 'http_errors' => false,
			]);
		} catch (\GuzzleHttp\Exception\ClientException  $e) {
			// return $e->getMessage();
			$response = $e->getResponse();
			$responseBodyAsString = $response->getBody()->getContents();
			$arrResponse = json_decode($responseBodyAsString, true);
			$stringRes = (isset($arrResponse['message'])) ? $arrResponse['message'] : $responseBodyAsString;
			throw new StoreResourceFailedException($stringRes, [
				'board_id' => $this->parameter['board_id']
			]);
		}

		if ($res->getStatusCode() !== 200) {
			// return $res->getStatusCode();
			throw new StoreResourceFailedException("Something wrong to your external code data. CALL IT!", [
				'status_code' => $res->getStatusCode(),
				'body' => $res->getBody()
			]);
		}

		$result = json_decode($res->getBody(), true);

		if ( /*array_key_exists('judge', $result ) || */$result == null) {
			// return $result;
			throw new StoreResourceFailedException("SUMBER EXTERNAL HARUS SELALU MENGANDUNG 'JUDGE' & 'STATUS'!", [
				'result' => $result,
				'url' => $url,
				'response' => $res->getStatusCode() //json_decode( json_encode($res), true )
			]);
		}

		// end point should always contain status and judge;
		if (($result['success']) && ($result['judge'] != 'NG')) {
			$this->setStatus('OUT');
			$this->setJudge("OK");
		} else {
			$this->setStatus('OUT');
			$this->setJudge("NG");
		}

		$this->setStep($result);
	}

	// this method triggered by loadStep();
	private function setStep($model)
	{
		$this->step = $model;
	}

	public function getStep()
	{
		return $this->step;
	}

	public function setJudge($judge)
	{
		$this->judge = $judge;
	}

	public function getJudge()
	{
		return $this->judge;
	}

	public function getStatus()
	{
		return $this->status;
	}

	public function setStatus($status)
	{
		// make $status uppercase
		$status = strtoupper($status);

		if (!in_array($status, $this->allowedStatus)) {
			throw new StoreResourceFailedException("Status " . $status . " not allowed ", [
				'allowed status' => $this->allowedStatus
			]);
		}

		$this->status = $status;
	}

	// it used to set process
	public function getSequence()
	{
		$board   = $this->getBoard();
		$scanner = $this->getScanner();

		if (is_null($board)) {
			throw new StoreResourceFailedException("Board is not defined yet!", [
				'message' => 'getSequence method dependence to board',
				'node' => json_decode($this, true)
			]);
		}

		if (is_null($scanner)) {
			throw new StoreResourceFailedException("Scanner is null", [
				'message' => 'getSequence method dependence to setScannerId method'
			]);
		}

		if (!is_null($board['name'])) {
			// code below to avoid undefined error
			$this->parameter['modelname'] = (isset($this->parameter['modelname'])) ? $this->parameter['modelname'] : null;
			if ($board['name'] != $this->parameter['modelname']) {
				throw new StoreResourceFailedException($this->confirmation_view_error, [
					'node' => json_decode($this, true),
					'server-modelname' => $this->board['name']
				]);
			}

			$sequence = Sequence::select(['process'])
				->where('modelname', $board['name'])
				->where('line_id', $scanner['line_id']);

			if ($this->getModelType() == 'board') {
				$sequence =	$sequence->where('pwbname', $board['pwbname']);
			} else {
				// disini kita harus determine wheter it is panel or mecha;
				$sequence =	$sequence->where('pwbname', $this->getIdType());
			}

			$sequence = $sequence->first();

			if ($sequence) {
				$this->setProcess($sequence['process']);
			}
		}

		return $this;
	}

	public function setProcess($process)
	{
		$this->process = $process;
	}

	public function getProcess()
	{
		/*if (is_null( $this->process) ) {
			$this->getSequence();
		}*/
		return $this->process;
	}

	public function setLineprocess($lineprocess_id)
	{

		// cek status internal atau external
		$lineprocess = Lineprocess::select([
			'id',
			'name',
			'type',
			'std_time',
			'endpoint_id',
			'join_qty', //added to get in hasChildren
		])->find($lineprocess_id);

		if ($lineprocess == null) {
			throw new StoreResourceFailedException("lineprocess with id=" . $lineprocess_id . " not found", [
				'current_step' 	=> $this->scanner['lineprocess_id'],
				'process'		=> $this->process,
			]);
		}

		$this->lineprocess = $lineprocess;
	}

	public function getLineprocess()
	{
		/*return array of lineprocess*/
		/*
			'id',
			'name',
			'type',
			'std_time',
			'endpoint_id',
			'join_qty', //added to get in hasChildren
		*/
		return $this->lineprocess;
	}

	public function initCurrentPosition()
	{
		if (is_null($this->process)) {
			if ($this->getModelType() == 'board') {
				$pwbname  = $this->board['pwbname'];
			} else {
				// disini kita harus determine wheter it is panel or mecha;
				$pwbname  = $this->getIdType();
			}

			throw new StoreResourceFailedException("PENGATURAN PROSES TIDAK DITEMUKAN. klik see details untuk info lebih lanjut!", [
				'message' => 'Pengaturan sequence dengan modelname = "' . $this->board['name'] . '", pwbname="' . $pwbname . '", dan line_id="' . $this->scanner['line_id'] . '" tidak ditemukan! tolong segera buat!',
				'node'	  => json_decode($this, true),
			]);
		}

		if (is_null($this->scanner)) {
			throw new StoreResourceFailedException("scanner not registered yet", [
				'message' => 'scanner not registered yet'
			]);
		}

		// set process into array
		$process = explode(',', $this->process);

		// get current process index;
		$this->key = array_search($this->scanner['lineprocess_id'], $process);
		// $lineprocess_id tidak ditemukan di $process
		if ($this->key === false) { // === is required since 0 is false if its using == (two sama dengan)
			throw new StoreResourceFailedException("SCAN " . $this->getIdType() . " '" . $this->getDummyId() . "' TIDAK SEHARUSNYA DILAKUKAN DI STEP INI.", [
				'current_step' 	=> $this->scanner['lineprocess_id'],
				'process'		=> $process,
				'node'			=> json_decode($this, true),
			]);
		}

		$this->firstSequence = ($this->key === 0) ? true : false;
	}

	public function move($step = 1)
	{
		$process = explode(',', $this->process);

		// it's using $this->key for avoid error on first index;
		$this->key = $this->key + $step;
		// cek new index key ada di array $process as key. prevent index not found error 
		if (array_key_exists($this->key, $process)) {

			$newLineProcessId = $process[$this->key];

			// setup $this->lineprocess to prev step;
			$this->setLineprocess($newLineProcessId);

			// will get the last scanner inputed by users
			$scanner = Scanner::select([
				'id',
				'line_id',
				'lineprocess_id',
				'name',
				'mac_address',
				'ip_address',
			])->where('lineprocess_id', $newLineProcessId)
				->where('line_id', $this->scanner['line_id'])
				->orderBy('id', 'desc')
				->first();

			if (!$scanner) { //kalau scanner tidak ketemu
				throw new StoreResourceFailedException("scanner not registered yet", [
					'message' => 'scanner not registered yet'
				]);
			}
			// setup new scanner id value;
			$this->scanner_id = $scanner['id'];
			$this->scanner = $scanner;

			// set lineprocess
			$this->setLineprocess($this->scanner['lineprocess_id']);
			// set column setting;
			$this->initColumnSetting();

			// run load step to changes status & judge
			$this->loadStep();
		}

		// kalau 0, maka dia sequence pertama; we need to init key
		$this->initCurrentPosition();

		return $this;
	}

	/*
	* this is void, to update guid master of panel & board;
	* don't understand enough how to achieve it;
	* this method is run by save method 
	*/
	public function updateGuidSibling()
	{
		/*
		* yang meng update itu child yang sudah punya guid, dia update teman temannya.
		* bukan parent yang yang punya child;
		*/

		if ($this->getModelType() == 'board') {
			# we need to determine which column need to update, guid ticket or guid master 

			// jika guid ticket nya tidak null, maka update;
			if ($this->guid_ticket != null) {
				// update yang guid ticket nya masih null;
				// ketika join;
				Board::where('guid_ticket', null)
					// ->where('board_id', $this->parameter['board_id'] )
					->where(function ($query) {
						$this->ignoreSideQuery($query);
					})
					->where('lotno', $this->lotno)
					->update(['guid_ticket' => $this->guid_ticket]);
			}

			if ($this->guid_master != null) {
				// update yang guid ticket nya masih null;
				// ketika join;
				Board::where('guid_master', null)
					// ->where('board_id', $this->parameter['board_id'] )
					->where(function ($query) {
						$this->ignoreSideQuery($query);
					})
					->where('lotno', $this->lotno)
					->update(['guid_master' => $this->guid_master]);
			}
		}

		if ($this->getModelType() == 'part') {
			# we need to determine which column need to update, guid ticket or guid master 

			// jika guid ticket nya tidak null, maka update;
			if ($this->guid_ticket != null) {
				// update yang guid ticket nya masih null;
				// ketika join;
				Part::where('guid_ticket', null)
					->where('barcode', $this->parameter['board_id'])
					->update(['guid_ticket' => $this->guid_ticket]);
			}

			if ($this->guid_master != null) {
				// update yang guid ticket nya masih null;
				// ketika join;
				Part::where('guid_master', null)
					->where('barcode', $this->parameter['board_id'])
					->update(['guid_master' => $this->guid_master]);
			}
		}

		if ($this->getModelType() == 'ticket') {
			// get guid master;
			if ($this->guid_master != null) {
				// get ticket & board that has same guid ticket

				// we need to check guid_ticket & guid_master berbeda;
				Ticket::where('guid_master', null)
					->where('guid_ticket', $this->guid_ticket)
					->update(['guid_master' => $this->guid_master]);

				Board::where('guid_master', null)
					->where('guid_ticket', $this->guid_ticket)
					->update(['guid_master' => $this->guid_master]);
			}
		}
	}

	public function updateChildren()
	{
		// gausah running method ini kalau bukan langkah join
		if ($this->isJoin() == false) {
			return;
		}

		// check type model;
		if ($this->getModelType() == 'ticket') {
			// get child, that has already scan by the same scanner & has same guid_ticket
			$child = Board::where('guid_ticket', $this->guid_ticket)
				->where('scanner_id', $this->scanner_id)
				->orderBy('id', 'desc')
				->first();

			if ($child != null) {
				// jika last status dari board adalah 'IN'
				if ($child->status == 'IN') {
					// insert out nya untuk si child;
					$newBoard = new Board([
						'board_id' => $child->board_id,
						'guid_master' => $child->guid_master,
						'guid_ticket' => $child->guid_ticket,
						'scanner_id' => $this->scanner_id,
						'modelname'	=> $child->modelname,
						'lotno'	=> $child->lotno,
						'status' => 'OUT',
						'judge' => 'OK',
						'scan_nik' => $this->parameter['nik'],
					]);

					$newBoard->save();
				}
			}

			if ($this->isSettingContain('part')) {

				$child = Part::where('guid_ticket', $this->guid_ticket)
					->where('scanner_id', $this->scanner_id)
					->orderBy('id', 'desc')
					->first();

				if ($child != null) {
					if ($child->status == 'IN') {
						$newChild = new Part([
							'barcode' 		=> $child->barcode,
							'guid_master'	=> $child->guid_master,
							'guid_ticket'	=> $child->guid_ticket,
							'status' 		=> 'OUT',
							'scan_nik' 		=> $this->parameter['nik'],
							'scanner_id' 	=> $this->scanner_id,
							'judge' 		=> 'OK',
						]);

						$newChild->save();
					}
				}
			}
		}

		if ($this->getModelType() == 'master') {
			// this if is to avoid updating unnecessary table;
			// so it's only updating in join setting;
			if ($this->isSettingContainBoard()) {
				// board;
				$child = Board::where('guid_master', $this->guid_master)
					->where('scanner_id', $this->scanner_id)
					->orderBy('id', 'desc')
					->first();

				if ($child != null) {
					// jika last status dari board adalah 'IN'
					if ($child->status == 'IN') {
						// insert out nya untuk si child;
						$newBoard = new Board([
							'board_id' => $child->board_id,
							'guid_master' => $child->guid_master,
							'guid_ticket' => $child->guid_ticket,
							'scanner_id' => $this->scanner_id,
							'modelname'	=> $child->modelname,
							'lotno'	=> $child->lotno,
							'status' => 'OUT',
							'judge' => 'OK',
							'scan_nik' => $this->parameter['nik'],
						]);

						$newBoard->save();
					}
				}
			}

			if ($this->isSettingContain('part')) {

				$child = Part::where('guid_master', $this->guid_master)
					->where('scanner_id', $this->scanner_id)
					->orderBy('id', 'desc')
					->first();

				if ($child != null) {
					if ($child->status == 'IN') {
						$newChild = new Part([
							'barcode' 		=> $child->barcode,
							'guid_master'	=> $child->guid_master,
							'guid_ticket'	=> $child->guid_ticket,
							'status' 		=> 'OUT',
							'scan_nik' 		=> $this->parameter['nik'],
							'scanner_id' 	=> $this->scanner_id,
							'judge' 		=> 'OK',
						]);

						$newChild->save();
					}
				}
			}

			if ($this->isSettingContain('ticket')) {
				// ticket;
				$ticket = Ticket::where('guid_master', $this->guid_master)
					->where('scanner_id', $this->scanner_id)
					->orderBy('id', 'desc')
					->first();

				if ($ticket != null) {
					if ($ticket->status == 'IN') {
						$newTicket = new Ticket([
							'ticket_no' => $ticket->ticket_no,
							'guid_master' => $ticket->guid_master,
							'guid_ticket' => $ticket->guid_ticket,
							'scanner_id' => $this->scanner_id,
							'status' => 'OUT',
							'judge' => 'OK',
							'scan_nik' => $this->parameter['nik'],
						]);

						$newTicket->save();
					}
				}
			}
		}
	}

	public function isFirstSequence()
	{
		// default value of property below is false;
		// when move to prev and it's figure it out that is has key 0, (first index)
		// it'll setup to true;
		return $this->firstSequence;
	}

	public function prev()
	{
		return $this->move(-1);
	}

	public function next()
	{
		return $this->move(1);
	}

	public function initLocations()
	{
		$parameter = $this->parameter;
		if (isset($parameter['locations'])) {
			# code...
			$this->setLocations($parameter['locations']);
		}
	}

	public function hasInspect()
	{
		try {
			$lineprocessId = $this->getLineprocess()['id'];
			$scannerId = $this->getScanner()['id'];
			//code...
			$hasInspect = LineprocessInspect::select('has_log')
				->where('lineprocess_id', $lineprocessId)
				->where('scanner_id', $scannerId)
				->where('has_log', 1) //boolean, 1 or 0; 1 == aktif
				->first();

			if (!$hasInspect) {
				return false;
			} else {
				return true;
			}
		} catch (QueryException $th) {
			//throw $th;
			return false;
		}
	}

	public function InspectionLogOk()
	{
		// jika parameter judge == NG, gausah check ini;langsung return true aja
		// biar operator bisa meng NG kan Inspect log yang NG;

		if ($this->parameter['judge'] != 'NG') {
			// cek inspection log if it not ok, it's throw exception
			$guid = $this->getUniqueId(); //it can be board_id or guid, depend 
			$scannerId = $this->getScanner()['id'];
			$lineprocessId = $this->getLineprocess()['id'];
			// created at tidak akan ada jika source data dari external / API. 
			// that's why we need to check isset
			// if last IN created_at not found, created_at = 3 minutes ago;
			$createdAt = (is_null($this->getStep()) || (!isset($this->getStep()->created_at)))
				? Carbon::now()->subMinutes(3)->toDateTimeString() : $this->getStep()->created_at->toDateTimeString();

			try {
				//code...
				$isOk = InspectionLog::where('unique_id', $guid)
					->where(function ($q) use ($scannerId, $lineprocessId) {
						$q->where('scanner_id', $scannerId)
							->orWhere('lineprocess_id', $lineprocessId);
					}) //->where('judgement', 'OK')
					->where('created_at', '>=', $createdAt)
					->orderBy('id', 'desc')
					->first(); //it need to check the data;

				if (!$isOk) {
					throw new StoreResourceFailedException('INSPECTION LOG BELUM ADA. MOHON PASTIKAN INSPECT OK.', [
						'messages' => "data inspection log dengan guid = '{$guid}' && judgement = 'OK' && ( scanner id = '{$scannerId}' || lineprocess_id = '{$lineprocessId}' ) && created_at >= '{$createdAt}' tidak ditemukan.",
						'guid' => $guid,
						'showResend' => true, //it's mandatory for the front end to keep the resend button;
						'scanner_id' => $scannerId,
						'lineprocess_id' => $lineprocessId,
						'node' => json_decode($this, true),
					]);
				}

				// data ada, baru check OK atau NG
				if ($isOk['judgement'] == "OK") {
					$isOk = true;
				} else {
					// ini NG;
					throw new StoreResourceFailedException('INSPECTION LOG NG. MOHON PASTIKAN INSPECT OK.', [
						'messages' => "data inspection log dengan guid = '{$guid}' && judgement = 'OK' && ( scanner id = '{$scannerId}' || lineprocess_id = '{$lineprocessId}' ) && created_at >= '{$createdAt}' tidak ditemukan.",
						'guid' => $guid,
						'showResend' => true, //it's mandatory for the front end to keep the resend button;
						'scanner_id' => $scannerId,
						'lineprocess_id' => $lineprocessId,
						'node' => json_decode($this, true),
					]);
				}

				return $isOk;
			} catch (QueryException $th) {
				//throw $th;
				$isOk = true; //kalau InspectionLog throw exception, ini akan ok terus;
				return $isOk;
			}
		} else {
			return true;
		}
	}

	public function isRework()
	{
		if (!isset($this->parameter['isRework'])) {
			return false; //kalau ga ada parameter isRework, pasti return false;
			// artinya ini bukan proses rework;
		}

		return $this->parameter['isRework'];

		/* ini return true, ketika client kirim parameter rework, dan guid belum di generate.
		method ini digunakan untuk  menentukan apakah kita perlu check ke belakang atau tidak
		pada saat kondisi rework. */
		return ($this->parameter['isRework'] && ($this->isGuidGenerated() == false));
	}

	public function getParameter()
	{
		return $this->parameter;
	}

	public function checkMechaCounter()
	{
		if ($this->getIdType() !== 'mecha') {
			return true;
		}

		if ($this->getModelType() !== "ticket") {
			return true;
		}

		$scanner = $this->getScanner();

		$scannerId = $scanner->id;

		$data = Ticket::where($this->dummy_column, $this->getDummyId())
			->where('status', "OUT")
			->where('scanner_id', $scannerId);

		$counter = $data->count();

		$maxCounter = setting('admin.max_mecha_counter');
		if ($counter >= (int) $maxCounter) {
			throw new StoreResourceFailedException("Mecha sudah digunakan {$counter} kali. max digunakan adalah {$maxCounter} kali.", [
				'counter' => $counter,
				'maxCounter' => $maxCounter,
				'column' => $this->dummy_column,
				'unique' => $this->getDummyId(),
				'scanner_id' => $scannerId,
				'data' => $data->get()

			]);
		}

		return true;
	}

	public function finishMaster()
	{
		if ($this->getModelType() !== 'master') {
			return false;
		}

		if (!isset($this->parameter['serial_number'])) {
			if ($this->isSerialMandatory()) {
				throw new StoreResourceFailedException("INI PROCESS TERKAHIR, MOHON INPUT SERIAL NUMBER.", [
					'id_type' => $this->getIdType(),
					'serial_no_mandatory' => $this->isSerialMandatory(),
					'keterangan' => 'parameter serial number not isset',
					'status' => $this->getStatus(),
					'model' => $this->model->toArray(), // ini ada karena finishMaster dipanggil setelah $this->save()
				]);
			};
			return false;
		}

		if (is_null($this->parameter['serial_number'])) {
			if ($this->isSerialMandatory()) {
				throw new StoreResourceFailedException("INI PROCESS TERKAHIR, MOHON INPUT SERIAL NUMBER.", [
					'id_type' => $this->getIdType(),
					'serial_no_mandatory' => $this->isSerialMandatory(),
					'keterangan' => 'parameter serial number is null',
					'status' => $this->getStatus(),
					'model' => $this->model->toArray(), // ini ada karena finishMaster dipanggil setelah $this->save()
				]);
			};
			return false;
		}

		$serialNumber = $this->getSerialNumber(); //$this->parameter['serial_number'];

		$updated = Master::where($this->getUniqueColumn(), $this->getGuidMaster())
			->where('serial_no', null)
			->where($this->dummy_column, $this->getDummyId())
			// ->get();
			->update(['serial_no' => $serialNumber]);


		return $updated;
	}

	public function getSerialNumber()
	{
		$serialNumber = '';

		if (setting("admin.include_modelname_on_serialno")) {
			$serialNumber .= $this->parameter['modelname'] . " ";
		}

		$serialNumber .= $this->parameter['serial_number'];

		$year = Carbon::now()->subYears(10);

		// get data mulai sepuluh tahun lalu dengan specific serialnumber
		$isExists = Master::where('serial_no', $serialNumber)
			->where('created_at', '>', $year)
			->orderBy('id', 'desc')
			->first();

		// kalau sudah ada sebelumnya.
		if (!is_null($isExists)) {
			// hapus yang keinput sebelumnya
			$this->rollbackMaster();

			throw new StoreResourceFailedException("SERIAL INI '{$serialNumber}' SUDAH DIGUNAKAN. MOHON GUNAKAN SERIAL NO LAIN.", [
				'serial_number' => $serialNumber,
				'old_data' => $isExists->toArray(),
				'year' => $year
			]);
		}


		return $serialNumber;
	}

	// pikirkan ulang untuk implementasi ini.
	// khawatir jika ini proses terakhir, tapi bukan master, akan bermalasalah.
	/* 
		return @boolean tergantung apakah serial number mandatory
		menentukan mandatory atau tidaknya dari :
		return contain master && is last process
	*/
	public function isSerialMandatory()
	{
		/* getIdType contain master, board, mecha, panel */
		if ($this->getIdType() != 'master') {
			return false;
		}

		// this model sudah ter input
		if ($this->model->exists) {
			if ($this->model->status == "IN") {
				return false;
			}
		}

		$scanner = $this->getScanner();
		$process = explode(',', $this->getProcess());

		$res =  $process[count($process) - 1] == $scanner['lineprocess_id'];

		if ($res) {
			// delete data terakhir yang diinput;
			$this->rollbackMaster();
		}

		return $res;
	}

	public function rollbackMaster()
	{

		$master = Master::where($this->dummy_column, $this->getDummyId())
			->where($this->getUniqueColumn(), $this->getUniqueId())
			->where('scanner_id', $this->scanner_id)
			->where('status', "OUT")
			->where('serial_no', null)
			->orderBy('id', 'desc')
			// ->first();
			->delete();

		return $master;
	}

	public function checkFifo()
	{
		// check apakah ini fifo mode

		if (!isset($this->parameter['fifoMode'])) return true;

		if ($this->parameter['fifoMode'] == false) return true;

		$scanner = $this->getScanner();
		$lastModelAtSpecificProcess = ($this->getModel())
			->where('scanner_id', $scanner['id'])
			->where('serial_no', null)
			->orderBy('id', 'desc')
			->first();

		if (!$lastModelAtSpecificProcess) {
			//langsung return karena last set nya ga ada.;
			return true;
		}

		// check modelnya sama apa engga;
		if ($lastModelAtSpecificProcess[$this->dummy_column] != $this->dummy_id) {
			if ($lastModelAtSpecificProcess->status == "IN") {
				throw new StoreResourceFailedException("DUMMY SEBELUMNYA BELUM SCAN OUT ('{$lastModelAtSpecificProcess[$this->dummy_column]}'). MOHON PASTIKAN SUDAH SCAN OUT ATAU NON AKTIFKAN FITUR FIFO. ", [
					"last_set" => $lastModelAtSpecificProcess->toArray(),
					"set"      => $this->parameter
				]);
			}
		}

		return true;
	}

	public function getWhereNotLikeIn($query,$array_param,$column)
	{
		return $query;
		if(is_array($array_param))
		{
			foreach ($array_param as $value) {
				$query = $query->where($column,'not like',$value);
			}
			return $query;
		}
		return $query;
		// return $query = $query->where($column,'not like',$array_param);

	}
}
