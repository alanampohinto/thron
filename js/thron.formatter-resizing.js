;(function($, Drupal, window, undefined) {
    
  // linked fixed width x height
  Drupal.behaviors.thronFormatterResizingConfig = {
    attach: function (context, settings) {
      if (typeof settings.thron_embed_form !== 'undefined') {
        
        $wrap   = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings"]');
        $link   = $wrap.find('.form-item-attributes-data-entity-embed-display-settings-embed-resizing-fixed-link');
        $width  = $wrap.find('.form-item-attributes-data-entity-embed-display-settings-embed-resizing-fixed-width  input[type="number"]');
        $height = $wrap.find('.form-item-attributes-data-entity-embed-display-settings-embed-resizing-fixed-height input[type="number"]');
        
        var linkFixedRatio = function (event) {
          var source = event.target;
          if ($(event.target).val() == '') {
            $(event.target).val(0);
          }
          
          if ($link.hasClass('link-on')) {
            if (source.name.indexOf('width') > 0) {
              // update height
              $height.val( Math.floor($width.val() / settings.thron_embed_form.aspectRatio) );
            }
            else {
              // update width
              $width.val( Math.floor($height.val() * settings.thron_embed_form.aspectRatio) );
            }
          }
        };
        
        $width.on('change', linkFixedRatio);
        $height.on('change', linkFixedRatio);
        $width.on('keyup', linkFixedRatio);
        $height.on('keyup', linkFixedRatio);
        
        if ($link.find('input').is(':checked')) {
          $link.addClass('link-on');
        }
        
        $link.find('input')
          .on('click', function () {
            if ($link.hasClass('link-on')) {
              $link.removeClass('link-on');
            }
            else {
              $link.addClass('link-on');
              $width.trigger('change');
            }
          });
      }
    }
  };
  
  Drupal.behaviors.cropSliderSync = {
    attach: function (context, settings) {
      $(once("crop-slider-sync", ".wrapper-range", context)).each(function () {
        var $wrapper = $(this);
        var $range = $wrapper.find('input[type="range"]');
        var $text = $wrapper.find('input[type="text"]');

        $range.on("input", function () {
          $text.val($(this).val());
        });
      });
    },
  };
})(jQuery, Drupal, window, once);
