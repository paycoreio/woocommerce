/* global wc_paycore_params */
jQuery( function( $ ) {
	'use strict';
	/* Open and close for legacy class */
	$( 'form.checkout, form#order_review' ).on( 'change', 'input[name="wc-paycore-payment-token"]', function() {
		if ( 'new' === $( '.paycore-legacy-payment-fields input[name="wc-paycore-payment-token"]:checked' ).val() ) {
			$( '.paycore-legacy-payment-fields #paycore-payment-data' ).slideDown( 200 );
		} else {
			$( '.paycore-legacy-payment-fields #paycore-payment-data' ).slideUp( 200 );
		}
	} );

	/**
	 * Object to handle Paycore payment forms.
	 */
	var wc_paycore_form = {

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			// checkout page
			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( 'form.woocommerce-checkout' )
				.on(
					'checkout_place_order_paycore',
					this.onSubmit
				);

			debugger;
            if ( wc_paycore_params.legacy ) {
            	console.log('titit');
                $( 'form.woocommerce-checkout' )
                    .on(
                        'submit',
                        this.onSubmit
                    );
            }

			// pay order page
			if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
			}

			$( 'form#order_review' )
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
			}

			$( 'form#add_payment_method' )
				.on(
					'submit',
					this.onSubmit
				);

			$( document )
				.on(
					'change',
					'#wc-paycore-cc-form :input',
					this.onCCFormChange
				)
				.on(
					'paycoreError',
					this.onError
				)
				.on(
					'checkout_error',
					this.clearToken
				);
		},

		isPaycoreChosen: function() {
			return $( '#payment_method_paycore' ).is( ':checked' ) && ( ! $( 'input[name="wc-paycore-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-paycore-payment-token"]:checked' ).val() );
		},

		hasToken: function() {
			return 0 < $( 'input.paycore_token' ).length;
		},

		block: function() {
			wc_paycore_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			wc_paycore_form.form.unblock();
		},

		onError: function( e, responseObject ) {
			var message = responseObject.response.error.message;

			// Customers do not need to know the specifics of the below type of errors
			// therefore return a generic localizable error message.
			if ( 
				'invalid_request_error' === responseObject.response.error.type ||
				'api_connection_error'  === responseObject.response.error.type ||
				'api_error'             === responseObject.response.error.type ||
				'authentication_error'  === responseObject.response.error.type ||
				'rate_limit_error'      === responseObject.response.error.type
			) {
				message = wc_paycore_params.invalid_request_error;
			}

			if ( 'card_error' === responseObject.response.error.type && wc_paycore_params.hasOwnProperty( responseObject.response.error.code ) ) {
				message = wc_paycore_params[ responseObject.response.error.code ];
			}

			$( '.wc-paycore-error, .paycore_token' ).remove();
			$( '#paycore-card-number' ).closest( 'p' ).before( '<ul class="woocommerce_error woocommerce-error wc-paycore-error"><li>' + message + '</li></ul>' );
			wc_paycore_form.unblock();
		},

		onSubmit: function( e ) {
			if ( wc_paycore_form.isPaycoreChosen() && ! wc_paycore_form.hasToken() ) {
                e.preventDefault();
                wc_paycore_form.block();
				$.ajax({
                    type:		'POST',
                    url:		wc_checkout_params.checkout_url,
                    data:		wc_paycore_form.form.serialize(),
                    dataType:   'json',
                    success:	function( result ) {
                        try {
                            if ( 'success' === result.result ) {
                                if (result.orderId !== undefined) {
                                	var paymentData = $('#paycore-payment-data');
                                    var form = $('<form></form>');
                                    form.attr('method', 'post');
                                    form.attr('action', 'http://checkout.paycore.io');
                                    form.attr('id', 'redirect_form');
                                    form.append('<input type="hidden" name="amount" value="' + paymentData.attr('data-amount') + '">');
                                    form.append('<input type="hidden" name="currency" value="' + paymentData.attr('data-currency').toUpperCase() + '">');
                                    form.append('<input type="hidden" name="public_key" value="' + paymentData.attr('data-public-key') + '">');
                                    form.append('<input type="hidden" name="payment_method" value="' + $('input[name="paycore_payment_method"]:checked').attr('value') + '">');
                                    form.append('<input type="hidden" name="reference" value="' + result.orderId + '">');

                                    if (result.returnUrl !== undefined) {
                                        form.append('<input type="hidden" name="return_url" value="' + result.returnUrl + '">');
									}

                                    if (result.ipnUrl !== undefined) {
                                        form.append('<input type="hidden" name="ipn_url" value="' + result.ipnUrl + '">');
                                    }

                                    $('body').append(form);
                                    form.submit();
                                }

                                return false;
                            } else if ( 'failure' === result.result ) {
                                throw 'Result failure';
                            } else {
                                throw 'Invalid response';
                            }
                        } catch( err ) {
                            // Reload page
                            if ( true === result.reload ) {
                                window.location.reload();
                                return;
                            }

                            // Trigger update in case we need a fresh nonce
                            if ( true === result.refresh ) {
                                $( document.body ).trigger( 'update_checkout' );
                            }

                            // Add new errors
                            if ( result.messages ) {
                                wc_paycore_form.submit_error( result.messages );
                            } else {
                                wc_paycore_form.submit_error( '<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>' );
                            }
                        }
                    },
                    error:	function( jqXHR, textStatus, errorThrown ) {
                        wc_paycore_form.submit_error( '<div class="woocommerce-error">' + errorThrown + '</div>' );
                    }
				});

				return false;
			}
		},

		onCCFormChange: function() {
			$( '.wc-paycore-error, .paycore_token' ).remove();
		},

        submit_error: function( error_message ) {
            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            wc_paycore_form.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
            wc_paycore_form.form.removeClass( 'processing' ).unblock();
            wc_paycore_form.form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
            $( 'html, body' ).animate({
                scrollTop: ( $( 'form.checkout' ).offset().top - 100 )
            }, 1000 );
            $( document.body ).trigger( 'checkout_error' );
        },

		onPaycoreResponse: function( status, response ) {
			if ( response.error ) {
				$( document ).trigger( 'paycoreError', { response: response } );
			} else {
				// check if we allow prepaid cards
				if ( 'no' === wc_paycore_params.allow_prepaid_card && 'prepaid' === response.card.funding ) {
					response.error = { message: wc_paycore_params.no_prepaid_card_msg };

					$( document ).trigger( 'paycoreError', { response: response } );
					
					return false;
				}

				// token contains id, last4, and card type
				var token = response.id;

				// insert the token into the form so it gets submitted to the server
				wc_paycore_form.form.append( "<input type='hidden' class='paycore_token' name='paycore_token' value='" + token + "'/>" );
				wc_paycore_form.form.submit();
			}
		},

		clearToken: function() {
			$( '.paycore_token' ).remove();
		}
	};

	wc_paycore_form.init();
} );
