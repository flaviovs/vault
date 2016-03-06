<?php
use Vault\Esc;
?>
<p>Use this form to unlock the information you requested.<p>

<form id="input" action="<?php echo $action ?>" method="POST">
  <input type="hidden" name="form_token" value="<?php echo Esc::attr( $form_token ) ?>">
  <input type="hidden" name="reqid" value="<?php echo Esc::attr( $reqid ) ?>">
  <input type="hidden" name="m" value="<?php echo Esc::attr( $mac ) ?>">

  <div id="element-key" class="form-group">
	<label for="key">Unlock key</label>
	<input type="text" name="key" id="key" required autocomplete="off">
	<div class="help">It is in the same notification where you received this URL.</div>
  </div>

  <input type="submit" value="Unlock">
</form>
