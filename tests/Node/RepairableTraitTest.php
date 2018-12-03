<?php

namespace App\Functional\Api\V1\Controllers;

use App\NewTestCase as TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Dingo\Api\Exception\StoreResourceFailedException;
use App\Master;
use App\Scanner;
use App\Lineprocess;
use App\LineprocessStart;
use App\Repair;

class RepairableTraitTest extends TestCase
{
    protected $mock;

    protected function initMock(){
        $this->mock = $this->getMockForTrait('App\Api\V1\Traits\RepairableTrait');
        return $this->mock;
    }

    protected function getDummyData(){
        return [
            [   
                "id" =>  2,
                "name" =>  "panel",
                "dummy_column" =>  "ticket_no",
                "table_name" =>  "tickets",
                "code_prefix" =>  "MAPNL",
                "level" =>  2,
                "pivot" =>  [
                    "lineprocess_id" =>  55,
                    "column_setting_id" =>  2
                ]                        
            ],
            [
                "id" =>  7,
                "name" =>  "lcd",
                "dummy_column" =>  "barcode",
                "table_name" =>  "parts",
                "code_prefix" =>  "G1P85",
                "level" =>  3,
                "pivot" =>  [
                    "lineprocess_id" =>  55,
                    "column_setting_id" =>  7
                ]
        ]];
    }

    public function getMock(){
        $mock = (is_null($this->mock)) ? $this->initMock() : $this->mock;
        return $mock;
    }

    public function seedTableMaster(){
        $scanners = factory(Scanner::class, 2 )
        ->create([
            'lineprocess_id' => function (){
                return factory(Lineprocess::class)->create()->id;
            }
        ]);
        
        $master = new Master;
        $master->ticket_no_master = 'MAMSTTESTING';
        $master->guid_master = 'some-guid-master-temp';
        $master->status = 'OUT';
        $master->scanner_id = 1; //we'll need to specify this scanner;
        $master->scan_nik = '39597';
        $master->judge = 'NG';
        $master->save();
    }

    public function testGetLineprocessNgReturnNull(){
        $mock = $this->getMock();
        $master = new Master;
        // $this->expectException(StoreResourceFailedException::class); 
        $null = $mock->getLineprocessNg( $master , 'guid_master', 'someguimaster') ;
        $this->assertNull($null);
    }

    public function testGetLineprocessNgReturnLineprocessId(){
        $mock = $this->getMock();
        
        // jalankan seeder
        $this->seedTableMaster();
        $master = new Master;
        $lineprocessNg = $mock->getLineprocessNg( $master , 'guid_master', 'some-guid-master-temp');
        $this->assertNotNull( $lineprocessNg );
        $this->assertInternalType('int', $lineprocessNg );
        $this->assertEquals(1, $lineprocessNg );
    }

    public function testGetJoinQuery(){
        $mock = $this->getMock();
        $master = new Master;
        $data = $mock->getJoinQuery($master);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Builder', $data );    
    }

    public function testIsAfterNgProcess(){
        $mock = $this->getMock();
        /*parameters : process, lineprocess_id, lineprocessNg*/
        $true = $mock->isAfterNgProcess('1,2,3,4,5', 4, 1 );
        $this->assertTrue($true);
        $false = $mock->isAfterNgProcess('1,2,3,4,5', 2, 4 );
        $this->assertFalse($false);
    }

    public function testIsAfterNgProcessError(){
        $mock = $this->getMock();
        /*below exeption will be thrown*/
        $this->expectException(StoreResourceFailedException::class);
        /*parameters : process, lineprocess_id, lineprocessNg*/
        $true = $mock->isAfterNgProcess('1,2,3,4,5', 7, 1 );
    }

    protected function seedLineprocessStart(){
        factory(Lineprocess::class, 2)->create();

        /*seeding to lineprocessStart no longer needed due to we already setup event & listener in lineprocess model*/
        /*so every time lineprocess has new record, it will automatically insert into lineprocessStart too*/

        /*$lineprocessStart = new LineprocessStart([
            'lineprocess_id' => 1,
            'start_id' => 2,
        ]);

        $lineprocessStart->save();*/
    }

