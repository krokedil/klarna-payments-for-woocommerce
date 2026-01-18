<?php
namespace Krokedil\Klarna;

/**
 * Features class.
 * Contains constants for Klarna feature identifiers, and grouped feature arrays.
 */
class Features {
	public const PAYMENTS                    = 'payments:payments';
	public const PAYMENTS_RECURRING          = 'payments:recurring';
	public const OSM_PRODUCT_PAGE            = 'on-site-messaging:product-page';
	public const OSM_CART_PAGE               = 'on-site-messaging:cart-page';
	public const OSM_PROMOTIONAL_BANNER      = 'on-site-messaging:promotional-banner';
	public const KEC_ONE_STEP                = 'klarna-express-checkout:1-step';
	public const KEC_TWO_STEP                = 'klarna-express-checkout:2-step';
	public const SIWK_ACCOUNT_CREATION_PAGE  = 'sign-in-with-klarna:account-creation-page';
	public const SIWK_AUTHENTICATION_PAGE    = 'sign-in-with-klarna:authentication-page';
	public const SIWK_CART_PAGE              = 'sign-in-with-klarna:cart-page';
	public const SUPPLEMENTARY_PURCHASE_DATA = 'supplementary-purchase-data';

	/**
	 * List of all OSM features.
	 *
	 * @var array
	 */
	public const OSM = [
		self::OSM_PRODUCT_PAGE,
		self::OSM_CART_PAGE,
		self::OSM_PROMOTIONAL_BANNER,
	];

	/**
	 * List of all KEC features.
	 *
	 * @var array
	 */
	public const KEC = [
		self::KEC_ONE_STEP,
		self::KEC_TWO_STEP,
	];

	/**
	 * List of all SIWK features.
	 *
	 * @var array
	 */
	public const SIWK = [
		self::SIWK_ACCOUNT_CREATION_PAGE,
		self::SIWK_AUTHENTICATION_PAGE,
		self::SIWK_CART_PAGE,
	];
}
