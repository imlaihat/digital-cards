(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.digitalCardMerchantPortal = {
    attach: function (context) {
      once('digital-card-merchant-portal', '#dco-merchant-app', context).forEach(function (app) {
        var form = app.querySelector('form');
        var result = app.querySelector('.dco-result');
        form.addEventListener('submit', function (event) {
          event.preventDefault();
          var nfc = form.elements.nfc.value.trim();
          result.textContent = Drupal.t('Checking eligibility…');
          fetch(Drupal.url('api/digital-card/offers/' + encodeURIComponent(nfc)), {credentials: 'same-origin', headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json().then(function (data) { return {ok: response.ok, data: data}; }); })
            .then(function (response) {
              result.replaceChildren();
              if (!response.ok || !response.data.offers || !response.data.offers.length) {
                result.textContent = response.data.message || Drupal.t('No eligible offers were found.');
                return;
              }
              response.data.offers.forEach(function (offer) {
                var card = document.createElement('article');
                card.className = 'dco-card';
                var title = document.createElement('h3');
                title.textContent = offer.title + ' · ' + offer.partner;
                var benefit = document.createElement('strong');
                benefit.textContent = offer.benefit;
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'button button--primary';
                button.textContent = Drupal.t('Redeem securely');
                button.addEventListener('click', function () { redeem(offer.id, nfc, button, result); });
                card.append(title, benefit, button);
                result.appendChild(card);
              });
            })
            .catch(function () { result.textContent = Drupal.t('Eligibility could not be checked. Try again.'); });
        });
        // A static card opened by an authenticated Merchant links here with
        // ?nfc=... so the portal can verify that exact card immediately.
        var requestedNfc = new URLSearchParams(window.location.search).get('nfc');
        if (requestedNfc && /^[A-Za-z0-9_-]{3,128}$/.test(requestedNfc)) {
          form.elements.nfc.value = requestedNfc;
          form.requestSubmit();
        }
      });
    }
  };

  function redeem(offerId, nfc, button, result) {
    button.disabled = true;
    Promise.all([
      fetch(Drupal.url('session/token'), {credentials: 'same-origin'}).then(function (response) { return response.text(); })
    ]).then(function (values) {
      return fetch(Drupal.url('api/digital-card/offers/' + offerId + '/redeem/' + encodeURIComponent(nfc)), {
        method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-Token': values[0], Accept: 'application/json'}
      });
    }).then(function (response) { return response.json(); }).then(function (data) {
      if (!data.success) { throw new Error(data.message || Drupal.t('Redemption failed.')); }
      result.replaceChildren();
      var success = document.createElement('div');
      success.className = 'messages messages--status';
      success.textContent = Drupal.t('Redeemed successfully. Reference: @reference', {'@reference': data.reference});
      result.appendChild(success);
    }).catch(function (error) {
      button.disabled = false;
      window.alert(error.message);
    });
  }
})(Drupal, once);
