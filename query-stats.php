<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Route Stats by Route Group</title>
<style type="text/css">
<!--
@import url("style.css");
-->
</style>
</head>
<body>

<!-- change stome stuff on the Fleet Manager Development service -->

<?php

require("{$_SERVER['DOCUMENT_ROOT']}/php/globals.php");
require("{$_SERVER['DOCUMENT_ROOT']}/php/adodb5/adodb.inc.php");

// Get inbound URL
if (isset($_SERVER['HTTP_HOST'])) {

  $server = $_SERVER['HTTP_HOST'];

} else if (isset($_SERVER['argc']) && $_SERVER['argc'] > 1 ) {

  $server = $_SERVER['argv'][1];

} else {

  $server = 'applications.gomobileiq.com';

}

$host = "MOBILEIQ-MSSQL";
$user = "sa"; 

// production server
if ( strrpos($server, 'gomobileiq.com') ) {
  $pass = "quickbrownF0x"; 
  $database = "SE100989";
  $table = "2013 August Route Balancing rev2";
  $table_stats = $table . "_route_stats";
  $routeFilter = 'LW-D';
}

// development server
if ( strrpos($server, 'freeroutingsoftware.com') ) {
  $pass = "1919BlackS0x"; 
  $database = "SE100983";
  $table = "customer";
  $table_stats = $table . "_route_stats";
}

?>

  <h3><?php echo $table; ?></h3>
  <table id="hor-minimalist-b" summary="Employee Pay Sheet">
    <thead>
      <tr>
        <th scope="col">Loc-Rte</th>
        <th scope="col"></th>
        <th scope="col">Route Group</th>
        <th scope="col">Stops</th>
        <th scope="col">Mileage</th>
        <th scope="col">Route Hours</th>
        <th scope="col">Water</th>
        <th scope="col">Salt</th>
        <th scope="col">Units</th>
      </tr>
    </thead>
    <tbody>

<?php

UpdateStats($pass, $database, $table, $table_stats);

ShowResults($host, $user, $pass, $database, $table_stats, $routeFilter);

exit;

/* ------------------------------------------------------------------------------------------*/
function UpdateStats($pass, $database, $table, $table_stats) {

  $db = ADONewConnection('odbc_mssql');

  $dsn = "Driver={SQL Server};Server=MOBILEIQ-MSSQL;Database={$database};";
  $db->Connect($dsn, 'sa', $pass) or die("Couldn't connect to MOBILEIQ-MSSQL");
  
  // clean out any junk records
  $db->Execute("delete from [{$table_stats}] where start + '-' + route + '-' + convert(varchar, daycode) not in (select distinct start + '-' + route + '-' + convert(varchar, weekday) from [$table])");

  // query for dirty stats that require updating
  $rs = $db->Execute("exec dbo.usp_symQryStatsToCalc '{$table}', '{$table_stats}'");

  // loop through result set
  while( !$rs->EOF ) {

      // calculate stats for all dirty records regardless of filter conditions
//    if ( in_array("{$rs->fields[0]}-{$rs->fields[1]}", $routeFilter) ) {

        $sql = "exec dbo.usp_symCalcAndStoreStatsForTables '[{$table}]', 'customer', 'customer_conductor', '[{$table_stats}]', '{$rs->fields[0]}', '{$rs->fields[1]}', {$rs->fields[2]}";
        
        $db->Execute($sql);

//    }

    $rs->MoveNext();
  }
  
}

/* ------------------------------------------------------------------------------------------*/
function ShowResults($host, $user, $pass, $database, $table_stats, $routeFilter) {

  mssql_connect($host, $user, $pass);
  mssql_select_db($database);

  $query = BuildStatsQuery($table_stats);
  $rs = mssql_query($query); 

  while ($row = mssql_fetch_assoc($rs)) {

    if (!$routeFilter || $row['Loc-Rte'] == $routeFilter) {

      $image = '';
      switch ($row['RouteGroup'])
      {
        case '1/6/11/16':
          $image = "<img src='images/red-circle.png'>";
          break;
        case '2/7/12/17':
          $image = "<img src='images/yellow-circle.png'>";
          break;
        case '3/8/13/18':
          $image = "<img src='images/blue-circle.png'>";
          break;
        case '4/9/14/19':
          $image = "<img src='images/cyan-circle.png'>";
          break;
        case '5/10/15/20':
          $image = "<img src='images/green-circle.png'>";
          break;     
      }

      print <<< DOC

      <tr>

        <td>{$row['Loc-Rte']}</td>
        <td>$image</td>
        <td>{$row['RouteGroup']}</td>
        <td>{$row['Stops']}</td>
        <td>{$row['Mileage']}</td>
        <td class='highlight'>{$row['Route Hours']}</td>
        <td>{$row['Water']}</td>
        <td>{$row['Salt']}</td>
        <td>{$row['Units']}</td>

      </tr>

DOC;

    } // end if
  } // end while

  mssql_close();

}

