<?php
/**
 * Footer (seller details + legal links), mobile order bar, toast and the cart drawer
 * with the 3-step checkout: cart -> contacts & receiving -> payment (contract C8).
 */

defined( 'ABSPATH' ) || exit;

$knu_wa_digits = preg_replace( '/\D+/', '', (string) knu_setting( 'whatsapp' ) );
$knu_tg        = ltrim( (string) knu_setting( 'telegram' ), '@' );
$knu_wa_label  = $knu_wa_digits;
if ( 12 === strlen( $knu_wa_digits ) ) {
	$knu_wa_label = '+' . substr( $knu_wa_digits, 0, 3 ) . ' ' . substr( $knu_wa_digits, 3, 3 ) . ' ' . substr( $knu_wa_digits, 6, 3 ) . ' ' . substr( $knu_wa_digits, 9, 3 );
} elseif ( $knu_wa_digits ) {
	$knu_wa_label = '+' . $knu_wa_digits;
}

$knu_home = is_front_page() ? '' : home_url( '/' );

$knu_legal = array();
foreach ( array( 'offer', 'privacy', 'returns', 'delivery-payment' ) as $knu_doc ) {
	$knu_legal[ $knu_doc ] = function_exists( 'knu_legal_url' ) ? knu_legal_url( $knu_doc ) : home_url( '/' . $knu_doc . '/' );
}

$knu_seller = array();
foreach ( array( 'seller_name', 'seller_address', 'seller_phone', 'seller_email', 'seller_inn' ) as $knu_skey ) {
	$knu_seller[ $knu_skey ] = trim( (string) knu_setting( $knu_skey ) );
}

$knu_consent = sprintf(
	esc_html( knu_t( 'js.form.consent' ) ),
	'<a href="' . esc_url( $knu_legal['offer'] ) . '" target="_blank" rel="noopener">' . esc_html( knu_t( 'js.form.consent_offer' ) ) . '</a>',
	'<a href="' . esc_url( $knu_legal['privacy'] ) . '" target="_blank" rel="noopener">' . esc_html( knu_t( 'js.form.consent_privacy' ) ) . '</a>'
);

