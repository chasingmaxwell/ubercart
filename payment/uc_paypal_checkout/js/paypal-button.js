/**
 * @file Provides the JavaScript integration with PayPal Checkout on the cart page.
 */

(function ($) {
  'use strict';

  Drupal.behaviors.ucPaypalCheckout = {
    attach: function (context, settings) {
      paypal.Button.render(
        {
          // Configuration
          env: settings.ucPaypalCheckout.env,
          locale: settings.ucPaypalCheckout.locale,
          style: settings.ucPaypalCheckout.buttonStyle,
          commit: true,

          // Set up a payment
          payment: function (data, actions) {
            const formData = $('#uc-cart-view-form')
              .serializeArray()
              .reduce(function (values, current) {
                values[current.name] = current.value;
                return values;
              }, {});
            formData.op = 'PayPalCheckout';
            return actions.request
              .post(settings.ucPaypalCheckout.urls.cart, formData)
              .then(function (res) {
                return JSON.parse(res).id;
              })
              .catch(function () {
                window.location.href = setting.ucPaypalCheckout.urls.error;
              });
          },

          // Execute the payment
          onAuthorize: function (data, actions) {
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
                window.location.href = setting.ucPaypalCheckout.urls.error;
              });
          },
          onError: function (error) {
            window.location.href = setting.ucPaypalCheckout.urls.error;
          }
        },
        '#paypal-button'
      );
    }
  };
})(jQuery);
