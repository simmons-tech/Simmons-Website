<?php
if(realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__))
  exit;
if ($sdsHeaderIncluded) {
?>
</td>
</tr>
</table>

</div>
<?php
}
?>


<div class="footer">
<hr />
<a href="<?php echo sdsLink(SDS_BASE_URL . '/users/about.php') ?>">Simmons DB</a>
: dramage, 2002; with bonawitz, dheera, psaindon
<!-- v1.0 written by dramage, 2002 --><br />
<span style="font-size: smaller">
PHP <?php echo phpversion() ?>;
<?php
$query = "SELECT split_part(version(),' on ',1)";
$result = sdsQuery($query);
if(!$result or pg_num_rows($result) != 1)
  contactTech("Can't get postgres version number");
list($version) = pg_fetch_array($result);
echo $version;
?>;
dbname: <?php global $SDB; echo pg_dbname($SDB) ?>
</span>
</div>

<?php if ($sdsHeaderIncluded) { ?>
</body>
</html>

<?php } ?>