    public function testGetStartId(){
        $mock = $this->getMock();
        $this->seedLineprocessStart();
        $lineprocessId = 1;
        $result = $mock->getStartId($lineprocessId);
        $this->assertEquals($result, 1); //1 is filled in seedLineprocessStart method;
    }

    public function testGetStartIdReturnException(){
        $mock = $this->getMock();
        /*getStartId jika dipanggil dengan lineprocess yg belum diisi / tidak ditemukan akan return exception*/
        $this->expectException(StoreResourceFailedException::class);
        $mock->getStartId(1);
    }

    protected function seedRepairTable(){
        $repair = new Repair([
            'unique_id' => 'someuniqueid',
            'dummy_id' => 'somedummy'
        ]);

        $repair->save();
    }

    public function testIsRepaired(){
        $mock = $this->getMock();
        $this->seedRepairTable(); //seed the table
        // unique_id & isNgRecordExists; true && true
        $true = $mock->isRepaired('someuniqueid', true );
        $this->assertTrue($true);

        /*parameter 1 = unique_id, parameter 2 = lineprocess_id */
        $false = $mock->isRepaired('someuniqueid', 12 );
        $this->assertFalse($false);
        
        /*parameter 1 = unique_id, parameter 2 = lineprocess_id */
        $false = $mock->isRepaired('anotherUniquerId', true );
        $this->assertFalse($false);

        /*parameter 1 = unique_id, parameter 2 = lineprocess_id */
        $false = $mock->isRepaired('anotherUniquerId', 12 );
        $this->assertFalse($false);

    }

    public function testIsRepairedOneMoreTime(){
        $mock = $this->getMock();
        $this->seedRepairTable(); //seed the table
        
        $false = $mock->isRepaired('anotherUniquerId', 2 );
        $this->assertFalse($false);
    }

    public function testGetLineprocessNgName(){
        $mock = $this->getMock();
        /*seed lineprocess dengan name 'proses 1' */
        $lineprocess = factory(Lineprocess::class)->create(['name' => 'proses 1']);
        $result = $mock->getLineprocessNgName(1);

        $this->assertEquals('proses 1', $result );
    }

    public function testGetLineprocessNgNameReturnException(){
        $mock = $this->getMock();
        /*seed lineprocess dengan name 'proses 1' */
        $this->expectException(StoreResourceFailedException::class);
        $result = $mock->getLineprocessNgName(1);
    }

    public function testIsBeforeStartIdReturnTrue(){
        $mock = $this->getMock();
        $result = $mock->isBeforeStartId('1,2,3,4', 2, 3);
        $this->assertTrue($result);
    }

    public function testIsBeforeStartIdReturnFalse(){
        $mock = $this->getMock();
        $false1 = $mock->isBeforeStartId('1,2,3,4', 3, 3);
        $false2 = $mock->isBeforeStartId('1,2,3,4', 4, 3);
        $this->assertFalse($false1);
        $this->assertFalse($false2);
    }

    public function testGetFurthestNgProcessSuccess(){
        $mock = $this->getMock();
        $this->seedTableMaster();
        $master = new Master;
        $lineprocessNg = $mock->getFurthestNgProcess($master , 'guid_master', 'some-guid-master-temp', '1,2,3');
        // $lineprocessNg = $mock->getLineprocessNg( $master , 'guid_master', 'some-guid-master-temp');
        $this->assertNotNull( $lineprocessNg );
        $this->assertInternalType('int', $lineprocessNg );
        $this->assertEquals(1, $lineprocessNg );
    }

