"use strict"
const { KlarnaSDK } = await import("@klarna/websdk_v2");
const $ = jQuery;
let configData = {};

const params = document.getElementById(
	'wp-script-module-data-@klarna/interoperability_token'
);

if (params?.textContent) {
	try { configData = JSON.parse(params.textContent); } catch { }
}

const kp_interoperability_token = {
	params: null,
	Klarna: null,

	init: async function () {
		this.params = configData;
		this.updateGlobalToken( this.params.token )
		this.updateGlobalData( this.params.data )
		this.Klarna = await KlarnaSDK({
			clientId: this.params.client_id
		});
		this.bindKlarnaEvents();

		// Set the klarna object to the window for other scripts to use as well.
		$('body').trigger({ type: 'klarna_wc_sdk_loaded', Klarna: kp_interoperability_token.Klarna });

		// Check if we should send shopping data to Klarna.
		if ( true === kp_interoperability_token.params.send_data ) {
			// Update the session data when cart or checkout is updated.
			$( document.body ).on( "updated_cart_totals added_to_cart removed_from_cart updated_checkout wc-blocks_added_to_cart wc-blocks_removed_from_cart", kp_interoperability_token.updateSessionData );
		}
	},

	bindKlarnaEvents: async function () {
		kp_interoperability_token.Klarna.Interoperability.on( "tokenupdate", kp_interoperability_token.updateSessionToken )

		// If we did not get a token from Klarna yet, trigger the token request.
		if ( undefined === window.klarna_interoperability_token ) {
			const result = await kp_interoperability_token.Klarna.Interoperability.token()
			kp_interoperability_token.updateSessionToken( { interoperabilityToken: result } )
		}
	},

	updateGlobalToken: function ( token ) {
		// If the token is not set, return.
		if ( ! token || token === "" ) {
			return
		}

		window.klarna_interoperability_token = token
	},

	updateGlobalData: async function ( data ) {
		// If the data is not set, return.
		if ( ! data || data === "" ) {
			return
		}

		window.klarna_interoperability_data = data
	},

	updateSessionToken: async function ( token ) {
		const interoperabilityToken = token.interoperabilityToken
		const { token_url, token_nonce } = kp_interoperability_token.params.ajax
		await $.ajax( {
			url: token_url,
			type: "POST",
			data: {
				token: interoperabilityToken,
				nonce: token_nonce,
			},
			async: true,
			success: function ( response ) {
				kp_interoperability_token.updateGlobalToken( interoperabilityToken )
				// Check if we should send shopping data to Klarna.
				if ( true === kp_interoperability_token.params.send_data ) {
					kp_interoperability_token.updateSessionData()
				}
			},
			error: function ( error ) {
				console.error( "Error updating Interoperability Token:", error )
			},
		} )
	},

	updateSessionData: async function () {
		const { data_url, data_nonce } = kp_interoperability_token.params.ajax
		await $.ajax( {
			url: data_url,
			type: "POST",
			data: {
				nonce: data_nonce,
			},
			async: true,
			success: function ( response ) {
				const updatedData = response.data
				kp_interoperability_token.updateGlobalData( updatedData )

			},
			error: function ( error ) {
				console.error( "Error updating Interoperability data:", error )
			},
		} )
	},
}

kp_interoperability_token.init();
export const klarna_interoperability = kp_interoperability_token;
