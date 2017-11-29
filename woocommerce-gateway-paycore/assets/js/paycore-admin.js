jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Paycore admin functions.
	 */
	var wc_paycore_admin = {
		isTestMode: function() {
			return $( '#woocommerce_paycore_testmode' ).is( ':checked' );
		},

		getSecretKey: function() {
			if ( wc_paycore_admin.isTestMode() ) {
				return $( '#woocommerce_paycore_test_secret_key' ).val();
			} else {
				return $( '#woocommerce_paycore_secret_key' ).val();
			}
		},

		sortInputs: function(topMethods) {
            var inputs = $('#inputs');
            inputs.html('');
            for (var i = 0; i < topMethods.length; i++ ) {
                var paymentMethod = $(topMethods[i]);
                var method = paymentMethod.attr('data-payment-method');
                var icon = paymentMethod.attr('data-icon');

                var inputMethod = $('<input type="hidden" name="woocommerce_paycore_payment_methods[' + method+ ']">');
                inputMethod.attr('value', icon);

                inputs.append(inputMethod);
            }
		},

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_paycore_testmode', function() {
				var test_secret_key = $( '#woocommerce_paycore_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					test_publishable_key = $( '#woocommerce_paycore_test_publishable_key' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_paycore_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_publishable_key = $( '#woocommerce_paycore_publishable_key' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_secret_key.show();
					test_publishable_key.show();
					live_secret_key.hide();
					live_publishable_key.hide();
				} else {
					test_secret_key.hide();
					test_publishable_key.hide();
					live_secret_key.show();
					live_publishable_key.show();
				}
			} );

			$( '#woocommerce_paycore_testmode' ).change();

			// Validate the keys to make sure it is matching test with test field.
			$( '#woocommerce_paycore_secret_key, #woocommerce_paycore_publishable_key' ).on( 'input', function() {
				var value = $( this ).val();

				if ( value.indexOf( '_live_' ) === -1 ) {
                    $( '.paycore-error-description', $( this ).parent() ).remove();
					$( this ).css( 'border-color', 'red' ).after( '<span class="description paycore-error-description" style="color:red; display:block;">' + wc_paycore_admin_params.localized_messages.not_valid_live_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.paycore-error-description', $( this ).parent() ).remove();
				}
			}).trigger( 'input' );

			// Validate the keys to make sure it is matching live with live field.
			$( '#woocommerce_paycore_test_secret_key, #woocommerce_paycore_test_publishable_key' ).on( 'input', function() {
				var value = $( this ).val();

				if ( value.indexOf( '_test_' ) === -1 ) {
                    $( '.paycore-error-description', $( this ).parent() ).remove();
					$( this ).css( 'border-color', 'red' ).after( '<span class="description paycore-error-description" style="color:red; display:block;">' + wc_paycore_admin_params.localized_messages.not_valid_test_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.paycore-error-description', $( this ).parent() ).remove();
				}
			}).trigger( 'input' );

            $( "#sortable1, #sortable2" ).sortable({
                connectWith: ".connectedSortable"
            }).disableSelection();

            $("#sortable1").on('sortupdate', function (){
                var topPayments = $( "#sortable1 li");
                wc_paycore_admin.sortInputs(topPayments);
            });

            $("#sortable1").trigger('sortupdate');
		}
	};

	wc_paycore_admin.init();
});
