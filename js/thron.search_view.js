/**
 * @file
 */
(function ($, D, once) {

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

      $(once('thron-bind-click-event', '.grid-item', context))
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
      $(once('thron-bind-submit-event', '.entity-browser-form', context))
          .on('submit', function () {
            $('body').prepend('<div class="overlay-throbber"><div class="throbber-spinner"></div></div></div>');
          });
    }
  };

}(jQuery, Drupal, once));
