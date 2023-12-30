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

	// TODO: call this via cronjob
	public function renewAll() {
		OIDplus::db()->query("delete from ###altids");
		$resQ = OIDplus::db()->query("select * from ###objects");
		while ($row = $resQ->fetch_array()) {
			$this->saveAltIdsForQuery($row['id']);
		}
	}

	protected function saveAltIdsForQuery(string $id){

		// Why prefiltering? Consider the following testcase:
		// "oid:1.3.6.1.4.1.37553.8.8.2" defines alt ID "mac:63-CF-E4-AE-C5-66" which is NOT canonized (otherwise it would not look good)!
		// You must be able to enter "mac:63-CF-E4-AE-C5-66" in the search box, which gets canonized
		// to mac:63CFE4AEC566 and must be resolved to "oid:1.3.6.1.4.1.37553.8.8.2" by this plugin.
		// Therefore we use self::special_in_array().
		// However, it is mandatory, that previously saveAltIdsForQuery("oid:1.3.6.1.4.1.37553.8.8.2") was called once!
		// Please also note that the "weid:" to "oid:" converting is handled by prefilterQuery(), but only if the OID plugin is installed.

		$obj = OIDplusObject::parse($id);
		if (!$obj) return; // e.g. if plugin is disabled
		$ary = $obj->getAltIds();
		$origin = $obj->nodeId(true);
		$origin_prefiltered = OIDplus::prefilterQuery($origin, false);
		if($origin_prefiltered !== $origin){
			$resQ = OIDplus::db()->query("select origin, alternative from ###altids WHERE origin = ? AND alternative = ?",
				[$origin, $origin_prefiltered]);
			if(!$resQ->any()){
				OIDplus::db()->query("INSERT INTO ###altids (origin, alternative) VALUES (?,?);", [$origin, $origin_prefiltered]);
			}
		}
		foreach ($ary as $a) {
			$alternative = $a->getNamespace() . ':' . $a->getId();
			$resQ = OIDplus::db()->query("select origin, alternative from ###altids WHERE origin = ? AND alternative = ?",
				[$origin, $alternative]);
			if(!$resQ->any()){
				OIDplus::db()->query("INSERT INTO ###altids (origin, alternative) VALUES (?,?);", [$origin, $alternative]);
			}

			$alternative_prefiltered = OIDplus::prefilterQuery($alternative, false);
			if($alternative_prefiltered !== $alternative){
				$resQ = OIDplus::db()->query("select origin, alternative from ###altids WHERE origin = ? AND alternative = ?",
					[$origin, $alternative_prefiltered]);
				if(!$resQ->any()){
					OIDplus::db()->query("INSERT INTO ###altids (origin, alternative) VALUES (?,?);", [$origin, $alternative_prefiltered]);
				}
			}
		}
	}

	public function getAlternativesForQuery(string $id/* INTF_OID_1_3_6_1_4_1_37476_2_5_2_3_7 signature takes just 1 param!? , $noCache = false*/): array {

		// DM 30.12.2023 : Why handle "weid:" here? It is handled by prefilterQuery(), if OID plugin is installed
		/*
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
			$altId,  // <-- not always defined!
		];
		*/

		$id_prefiltered = OIDplus::prefilterQuery($id, false);

		$res = [
			$id,
			$id_prefiltered
		];

		$resQ = OIDplus::db()->query("select origin, alternative from ###altids WHERE origin = ? OR alternative = ? OR origin = ? OR alternative = ?", [$res[0],$res[0],$res[1],$res[1]]);
		while ($row = $resQ->fetch_array()) {
			if(!in_array($row['origin'], $res))$res[]=$row['origin'];
			if(!in_array($row['alternative'], $res))$res[]=$row['alternative'];
		}

		return array_unique($res);
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
