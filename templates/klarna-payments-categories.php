<?php

if ( is_array( WC()->session->get( 'klarna_payments_categories' ) ) ) {
	foreach ( WC()->session->get( 'klarna_payments_categories' ) as $payment_category ) {
		$payment_category_id   = $payment_category->identifier;
		$payment_category_name = $payment_category->name;
		?>
		<li class="wc_payment_method payment_method_klarna_payments_<?php echo $payment_category_id; ?>">
			<input id="payment_method_klarna_payments_<?php echo $payment_category_id; ?>"
			       type="radio" class="input-radio"
			       name="payment_method" value="klarna_payments"
			       data-order_button_text="Place order"/>

			<label for="payment_method_klarna_payments_<?php echo $payment_category_id; ?>">
				<?php echo $payment_category_name; ?> <img
					src="https://cdn.klarna.com/1.0/shared/image/generic/logo/en_us/basic/logo_black.png?width=75"
					alt="Klarna">
			</label>
			<div
				class="payment_box payment_method_klarna_payments_<?php echo $payment_category_id; ?>"
				style="display:none;">
				<div id="klarna_payments_<?php echo $payment_category_id; ?>_container" class="klarna_payments_container" data-payment_method_category="<?php echo $payment_category_id; ?>"></div>
		</li>
		<?php
	}
}
