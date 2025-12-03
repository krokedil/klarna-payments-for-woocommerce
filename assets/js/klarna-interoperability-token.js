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
		this.Klarna = await KlarnaSDK({
			clientId: this.params.client_id,
			environment: 'playground'
		});
		this.bindKlarnaEvents();

		// Set the klarna object to the window for other scripts to use as well.
		$('body').trigger({ type: 'klarna_wc_sdk_loaded', Klarna: kp_interoperability_token.Klarna });
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

	updateSessionToken: async function ( token ) {
		const interoperabilityToken = token.interoperabilityToken
		const { url, nonce } = kp_interoperability_token.params.ajax
		await $.ajax( {
			url: url,
			type: "POST",
			data: {
				token: interoperabilityToken,
				nonce: nonce,
			},
			async: true,
			success: function ( response ) {
				kp_interoperability_token.updateGlobalToken( interoperabilityToken )
			},
			error: function ( error ) {
				console.error( "Error updating Interoperability Token:", error )
			},
		} )
	},
}

kp_interoperability_token.init();
export const klarna_interoperability = kp_interoperability_token;
