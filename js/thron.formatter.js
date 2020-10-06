/**
 * @file
 */

(function ($, Drupal) {

    'use strict';

    // Enables the THRON Universal player for configured elements of context
    Drupal.behaviors.ThronPlayerEmbedd = {
      attach: function (context, settings) {
        if (settings.thron && settings.thron.players) {
          for (var k in settings.thron.players) {
            var options = settings.thron.players[k];
            var playerId = 'THRONPlayer_instance__'+k;
            window[playerId] = new THRONContentExperience(k, options);
          }
        }
      }
    };

}(jQuery, Drupal));