$knu_close_icon = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><path d="M4 4l12 12M16 4L4 16" stroke-linecap="round"/></svg>';
$knu_back_icon  = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><path d="M12 4l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$knu_fees       = array(
	'pickup'  => 0,
	'bishkek' => 150,
	'regions' => 300,
);
?>
<footer class="site-footer" data-inert-root>
	<div class="container site-footer__grid">
		<div class="site-footer__brand">
			<img class="site-footer__logo" src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-white.png' ) ); ?>" width="56" height="56" alt="<?php echo esc_attr( knu_t( 'brand.logo_alt' ) ); ?>" loading="lazy" onerror="this.classList.add('is-missing')">
			<p class="site-footer__about"><?php knu_e( 'footer.about' ); ?></p>
		</div>

		<div class="site-footer__col">
			<h2 class="site-footer__title"><?php knu_e( 'footer.contacts' ); ?></h2>
			<ul class="site-footer__list" role="list">
				<li><?php knu_e( 'footer.address' ); ?></li>
				<?php if ( $knu_wa_digits ) : ?>
					<li><a href="<?php echo esc_url( 'https://wa.me/' . $knu_wa_digits ); ?>" rel="noopener" target="_blank">WhatsApp <?php echo esc_html( $knu_wa_label ); ?></a></li>
				<?php endif; ?>
				<?php if ( $knu_tg ) : ?>
					<li><a href="<?php echo esc_url( 'https://t.me/' . $knu_tg ); ?>" rel="noopener" target="_blank">Telegram @<?php echo esc_html( $knu_tg ); ?></a></li>
				<?php endif; ?>
			</ul>
			<h2 class="site-footer__title site-footer__title--gap"><?php knu_e( 'footer.social' ); ?></h2>
			<ul class="site-footer__list" role="list">
				<li><a href="https://www.instagram.com/knu_j.balasagyn/" rel="noopener" target="_blank">Instagram @knu_j.balasagyn</a></li>
				<li><a href="https://t.me/KNUJusupBalasagyn" rel="noopener" target="_blank">Telegram KNUJusupBalasagyn</a></li>
			</ul>
		</div>

		<?php if ( array_filter( $knu_seller ) ) : ?>
			<div class="site-footer__col">
				<h2 class="site-footer__title"><?php knu_e( 'footer.seller' ); ?></h2>
				<ul class="site-footer__list site-footer__list--plain" role="list">
					<?php if ( $knu_seller['seller_name'] ) : ?>
						<li class="site-footer__strong"><?php echo esc_html( $knu_seller['seller_name'] ); ?></li>
					<?php endif; ?>
					<?php if ( $knu_seller['seller_inn'] ) : ?>
						<li><?php knu_e( 'footer.inn' ); ?> <?php echo esc_html( $knu_seller['seller_inn'] ); ?></li>
					<?php endif; ?>
					<?php if ( $knu_seller['seller_address'] ) : ?>
						<li><?php echo esc_html( $knu_seller['seller_address'] ); ?></li>
					<?php endif; ?>
					<?php if ( $knu_seller['seller_phone'] ) : ?>
						<li><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $knu_seller['seller_phone'] ) ); ?>"><?php echo esc_html( $knu_seller['seller_phone'] ); ?></a></li>
					<?php endif; ?>
					<?php if ( $knu_seller['seller_email'] && is_email( $knu_seller['seller_email'] ) ) : ?>
						<li><a href="<?php echo esc_url( 'mailto:' . $knu_seller['seller_email'] ); ?>"><?php echo esc_html( $knu_seller['seller_email'] ); ?></a></li>
					<?php elseif ( $knu_seller['seller_email'] ) : ?>
						<li><?php echo esc_html( $knu_seller['seller_email'] ); ?></li>
					<?php endif; ?>
				</ul>
			</div>
		<?php endif; ?>

		<nav class="site-footer__col" aria-labelledby="footer-docs-title">
			<h2 class="site-footer__title" id="footer-docs-title"><?php knu_e( 'footer.docs' ); ?></h2>
			<ul class="site-footer__list" role="list">
				<?php foreach ( $knu_legal as $knu_doc => $knu_url ) : ?>
					<li><a href="<?php echo esc_url( $knu_url ); ?>"><?php knu_e( 'legal.' . $knu_doc ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	</div>
	<div class="container site-footer__bottom">
		<p><?php knu_e( 'footer.rights' ); ?></p>
		<p><?php knu_e( 'footer.prices' ); ?></p>
		<p class="site-footer__study"><?php knu_e( 'footer.study' ); ?></p>
		<?php if ( is_user_logged_in() ) : ?>
			<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=knu_order' ) ); ?>"><?php knu_e( 'footer.admin' ); ?></a></p>
		<?php endif; ?>
	</div>
</footer>

<div class="mbar" data-mbar data-inert-root>
	<button class="btn btn--primary btn--block mbar__btn" type="button" data-mbar-btn data-home="<?php echo esc_url( home_url( '/' ) ); ?>">
		<span data-mbar-label><?php knu_e( 'mbar.order' ); ?></span>
	</button>
</div>

<div class="toast" data-toast role="status" aria-live="polite" hidden>
	<span class="toast__text" data-toast-text></span>
	<button class="toast__btn" type="button" data-cart-open><?php knu_e( 'js.toast.open' ); ?></button>
</div>

<div class="drawer" id="cart-drawer" data-drawer hidden>
	<div class="drawer__overlay" data-drawer-close></div>
	<div class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="drawer-title" tabindex="-1" data-drawer-panel>
		<div class="drawer__head">
			<button class="icon-btn drawer__back" type="button" data-back aria-label="<?php echo esc_attr( knu_t( 'js.checkout.back' ) ); ?>" hidden><?php echo $knu_back_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></button>
			<h2 class="drawer__title" id="drawer-title" data-drawer-title><?php knu_e( 'js.cart.title' ); ?></h2>
			<button class="icon-btn" type="button" data-drawer-close aria-label="<?php echo esc_attr( knu_t( 'js.cart.close' ) ); ?>"><?php echo $knu_close_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></button>
		</div>

		<ol class="stepper" data-stepper aria-label="<?php echo esc_attr( knu_t( 'js.step.label' ) ); ?>">
			<?php foreach ( array( 'cart', 'contact', 'payment' ) as $knu_si => $knu_step ) : ?>
				<li class="stepper__item" data-step="<?php echo esc_attr( $knu_step ); ?>">
					<span class="stepper__num" aria-hidden="true"><?php echo (int) $knu_si + 1; ?></span>
					<span class="stepper__label"><?php knu_e( 'js.step.' . $knu_step ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

		<!-- Step 1: cart -->
		<div class="drawer__view" data-view="cart">
			<div class="drawer__body">
				<ul class="cart-list" data-cart-list role="list"></ul>
				<div class="cart-empty" data-cart-empty hidden>
					<p class="cart-empty__title"><?php knu_e( 'js.cart.empty' ); ?></p>
					<p class="cart-empty__text"><?php knu_e( 'js.cart.empty_text' ); ?></p>
					<a class="btn btn--outline" href="<?php echo esc_url( $knu_home ); ?>#collection" data-drawer-link><?php knu_e( 'js.cart.browse' ); ?></a>
				</div>
			</div>
			<div class="drawer__foot" data-cart-foot>
				<dl class="totals">
					<div class="totals__row"><dt><?php knu_e( 'js.cart.subtotal' ); ?></dt><dd data-cart-subtotal></dd></div>
					<div class="totals__row totals__row--muted"><dt><?php knu_e( 'js.cart.delivery' ); ?></dt><dd><?php knu_e( 'js.cart.delivery_later' ); ?></dd></div>
				</dl>
				<button class="btn btn--primary btn--block" type="button" data-to-checkout><?php knu_e( 'js.cart.checkout' ); ?></button>
				<p class="guest-note"><?php knu_e( 'js.guest' ); ?></p>
			</div>
		</div>

		<!-- Steps 2-3: contacts & receiving, payment -->
		<form class="drawer__view checkout" data-view="checkout" data-checkout novalidate hidden>
			<div class="drawer__body">
				<div class="form-alert" data-form-alert role="alert" hidden>
					<p data-form-alert-text></p>
					<a class="form-alert__link" href="#" data-wa-fallback target="_blank" rel="noopener" hidden><?php knu_e( 'js.err.whatsapp' ); ?></a>
				</div>

				<div class="checkout__panel" data-panel="contact">
					<p class="guest-note guest-note--top"><?php knu_e( 'js.guest' ); ?></p>

					<div class="field">
						<label class="field__label" for="co-name"><?php knu_e( 'js.form.name' ); ?></label>
						<input class="field__input" id="co-name" name="name" type="text" autocomplete="name" maxlength="60" required aria-describedby="co-name-err">
						<p class="field__error" id="co-name-err" data-error="name"></p>
					</div>

					<div class="field">
						<label class="field__label" for="co-phone"><?php knu_e( 'js.form.phone' ); ?></label>
						<input class="field__input" id="co-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+996 700 123 456" required aria-describedby="co-phone-err" data-phone>
						<p class="field__error" id="co-phone-err" data-error="phone"></p>
					</div>

					<fieldset class="field field--group" aria-describedby="co-delivery-err">
						<legend class="field__label"><?php knu_e( 'js.form.receive' ); ?></legend>
						<?php foreach ( $knu_fees as $knu_key => $knu_fee ) : ?>
							<label class="choice">
								<input class="choice__input" type="radio" name="delivery" value="<?php echo esc_attr( $knu_key ); ?>"<?php checked( 'pickup', $knu_key ); ?>>
								<span class="choice__box" aria-hidden="true"></span>
								<span class="choice__text">
									<span class="choice__title"><?php knu_e( 'js.delivery.' . $knu_key ); ?></span>
									<span class="choice__note"><?php knu_e( 'js.delivery.' . $knu_key . '_note' ); ?></span>
								</span>
								<span class="choice__price" data-fee="<?php echo esc_attr( $knu_key ); ?>"><?php echo esc_html( $knu_fee ? knu_money( $knu_fee ) : knu_t( 'js.cart.free' ) ); ?></span>
							</label>
						<?php endforeach; ?>
						<p class="field__error" id="co-delivery-err" data-error="delivery"></p>
					</fieldset>

					<div class="field" data-address-field hidden>
						<label class="field__label" for="co-address"><?php knu_e( 'js.form.address' ); ?></label>
						<input class="field__input" id="co-address" name="address" type="text" autocomplete="street-address" maxlength="200" placeholder="<?php echo esc_attr( knu_t( 'js.form.address_ph' ) ); ?>" aria-describedby="co-address-err">
						<p class="field__error" id="co-address-err" data-error="address"></p>
					</div>

					<div class="field">
						<label class="field__label" for="co-comment"><?php knu_e( 'js.form.comment' ); ?> <span class="field__opt">(<?php knu_e( 'js.form.optional' ); ?>)</span></label>
						<textarea class="field__input field__input--area" id="co-comment" name="comment" rows="2" maxlength="500" placeholder="<?php echo esc_attr( knu_t( 'js.form.comment_ph' ) ); ?>" aria-describedby="co-comment-err"></textarea>
						<p class="field__error" id="co-comment-err" data-error="comment"></p>
					</div>

					<div class="field">
						<label class="check" for="co-consent">
							<input class="check__input" id="co-consent" name="consent" type="checkbox" value="1" required aria-describedby="co-consent-err">
							<span class="check__box" aria-hidden="true"></span>
							<span class="check__text"><?php echo $knu_consent; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></span>
						</label>
						<p class="field__error" id="co-consent-err" data-error="consent"></p>
					</div>
				</div>

				<div class="checkout__panel" data-panel="payment" hidden>
					<fieldset class="field field--group" aria-describedby="co-payment-err">
						<legend class="field__label"><?php knu_e( 'js.payment.title' ); ?></legend>
						<?php foreach ( array( 'qr', 'cash' ) as $knu_pi => $knu_pay ) : ?>
							<label class="choice choice--pay">
								<input class="choice__input" type="radio" name="payment" value="<?php echo esc_attr( $knu_pay ); ?>"<?php checked( 0, $knu_pi ); ?>>
								<span class="choice__box" aria-hidden="true"></span>
								<span class="choice__text">
									<span class="choice__title"><?php knu_e( 'js.payment.' . $knu_pay ); ?></span>
									<span class="choice__note"><?php knu_e( 'js.payment.' . $knu_pay . '_note' ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
						<p class="field__error" id="co-payment-err" data-error="payment"></p>
					</fieldset>
					<dl class="review" data-review></dl>
				</div>

				<div class="hp" aria-hidden="true">
					<label for="co-website">Website</label>
					<input id="co-website" name="website" type="text" tabindex="-1" autocomplete="off">
				</div>
			</div>
			<div class="drawer__foot">
				<dl class="totals">
					<div class="totals__row"><dt><?php knu_e( 'js.cart.subtotal' ); ?></dt><dd data-co-subtotal></dd></div>
					<div class="totals__row"><dt><?php knu_e( 'js.cart.delivery' ); ?></dt><dd data-co-delivery></dd></div>
					<div class="totals__row totals__row--total"><dt><?php knu_e( 'js.cart.total' ); ?></dt><dd data-co-total></dd></div>
				</dl>
				<button class="btn btn--primary btn--block" type="button" data-next><?php knu_e( 'js.form.next' ); ?></button>
				<button class="btn btn--primary btn--block" type="submit" data-submit hidden><?php knu_e( 'js.form.submit' ); ?></button>
				<p class="checkout__note" data-checkout-note hidden><?php knu_e( 'js.form.note' ); ?></p>
			</div>
		</form>

		<!-- QR payment -->
		<div class="drawer__view pay" data-view="pay" hidden>
			<div class="drawer__body pay__body">
				<p class="pay__demo"><?php knu_e( 'js.pay.demo' ); ?></p>
				<div class="pay__qr" data-qr role="img" aria-label="<?php echo esc_attr( knu_t( 'js.pay.qr_alt' ) ); ?>">
					<span class="pay__qr-status" data-qr-status><?php knu_e( 'js.pay.loading' ); ?></span>
				</div>
				<dl class="pay__facts">
					<div><dt><?php knu_e( 'js.pay.amount' ); ?></dt><dd class="pay__amount" data-pay-amount></dd></div>
					<div><dt><?php knu_e( 'js.success.number' ); ?></dt><dd data-pay-number></dd></div>
					<div data-pay-recipient-row><dt><?php knu_e( 'js.pay.recipient' ); ?></dt><dd data-pay-recipient></dd></div>
				</dl>
				<p class="pay__scan"><?php knu_e( 'js.pay.scan' ); ?></p>
				<ul class="pay__banks" role="list">
					<li class="chip chip--strong">ELQR</li>
					<li class="chip">MBank</li>
					<li class="chip">O!Деньги</li>
					<li class="chip">Bakai</li>
					<li class="chip">Optima</li>
				</ul>
				<div class="form-alert" data-pay-alert role="alert" hidden>
					<p data-pay-alert-text></p>
					<a class="form-alert__link" href="#" data-pay-wa target="_blank" rel="noopener"><?php knu_e( 'js.err.whatsapp' ); ?></a>
				</div>
			</div>
			<div class="drawer__foot">
				<button class="btn btn--primary btn--block" type="button" data-paid><?php knu_e( 'js.pay.done' ); ?></button>
			</div>
		</div>

		<!-- Success -->
		<div class="drawer__view success" data-view="success" hidden>
			<div class="drawer__body success__body">
				<span class="success__mark" aria-hidden="true">
					<svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M7 14.5l4.5 4.5L21 9.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</span>
				<p class="success__title" tabindex="-1" data-success-title><?php knu_e( 'js.success.title' ); ?></p>
				<dl class="success__facts">
					<div><dt><?php knu_e( 'js.success.number' ); ?></dt><dd data-success-number></dd></div>
					<div><dt><?php knu_e( 'js.success.total' ); ?></dt><dd data-success-total></dd></div>
				</dl>
				<p class="success__text" data-success-text><?php knu_e( 'js.success.text' ); ?></p>
				<div class="success__actions">
					<a class="btn btn--primary btn--block" href="#" data-success-wa target="_blank" rel="noopener"><?php knu_e( 'js.success.whatsapp' ); ?></a>
					<a class="btn btn--outline btn--block" href="<?php echo esc_url( 'https://t.me/' . $knu_tg ); ?>" data-success-tg target="_blank" rel="noopener"><?php knu_e( 'js.success.telegram' ); ?></a>
					<button class="link-btn" type="button" data-drawer-close><?php knu_e( 'js.success.done' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
