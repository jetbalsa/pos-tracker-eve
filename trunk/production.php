<?php

include_once 'eveconfig/config.php';
include_once 'includes/dbfunctions.php';

EveDBInit();

include_once 'includes/eveclass.php';
include 'includes/class.pos.php';
include_once 'includes/eveRender.class.php';

$eveRender = New eveRender($config, $mod, false);
$colors    = $eveRender->themeconfig;
$eveRender->Assign('config',    $config);

$eve     = New Eve();
$posmgmt = New POSMGMT();

$theme_id = $eve->SessionGetVar('theme_id');
$eveRender->Assign('theme_id', $theme_id);
$access = $eve->SessionGetVar('access');
$highly_trusted = $eve->SessionGetVar('highly_trusted');
$userinfo = $posmgmt->GetUserInfo();
$eveRender->Assign('userinfo', $userinfo);
$eveRender->Assign('highly_trusted', $highly_trusted);

$user_id = $_SESSION['delsid'];
if ($access >= 2) {

    $action = $eve->VarCleanFromInput('action');

    if ($action == 'Update Amount') {
		$sql = "SELECT * FROM ".TBL_PREFIX."silo_info";
        $result = mysql_query($sql) or die('Could not get access to the user/pos database; ' . mysql_error());
		while ($value = mysql_fetch_array($result)) {
		$new_amount   = $eve->VarCleanFromInput('new_amount_'.$value[0]);
        $structure_id = $eve->VarCleanFromInput('structure_id_'.$value[0]);
			if ($value[4] != $new_amount)
			{
			UpdateSiloAmount(array('new_amount' => $new_amount, 'structure_id' => $structure_id));
			}
		}
	}

    $regions = $posmgmt->GetInstalledRegions();
    $systems = $posmgmt->GetSystemsWithPos();

    $optregions[] = 'All Regions';
    foreach ($regions as $regID => $region) {
        $optregions[$regID] = $region['regionName'];
    }
    $optsystems[] = 'All Systems';
    foreach ($systems as $sysID => $system) {
        $optsystems[$system['solarSystemID']] = $system['solarSystemName'];
    }

    $args = array();
    $filter_regionID = $eve->VarCleanFromInput('filter_regionID');
    $filter_systemID = $eve->VarCleanFromInput('filter_systemID');
    $filter_pos_id   = $eve->VarCleanFromInput('filter_pos_id');

    if ($access <= 2) {
	$args['ownerID'] = $userinfo['eve_id'];
    }
    if ($filter_regionID) {
        $eveRender->Assign('filter_regionID', $filter_regionID);
        $args['regionID'] = $filter_regionID;
    }
    if ($filter_systemID) {
        $eveRender->Assign('filter_systemID', $filter_systemID);
        $args['systemID'] = $filter_systemID;
    }
    if ($filter_pos_id) {
        $eveRender->Assign('filter_pos_id', $filter_pos_id);
        $args['pos_ids'][] = $filter_pos_id;
    }

    $allsilos = GetAllProd($args);

    $optposids[0] = 'All';
    foreach ($allsilos as $dummy) {
        $optposids[$dummy['pos_id']] = $dummy['locationName'];
    }

    $eveRender->Assign('owner_bgcolor',      $colors['owner_background_color']);
    $eveRender->Assign('regions',            $regions);
    $eveRender->Assign('optregions',         $optregions);
    $eveRender->Assign('systems',            $systems);
    $eveRender->Assign('optsystems',         $optsystems);
    $eveRender->Assign('optposids',          $optposids);
    $eveRender->Assign('allsilos', $allsilos);
    $eveRender->Assign('access', $access);
    $eveRender->Display('production.tpl');
} else {
	$eve->SessionSetVar('errormsg', 'Access Denied - Redirecting you back!');
	$eve->RedirectUrl('track.php');
}


function UpdateSiloAmount($args)
{

    global $eve;

    if (!isset($args['structure_id']) || !is_numeric($args['structure_id'])) {
        return false;
    }
    if (!isset($args['new_amount']) || !is_numeric($args['new_amount'])) {
        return false;
    }

    $dbconn =& DBGetConn(true);

    $sql = "UPDATE ".TBL_PREFIX."silo_info SET material_ammount = '".Eve::VarPrepForStore($args['new_amount'])."' WHERE silo_id = '".Eve::VarPrepForStore($args['structure_id'])."'";

    $dbconn->Execute($sql);

    if ($dbconn->ErrorNo() != 0) {
        echo $dbconn->ErrorMsg() . $sql;
        return false;
    }

    $time = time();

    $sql = "INSERT INTO ".TBL_PREFIX."update_log VALUES ('NULL', '1', '" . Eve::VarPrepForStore($args['structure_id']) . "', '2', 'Update Silo', '" . Eve::VarPrepForStore($time)."')";
    $dbconn->Execute($sql);

    if ($dbconn->ErrorNo() != 0) {
        echo $dbconn->ErrorMsg() . $sql;
        return false;
    }

    $filter_regionID = $eve->VarCleanFromInput('filter_regionID');
    $filter_systemID = $eve->VarCleanFromInput('filter_systemID');
    $filter_pos_id   = $eve->VarCleanFromInput('filter_pos_id');

    $url = 'production.php';
    //if ($filter_systemID || $filter_regionID) {

    $eve->RedirectUrl('production.php?filter_regionID='.$filter_regionID.'&filter_systemID='.$filter_systemID.'&filter_pos_id='.$filter_pos_id);

}

