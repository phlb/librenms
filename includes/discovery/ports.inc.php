<?php

// Build SNMP Cache Array

$port_stats = array();
$port_stats = snmpwalk_cache_oid($device, "ifDescr", $port_stats, "IF-MIB");
$port_stats = snmpwalk_cache_oid($device, "ifName", $port_stats, "IF-MIB");
$port_stats = snmpwalk_cache_oid($device, "ifType", $port_stats, "IF-MIB");

// End Building SNMP Cache Array

if ($debug) { print_r($port_stats); }

// Build array of ports in the database

// FIXME -- this stuff is a little messy, looping the array to make an array just seems wrong. :>
//       -- i can make it a function, so that you don't know what it's doing.
//       -- $ports_db = adamasMagicFunction($ports_db); ?

if ($device['os_group'] == "unix") {
  $port_index_name='ifName';
}
else {
  $port_index_name='ifIndex';
}

foreach (dbFetchRows("SELECT * FROM `ports` WHERE `device_id` = ?", array($device['device_id'])) as $port)
{
  $ports_db[$port[$port_index_name]] = $port;
  $ports_db_l[$port[$port_index_name]] = $port['port_id'];
}

// New interface detection
foreach ($port_stats as $ifIndex => $port)
{
  $ifName = $port['ifName'];
  $port_index= $port[$port_index_name];
  // Check the port against our filters.
  if (is_port_valid($port, $device))
  {
    if (!is_array($ports_db[$port_index]))
    {
      $port_id = dbInsert(array('device_id' => $device['device_id'], 'ifIndex' => $ifIndex, 'ifName' => $ifName), 'ports');
      $ports_db[$port_index] = dbFetchRow("SELECT * FROM `ports` WHERE `device_id` = ? AND `$port_index_name` = ?", array($device['device_id'], $port_index));
      echo("Adding: ".$port['ifName']."(".$ifIndex.")(".$ports_db[$port_index]['port_id'].")");
    } elseif ($ports_db[$port_index]['deleted'] == "1") {
      dbUpdate(array('deleted' => '0'), 'ports', '`port_id` = ?', array($ports_db[$port_index]['port_id']));
      $ports_db[$port_index]['deleted'] = "0";
      echo("U");
    } elseif ($ports_db[$port_index]['ifName']  != $ifName) {
      dbUpdate(array('ifName' => $ifName), 'ports', '`port_id` = ?', array($ports_db[$port_index]['port_id']));
      $ports_db[$port_index]['ifName'] = $ifName;
      echo ("U");
    } elseif ($ports_db[$port_index]['ifIndex'] != $ifIndex) {
      dbUpdate(array('ifIndex' => $ifIndex), 'ports', '`port_id` = ?', array($ports_db[$port_index]['port_id']));
      $ports_db[$port_index]['ifIndex'] = $ifIndex;
      echo ("U");
    } else {
      echo(".");
    }
    // We've seen it. Remove it from the cache.
    unset($ports_l[$port_index]);
  } else {
    if (is_array($ports_db[$port_index])) {
      if ($ports_db[$port_index]['deleted'] != "1")
      {
        dbUpdate(array('deleted' => '1'), 'ports', '`port_id` = ?', array($ports_db[$port_index]['port_id']));
        $ports_db[$port_index]['deleted'] = "1";
        echo("-");
      }
    }
    echo("X");
  }
}
// End New interface detection

// Interface Deletion
// If it's in our $ports_l list, that means it's not been seen. Mark it deleted.
foreach ($ports_l as $ifIndex => $port_id)
{
  if ($ports_db[$ifIndex]['deleted'] == "0")
  {
    dbUpdate(array('deleted' => '1'), 'ports', '`port_id` = ?', array($port_id));
    echo("-".$ifIndex);
  }
}
// End interface deletion
echo("\n");



// Clear Variables Here
unset($port_stats);
unset($ports_db);
unset($ports_db_db);
unset($port_index_name);
?>
