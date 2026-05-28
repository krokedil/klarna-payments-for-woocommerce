"use strict"
const { KlarnaSDK } = await import("@klarna/websdk_v2");
const $ = jQuery;
let configData = {};

const params = document.getElementById(
	'wp-script-module-data-@klarna/klarna_network_session_token'
);

if (params?.textContent) {
	try { configData = JSON.parse(params.textContent); } catch { }
}

const kp_network_session_token = {
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
		$('body').trigger({ type: 'klarna_wc_sdk_loaded', Klarna: kp_network_session_token.Klarna });

		// Check if we should send shopping data to Klarna.
		if ( true === kp_network_session_token.params.send_data ) {
			// Update the session data when cart or checkout is updated.
			$( document.body ).on( "updated_cart_totals added_to_cart removed_from_cart updated_checkout wc-blocks_added_to_cart wc-blocks_removed_from_cart", kp_network_session_token.updateSessionData );
		}
	},

	bindKlarnaEvents: async function () {
		kp_network_session_token.Klarna.NetworkSession.on( "tokenupdate", kp_network_session_token.updateSessionToken )

		// If we did not get a token from Klarna yet, trigger the token request.
		if ( undefined === window.klarna_network_session_token ) {
			const result = await kp_network_session_token.Klarna.NetworkSession.token()
			kp_network_session_token.updateSessionToken( { networkSessionToken: result } )
		}
	},

	updateGlobalToken: function ( token ) {
		// If the token is not set, return.
		if ( ! token || token === "" ) {
			return
		}

		window.klarna_network_session_token = token
	},

	updateGlobalData: async function ( data ) {
		// If the data is not set, return.
		if ( ! data || data === "" ) {
			return
		}

		window.klarna_network_session_data = data
	},

	updateSessionToken: async function ( token ) {
		const networkSessionToken = token.networkSessionToken
		const { token_url, token_nonce } = kp_network_session_token.params.ajax
		await $.ajax( {
			url: token_url,
			type: "POST",
			data: {
				token: networkSessionToken,
				nonce: token_nonce,
			},
			async: true,
			success: function ( response ) {
				kp_network_session_token.updateGlobalToken( networkSessionToken )
				// Check if we should send shopping data to Klarna.
				if ( true === kp_network_session_token.params.send_data ) {
					kp_network_session_token.updateSessionData()
				}
			},
			error: function ( error ) {
				console.error( "Error updating Network Session Token:", error )
			},
		} )
	},

	updateSessionData: async function () {
		const { data_url, data_nonce } = kp_network_session_token.params.ajax
		await $.ajax( {
			url: data_url,
			type: "POST",
			data: {
				nonce: data_nonce,
			},
			async: true,
			success: function ( response ) {
				const updatedData = response.data
				kp_network_session_token.updateGlobalData( updatedData )

			},
			error: function ( error ) {
				console.error( "Error updating Network Session data:", error )
			},
		} )
	},
}

kp_network_session_token.init();
export const klarna_network_session = kp_network_session_token;
