<?php

/*
 * OIDplus 2.0
 * Copyright 2022 Daniel Marschall, ViaThinkSoft / Till Wehowski, Frdlweb
 *
 * Licensed under the MIT License.
 */

namespace Frdlweb\OIDplus;

use ViaThinkSoft\OIDplus\OIDplus;
use ViaThinkSoft\OIDplus\OIDplusObject;
use ViaThinkSoft\OIDplus\OIDplusPagePluginPublic;

class OIDplusPagePublicAltIds extends OIDplusPagePluginPublic {

	public function action($actionID, $params) {

	}

	public function gui($id, &$out, &$handled) {

	}

	public function publicSitemap(&$out) {

	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
		return false;
	}

	private function cache_id() {
		static $cache_id = null;
		if (!is_null($cache_id)) return $cache_id;
		$cache_id  =  'Create='.OIDplus::db()->getScalar("select max(created) as ts from ###objects where created is not null;");
		$cache_id .= '/Update='.OIDplus::db()->getScalar("select max(updated) as ts from ###objects where updated is not null;");
		$cache_id .= '/Count='.OIDplus::db()->getScalar("select count(id) as cnt from ###objects;");
		$plugin_versions = array();
		foreach (OIDplus::getObjectTypePluginsEnabled() as $otp) {
			$plugin_versions[] = '/'.$otp->getManifest()->getOid().'='.$otp->getManifest()->getVersion();
		}
		sort($plugin_versions);
		$cache_id .= implode('',$plugin_versions);
		return $cache_id;
	}

	public function readAll($noCache = false) {
		static $local_cache = null;

		$cache_file = OIDplus::localpath().'/userdata/cache/frdl_alt_id.ser';
		if ($noCache === false) {
			// Local cache (to save time for multiple calls during the same HTTP request)
			if (!is_null($local_cache)) return $local_cache;

			// File cache (to save time between HTTP requests)
			if (file_exists($cache_file)) {
				$cache_data = unserialize(file_get_contents($cache_file));
				$cache_id = $cache_data[0];
				if ($cache_id == $this->cache_id()) {
					return $cache_data[1];
				}
			}
		}

		$alt_ids = array();
		$rev_lookup = array();

		$res = OIDplus::db()->query("select id from ###objects");
		while ($row = $res->fetch_array()) {
			$obj = OIDplusObject::parse($row['id']);
			if (!$obj) continue; // e.g. if plugin is disabled
			$ary = $obj->getAltIds();
			foreach ($ary as $a) {
				$origin = $obj->nodeId(true);
				$alternative = $a->getNamespace() . ':' . $a->getId();

				if (!isset($alt_ids[$origin])) $alt_ids[$origin] = array();
				$alt_ids[$origin][] = $alternative;

				if (!isset($rev_lookup[$alternative])) $rev_lookup[$alternative] = array();
				$rev_lookup[$alternative][] = $origin;
			}
		}

		$data = array($alt_ids, $rev_lookup);

		// File cache (to save time between HTTP requests)
		$cache_data = array($this->cache_id(), $data);
		@file_put_contents($cache_file, serialize($cache_data));

		// Local cache (to save time for multiple calls during the same HTTP request)
		$local_cache = $data;

		return $data;
	}

	public function getAlternativesForQuery($id/* 1.3.6.1.4.1.37476.2.5.2.3.7 signature takes just 1 param!? , $noCache = false*/) {

		static $caches = array();

		if(/*$noCache === false && */isset($caches[$id]) ){
			return $caches[$id];
		}

		if (strpos($id,':') !== false) {
			list($ns, $altIdRaw) = explode(':', $id, 2);
			if($ns === 'weid'){
				$id='oid:'.\Frdl\Weid\WeidOidConverter::weid2oid($id);
			}
		}

		list($alt_ids, $rev_lookup) = $this->readAll(false);

		$res = [
			$id,
		];
		if(isset($rev_lookup[$id])){
			$res = array_merge($res, $rev_lookup[$id]);
		}
		foreach($alt_ids as $original => $altIds){
			if($id === $original || in_array($id, $altIds) ){
				 $res = array_merge($res, $altIds);
				 $res = array_merge($res, [$original]);
			}
		}

		$weid = false;
		foreach($res as $alt){
			if (strpos($alt,':') !== false) {
				list($ns, $altIdRaw) = explode(':', $alt, 2);
				if($ns === 'oid'){
					$weid=\Frdl\Weid\WeidOidConverter::oid2weid($altIdRaw);
					break;
				}
			}
		}

		if ($weid !== false) {
			$res[]=$weid;
		}
		$res = array_unique($res);

		$caches[$id] = $res;

		return $res;
	}

	public function implementsFeature($id) {
		if (strtolower($id) == '1.3.6.1.4.1.37476.2.5.2.3.4') return true; // whois*Attributes
		if (strtolower($id) == '1.3.6.1.4.1.37476.2.5.2.3.7') return true; // getAlternativesForQuery
		return false;
	}

	public function tree_search($request) {
		return false;
	}

	public function getCanonical($id){
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

	public function whoisObjectAttributes($id, &$out) {
		// Interface 1.3.6.1.4.1.37476.2.5.2.3.4

		$xmlns = 'oidplus-frdlweb-altids-plugin';
		$xmlschema = 'urn:oid:1.3.6.1.4.1.37553.8.1.8.8.53354196964.641310544.1714020422';
		$xmlschemauri = OIDplus::webpath(__DIR__.'/altids.xsd',OIDplus::PATH_ABSOLUTE);

		$handleShown = false;
		$canonicalShown = false;

		foreach($this->getAlternativesForQuery($id) as $alt) {

			if (strpos($alt,':') === false) continue;

			list($ns, $altIdRaw) = explode(':', $alt, 2);

 			if (($canonicalShown === false) && ($ns === 'oid')) {
				$canonicalShown=true;

				$out[] = [
					'xmlns' => $xmlns,
					'xmlschema' => $xmlschema,
					'xmlschemauri' => $xmlschemauri,
					'name' => 'canonical-identifier',
					'value' => $ns.':'.$altIdRaw,
				];

			}

			if (($handleShown === false) && ($alt === $id)) {
				$handleShown=true;

				$out[] = [
					'xmlns' => $xmlns,
					'xmlschema' => $xmlschema,
					'xmlschemauri' => $xmlschemauri,
					'name' => 'handle-identifier',
					'value' => $alt,
				];

			}

			$out[] = [
				'xmlns' => $xmlns,
				'xmlschema' => $xmlschema,
				'xmlschemauri' => $xmlschemauri,
				'name' => 'alternate-identifier',
				'value' => $ns.':'.$altIdRaw,
			];

		}
	}

	public function whoisRaAttributes($email, &$out) {
		// Interface 1.3.6.1.4.1.37476.2.5.2.3.4
	}

 }
