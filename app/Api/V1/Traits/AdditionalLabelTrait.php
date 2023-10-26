<?php


namespace App\Api\V1\Traits;
use Dingo\Api\Exception\StoreResourceFailedException;
use Dingo\Api\Http\Request;
use App\AdditionalLabel;
use Illuminate\Database\QueryException;

trait AdditionalLabelTrait {
    public function storeAdditionalLabel($data , $guid = null ) {
        $guidMaster = ($guid == null )? $this->getGuidMaster() : $guid;
        $guidTicket = ($guid == null )? $this->getGuidTicket() : $guid;
        $content  = $data;

        if($content == null || $guidMaster == null || $guidTicket == null) {
            return null; // ??
        }
        if(isset($content)){
            $additionalLabel = new AdditionalLabel();
            $additionalLabel->guid_master = $guidMaster;
            $additionalLabel->guid_ticket = $guidTicket;
            $additionalLabel->content = $content;
            $result = $additionalLabel->save();

            if(!$result){
                return false;
            }
        }

        return true;
    }

}