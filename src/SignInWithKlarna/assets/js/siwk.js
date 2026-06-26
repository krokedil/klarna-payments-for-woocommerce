const $ = jQuery;
const { klarna_interoperability } = await import("@klarna/network_session_token");
let configData = {};
const params = document.getElementById(
    'wp-script-module-data-@klarna/siwk'
);

if (params?.textContent) {
    try { configData = JSON.parse(params.textContent); } catch { }
}

const siwk = {
    Klarna: null,
    params: null,
    button: null,
    buttonWrapper: "#klarna-identity-button",

    mount: function () {
        // Dedupe duplicate #klarna-identity-button divs across placements.
        // The mini-cart (header) renders before the page-body placements (login form,
        // proceed-to-checkout). When both fire on /my-account/, /cart/, or /checkout/,
        // multiple divs share the same id and the SDK mounts onto the first match —
        // typically the off-canvas mini-cart, leaving the dedicated div empty.
        // Strip IDs from all but the last occurrence so the page-body placement wins.
        const allButtons = document.querySelectorAll(siwk.buttonWrapper);        
        if (allButtons.length > 1) {
            for (let i = 0; i < allButtons.length - 1; i++) {
                allButtons[i].removeAttribute('id');
            }
        }

        siwk.button = siwk.Klarna.Identity.button({
            scope: siwk.params.scope,
            redirectUri: siwk.params.redirect_uri,
            locale: siwk.params.locale,
            theme: siwk.params.theme,
            shape: siwk.params.shape,
            alignment: siwk.params.alignment,
        });

        // Only mount and register the events if the buttonWrapper exists.
        const $buttonWrapper = $(siwk.buttonWrapper);
        if ($buttonWrapper === undefined || $buttonWrapper.length === 0) {
            return;
        }

        siwk.Klarna.Identity.on("signin", siwk.onSignin);
        siwk.Klarna.Identity.on("error", siwk.onError);
        siwk.button.mount(siwk.buttonWrapper);
    },

    onSignin: async function (response) {
        const { idToken } = response

        jQuery.ajax({
            type: "POST",
            url: siwk.params.sign_in_from_popup_url,
            data: {
                url: window.location.href,
                id_token: idToken,
                nonce: siwk.params.sign_in_from_popup_nonce,
            },
            success: (data) => {
                if (data.success) {
                    const { redirect } = data.data
                    window.location = redirect
                } else {
                    console.warn("siwk sign-in failed", data)
                }
            },
            error: (error) => {
                console.warn("siwk sign-in error", error)
            },
        })
    },

    onError: async function (error) {
        console.warn("siwk error", error)
    },

    init: async function (e) {
        siwk.params = configData;
        siwk.Klarna = klarna_interoperability.Klarna;

        siwk.mount();
    }
}

$('body').on('klarna_wc_sdk_loaded', siwk.init);
