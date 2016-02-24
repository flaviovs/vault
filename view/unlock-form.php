<p>Use this form to unlock the information you requested.<p>

<form id="input" action="<?php echo $action ?>" method="POST">
  <input type="hidden" name="reqid" value="<?php echo $reqid ?>">
  <input type="hidden" name="m" value="<?php echo htmlspecialchars($mac) ?>">

  <div id="element-key" class="form-group">
	<label for="key">Unlock key</label>
	<input type="text" name="key" id="key" required>
  </div>

  <input type="submit" value="Unlock">
</form>
