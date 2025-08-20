jQuery( function ( $ ) {
	"use strict"

	const kp_interoperability_token = {
		params: null,
		klarnaBindAttempts: 0,

		init: function () {
			this.params = klarna_interoperability_token_params
			this.updateGlobalToken( this.params.token )
			this.bindKlarnaEvents()
		},

		bindKlarnaEvents: function () {
			// Ensure the Klarna object exists in the global scope.
			if ( typeof window.Klarna === "undefined" || typeof window.Klarna.Interoperability === "undefined" ) {
				if ( kp_interoperability_token.klarnaBindAttempts >= 10 ) {
					return
				}

				kp_interoperability_token.klarnaBindAttempts++
				setTimeout( kp_interoperability_token.bindKlarnaEvents, 250 )
				return
			}
			Klarna.Interoperability.on( "tokenupdate", kp_interoperability_token.updateSessionToken )

			// If we did not get a token from Klarna yet, trigger the token request.
			if ( undefined === window.klarna_interoperability_token ) {
				Klarna.Interoperability.token()
			}
		},

		updateGlobalToken: function ( token ) {
			// If the token is not set, return.
			if ( ! token || token === "" ) {
				return
			}

			window.klarna_interoperability_token = token
		},

		updateSessionToken: function ( token ) {
			const interoperabilityToken = token.interoperabilityToken
			const { url, nonce } = kp_interoperability_token.params.ajax

			$.ajax( {
				url: url,
				type: "POST",
				data: {
					token: interoperabilityToken,
					nonce: nonce,
				},
				success: function ( response ) {
					kp_interoperability_token.updateGlobalToken( interoperabilityToken )
				},
				error: function ( error ) {
					console.error( "Error updating Interoperability Token:", error )
				},
			} )
		},
	}

	kp_interoperability_token.init()
} )
