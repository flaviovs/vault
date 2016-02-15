<form id="input" action="<?php echo $action ?>" method="POST">
  <input type="hidden" name="reqid" value="<?php echo $reqid ?>">
  <input type="hidden" name="m" value="<?php echo htmlspecialchars($mac) ?>">

  <?php if ( $instructions ): ?>
  <div id="instructions">
	<?php echo $instructions ?>
  </div>
  <?php endif ?>

  <textarea name="secret" id="secret">
  </textarea>

  <input type="submit">
</form>
