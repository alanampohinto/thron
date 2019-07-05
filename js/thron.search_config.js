/**
 * @file
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Registers behaviours related to the THRONSearch config form.
   */
  Drupal.behaviors.ThronSearchConfig = {
    attach: function (context, settings) {
      
      // Sortable tags widget must be visible only with tag filter enabled.
      $('.form-type-thron-tags-sortable', context).each(function () {
        var $sortableWidget = $(this).find('.sortable-widget'),
            $available = $sortableWidget.find('.available ul'),
            $selected  = $sortableWidget.find('.selected ul')
        ;
        
        $available.once('thron-search-config-sortable').sortable({
          connectWith: $selected,
          containment: $sortableWidget,
          cursor: 'grabbing'
        }).disableSelection();
        
        
        $selected.once('thron-search-config-sortable').sortable({
          connectWith: $available,
          containment: $sortableWidget,
          cursor: 'grabbing',
          receive: function (event, ui) {
            var tag_id = $(ui.item).attr('data-tag-id');
            var cs_id = $(this).attr('data-target-select');
            var cs = $(context).find(cs_id);
            cs.find('option[value="'+ tag_id +'"]').attr('selected', 'selected');
          },
          remove: function (event, ui) {
            var tag_id = $(ui.item).attr('data-tag-id');
            var cs_id = $(this).attr('data-target-select');
            var cs = $(context).find(cs_id);
            cs.find('option[value="'+ tag_id +'"]').removeAttr('selected');
          }
        }).disableSelection();
      })
    }
  };

}(jQuery, Drupal));
