<form id="input" action="<?php echo $action ?>" method="POST">
  <input type="hidden" name="reqid" value="<?php echo $reqid ?>">
  <input type="hidden" name="m" value="<?php echo htmlspecialchars($mac) ?>">

  <label for="key">Unlock key</label>
  <input type="text" name="key" id="key" required>

  <input type="submit">
</form>
