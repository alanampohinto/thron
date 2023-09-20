/**
 * @file
 */
(function ($, Drupal, once) {

  'use strict';

  /**
   * Registers behaviours related to the THRONSearch config form.
   */
  Drupal.behaviors.ThronSearchConfig = {
    attach: function (context, settings) {

      // Sortable tags widget must be visible only with tag filter enabled.
      $(once('thron-search-config-sortable', '.form-type-thron-tags-sortable', context))
        .each(function () {
          let $sortableWidget = $(this).find('.sortable-widget'),
              $available = $sortableWidget.find('.available ul')[0],
              $selected  = $sortableWidget.find('.selected ul')[0],
              $availableSortable = new Sortable($available, {
                group: $(this).parents('fieldset')[0].getAttribute('id'),
                sort: true,
              }),
              $selectedSortable = new Sortable($selected, {
                group: $(this).parents('fieldset')[0].getAttribute('id'),
                sort: true,
                onAdd: function (event) {
                  var tag_id = $(event.item).attr('data-tag-id');
                  var cs_id = $(this.el).attr('data-target-select');
                  var cs = $(context).find(cs_id);
                  cs.find('option[value="'+ tag_id +'"]').attr('selected', 'selected');
                },
                onRemove: function (event) {
                  var tag_id = $(event.item).attr('data-tag-id');
                  var cs_id = $(this.el).attr('data-target-select');
                  var cs = $(context).find(cs_id);
                  cs.find('option[value="'+ tag_id +'"]').removeAttr('selected');
                }
              })
          ;
      })
    }
  };

}(jQuery, Drupal, once));
