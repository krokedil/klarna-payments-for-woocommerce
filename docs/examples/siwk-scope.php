<?php
/**
 * Request additional customer data with Sign in with Klarna.
 *
 * The plugin always requests the scopes it needs to create a customer account
 * (openid, offline_access, customer:login, profile:name, profile:email,
 * profile:phone and profile:billing_address). This filter only adds to them.
 *
 * Every scope you add must also be enabled for your client in the Klarna
 * Merchant Portal. Otherwise Klarna stops the sign-in with "invalid_scope".
 *
 * Available scopes: https://docs.klarna.com/conversion-boosters/sign-in-with-klarna/integrate-sign-in-with-klarna/web-sdk-integration/#scopes-and-claims
 */
add_filter(
	'siwk_scope',
	function ( $scope ) {
		// The scopes to add.
		$additional_scopes = array( 'profile:date_of_birth' );

		$scopes = preg_split( '/\s+/', (string) $scope, -1, PREG_SPLIT_NO_EMPTY );

		return implode( ' ', array_unique( array_merge( $scopes, $additional_scopes ) ) );
	}
);
