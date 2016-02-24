<aside id="help">

  <ul>
	<li><b>What is this?</b> This is a tool to securely exchange information between you and our Happiness Engineers. All information that you input here will be kept secure, and we will take care that it reaches only the intended persons.</li>
	<li><b>Not sure why you are seeing this?</b> An Engineer must have previously communicated to you that he or she is in need of additional information. If you do not recall being contacted by any of our Engineers about this, please disregard this link, and do not enter any information below.</li>
	<li><b>Double-check the information you provide</b> You will be able to submit this form only once, so check that the information you will submit is valid. If you think that you made a mistake and need to correct information that you sent to us, ask an Engineer for another secure link.  <strong>Never</strong> submit sensitive data using e-mail, chat, or any other insecure media!</li>
  </ul>

</aside>

<form id="input" action="<?php echo $action ?>" method="POST">
  <input type="hidden" name="reqid" value="<?php echo $reqid ?>">
  <input type="hidden" name="m" value="<?php echo htmlspecialchars($mac) ?>">

  <?php if ( $instructions ): ?>
  <div id="instructions"><?php echo $instructions ?></div>
  <?php endif ?>

  <div class="form-group">
	<label for="secret">Input the requested information:</label>
	<textarea name="secret" id="secret" rows="5" required></textarea>
  </div>

  <div class="form-group">
	<input type="submit" value="Submit »">
  </div>

</form>
