<?php
require_once('../sds.php');
sdsRequireGroup("LOUNGE-CHAIRS");

sdsIncludeHeader("Lounge Management");

$signup_enabled = sdsGetIntOption('enable-lounge-signups');
$signup_value = sdsGetIntOption('lounge-signup-value');
?>
<h2>Options</h2>
<form action="loungeoptions.php" method="post">
<?php echo sdsForm() ?>
  <table>
    <tr>
      <td>Lounge Signups:</td>
      <td>
        <select name="signup">
          <option<?php echo $signup_enabled?' selected="selected"':'' ?>>Enabled</option>
          <option<?php echo $signup_enabled?'':' selected="selected"' ?>>Disabled</option>
        </select>
      </td>
    </tr>
    <tr>
      <td>Signup Value:</td>
      <td><input type="text" name="value" value="<?php echo $signup_value ?>" size="5" /></td>
    </tr>
  </table>
  <input type="submit" value="Update" />
</form>

<h2>Administrative Tasks</h2>
WARNING: There are no undo buttons.
<table>
  <tr>
    <td>
      <form action="../loungeexpense/archiveall.php" method="post">
        <?php echo sdsForm() ?>
        <input type="submit" value="Clear Lounge Expenses" />
      </form>
    </td>
    <td>
      <form action="clearmembership.php" method="post">
        <?php echo sdsForm() ?>
        <input type="submit" value="Clear All Lounge Membership" />
      </form>
    </td>
  </tr>
</table>

<h2 id="current">Current Lounges</h2>
<form action="loungeupdate.php" method="post">
<?php echo sdsForm() ?>
  <table>
    <tr>
      <th>Disable</th>
      <th>ID</th>
      <th>Name</th>
      <th>1<sup>st</sup> Contact</th>
      <th>2<sup>nd</sup> Contact</th>
      <th>Funds</th>
    </tr>
<?php
$query = "SELECT lounge,description,url,contact,contact2,allocation FROM active_lounges ORDER BY lounge";
$result = sdsQuery($query);
if(!$result)
  contactTech("Can't find lounges");

while($record = pg_fetch_array($result)) {
  unset($matches);
  if(!preg_match('/^lounge-(.*)$/',$record['lounge'],$matches)) {
    echo "    </tr>\n  </table>\n</form>\n";
    contactTech("Malformed lounge ID");
  }
  $lounge = htmlspecialchars($matches[1]);
?>
    <tr>
      <td><input type="checkbox" name="disable[<?php echo $lounge ?>]" /></td>
      <td><?php echo $lounge ?></td>
      <td><input type="text" name="name[<?php echo $lounge ?>]" value="<?php echo htmlspecialchars($record['description']) ?>" size="30" /></td>
      <td><input type="text" name="contact[<?php echo $lounge ?>]" value="<?php echo htmlspecialchars($record['contact']) ?>" size="10" /></td>
      <td><input type="text" name="contact2[<?php echo $lounge ?>]" value="<?php echo htmlspecialchars($record['contact2']) ?>" size="10" /></td>
      <td><input type="text" name="allocation[<?php echo $lounge ?>]" value="<?php echo $record['allocation'] ?>" size="7" /></td>
    </tr>
    <tr>
      <td></td>
      <td></td>
      <td colspan="4"><input type="text" name="url[<?php echo $lounge ?>]" value="<?php echo htmlspecialchars($record['url']) ?>" size="65" /></td>
    </tr>
<?php
}
pg_free_result($result);
?>
  </table>
  <input type="submit" value="Update Lounge Information" />
</form>

<h2>Add a new Lounge</h2>
<form action="newlounge.php" method="post">
<?php echo sdsForm() ?>
  <table>
    <tr>
      <td>Lounge ID (required):</td>
      <td><input type="text" name="id" /></td>
    </tr>
    <tr>
      <td>Lounge Name (required):</td>
      <td><input type="text" name="name" size="30" /></td>
    </tr>
    <tr>
      <td>First Contact (required):</td>
      <td><input type="text" name="contact" size="12" /></td>
    </tr>
    <tr>
      <td>Second Contact:</td>
      <td><input type="text" name="contact2" size="12" /></td>
    </tr>
    <tr>
      <td>URL:</td>
      <td><input type="text" name="url" size="60" /></td>
    </tr>
  </table>
  <input type="submit" value="Create Lounge" />
</form>

<h2>Reenable an Old Lounge</h2>
<form action="reactivate.php" method="post">
<?php echo sdsForm() ?>
  <select name="lounge">
<?php
# some very old lounges have names not following the standard pattern
$query = "SELECT lounge,description FROM lounges WHERE NOT active AND lounge LIKE 'lounge-%' ORDER BY lounge";
$result = sdsQuery($query);
if(!$result)
  contactTech("Could not find old lounges");

while($record = pg_fetch_array($result)) {
  unset($matches);
  if(!preg_match('/^lounge-(.*)$/',$record['lounge'],$matches)) {
    # cannot happen
    echo "  </select>\n</form>\n";
    contactTech("Malformed lounge ID");
  }
  $lounge = htmlspecialchars($matches[1]);
  echo '    <option value="',$lounge,'">',$lounge,': ',
    htmlspecialchars($record['description']),"</option>\n";
}
?>
  </select>
  <input type="submit" value="Reenable" />
</form>

<?php
sdsIncludeFooter();
