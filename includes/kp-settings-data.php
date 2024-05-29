<?php

// Only here for testing purposes.
return array(
	'support' =>
	array(
		'links'      =>
		array(
			array(
				'text'   => 'General information',
				'target' => '_blank',
				'href'   =>
				array(
					'en' => 'https://krokedil.com/product/klarna-checkout-for-woocommerce/',
					'sv' => 'https://krokedil.se/product/klarna-checkout-for-woocommerce/',
				),
			),
			array(
				'text'   => 'Technical documentation',
				'target' => '_blank',
				'href'   =>
				array(
					'en' => 'https://docs.krokedil.com/klarna-checkout-for-woocommerce/',
				),
			),
			array(
				'text'   => 'General support information',
				'target' => '_blank',
				'href'   =>
				array(
					'en' => 'https://docs.krokedil.com/krokedil-general-support-info/',
				),
			),
		),
		'link_texts' => array(
			array(
				'text' => array(
					'en' => 'If you have question regarding a certain purchase, you are welcome to contact %s.',
					'sv' => '',
				),
				'link' => array(
					'text'   => 'Klarna',
					'target' => '_blank',
					'class'  => 'no-external-icon',
					'href'   =>
					array(
						'en' => 'https://klarna.com/merchant-support',
					),
				),
			),
			array(
				'text' => array(
					'en' => 'If you have <b>technical questions or question regarding the configuration</b> of the plugin, you are welcome to contact %s, the plugin developer.',
					'sv' => '',
				),
				'link' => array(
					'text'   => 'Krokedil',
					'target' => '_blank',
					'class'  => 'no-external-icon',
					'href'   =>
					array(
						'en' => 'https://krokedil.com/support/',
						'sv' => 'https://krokedil.se/support/',
					),
				),
			),
		),
	),
	'sidebar' =>
	array(
		'plugin_resources'     =>
		array(
			'links' =>
			array(
				array(
					'text'   => 'General information',
					'target' => '_blank',
					'href'   =>
					array(
						'en' => 'https://krokedil.com/product/klarna-checkout-for-woocommerce/',
						'sv' => 'https://krokedil.se/product/klarna-checkout-for-woocommerce/',
					),
				),
				array(
					'text'   => 'Technical documentation',
					'target' => '_blank',
					'href'   =>
					array(
						'en' => 'https://docs.krokedil.com/klarna-checkout-for-woocommerce/',
					),
				),
				array(
					'text' => 'Support',
					'href' =>
					array(
						'en' => '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=klarna_payments&subsection=kco-support',
					),
				),
				array(
					'text' => 'Add-ons',
					'href' =>
					array(
						'en' => '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=klarna_payments&subsection=kco-addons',
					),
				),
			),
		),
		'additional_resources' =>
		array(
			'links' =>
			array(
				array(
					'text'   => 'General Support Information',
					'target' => '_blank',
					'href'   =>
					array(
						'en' => 'https://docs.krokedil.com/krokedil-general-support-info/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar',
					),
				),
				1 =>
				array(
					'text'   => 'Other Krokedil plugins',
					'target' => '_blank',
					'href'   =>
					array(
						'en' => 'https://krokedil.com/products/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar',
						'sv' => 'https://krokedil.se/produkter/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar',
					),
				),
				array(
					'text'   => 'Krokedil blog',
					'target' => '_blank',
					'href'   =>
					array(
						'en' => 'https://krokedil.com/knowledge/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar',
						'sv' => 'https://krokedil.se/kunskap/?utm_source=kco&utm_medium=wp-admin&utm_campaign=settings-sidebar',
					),
				),
			),
		),
	),
	'addons'  =>
	array(
		'items' =>
		array(
			array(
				'slug'        => 'klarna-order-management-for-woocommerce',
				'title'       => 'Klarna Order Management',
				'image'       => array(
					'type' => 'base64',
					'src'  => 'data:image/webp;base64,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',
				),
				'description' => array(
					'en' => 'Handle post purchase order management in Klarna\'s system directly from WooCommerce . This way you can save time and don\'t have to work in both systems simultaneously.',
					'sv' => 'Hantera ordrar i Klarnas system direkt från WooCommerce. På så sätt sparar du tid och behöver inte arbeta i båda systemen samtidigt.',
				),
				'link'        => array(
					'class' => 'thickbox open-plugin-details-modal',
					'text'  => array(
						'en' => 'Learn more',
						'sv' => 'Läs mer',
					),
					'href'  => array(
						'en' => '/wp-admin/plugin-install.php?tab=plugin-information&plugin=klarna-order-management-for-woocommerce&TB_iframe=true&width=772&height=1005',
						'sv' => '/wp-admin/plugin-install.php?tab=plugin-information&plugin=klarna-order-management-for-woocommerce&TB_iframe=true&width=772&height=1005',
					),
				),
			),
			array(
				'slug'        => 'post-purchase-upsell-for-woocommerce',
				'title'       => 'Post Purchase Upsell',
				'image'       => array(
					'type' => 'base64',
					'src'  => 'data:image/webp;base64,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',
				),
				'description' => array(
					'en' => 'With Post Purchase Upsell, the customer can add additional products to their order after a completed purchase. They easily choose which of the selectable products they want to add and update their order with one click.',
					'sv' => 'Med Post Purchase Upsell kan kunden lägga till ytterligare produkter till sin beställning efter att de genomfört ett köp. De väljer enkelt vilka av de valbara produkterna de vill lägga till och uppdaterar sin order med ett klick.',
				),
				'link'        => array(
					'target' => '_blank',
					'text'   => array(
						'en' => 'Learn more',
						'sv' => 'Läs mer',
					),
					'href'   =>
					array(
						'en' => 'https://krokedil.com/product/post-purchase-upsell-for-woocommerce/',
						'sv' => 'https://krokedil.se/produkt/post-purchase-upsell-for-woocommerce/',
					),
				),
			),
			array(
				'slug'        => 'partial-delivery-for-woocommerce',
				'title'       => 'Partial Delivery',
				'image'       => array(
					'type' => 'base64',
					'src'  => 'data:image/webp;base64,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',
				),
				'description' => array(
					'en' => 'Enable partially delivered orders in WooCommerce, manage back ordered products automatically and give your customers a full overview under “My account”.',
					'sv' => 'Möjliggör dellevererade ordrar i WooCommerce, hantera restnoterade produkter automatiskt och ge era kunder full överblick under “Mitt konto”.',
				),
				'link'        => array(
					'target' => '_blank',
					'text'   => array(
						'en' => 'Learn more',
						'sv' => 'Läs mer',
					),
					'href'   =>
					array(
						'en' => 'https://krokedil.com/product/partial-delivery-for-woocommerce/',
						'sv' => 'https://krokedil.se/produkt/partial-delivery-for-woocommerce/',
					),
				),
			),
		),
	),
);
