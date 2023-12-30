<?php

/*
 * OIDplus 2.0
 * Copyright 2022 - 2023 Daniel Marschall, ViaThinkSoft / Till Wehowski, Frdlweb
 *
 * Licensed under the MIT License.
 */

namespace Frdlweb\OIDplus;

use ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_4;
use ViaThinkSoft\OIDplus\INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_7;
use ViaThinkSoft\OIDplus\OIDplus;
use ViaThinkSoft\OIDplus\OIDplusObject;
use ViaThinkSoft\OIDplus\OIDplusPagePluginPublic;

// phpcs:disable PSR1.Files.SideEffects
\defined('INSIDE_OIDPLUS') or die;
// phpcs:enable PSR1.Files.SideEffects

class OIDplusPagePublicAltIds extends OIDplusPagePluginPublic
	implements INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_4, /* whois*Attributes */
	INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_7  /* getAlternativesForQuery */
{

	/**
	 * @param string $actionID
	 * @param array $params
	 * @return array
	 * @throws \ViaThinkSoft\OIDplus\OIDplusException
	 */
	//will be extended?
	//public function action(string $actionID, array $params): array {
	//	return parent::action($actionID, $params);
	//}

	//+ add table altids
	public function init(bool $html=true) {
		// TODO: Also support SQL Server, PgSql, Access, SQLite, Oracle
		if (!OIDplus::db()->tableExists("###altids")) {
			OIDplus::db()->query("CREATE TABLE ###altids (   `origin` varchar(255) NOT NULL,    `alternative` varchar(255) NOT NULL,    UNIQUE KEY (`origin`, `alternative`)   )");
		}

		// Whenever a user visits a page, we need to update our cache, so that reverse-lookups are possible later
		// TODO! Dirty hack. We need a cleaner solution...
		if (isset($_REQUEST['goto'])) $this->saveAltIdsForQuery($_REQUEST['goto']);
		if (isset($_REQUEST['id'])) $this->saveAltIdsForQuery($_REQUEST['id']);
	}


	protected function saveAltIdsForQuery(string $id){
		$obj = OIDplusObject::parse($id);
		if (!$obj) return; // e.g. if plugin is disabled
		$ary = $obj->getAltIds();
		$origin = $obj->nodeId(true);
			$straw_prefiltered = OIDplus::prefilterQuery($origin, false);
			if($straw_prefiltered !== $origin){
			    $resQ = OIDplus::db()->query("select origin, alternative from ###altids WHERE origin = ? AND alternative = ?",
				[$origin, $straw_prefiltered]);
			       if(!$resQ->any()){
				   OIDplus::db()->query("INSERT INTO ###altids (origin, alternative) VALUES (?,?);", [$origin, $straw_prefiltered]);
			       }                           
			}		
		foreach ($ary as $a) {
			// why in every iteration? $origin = $obj->nodeId(true);
			$alternative = $a->getNamespace() . ':' . $a->getId();
			$resQ = OIDplus::db()->query("select origin, alternative from ###altids WHERE origin = ? AND alternative = ?",
				[$origin, $alternative]);
			if(!$resQ->any()){
				OIDplus::db()->query("INSERT INTO ###altids (origin, alternative) VALUES (?,?);", [$origin, $alternative]);
			}
			
			$straw_prefiltered = OIDplus::prefilterQuery($alternative, false);
			if($straw_prefiltered !== $alternative){
			    $resQ = OIDplus::db()->query("select origin, alternative from ###altids WHERE origin = ? AND alternative = ?",
				[$origin, $straw_prefiltered]);
			       if(!$resQ->any()){
				   OIDplus::db()->query("INSERT INTO ###altids (origin, alternative) VALUES (?,?);", [$origin, $straw_prefiltered]);
			       }                           
			}
		}
	}

	/**
	 * Acts like in_array(), but allows includes prefilterQuery, e.g. `mac:AA-BB-CC-DD-EE-FF` can be found in an array containing `mac:AABBCCDDEEFF`.
	 * @param string $needle
	 * @param array $haystack
	 * @return bool
	 */
	private static function special_in_array(string $needle, array $haystack) {
		$needle_prefiltered = OIDplus::prefilterQuery($needle,false);
		foreach ($haystack as $straw) {
			$straw_prefiltered = OIDplus::prefilterQuery($straw, false);
			if ($needle == $straw) return true;
			else if ($needle == $straw_prefiltered) return true;
			else if ($needle_prefiltered == $straw) return true;
			else if ($needle_prefiltered == $straw_prefiltered) return true;
		}
		return false;
	}

	/**
	 * @param string $id
	 * @return string[]
	 * @throws \ViaThinkSoft\OIDplus\OIDplusException
	// I do not get this? Similar to versions before (readAll) and why !in_array but if in special_in_array?
        //  mac:63CFE4AEC566 and must be solved to "oid:1.3.6.1.4.1.37553.8.8.2":
	//  -> moved to "where  if we save entries" NOT read all!?!
	public function getAlternativesForQuery(string $id ): array {
		$res = [ $id ];

		// Consider the following testcase:
		// "oid:1.3.6.1.4.1.37553.8.8.2" defines alt ID "mac:63-CF-E4-AE-C5-66" which is NOT canonized!
		// You must be able to enter "mac:63-CF-E4-AE-C5-66" in the search box, which gets canonized
		// to mac:63CFE4AEC566 and must be solved to "oid:1.3.6.1.4.1.37553.8.8.2" by this plugin.
		// Therefore we use self::special_in_array().
		// However, it is mandatory, that previously saveAltIdsForQuery("oid:1.3.6.1.4.1.37553.8.8.2") was called once!
		// Please also note that the "weid:" to "oid:" converting is handled by prefilterQuery(), but only if the OID plugin is installed.

		$resQ = OIDplus::db()->query("select origin, alternative from ###altids");
		while ($row = $resQ->fetch_array()) {
			$test = [ $row['origin'], $row['alternative'] ];
			if (self::special_in_array($id, $test)) {
				$res[] = $row['origin'];
				$res[] = $row['alternative'];
			}
		}

		return array_unique($res);
	}
 */
	public function getAlternativesForQuery(string $id/* INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_7 signature takes just 1 param!? , $noCache = false*/): array {

		if (strpos($id,':') !== false) {
			list($ns, $altIdRaw) = explode(':', $id, 2);
			if($ns === 'weid'){
				$altId = $id;
				$id='oid:'.\Frdl\Weid\WeidOidConverter::weid2oid($id);
			}elseif($ns === 'oid'){					
				$altId=\Frdl\Weid\WeidOidConverter::oid2weid($altIdRaw);				
			}
		}

		 $this->saveAltIdsForQuery($id);
		$res = [
			$id,
			$altId,
		];

		// This would be like the OLD readAll approach: $resQ = OIDplus::db()->query("select origin, alternative from ###altids"); !?!
		$resQ = OIDplus::db()->query("select origin, alternative from ###altids WHERE `origin`= ? OR `alternative`= ?", [$id,$id]);
		while ($row = $resQ->fetch_array()) {
			if(!in_array($row['origin'], $res))$res[]=$row['origin'];
			if(!in_array($row['alternative'], $res))$res[]=$row['alternative'];			
		}
		return $res;
	}

	/**
	 * @param string $id
	 * @param array $out
	 * @param bool $handled
	 * @return void
	 */
	public function gui(string $id, array &$out, bool &$handled) {

	}

	/**
	 * @param array $out
	 * @return void
	 */
	public function publicSitemap(array &$out) {

	}

	/**
	 * @param array $json
	 * @param string|null $ra_email
	 * @param bool $nonjs
	 * @param string $req_goto
	 * @return bool
	 */
	public function tree(array &$json, string $ra_email=null, bool $nonjs=false, string $req_goto=''): bool {
		return false;
	}



	/**
	 * @param string $request
	 * @return array|false
	 */
	public function tree_search(string $request) {
		return false;
	}

	/**
	 * @param string $id
	 * @return false|mixed|string
	 * @throws \ViaThinkSoft\OIDplus\OIDplusException
	 */
	public function getCanonical(string $id){
		foreach($this->getAlternativesForQuery($id) as $alt){
			if (strpos($alt,':') !== false) {
				list($ns, $altIdRaw) = explode(':', $alt, 2);
				if($ns === 'oid'){
					return $alt;
				}
			}
		}

		return false;
	}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_4
	 * @param string $id
	 * @param array $out
	 * @return void
	 * @throws \ViaThinkSoft\OIDplus\OIDplusException
	 */
	public function whoisObjectAttributes(string $id, array &$out) {
		$xmlns = 'oidplus-frdlweb-altids-plugin';
		$xmlschema = 'urn:oid:1.3.6.1.4.1.37553.8.1.8.8.53354196964.641310544.1714020422';
		$xmlschemauri = OIDplus::webpath(__DIR__.'/altids.xsd',OIDplus::PATH_ABSOLUTE_CANONICAL);

		$handleShown = false;
		$canonicalShown = false;

		$out1 = array();
		$out2 = array();

		$tmp = $this->getAlternativesForQuery($id);
		sort($tmp); // DM 26.03.2023 : Added sorting (intended to sort "alternate-identifier")
		foreach($tmp as $alt) {
			if (strpos($alt,':') === false) continue;

			list($ns, $altIdRaw) = explode(':', $alt, 2);

			if (($canonicalShown === false) && ($ns === 'oid')) {
				$canonicalShown=true;

				$out1[] = [
					'xmlns' => $xmlns,
					'xmlschema' => $xmlschema,
					'xmlschemauri' => $xmlschemauri,
					'name' => 'canonical-identifier',
					'value' => $ns.':'.$altIdRaw,
				];

			}

			if (($handleShown === false) && ($alt === $id)) {
				$handleShown=true;

				$out1[] = [
					'xmlns' => $xmlns,
					'xmlschema' => $xmlschema,
					'xmlschemauri' => $xmlschemauri,
					'name' => 'handle-identifier',
					'value' => $alt,
				];

			}

			if ($alt !== $id) { // DM 26.03.2023 : Added condition that alternate must not be the id itself
				$out2[] = [
					'xmlns' => $xmlns,
					'xmlschema' => $xmlschema,
					'xmlschemauri' => $xmlschemauri,
					'name' => 'alternate-identifier',
					'value' => $ns.':'.$altIdRaw,
				];
			}

		}

		// DM 26.03.2023 : Added this
		$out = array_merge($out, $out1); // handle-identifier and canonical-identifier
		$out = array_merge($out, $out2); // alternate-identifier

	}

	/**
	 * Implements interface INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_4
	 * @param string $email
	 * @param array $out
	 * @return void
	 */
	public function whoisRaAttributes(string $email, array &$out) {

	}

}
