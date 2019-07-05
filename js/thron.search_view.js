/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Registers behaviours related to the THRON search view widget.
   */
  Drupal.behaviors.THRONSearchView = {
    attach: function () {
      var $submit = $('.entity-browser-thron-entity-browser-form').find('input.form-submit[name="op"]');
      $submit.prop('disabled', true);

      var $view = $('.grid');

      $view.imagesLoaded(function () {
        // Add a class to reveal the loaded images, which avoids FOUC.
        $('.grid-item').addClass('item-style');
      });

      $('.grid-item').once('thron-bind-click-event').click(function () {
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

      // Display throbber overlay when pager is used.
      $('#edit-next, #edit-previous').once('thron-bind-click-event').click(function () {
        $('body').prepend('<div class="overlay-throbber"><div class="throbber-spinner"></div></div></div>');
      });

      // Display throbber overlay when search is submitted.
      $('.entity-browser-form').on('submit', function () {
        $('body').prepend('<div class="overlay-throbber"><div class="throbber-spinner"></div></div></div>');
      });

      // var checkImageSetCheckbox = function() {
      //   if ($(this).is(':checked')) {
      //     $('select[name="filters[content_type]"]').val('IMAGE');
      //   }
      // };
      //
      // $('input[name="media_image_set"]').on('change', checkImageSetCheckbox);
      //
      // checkImageSetCheckbox();  // initial
    }
  };

}(jQuery, Drupal));