    public function testGetFurthestNgProcessReturnNull(){
        $mock = $this->getMock();
        $master = new Master;
        $lineprocessNg = $mock->getFurthestNgProcess($master , 'guid_master', 'some-guid-master-temp', '1,2,3');
        // $lineprocessNg = $mock->getLineprocessNg( $master , 'guid_master', 'some-guid-master-temp');
        $this->assertNull( $lineprocessNg );
    }

    public function testIsBeforeOrEqualStartId(){
        $mock = $this->getMock();
        $result = $mock->isBeforeOrEqualStartId('1,2,3', 2, 2);
        $this->assertTrue($result);

        $false = $mock->isBeforeStartId('1,2,3,4,5', 4, 3 );
        $this->assertFalse($false);
    }

    public function testReworkCountReturnZero(Model $modelParam = null, $scannerIdParam = null, $uniqueColumnParam = null, $uniqueIdParam = null){
        $mock = $this->getMock();
        $master = new Master;
        $result = $mock->reworkCount($master, 1, 'guid_master', 'some-guid-master-temp');
        // record rework of master is 0;
        $this->assertEquals(0, $result );
    }

    public function seedTableMasterRework($judge='REWORK'){
        $scanners = factory(Scanner::class, 2 )
        ->create([
            'lineprocess_id' => function (){
                return factory(Lineprocess::class)->create()->id;
            }
        ]);
        
        $master = new Master;
        $master->ticket_no_master = 'MAMSTTESTING';
        $master->guid_master = 'some-guid-master-temp';
        $master->status = 'OUT';
        $master->scanner_id = 1; //we'll need to specify this scanner;
        $master->scan_nik = '39597';
        $master->judge = $judge;
        $master->save();
    }

    public function testReworkCount(Model $modelParam = null, $scannerIdParam = null, $uniqueColumnParam = null, $uniqueIdParam = null){
        $mock = $this->getMock();
        $this->seedTableMasterRework();
        $master = Master::where('judge', 'REWORK')->get();
        $this->assertGreaterThan(0, count($master));
        $master = new Master;
        $result = $mock->reworkCount($master, 1, 'guid_master', 'some-guid-master-temp');
        $this->assertEquals(1, $result );
    }

    public function testGetAllNgRecord(){
        $mock = $this->getMock();
        $model = new Master;
        // $allNgRecord is empty laravel collection;
        $allNgRecord = $mock->getAllNgRecord($model, 'guid_master', 'some-guid-master-temp');
        $this->assertEquals(0, $allNgRecord->count() );

        $this->seedTableMaster();
        // setelah master di isi dengan record ng, harusnya ga lagi 0 tapi 1;
        $allNgRecord = $mock->getAllNgRecord($model, 'guid_master', 'some-guid-master-temp');
        $this->assertEquals(1, $allNgRecord->count() );        
    }

    public function testHasRework(){
        $mock = $this->getMock();
        $this->seedTableMaster();
        $modelParam = new Master;
        $scannerIdParam = 1;
        $uniqueColumnParam = 'guid_master'; 
        $uniqueIdParam = 'some-guid-master-temp'; 
        $processParam = '1,2,3'; 
        
        $recordReworkParam = 2; // rework nya sudah ada 2 records di lineprocess saat ini;
        $ngRecordsParam = [['lineprocess_id' => 2]] ; //ng nya di proses 2
        $lineprocessParamId = 1; //current process / lineprocess saat ini
        $processParam  = '1,2,3,4,5'; //processnya
        
        $true = $mock->hasRework($recordReworkParam, $ngRecordsParam, $lineprocessParamId, $processParam );
        $this->assertTrue($true);

        $recordReworkParam = 1; // rework nya sudah ada 1 records di lineprocess saat ini;
        $ngRecordsParam = [['lineprocess_id' => 2]] ; //ng nya di proses 2
        $lineprocessParamId = 1; //current process / lineprocess saat ini
        $processParam  = '1,2,3,4,5'; //processnya
        
        $false = $mock->hasRework($recordReworkParam, $ngRecordsParam, $lineprocessParamId, $processParam );
        $this->assertTrue($false);
    }


}