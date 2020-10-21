/**
 * @file
 */
(function ($, Drupal) {

  'use strict';
  var player;

  function getRangeValue(id) {

    $('[data-drupal-selector=' + id + ']').on('change mousemove', function () {
      $('[data-drupal-selector=' + id + '-input]').val(this.value);
    });
  }

  function playerLoad(clientId, xcontentId, sessId, scalemode, noSkin = true) {
    var cropParams = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-player-params"]');
    var rtieParams = {};
    if (typeof scalemode == "object") {
      rtieParams = scalemode;
      cropParams.val(JSON.stringify(scalemode));
    }
    if (scalemode.scalemode === 'manual' && !scalemode.hasOwnProperty('cropmode')) {
      scalemode.cropmode = "pixel";
    }
    if (scalemode != 'manual' && typeof scalemode != "object") {
      cropParams.val('');
    }
    var options = {
      clientId: clientId,
      xcontentId: xcontentId,
      sessId: sessId,
      rtie: rtieParams
    };
    if (noSkin === true) {
      options.noSkin = true;
      options.mouseWheelZoom = false;
    }

    var cropPlayer = THRONContentExperience("rtisg", options);
    return cropPlayer;
  }

  function paramsDisableEnable(id, prop) {
    $('[data-drupal-selector=' + id + ']').prop("disabled", prop);
    $('[data-drupal-selector=' + id + '-input]').prop("disabled", prop);
  }

  function getRtieParams(clientId, xcontentId, sessId, params) {
    var transform = document.querySelector("#rtisg .th-image-player").style.transform;
    var numbers = transform.split("matrix(")[1].split(")")[0].split(",").map(function (item) {
      return parseInt(item);
    });
    var translateX = numbers[4];
    var translateY = numbers[5];
    var scale = numbers[0];

    // get size of some elements (this is needed to calculate crop params for RTIE from matrix information)
    var imageSize = document.querySelector("#rtisg .th-image-player img").getBoundingClientRect();
    var playerSize = document.querySelector("#rtisg").getBoundingClientRect();

    // we also need the size of original image, do getcontentdetails again (player does not expose this info)
    var url = "https://{clientId}-view.thron.com/api/xcontents/resources/delivery/getContentDetail?clientId={clientId}&xcontentId={xcontentId}&templateId=CE1&pkey={sessId}";
    url = url.replace(/{clientId}/g, clientId);
    url = url.replace("{xcontentId}", xcontentId);
    url = url.replace("{sessId}", sessId);
    $.get(url)
      .then(function (contentdetails) {
        // get original content size
        var originalHeight = contentdetails.content.deliverySize.maxHeight;
        var originalWidth = contentdetails.content.deliverySize.maxWidth;

        // now map to RTIE params
        var cropx = (imageSize.width / 2) - Math.min(playerSize.width / 2, imageSize.width / 2) - translateX;
        var cropy = (imageSize.height / 2) - Math.min(playerSize.height / 2, imageSize.height / 2) - translateY;
        var cropw = Math.min(imageSize.right, playerSize.right) - Math.max(imageSize.left, playerSize.left);
        var croph = Math.min(imageSize.bottom, playerSize.bottom) - Math.max(imageSize.top, playerSize.top);

        // save width and height to be able to restore image later at the same dimensions
        var originalCropW = cropw;
        var originalCropH = croph;

        // sanitize params to not be negative or higher than image width/height
        cropx = Math.min(imageSize.width, Math.max(0, cropx));
        cropy = Math.min(imageSize.height, Math.max(0, cropy));
        cropw = Math.min(imageSize.width, Math.max(0, cropw));
        croph = Math.min(imageSize.height, Math.max(0, croph));

        // adjust for real image dimensions
        cropx = cropx * originalWidth / imageSize.width;
        cropy = cropy * originalHeight / imageSize.height;
        cropw = cropw * originalWidth / imageSize.width;
        croph = croph * originalHeight / imageSize.height;

        // compose query string
        var {quality, enhance} = getInputValues();
        params = {
          scalemode: "manual",
          cropmode: "pixel",
          quality: quality,
          enhance: enhance,
          cropx: cropx,
          cropy: cropy,
          cropw: cropw,
          croph: croph
        };
        if (player) {
          player.destroy();
        }
        player = playerLoad(clientId, xcontentId, sessId, params);
      });
  }

  function buttonManualType(val) {
    if (val === 'manual') {
      $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-button-manual"]').show();
    } else {
      $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-button-manual"]').hide();
    }
  }

  function getInputValues() {
    var brightness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-brightness"]').val();
    var contrast = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-contrast"]').val();
    var sharpness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-sharpness"]').val();
    var color = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-color"]').val();
    var quality = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-quality"]').val();
    var enhance = 'brightness:' + brightness + ',contrast:' + contrast + ',sharpness:' + sharpness + ',color:' + color;
    return {quality, enhance};
  }

  function renderInputValue(settings, mode, noSkin = true) {
    var {quality, enhance} = getInputValues();
    var p = {
      scalemode: mode,
      quality: quality,
      enhance: enhance
    };
    if (player) {
      player.destroy();
    }
    player = playerLoad(
      settings.thron.crop.clientId,
      settings.thron.crop.xcontentId,
      settings.thron.crop.sessId,
      p,
      noSkin
    );
  }

  function getCropParams(p) {
    var cropParams = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-player-params"]');
    if (cropParams) {
      var params = JSON.parse(cropParams.val());
      if (params.hasOwnProperty('cropx') &&
        params.hasOwnProperty('cropy') &&
        params.hasOwnProperty('cropw') &&
        params.hasOwnProperty('croph')) {
        p.cropx = params.cropx;
        p.cropy = params.cropy;
        p.cropw = params.cropw;
        p.croph = params.croph;
      }
    }
    return p;
  }

  function renderInputChange(settings, mode, inputType) {

    switch (inputType) {
      case 'brightness':
        $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-brightness"]').once('thron').on('change', function (mode) {
          var contrast = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-contrast"]').val();
          var sharpness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-sharpness"]').val();
          var color = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-color"]').val();
          var quality = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-quality"]').val();
          var enhance = 'brightness:' + this.value + ',contrast:' + contrast + ',sharpness:' + sharpness + ',color:' + color;
          mode = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-crop-mode"]').val();
          var p = {
            scalemode: mode,
            quality: quality,
            enhance: enhance
          };
          p = getCropParams(p);
          if (player) {
            player.destroy();
          }
          player = playerLoad(
            settings.thron.crop.clientId,
            settings.thron.crop.xcontentId,
            settings.thron.crop.sessId,
            p
          );
        });
        break;
      case 'contrast':
        $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-contrast"]').once('thron').on('change', function (mode) {
          var brightness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-brightness"]').val();
          var sharpness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-sharpness"]').val();
          var color = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-color"]').val();
          var quality = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-quality"]').val();
          var enhance = 'brightness:' + brightness + ',contrast:' + this.value + ',sharpness:' + sharpness + ',color:' + color;
          mode = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-crop-mode"]').val();
          var p = {
            scalemode: mode,
            quality: quality,
            enhance: enhance
          };
          p = getCropParams(p);
          if (player) {
            player.destroy();
          }
          player = playerLoad(
            settings.thron.crop.clientId,
            settings.thron.crop.xcontentId,
            settings.thron.crop.sessId,
            p
          );
        });
        break;
      case 'sharpness':
        $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-sharpness"]').once('thron').on('change', function (mode) {
          var brightness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-brightness"]').val();
          var contrast = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-contrast"]').val();
          var color = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-color"]').val();
          var quality = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-quality"]').val();
          var enhance = 'brightness:' + brightness + ',contrast:' + contrast + ',sharpness:' + this.value + ',color:' + color;
          mode = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-crop-mode"]').val();
          var p = {
            scalemode: mode,
            quality: quality,
            enhance: enhance
          };
          p = getCropParams(p);
          if (player) {
            player.destroy();
          }
          player = playerLoad(
            settings.thron.crop.clientId,
            settings.thron.crop.xcontentId,
            settings.thron.crop.sessId,
            p
          );
        });
        break;
      case 'color':
        $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-color"]').once('thron').on('change', function (mode) {
          var brightness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-brightness"]').val();
          var contrast = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-contrast"]').val();
          var sharpness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-sharpness"]').val();
          var quality = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-quality"]').val();
          var enhance = 'brightness:' + brightness + ',contrast:' + contrast + ',sharpness:' + sharpness + ',color:' + this.value;
          mode = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-crop-mode"]').val();
          var p = {
            scalemode: mode,
            quality: quality,
            enhance: enhance
          };
          p = getCropParams(p);
          if (player) {
            player.destroy();
          }
          player = playerLoad(
            settings.thron.crop.clientId,
            settings.thron.crop.xcontentId,
            settings.thron.crop.sessId,
            p
          );
        });
        break;
      case 'quality':
        $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-quality"]').once('thron').on('change', function (mode) {
          var brightness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-brightness"]').val();
          var contrast = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-contrast"]').val();
          var sharpness = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-sharpness"]').val();
          var color = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-color"]').val();
          var enhance = 'brightness:' + brightness + ',contrast:' + contrast + ',sharpness:' + sharpness + ',color:' + color;
          mode = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-crop-mode"]').val();
          var p = {
            scalemode: mode,
            quality: this.value,
            enhance: enhance
          };
          p = getCropParams(p);
          if (player) {
            player.destroy();
          }
          player = playerLoad(
            settings.thron.crop.clientId,
            settings.thron.crop.xcontentId,
            settings.thron.crop.sessId,
            p
          );
        });
        break;
    }
  }

  function setFormFactor() {
    var resize = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-resizing"]').val();
    var defaultWidth = 600;
    var defaultHeight = 400;
    if (resize == 'fixed') {
      var ratio = 1;
      var width = $('input[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-resizing-fixed-width"]').val();
      var height = $('input[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-resizing-fixed-height"]').val();
      ratio = defaultWidth / width;
      width = defaultWidth;
      height = height * ratio;
      $('#rtisg').css("width", width);
      $('#rtisg').css("height", height);
    } else {
      $('#rtisg').css("width", defaultWidth);
      $('#rtisg').css("height", defaultHeight);
    }
  }

  Drupal.behaviors.ThronCrop = {
    attach: function (context, settings) {
      if (settings.thron && settings.thron.crop) {
        var element =
          [
            "edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-brightness",
            "edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-contrast",
            "edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-sharpness",
            "edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-color",
            "edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-quality"
          ];
        var cropMode = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-crop-mode"]');
        if (cropMode) {
          $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-crop-mode"]').once('thron').on('change', function () {
            buttonManualType(this.value);
            if (this.value === 'manual') {
              if (player) {
                player.destroy();
              }
              player = playerLoad(
                settings.thron.crop.clientId,
                settings.thron.crop.xcontentId,
                settings.thron.crop.sessId,
                this.value
              );
            } else {
              renderInputValue(settings, this.value);
            }
            var mode = this.value;
            renderInputChange(settings, mode, 'brightness');
            renderInputChange(settings, mode, 'contrast');
            renderInputChange(settings, mode, 'sharpness')
            renderInputChange(settings, mode, 'color')
            renderInputChange(settings, mode, 'quality')
          });
          var cropModeLoad = $(context).find('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-crop-mode"]').once('js_mod');
          if (cropModeLoad.length) {
            var cropValues = $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-player-params"]').val();
            if (cropValues === '') {
              renderInputValue(settings, cropMode.val());
            } else {
              if (player) {
                player.destroy();
              }
              player = playerLoad(
                settings.thron.crop.clientId,
                settings.thron.crop.xcontentId,
                settings.thron.crop.sessId,
                JSON.parse(cropValues)
              );
            }

            buttonManualType(cropModeLoad.val());
          }
          element.forEach(getRangeValue);
          if (cropMode.val()) {
            renderInputChange(settings, cropMode.val(), 'brightness');
            renderInputChange(settings, cropMode.val(), 'contrast');
            renderInputChange(settings, cropMode.val(), 'sharpness')
            renderInputChange(settings, cropMode.val(), 'color')
            renderInputChange(settings, cropMode.val(), 'quality')
          }

          $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-advanced-option-advanced-button-manual"]').once('thron').on('click', function (e) {
            e.preventDefault();
            if ($(this).hasClass('cropMode')) {
              if ($(this).attr('value') === 'Done') {
                getRtieParams(
                  settings.thron.crop.clientId,
                  settings.thron.crop.xcontentId,
                  settings.thron.crop.sessId
                );
              }
              $(this).attr('value', 'Crop');
              $(this).removeClass('cropMode');
              element.forEach(function (item) {
                paramsDisableEnable(item, false)
              });
            } else {
              $(this).attr('value', 'Done');
              $(this).addClass('cropMode');
              element.forEach(function (item) {
                paramsDisableEnable(item, true)
              });
              renderInputValue(settings, 'manual', false);
            }
          });
        }

        setFormFactor();
        $('[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-resizing"]').once('thron').on('change', function () {
          setFormFactor();
        });
        $('input[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-resizing-fixed-width"]').once('thron').change(function () {
          setFormFactor();
        });
        $('input[data-drupal-selector="edit-attributes-data-entity-embed-display-settings-embed-resizing-fixed-height"]').once('thron').change(function () {
          setFormFactor();
        });
      }
    }
  }


}(jQuery, Drupal));
