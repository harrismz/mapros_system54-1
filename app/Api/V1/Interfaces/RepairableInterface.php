<?php
namespace App\Api\V1\Interfaces;

interface RepairableInterface {
	/*required to implemented in node class */
	public function getModel();
	public function getUniqueColumn();
	public function getUniqueId();
	/*end*/

	public function getLineprocessNg();
	public function isAfterNgProcess();


}