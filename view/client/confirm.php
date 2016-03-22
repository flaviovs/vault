<?php
use Vault\Esc;
?>
<p>Here's a summary of your request:</p>
<table id="confirm-input">
  <tr>
	<th>Your e-mail:</th>
	<td><?php echo Esc::html( $req_email ) ?></td>
  </tr>
  <tr>
	<th>User's e-mail:</th>
	<td><?php echo Esc::html( $user_email ) ?></td>
  </tr>
  <tr>
	<th colspan="2">Additional instructions</th>
  </tr>
  <tr>
	<td colspan="2"><pre class="instructions"><?php echo Esc::instructions( $instructions ) ?></pre></td>
  </tr>
</table>

<form method="POST" action="<?php echo $action ?>">
  <input type="hidden" name="form_token" value="<?php echo Esc::attr( $form_token ) ?>">
  <input type="hidden" name="user_email" value="<?php echo Esc::attr( $user_email ) ?>">
  <input type="hidden" name="instructions" value="<?php echo Esc::attr( $instructions ) ?>">

  <input type="submit" value="Confirm">
</form>