/* ------------------------------------------------------------------------------------------*/
function BuildStatsQuery($scenario) {

  return 

"select 
start + '-' + route as 'Loc-Rte',
cast(sum(avgStops) as int) as Stops,
cast(round(sum(avgDistance), 2) as numeric(38,2)) as Mileage,
cast(round(sum(avgHoursRoute), 2) as numeric(38,2)) as 'Route Hours',
cast(round(sum(avgQuantity_2), 0) as decimal) as Water,
cast(round(sum(avgQuantity_1), 0) as decimal) as Salt,
cast(round(sum(avgQuantity_0), 0) as decimal) as Units,
'1/6/11/16' as RouteGroup
from
(select start, route,
avg(avgStops) as [avgStops],
avg(avgDistance) as [avgDistance],
avg(avgHoursRoute) as [avgHoursRoute],
avg(avgQuantity_0) as [avgQuantity_0],
avg(avgQuantity_1) as [avgQuantity_1],
avg(avgQuantity_2) as [avgQuantity_2]

from [{$scenario}] s

where calDate > getdate() and cast(daycode as varchar) in ('1', '6', '11', '16')
group by start, route, daycode) r

group by start, route

-- get the summary info by loc-rte for 2/7/12/17
union
  select 
  start + '-' + route as 'Loc-Rte',
  cast(sum(avgStops) as int) as Stops,
  cast(round(sum(avgDistance), 2) as numeric(38,2)) as Mileage,
  cast(round(sum(avgHoursRoute), 2) as numeric(38,2)) as 'Route Hours',
  cast(round(sum(avgQuantity_2), 0) as decimal) as Water,
  cast(round(sum(avgQuantity_1), 0) as decimal) as Salt,
  cast(round(sum(avgQuantity_0), 0) as decimal) as Units,
  '2/7/12/17' as RouteGroup
  from
  (select start, route,
  avg(avgStops) as [avgStops],
  avg(avgDistance) as [avgDistance],
  avg(avgHoursRoute) as [avgHoursRoute],
  avg(avgQuantity_0) as [avgQuantity_0],
  avg(avgQuantity_1) as [avgQuantity_1],
  avg(avgQuantity_2) as [avgQuantity_2]
  
  from [{$scenario}] s
  
  where calDate > getdate() and cast(daycode as varchar) in ('2', '7', '12', '17')
  group by start, route, daycode) r
  
  group by start, route

-- get the summary info by loc-rte for 3/8/13/18
union
  select 
  start + '-' + route as 'Loc-Rte',
  cast(sum(avgStops) as int) as Stops,
  cast(round(sum(avgDistance), 2) as numeric(38,2)) as Mileage,
  cast(round(sum(avgHoursRoute), 2) as numeric(38,2)) as 'Route Hours',
  cast(round(sum(avgQuantity_2), 0) as decimal) as Water,
  cast(round(sum(avgQuantity_1), 0) as decimal) as Salt,
  cast(round(sum(avgQuantity_0), 0) as decimal) as Units,
  '3/8/13/18' as RouteGroup
  from
  (select start, route,
  avg(avgStops) as [avgStops],
  avg(avgDistance) as [avgDistance],
  avg(avgHoursRoute) as [avgHoursRoute],
  avg(avgQuantity_0) as [avgQuantity_0],
  avg(avgQuantity_1) as [avgQuantity_1],
  avg(avgQuantity_2) as [avgQuantity_2]
  
  from [{$scenario}] s
  
  where calDate > getdate() and cast(daycode as varchar) in ('3', '8', '13', '18')
  group by start, route, daycode) r
  
  group by start, route

-- get the summary info by loc-rte for 4/9/14/19
union
  select 
  start + '-' + route as 'Loc-Rte',
  cast(sum(avgStops) as int) as Stops,
  cast(round(sum(avgDistance), 2) as numeric(38,2)) as Mileage,
  cast(round(sum(avgHoursRoute), 2) as numeric(38,2)) as 'Route Hours',
  cast(round(sum(avgQuantity_2), 0) as decimal) as Water,
  cast(round(sum(avgQuantity_1), 0) as decimal) as Salt,
  cast(round(sum(avgQuantity_0), 0) as decimal) as Units,
  '4/9/14/19' as RouteGroup
  from
  (select start, route,
  avg(avgStops) as [avgStops],
  avg(avgDistance) as [avgDistance],
  avg(avgHoursRoute) as [avgHoursRoute],
  avg(avgQuantity_0) as [avgQuantity_0],
  avg(avgQuantity_1) as [avgQuantity_1],
  avg(avgQuantity_2) as [avgQuantity_2]
  
  from [{$scenario}] s
  
  where calDate > getdate() and cast(daycode as varchar) in ('4', '9', '14', '19')
  group by start, route, daycode) r
  
  group by start, route

-- get the summary info by loc-rte for 5/10/15/20
union
  select 
  start + '-' + route as 'Loc-Rte',
  cast(sum(avgStops) as int) as Stops,
  cast(round(sum(avgDistance), 2) as numeric(38,2)) as Mileage,
  cast(round(sum(avgHoursRoute), 2) as numeric(38,2)) as 'Route Hours',
  cast(round(sum(avgQuantity_2), 0) as decimal) as Water,
  cast(round(sum(avgQuantity_1), 0) as decimal) as Salt,
  cast(round(sum(avgQuantity_0), 0) as decimal) as Units,
  '5/10/15/20' as RouteGroup
  from
  (select start, route,
  avg(avgStops) as [avgStops],
  avg(avgDistance) as [avgDistance],
  avg(avgHoursRoute) as [avgHoursRoute],
  avg(avgQuantity_0) as [avgQuantity_0],
  avg(avgQuantity_1) as [avgQuantity_1],
  avg(avgQuantity_2) as [avgQuantity_2]
  
  from [{$scenario}] s
  
  where calDate > getdate() and cast(daycode as varchar) in ('5', '10', '15', '20')
  group by start, route, daycode) r
  
  group by start, route

order by [Loc-Rte], RouteGroup";

}

?>

    </tbody>
</table>

</body>
</html>