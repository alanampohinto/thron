/**
 * @file
 */
(function ($, D) {

  'use strict';

  /**
   * Registers behaviours related to the THRON search view widget.
   */
  D.behaviors.THRONSearchView = {
    attach: function (context, settings) {
      var $submit = $('.entity-browser-thron-entity-browser-form').find('input.form-submit[name="op"]');
      $submit.prop('disabled', true);

      var $view = $('.grid');

      $view.imagesLoaded(function () {
        // Add a class to reveal the loaded images, which avoids FOUC.
        $('.grid-item').addClass('item-style');
      });

      $('.grid-item')
          .once('thron-bind-click-event')
          .on('click', function () {
            // Get current input
            var $input = $(this).find('.item-selector');
            var checked = $input.prop('checked');

            // Uncheck every media and disable submit.
            $submit.prop('disabled', true);
            $('.grid-item.checked').each(function () {
              $(this).removeClass('checked');
              $(this).find('.item-selector').prop('checked', false);
            });

            // Check if previously unchecked.
            if (!checked) {
              $input.prop('checked', true);
              $(this).addClass('checked');
              $submit.prop('disabled', false);
            }
          });

      // Display throbber overlay when search is submitted.
      $('.entity-browser-form')
          .once('thron-bind-submit-event')
          .on('submit', function () {
            $('body').prepend('<div class="overlay-throbber"><div class="throbber-spinner"></div></div></div>');
          });
    }
  };

}(jQuery, Drupal));
