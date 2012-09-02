<?php 
require_once("../sds.php");
sdsRequireGroup("RAC");

sdsIncludeHeader("Simmons RAC Batch Update");
?>

<form enctype="multipart/form-data" action="batchupdate-process.php" method="post">
  <?php echo sdsForm() ?>
  <input type="hidden" name="MAX_FILE_SIZE" value="2000000" />
  Send this file: <input name="batchfile" type="file" /><br />
  <label>Field separator:
    <select name="separator">
      <option value="," selected="selected">, (comma)</option>
      <option value="tab">Tab</option>
      <option value=":">: (colon)</option>
      <option value=";">; (semicolon)</option>
      <option value="other">Other (specify)</option>
    </select>
  </label>
  <input type="text" name="othersep" /><br />

  <input type="submit" value="Send File" />
</form>

<?php
sdsIncludeFooter();
