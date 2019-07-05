/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Registers behaviours related to the THRON config form.
   */
  Drupal.behaviors.THRONConfigForm = {
    attach: function () {
      $('body').once('thron-config-form').each(function () {
        $('input[name="avoid_classifications"]').on('change', function () {
          if ($(this).prop('checked')) {
            $('input[name^="classifications"]').prop('checked', false);
          }
        });

        $('input[name^="classifications"]').on('change', function () {
          if ($(this).prop('checked')) {
            $('input[name="avoid_classifications"]').prop('checked', false);
          }
        });
      });
    }
  };

}(jQuery, Drupal));
