/**
 * @var komMetaboxParams
 */
jQuery( function ( $ ) {
    const komMetabox = {
        init: function () {
            $( document ).on( "click", ".kom_order_sync--action", this.toggleOrderSync )
        },

        toggleOrderSync: async function ( e ) {
            e.preventDefault()
            const $this = $( this )
            const $metabox = $( "#klarna-om" )
            const omStatus = $this.attr( "data-kom-order-sync" )

            // Block the page to prevent changing the order during the request.
            $metabox.block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )

            // Make the AJAX request to toggle the order sync for the order.
            const result = await komMetabox.ajaxSetOrderSync( omStatus )
            if ( result.success ) {
                komMetabox.toggleButton( $this, "enabled" === omStatus ? true : false )
            } else {
                alert( "Failed to toggle order sync. Please try again." )
            }

            // Reload the page to ensure the metadata has been added to the form.
            location.reload()
        },

        toggleButton: function ( $button, enabled ) {
            $button
                .attr( "data-kom-order-sync", enabled )
                .toggleClass( "woocommerce-input-toggle--enabled" )
                .toggleClass( "woocommerce-input-toggle--disabled" )
        },

        ajaxSetOrderSync: async function ( omStatus ) {
            const orderId = komMetaboxParams.orderId
            const { url, action, nonce } = komMetaboxParams.ajax.setOrderSync

            const data = {
                nonce: nonce,
                action: action,
                order_id: orderId,
                om_status: omStatus,
            }

            return $.ajax( {
                type: "POST",
                url: url,
                data: data,
            } )
        },
    }

    komMetabox.init()
} )
