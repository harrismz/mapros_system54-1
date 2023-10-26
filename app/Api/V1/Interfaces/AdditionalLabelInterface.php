<?php
namespace App\Api\V1\Interfaces;

interface AdditionalLabelInterface {
    public function storeAdditionalLabel($data, $guid = null);
    public function getGuidMaster();
    public function getGuidTicket();
    public function getParameter();
}