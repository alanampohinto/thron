/**
 * @file
 */
(function ($, D, settings) {

  'use strict';

  /*setTimeout(function () {
  }, 1000);*/
  // Manage media extensions lazy loading.
  $('#thumbnails')
      .once('thron-load-extensions')
      .find('.grid-item span[thron-media-id]')
      .each(function (index, elem) {
        if (typeof settings.thron.media_extension.basepath == 'undefined') {
          return
        }

        var media_id = $(elem).attr('thron-media-id');
        D.ajax({
          url: `${settings.thron.media_extension.basepath}/${media_id}`,
          async: true
        }).execute();
      });

}(jQuery, Drupal, drupalSettings));