function GetallProd($args)
{

    global $eve, $posmgmt;

    $towers = $posmgmt->GetAllTowers($args);

    $silocap = array(1  => 30000,
                     2  => 20000,
                     3  => 40000,
                     4  => 20000,
                     5  => 20000,
                     6  => 30000,
                     7  => 30000,
                     8  => 20000,
                     9  => 20000,
                     10 => 20000,
                     11 => 30000,
                     12 => 40000,
                     13 => 40000,
                     14 => 30000);

    $allsilos = array();
    foreach($towers as $tower) {
        $pos_id = $tower['pos_id'];
		$access = $eve->SessionGetVar('access');
		$highly_trusted = $eve->SessionGetVar('highly_trusted');
		$owner_id				 = $tower['owner_id'];
		$sec_owner_id			 = $tower['secondary_owner_id'];
		
		$owner_info=$posmgmt->GetUserInfofromID($tower['owner_id']);
        $tower['owner_name']=$owner_info['name'];

        $sec_owner_info=$posmgmt->GetUserInfofromID($tower['secondary_owner_id']);
        $tower['secondary_owner_name']=$sec_owner_info['name'];
		$secret_pos         = $tower['secret_pos'];
		
		if ($secret_pos == 1 && $access != 5) { //Secret POS Access Check. Will go through if highly trusted or are a fuel tech of the tower.
			if ($highly_trusted == 1 || $eve_id == $owner_id || $eve_id == $sec_owner_id) {
				if ($access <= 2 && $eve_id != $owner_id && $eve_id != $sec_owner_id) { //Must be at View-All Manager or higher access to see secret POS.
					continue;
				}
			}
			else {
				echo $access;
				continue;
			}
		}
		
		if ($access == 1) { //View Only Access Check, should make sure they are ONLY looking at their own towers.
			if ($eve_id == $owner_id || $eve_id == $secondary_owner_id) {
			}
			else {
					continue;
			}
		}
		
        if ($tower['moonID']) {
            $sql = "SELECT ".TBL_PREFIX."evemoons.moonName FROM `".TBL_PREFIX."evemoons` WHERE ".TBL_PREFIX."evemoons.moonID='".$tower['moonID']."'";
            $result = mysql_query($sql) or die('Could not get access to the user/pos database; ' . mysql_error());
            $moonrow = mysql_fetch_array($result);
            $tower['locationName'] = $moonrow['moonName'];
        } else {
            $tower['locationName'] = $posmgmt->getSystemName($tower['systemID']);
        }

        $silos = $posmgmt->GetPosSilos($pos_id);
        $x = 0;
        $display_silo = false;
        $pos_race = $tower['pos_race'];

        //$allsilos = array();
        $silo = array();
        foreach($silos as $row0) {
            //$silo = array();
            $display_silo = true;

            $available_material = $current_material_vol = $available_silo_vol = $hourstofill = $silo_material_ammount = 0;

            //Basic info about Silo
            $x                      = $row0['silo_id'];
            $silo_link              = $row0['silo_link'];
            $silo_id                = $row0['silo_id'];
            $silo_type              = $row0['silo_type'];
            $silo_material_id       = $row0['material_id'];
            $silo_material_ammount  = $row0['material_ammount'];
            $silo_status            = $row0['status'];
            $silo_connection        = $row0['connection_id'];
            //How long ago was the silo updated
            $hoursago                     = floor($posmgmt->hoursago($silo_id, 2));
            $silo[$x]['hoursago']         = $hoursago;
            $silo[$x]['silo_id']          = $row0['silo_id'];
            $silo[$x]['silo_type']        = $row0['silo_type'];
            $silo[$x]['material_id']      = $row0['material_id'];
            //$silo[$x]['material_ammount'] =  $row0['material_id'];
            $silo[$x]['material_amount']  = $row0['material_ammount'];
            $silo[$x]['status']           = $row0['status'];
            $silo[$x]['connection_id']    = $row0['connection_id'];
            $silo[$x]['silo_link']        = (($row0['silo_link']) ? $row0['silo_link'] : 0);
            //if ($x == 495) { echo '<pre>';print_r($silo[$x]);echo '</pre>';exit; }
            //reactor/harvester info
            $row = $posmgmt->GetConnectedReator($silo_connection);

            if ($row) {
                $structure_type         = $row['structure_type'];
                $structure_material_id  = $row['material_id'];
                $silo[$x]['structure_type']         = $row['structure_type'];
                $silo[$x]['structure_material_id']  = $row['material_id'];
            }

            //Reactor/Harvster Name/type
            $struct = $posmgmt->GetStaticModInfo($structure_type);

            if ($struct) {
                $silo[$x]['structure_name'] = $struct['name'];
            }

            $matinfo = $posmgmt->GetStaticMatInfo($structure_material_id);

            if ($matinfo) {
                $silo[$x]['structure_material_name'] = $matinfo['material_name'];
            }

            //Moon harvester Rate
            if ($structure_type==16221 && $structure_material_id == $silo_material_id) {
                $rate = 100;
            }

            //Reactor Rates
            if($structure_type==16869 || $structure_type==20175) {
                //Reaction info
                $reactioninfo = $posmgmt->GetStaticReactionInfo($structure_material_id);

                if ($reactioninfo) {
                    //For Output Silo
                    if ($silo_material_id == $reactioninfo['material_id']) {
                        $rate = $reactioninfo['material_produced'];
                    } else {
                        //For Input Silo
                        if ($reactioninfo['material1_id'] == $silo_material_id) {
                            $rate = $reactioninfo['material1_required'];
                        }
                        if ($reactioninfo['material2_id'] == $silo_material_id) {
                            $rate = $reactioninfo['material2_required'];
                        }
                        if ($reactioninfo['material3_id'] == $silo_material_id) {
                            $rate = $reactioninfo['material3_required'];
                        }
                        if ($reactioninfo['material4_id'] == $silo_material_id) {
                            $rate = $reactioninfo['material4_required'];
                        }
                    }
                }
            }
            if (!$rate) { $rate = 100; }
            //Silo Material Information
            $smatinfo = $posmgmt->GetStaticMatInfo($silo_material_id);

            if ($smatinfo) {
                $silo[$x]['material_name'] = $smatinfo['material_name'];
                $silo[$x]['material_volume'] = $smatinfo['material_volume'];
                $material_volume = $smatinfo['material_volume'];
            }
            $silo[$x]['rate']                = $rate;
            $silo[$x]['rate_vol']            = $rate * $smatinfo['material_volume'];

            $silo[$x]['silo_capacity'] = $silocap[$tower['pos_race']];

            if($silo_status == 1) {

                //$rate_vol = $rate * $material_volume;
                $silo[$x]['direction']  = 'Output';


                $matinfo = $posmgmt->GetStaticMatInfo($silo[$x]['material_id']);
                $row = $posmgmt->GetConnectedReator($silo[$x]['connection_id']);
                if ($row) {
                    $silo[$x]['structure_type']         = $row['structure_type'];
                    $silo[$x]['structure_material_id']  = $row['material_id'];
                }
                if ($silo[$x]['structure_type'] == 16221 && $silo[$x]['structure_material_id'] == $silo[$x]['material_id']) {
                    // Harvestor
                    $rate = 100;
                }

                $rate_vol = $rate * $matinfo['material_volume'];

                $silo[$x]['rate']                = $rate;
                $silo[$x]['rate_vol']            = $rate_vol;
                $silo[$x]['material_volume']     = $matinfo['material_volume'];
                $silo[$x]['material_amount_max'] = $silo[$x]['silo_capacity'] / $silo[$x]['material_volume'];

                //$silo[$x]['hoursago'] = floor($posmgmt->hoursago($silo[$x], 2));

                if (!$silo[$x]['silo_link']) {

                    $silo[$x]['material_amount'] = ($silo[$x]['rate']*$silo[$x]['hoursago'])+$silo[$x]['material_amount'];
                    $silo[$x]['current_material_vol'] = $silo[$x]['material_amount'] * $silo[$x]['material_volume'];
                    $silo[$x]['available_silo_vol']   = $silo[$x]['silo_capacity'] - $silo[$x]['current_material_vol'];
                    if ($silo[$x]['available_silo_vol'] < 0) { $silo[$x]['available_silo_vol'] = 0; }

                    $silo[$x]['hourstofill'] = @floor($silo[$x]['available_silo_vol'] / $rate / $matinfo['material_volume']);

                    $silo[$x]['hourstofill_total']     = @floor($silo[$x]['silo_capacity'] / $rate / $matinfo['material_volume']);///$matinfo['material_volume']+$silo[$x]['material_amount'];
                    $silo[$x]['hourstofill_txt']       = $posmgmt->daycalc($silo[$x]['hourstofill']);
                    $silo[$x]['hourstofill_total_txt'] = $posmgmt->daycalc($silo[$x]['hourstofill_total']);

                    $silo[$x]['full'] = (($silo[$x]['current_material_vol'] >= $silo[$x]['silo_capacity']) ? 1 : 0);

                    if ($silo[$x]['full']) {
                        $silo[$x]['available_silo_vol'] = 0;
                        $silo[$x]['material_amount']    = $silo[$x]['silo_capacity'] / $matinfo['material_volume'];
                        $silo[$x]['current_material_vol'] = $silo[$x]['material_amount'] * $silo[$x]['material_volume'];
                    }
                } else {
                    $linked = $silo[$silo[$x]['silo_link']];
                    if (!$linked['full']) {
                        $silo[$x]['material_amount']    = 0;
                        $silo[$x]['available_silo_vol'] = $silo[$x]['silo_capacity'];
                        $silo[$x]['hourstofill']        = @floor(($silo[$x]['available_silo_vol']+$linked['available_silo_vol']) / $rate / $matinfo['material_volume']);
                        $silo[$x]['hourstofill_total']  = @floor(($silo[$x]['silo_capacity'] / $rate / $matinfo['material_volume']) + $linked['hourstofill']);
                    } else {
                        $silo[$x]['material_amount']    = ($rate_vol*$silo[$x]['hoursago'])/$matinfo['material_volume']+$silo[$x]['material_amount'];
                        $silo[$x]['current_material_vol'] = $silo[$x]['material_amount'] * $silo[$x]['material_volume'];
                        $silo[$x]['available_silo_vol'] = $silo[$x]['silo_capacity'] - $silo[$x]['current_material_vol'];
                        $silo[$x]['hourstofill']        = @floor($silo[$x]['available_silo_vol'] / $rate / $silo[$x]['material_volume']);

                        $silo[$x]['hourstofill_total']  = @floor($silo[$x]['silo_capacity'] / $rate_vol * $matinfo['material_volume']);
                    }
                    $silo[$x]['full']                  = (($silo[$x]['current_material_vol'] >= $silo[$x]['silo_capacity']) ? 1 : 0);
                    $silo[$x]['hourstofill_txt']       = $posmgmt->daycalc($silo[$x]['hourstofill']);
                    $silo[$x]['hourstofill_total_txt'] = $posmgmt->daycalc($silo[$x]['hourstofill_total']);

                }

                //if ($tower['pos_id'] == 51 && $x == 476)
                //if ($tower['pos_id'] == 18){// && $x == 476)
                //    echo $tower['pos_race'].'<pre>';print_r($silo);echo '</pre>';exit;
                //}


            } else {
                //Silo is emptying, input silo
                $silo[$x]['material_amount_orig'] = $silo[$x]['material_amount'];
                $silo[$x]['material_amount']     = $silo[$x]['material_amount']-($hoursago*$rate);
                $silo[$x]['material_amount_max'] = $silo[$x]['silo_capacity'] / $silo[$x]['material_volume'];//-($hoursago*$rate);
                $silo[$x]['hourstogo']       = $silo[$x]['hourstofill'] = @floor($silo[$x]['material_amount']/$rate);
                $silo[$x]['direction']       = 'Input';
                $silo[$x]['empty']           = (($silo[$x]['material_amount'] <= 0) ? 1 : 0);//(($silo[$x]['hourstogo'] <= 0) ? 1 : 0);
                if ($silo[$x]['hourstogo'] < 0) {
                    $silo[$x]['hourstogo'] = $silo[$x]['hourstofill'] = 0;

                }
                $silo[$x]['hourstogo_txt']       = $posmgmt->daycalc($silo[$x]['hourstogo']);
                $hours = $silo[$x]['hourstogo'];
            }//if ($x == 494) { echo '<pre>';print_r($silo[$x]);echo '</pre>';exit; }
        }
        if ($silo) {
            $tower['silos'] = $silo;
            $allsilos[$pos_id] = $tower;
        }

        //End Silo Tracking Code
    }

    //echo '<pre>';print_r($allsilos[58]['silos'][538]);echo '</pre>';exit;
    //echo '<pre>';print_r($allsilos);echo '</pre>';exit;

    foreach ($allsilos as $tower) {
        //foreach($tower['silos'] as $z) {
            $sortAarr[] = $tower['locationName'];
        //}
    }
    array_multisort($sortAarr, SORT_ASC, $allsilos);

    return $allsilos;
}
?>