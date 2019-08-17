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
$GLOBALS["code"]="own";
$code=$GLOBALS["code"];
//~ $code="own";
//~ $suppliers[$code]=array("code" => $code, "name" => "eigene Datenbanken","logo" => "open_env_logo.png", "height" => 50, "vendor" => false, "noExtSearch" => true,
$GLOBALS["suppliers"][$code]=array(
	"code" => $code, 
	"name" => s("own_database"),
	"logo" => "open_env_logo.png", 
	"height" => 50, 
	"vendor" => false, 
	"noExtSearch" => true,
"init" => function () use ($code) {
	eval(getFunctionHeader());
	$suppliers[$code]["urls"]["detail"]="edit.php?table=molecule&";
	$suppliers[$code]["urls"]["search"]="list.php?table=molecule&query=<0>&";
},
"requestResultList" => function ($query_obj) use ($code) {
	eval(getFunctionHeader());
	$retval["method"]="get";
	$retval["action"]=$urls["search"]."crit0=".$query_obj["crits"][0]."&op0=".$query_obj["ops"][0]."&val0=".$query_obj["vals"][0];
	return $retval;
},
"getDetailPageURL" => function ($catNo) use ($code) {
	eval(getFunctionHeader());
	list($db_id,$molecule_id)=$self["splitCatNo"]($catNo);
	return $urls["detail"]."dbs=".$db_id."&crit0=molecule.molecule_id&op0=ex&val0=".$molecule_id;
},
"getInfo" => function ($catNo) use ($code) { // format catNo (db_id+1)_(molecule_id)
	eval(getFunctionHeader());
	list($db_id,$molecule_id)=$self["splitCatNo"]($catNo);
	list($result)=mysql_select_array(array(
		"table" => "molecule", 
		"filter" => "molecule.molecule_id=".fixNull($molecule_id), 
		"dbs" => $db_id, 
		"flags" => QUERY_CUSTOM, 
	));
	
	unset($result["molecule_id"]);
	
	// load SDS from URL provided, keep reference to original src
	$self["fixMSDS"]($result);
	
	// Daten zurückgeben, Format dürfte schon stimmen
	return $result;
},
"getHitlist" => function ($searchText,$filter,$mode="ct",$paramHash=array()) use ($code) {
	eval(getFunctionHeader());
	// in allen Datenbanken entsprechenden Suchbegriff suchen
	addWildcards($searchText,$mode,"%");
	$filterText=$filter."=".fixStrSQL($searchText);
	array_shift($paramHash["db_list"]); // remove -1
	if (!count_compat($paramHash["db_list"])) { // no other db
		return array();
	}
	
	$results=mysql_select_array(array(
		"table" => "molecule", 
		"filter" => $filterText, 
		"dbs" => join(",",$paramHash["db_list"]), 
		"flags" => QUERY_CUSTOM, 
	));
	
	for ($a=0;$a<count_compat($results);$a++) {
		// catNos generieren
		$results[$a]["catNo"]=($results[$a]["db_id"]+1)."_".($results[$a]["molecule_id"]);
		unset($results[$a]["db_id"]);
		unset($results[$a]["molecule_id"]);
		
		// load SDS from URL provided, keep reference to original src
		$self["fixMSDS"]($results[$a]);
	}
	// Daten zurückgeben, Format dürfte schon stimmen
	return $results;
},
"fixMSDS" => function (& $result) use ($code) {
	if (!empty($result["default_safety_sheet_url"])) {
		$result["default_safety_sheet_url"]="-".$result["default_safety_sheet_url"];
	}
	if (!empty($result["alt_default_safety_sheet_url"])) {
		$result["alt_default_safety_sheet_url"]="-".$result["alt_default_safety_sheet_url"];
	}
},
"getBestHit" => function (& $hitlist,$name=NULL) use ($code) {
	if (count_compat($hitlist)>0) {
		return 0;
	}
},
"splitCatNo" => function ($catNo) use ($code) {
	list($db_id,$molecule_id)=explode("_",$catNo,2);
	$db_id--;
	$molecule_id+=0;
	return array($db_id,$molecule_id);
}, 
);
//~ $suppliers[$code]["init"]();
$GLOBALS["suppliers"][$code]["init"]();
?>
