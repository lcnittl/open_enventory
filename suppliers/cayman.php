<?php
/*
Copyright 2006-2018 Felix Rudolphi and Lukas Goossen
open enventory is distributed under the terms of the GNU Affero General Public License, see COPYING for details. You can also find the license under http://www.gnu.org/licenses/agpl.txt

open enventory is a registered trademark of Felix Rudolphi and Lukas Goossen. Usage of the name "open enventory" or the logo requires prior written permission of the trademark holders. 

This file is part of open enventory.

open enventory is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

open enventory is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with open enventory.  If not, see <http://www.gnu.org/licenses/>.
*/
// Carl Roth
$GLOBALS["code"]="cayman";
$GLOBALS["suppliers"][$GLOBALS["code"]]=new class extends Supplier {
	public $code;
	public $name = "Cayman Chemical";
	public $logo = "logo_cayman.png";
	public $height = 85;
	public $vendor = true;
	public $hasPriceList = 2;
	public $testCas = array("168968-01-2" => array(
			array("mdma"),
		)
	);
	public $excludeTests = array("emp_formula");
	public $urls=array(
		"server" => "https://www.caymanchem.com" // startPage
	);
	
	function __construct() {
        $this->code = $GLOBALS["code"];
		$this->urls["search"]=$this->urls["server"]."/seawolf/open/product/search?q=";
		$this->urls["detail"]=$this->urls["server"]."/product/";
		$this->urls["detail_data"]=$this->urls["server"]."/seawolf/open/product/details/";
		$this->urls["price"]=$this->urls["server"]."/seawolf/open/product/variants?ids=";
		$this->urls["startPage"]=$this->urls["server"];
   }
	
	public function requestResultList($query_obj) {
		$retval = array(	
			"method" => "url",
			"action" => $this->urls["search"].urlencode($query_obj["vals"][0][0])."&qf="
		);
		if ($query_obj["crits"][0]=="cas_nr") {
			$retval["action"].="cas_no";
		}
		else {
			$retval["action"].=$this->urls["search_suffix"];
		}

		return $retval;
	}
	
	public function getDetailPageURL($catNo) {
		if (empty($catNo)) {
			return;
		}
		return $this->urls["detail"].$catNo."?referrer=enventory";
	}
	
	public function getInfo($catNo) {
		global $noConnection,$default_http_options;
		
		$url=$this->urls["detail_data"].$catNo."/cch?";
		if (empty($url)) {
			return $noConnection;
		}
		$my_http_options=$default_http_options;
		$my_http_options["redirect"]=maxRedir;
		$my_http_options["header"]=array("Accept" => "application/json");
		$response=@oe_http_get($url,$my_http_options);
		if ($response==FALSE) {
			return $noConnection;
		}

		return $this->procDetail($response,$catNo);
	}
	
	public function getHitlist($searchText,$filter,$mode="ct",$paramHash=array()) {
		global $noConnection,$default_http_options;
		
		$url=$this->urls["search"].urlencode($searchText)."&qf=";
		$my_http_options=$default_http_options;
		$my_http_options["redirect"]=maxRedir;
		$my_http_options["header"]=array("Accept" => "application/json");
		$response=oe_http_get($url,$my_http_options);
		if ($response==FALSE) {
			return $noConnection;
		}

		return $this->procHitlist($response);
	}
	
	public function procDetail(& $response,$catNo="") {
		global $default_http_options;

		$json=json_decode(@$response->getBody(),TRUE);

		$result=array();
		$result["molecule_names_array"]=array();
		$result["price"]=array();
		
		$docs=$json["content"]??null;
		$beautifulCatNo=null;
		if (is_array($docs)) foreach ($docs as $doc) {
			$result["molecule_names_array"][]=fixTags($doc["name"]??"");
			$synonymsPlain=$doc["synonymsPlain"]??array();
			foreach ($synonymsPlain as $synonymPlain) {
				$result["molecule_names_array"][]=fixTags($synonymPlain);
			}
			$result["molecule_names_array"][]=fixTags($doc["formalNamePlain"]??"");
			
			$result["cas_nr"]=fixTags($doc["casNumber"]??"");
			$result["emp_formula"]=fixTags($doc["molecularFormula"]??"");
			$result["mw"]=getNumber($doc["formulaWeight"]??null);
			$beautifulCatNo=fixTags($doc["catalogNum"]??"");
			
			$sdsurl=$doc["productSDS"]??false;
			if ($sdsurl) {
				$result["default_safety_sheet"]="";
				$result["default_safety_sheet_url"]="-".$this->urls["server"]."/msdss/".htmlspecialchars_decode($sdsurl);
				$result["default_safety_sheet_by"]=$this->name;
			}
		}

		$result["supplierCode"]=$this->code;
		$result["catNo"]=$catNo;
		
		$my_http_options=$default_http_options;
		$my_http_options["redirect"]=maxRedir;
		$my_http_options["header"]=array("Accept" => "application/json");
		$response=@oe_http_get($this->urls["price"].$catNo,$my_http_options);
		if ($response!==FALSE) {
			$pricedata=json_decode(@$response->getBody(),TRUE);
			$docs=$pricedata["content"]??null;
			if (is_array($docs)) foreach ($docs as $doc) {
				list(,$amount,$amount_unit)=getRange(fixTags($doc["name"]??""));
				$result["price"][]=array(
					"supplier" => $this->code,
					"amount" => $amount,
					"amount_unit" => strtolower($amount_unit),
					"price" => getNumber($doc["amountEur"]??null),
					"currency" => "EUR", // always
					"beautifulCatNo" => $beautifulCatNo
				);
			}
		}

		//~ var_dump($result);

		return $result;
	}
	
	public function procHitlist(& $response) {
		$json=json_decode($response->getBody(),TRUE); // json_decode_nice somehow does not work here
//		echo "/*";
//		var_dump($json);//var_dump($body);die(json_last_error_msg());
//		echo "*/";

		$results=array();
		if (arrCount($json)) foreach ($json["content"]["docs"] as $doc) {
			if (isset($doc["catalogNum"])) {
				$results[]=array(
					"name" => fixTags($doc["name"]??""), 
					"cas_nr" => $doc["casNumber"]??"", 
					"beautifulCatNo" => $doc["catalogNum"], 
					"catNo" => $doc["catalogNum"], 
					"supplierCode" => $this->code, 
				);
			}
		}

		return $results;
	}
}
?>