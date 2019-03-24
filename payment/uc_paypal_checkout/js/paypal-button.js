/**
 * @file Provides the JavaScript integration with PayPal Checkout on the cart page.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.ucPaypalCheckout = {
    attach: function (context, settings) {
      $('#paypal-button').once().each(() => {
        var config = settings.ucPaypalCheckout.overrideConfig || {};

        // Configuration
        config.env = config.env || settings.ucPaypalCheckout.env;
        config.locale = config.locale || settings.ucPaypalCheckout.locale;
        config.style = config.style || settings.ucPaypalCheckout.buttonStyle;
        config.commit = config.commit || true;
        config.funding = config.funding || {};
        config.funding.allowed = config.funding.allowed || settings.ucPaypalCheckout.allowedFunding.map(function (source) { return paypal.FUNDING[source]; });

        console.log('***************'.repeat(9), config);

        // Set up a payment
        config.payment = function (data, actions) {
          return actions.request
            .get(settings.ucPaypalCheckout.urls.paymentCreate)
            .then(function (res) {
              return JSON.parse(res).id;
            })
            .catch(function () {
              window.location.href = settings.ucPaypalCheckout.urls.error;
            });
        };

        // Execute the payment
        config.onAuthorize = function (data, actions) {
          return actions.request
            .post(settings.ucPaypalCheckout.urls.paymentExecute, {
              paymentID: data.paymentID,
              payerID: data.payerID
            })
            .then(function (res) {
              window.location.href =
                settings.ucPaypalCheckout.urls.checkoutComplete;
            })
            .catch(function () {
              window.location.href = settings.ucPaypalCheckout.urls.error;
            });
        };

        // Handle errors
        config.onError = function (error) {
          window.location.href = settings.ucPaypalCheckout.urls.error;
        };


        paypal.Button.render(config, '#paypal-button');
      });
    }
  };
})(jQuery, Drupal);
