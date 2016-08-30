<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class nfc extends eqLogic {


  public static function deamon_info() {
    $return = array();
    $return['log'] = 'nfc_node';
    $return['state'] = 'nok';
    $pid = trim( shell_exec ('ps ax | grep "nfc/node/nfc.js" | grep -v "grep" | wc -l') );
    if ($pid != '' && $pid != '0') {
      $return['state'] = 'ok';
    }
    $return['launchable'] = 'ok';
    if (config::byKey('service','nfc') == '0' || config::byKey('service','nfc') == '') {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __('Le démon n\'est pas configuré', __FILE__);
    }
    return $return;
  }

  public static function deamon_start($_debug = false) {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      die();
    }
    log::add('nfc', 'info', 'Lancement du démon nfc');

    $service_path = realpath(dirname(__FILE__) . '/../../node/');

    if (!config::byKey('internalPort')) {
      $url = config::byKey('internalProtocol') . config::byKey('internalAddr') . config::byKey('internalComplement') . '/core/api/jeeApi.php?api=' . config::byKey('api');
    } else {
      $url = config::byKey('internalProtocol') . config::byKey('internalAddr'). ':' . config::byKey('internalPort') . config::byKey('internalComplement') . '/core/api/jeeApi.php?api=' . config::byKey('api');
    }
    $name = 'master';

    $cmd = 'nodejs ' . $service_path . '/nfc.js "' . $url . '" "' . $name . '"';
    if (config::byKey('dongle','nfc') != '') {
      $cmd = 'NOBLE_HCI_DEVICE_ID=' . config::byKey('dongle','nfc') . ' ' . $cmd;
    }

    log::add('nfc', 'debug', $cmd);
    $result = exec('sudo ' . $cmd . ' >> ' . log::getPathToLog('nfc_node') . ' 2>&1 &');
    if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
      log::add('nfc', 'error', $result);
      return false;
    }

    $i = 0;
    while ($i < 30) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add('nfc', 'error', 'Impossible de lancer le démon nfc, vérifiez le port', 'unableStartDeamon');
      return false;
    }
    message::removeAll('nfc', 'unableStartDeamon');
    log::add('nfc', 'info', 'Démon nfc lancé');
    return true;

  }

  public static function deamon_stop() {
    exec('kill $(ps aux | grep "nfc/node/nfc.js" | awk \'{print $2}\')');
    log::add('nfc', 'info', 'Arrêt du service nfc');
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
      sleep(1);
      exec('kill -9 $(ps aux | grep "nfc/node/nfc.js" | awk \'{print $2}\')');
    }
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
      sleep(1);
      exec('sudo kill -9 $(ps aux | grep "nfc/node/nfc.js" | awk \'{print $2}\')');
    }
  }

  public static function dependancy_info() {
    $return = array();
    $return['log'] = 'nfc_dep';
    $return['progress_file'] = '/tmp/nfc_dep';
    $noble = realpath(dirname(__FILE__) . '/../../node/node_modules/nfc');
    $request = realpath(dirname(__FILE__) . '/../../node/node_modules/request');
    $return['progress_file'] = '/tmp/nfc_dep';
    if (is_dir($noble) && is_dir($request)) {
      $return['state'] = 'ok';
    } else {
      $return['state'] = 'nok';
    }
    return $return;
  }

  public static function dependancy_install() {
    log::add('nfc','info','Installation des dépéndances nodejs');
    $resource_path = realpath(dirname(__FILE__) . '/../../resources');
    passthru('/bin/bash ' . $resource_path . '/nodejs.sh ' . $resource_path . ' > ' . log::getPathToLog('nfc_dep') . ' 2>&1 &');
  }

  public static function event() {
    $reader = init('name');
    $uid = init('uid');
    $nfc = self::byLogicalId($uid, 'nfc');
    if (!is_object($nfc)) {
      if (config::byKey('include_mode','nfc') != 1) {
        return false;
      }
      $nfc = new nfc();
      $nfc->setEqType_name('nfc');
      $nfc->setLogicalId($uid);
      $nfc->setConfiguration('uid', $uid);
      $nfc->setName($uid);
      $nfc->setIsEnable(true);
      event::add('nfc::includeDevice',
      array(
        'state' => $state
      )
    );
  }
  $nfc->setConfiguration('lastCommunication', date('Y-m-d H:i:s'));
  $nfc->save();
  $nfcCmd = nfcCmd::byEqLogicIdAndLogicalId($nfc->getId(),$reader);
  if (!is_object($nfcCmd)) {
    $nfcCmd = new nfcCmd();
    $nfcCmd->setName($reader);
    $nfcCmd->setEqLogic_id($nfc->getId());
    $nfcCmd->setLogicalId($reader);
    $nfcCmd->setType('info');
    $nfcCmd->setSubType('binary');
    $nfcCmd->setConfiguration('returnStateValue',0);
    $nfcCmd->setConfiguration('returnStateTime',1);
  }
  $nfcCmd->setConfiguration('value', 1);
  $nfcCmd->setConfiguration('reader', $reader);
  $nfcCmd->save();
  $nfcCmd->event(1);

}

}


class nfcCmd extends cmd {

}
