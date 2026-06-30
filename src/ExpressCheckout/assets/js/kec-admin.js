jQuery(function ($) {
    const kec_admin = {
        /**
         * Initialize the KEC admin logic.
         */
        init() {
            $('#woocommerce_klarna_payments_kec_flow').on('change', kec_admin.onFlowChange);
            kec_admin.onFlowChange.call($('#woocommerce_klarna_payments_kec_flow'));
        },

        /**
         * Handle the change event for the KEC flow setting.
         * @returns {void}
         */
        onFlowChange() {
            const value = $(this).val();
            if (value === 'one_step') {
                $('.kec-webhook-section').css('display', 'flex').show();
                return;
            }
            $('.kec-webhook-section').hide();
        },
    };

    kec_admin.init();
});


