jQuery( ( $ ) => {
    const copyToClipboard = () => {
        const src =
            "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IS0tIFVwbG9hZGVkIHRvOiBTVkcgUmVwbywgd3d3LnN2Z3JlcG8uY29tLCBHZW5lcmF0b3I6IFNWRyBSZXBvIE1peGVyIFRvb2xzIC0tPg0KPHN2ZyB3aWR0aD0iODAwcHgiIGhlaWdodD0iODAwcHgiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4NCjxwYXRoIGQ9Ik0yMC45OTgzIDEwQzIwLjk4NjIgNy44MjQ5NyAyMC44ODk3IDYuNjQ3MDYgMjAuMTIxMyA1Ljg3ODY4QzE5LjI0MjYgNSAxNy44Mjg0IDUgMTUgNUgxMkM5LjE3MTU3IDUgNy43NTczNiA1IDYuODc4NjggNS44Nzg2OEM2IDYuNzU3MzYgNiA4LjE3MTU3IDYgMTFWMTZDNiAxOC44Mjg0IDYgMjAuMjQyNiA2Ljg3ODY4IDIxLjEyMTNDNy43NTczNiAyMiA5LjE3MTU3IDIyIDEyIDIySDE1QzE3LjgyODQgMjIgMTkuMjQyNiAyMiAyMC4xMjEzIDIxLjEyMTNDMjEgMjAuMjQyNiAyMSAxOC44Mjg0IDIxIDE2VjE1IiBzdHJva2U9IiMxQzI3NEMiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiLz4NCjxwYXRoIGQ9Ik0zIDEwVjE2QzMgMTcuNjU2OSA0LjM0MzE1IDE5IDYgMTlNMTggNUMxOCAzLjM0MzE1IDE2LjY1NjkgMiAxNSAySDExQzcuMjI4NzYgMiA1LjM0MzE1IDIgNC4xNzE1NyAzLjE3MTU3QzMuNTE4MzkgMy44MjQ3NSAzLjIyOTM3IDQuNjk5ODkgMy4xMDE0OSA2IiBzdHJva2U9IiMxQzI3NEMiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiLz4NCjwvc3ZnPg=="
        const img = $( `<img alt='copy to clipboard' src=${ src } />` )
        img.css( {
            position: "absolute",
            top: "50%",
            right: 0,
            transform: "translateY(-50%)",
            height: "20px",
            width: "20px",
        } )

        const input = $( "#woocommerce_klarna_payments_siwk_callback_url" )

        const container = $( '<div class="siwk-container"></div>' )
        container.insertBefore( input.siblings( "p.description" ) )
        container.css( {
            width: "100%",
            position: "relative",
        } )

        $( input ).appendTo( container )
        $( img ).insertAfter( input )
        img.on( "click", async ( e ) => {
            await navigator.clipboard.writeText( input.val() )
            img.animate( { opacity: 0.5 }, 300, () => {
                img.animate( { opacity: 1 }, 300, () => {} )
            } )
        } )
    }

    const getImgUrl = () => {
        const theme = $( "#woocommerce_klarna_payments_siwk_button_theme" ).val()
        const shape = $( "#woocommerce_klarna_payments_siwk_button_shape" ).val()
        const alignment = $( "#woocommerce_klarna_payments_siwk_logo_alignment" ).val()

        const img = $( "#klarna-payments-settings-siwk .kp_settings__section_previews img" )
        img.attr(
            "src",
            img
                .attr( "src" )
                .replace(
                    /preview-(.*).png/,
                    `preview-${ shape }_shape-${ theme }_theme-${ alignment }_alignment.png`,
                ),
        )
    }

    $( document ).ready( () => {
        copyToClipboard()

        // Update the preview image when the select changes.
        $( document ).on(
            "change",
            "#woocommerce_klarna_payments_siwk_button_theme, #woocommerce_klarna_payments_siwk_button_shape, #woocommerce_klarna_payments_siwk_logo_alignment",
            getImgUrl,
        )
    } )
} )
