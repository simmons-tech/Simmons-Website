<?php
require_once('../sds.php');
sdsRequireGroup('HOUSE-COMM-LEADERSHIP');

$query = "SELECT agendaid FROM gov_agendas WHERE status = 'open'";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search agendas");
list($aid) = pg_fetch_array($result);
pg_free_result($result);

if(!isset($aid)) {
  sdsIncludeHeader("GovTracker","Simmons Government Online");
  echo "<h2>Agenda Ordering</h2>\n";
  echo "<p class='error'>There is no currenty open agenda.</p>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}

if(isset($_REQUEST['order'])) {
  $neworder = explode(':',trim(maybeStripslashes($_REQUEST['order']),':'));
  $query = "SELECT propid FROM gov_proposals WHERE agendaid='$aid' ORDER BY agendaorder, datesub";
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not search proposals");

  // Add elements to end of list
  $propids = array();
  while($data = pg_fetch_object($result)) {
    $propids[$data->propid] = true;
    $neworder[] = $data->propid;
  }
  pg_free_result($result);

  // remove any duplicates and props not on the agenda
  $updateorder = array();
  foreach($neworder as $propid) {
    if($propids[$propid]) {
      $propids[$propid] = false;
      $updateorder[] = $propid;
    }
  }

  $agendaorder = 0;
  $query = '';
  foreach($updateorder as $propid) {
    $query .= "UPDATE gov_proposals SET agendaorder='$agendaorder' WHERE propid='$propid';";
    $agendaorder++;
  }
  $result = sdsQuery($query);
  if(!$result)
    contactTech("Could not reorder agenda");
  pg_free_result($result);

  header("Location: " . SDS_BASE_URL .
	 sdsLink("govtracker/viewagenda.php","aid=$aid",true));
  exit;
}

sdsIncludeHeader("GovTracker","Simmons Government Online",
		 "<script type='text/javascript' src='agendaorder.js'></script>",
		 "onload='dragdropSetup()'");

echo "<h2>Agenda Ordering</h2>\n";

$query = "SELECT propid,title FROM gov_proposals WHERE agendaid='$aid' ORDER BY agendaorder";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not search proposals");
if(pg_num_rows($result) == 0) {
  pg_free_result($result);
  echo "<p style='font-style:italic'>There are no proposals on the current agenda.</p>\n";
  include('gt-footer.php');
  sdsIncludeFooter();
  exit;
}

?>

<h2 id='javascriptWarning' class='error'>This page requires JavaScript.</h2>

<p>Drag agenda items into the desired order.</p>

<div id='proplist' class="dragregion">
  <div class='posIndicator'></div>
<?php
while($data = pg_fetch_object($result)) {
  echo "  <div id='propdiv:",$data->propid,"' class='dragitem'>#",
    $data->propid,": ",htmlspecialchars($data->title),"</div>\n";
  echo "  <div class='posIndicator'></div>\n";
}
?>
</div>

<form action="agendaorder.php" method="post">
  <input type="hidden" id="orderReturn" name="order" value="" />
  <input type="submit" value="Save Order" />
</form>

<?php
include('gt-footer.php');
sdsIncludeFooter();
