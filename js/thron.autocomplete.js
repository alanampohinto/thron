/**
* Copied from "/core/misc/autocomplete.js"
* Edited for Thron TAGS Autocomplete
* 
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function ($, Drupal) {
  var autocomplete = void 0;
  
  function getHelper(element) {
    helperName = element.getAttribute('data-helper-name');
    helper = document.getElementsByName(helperName);
    return helper.length > 0 ? helper[0] : null;
  }
  
  function splitValues(value) {
    var result = [];
    var quote = false;
    var current = '';
    var valueLength = value.length;
    var character = void 0;

    for (var i = 0; i < valueLength; i++) {
      character = value.charAt(i);
      if (character === '"') {
        current += character;
        quote = !quote;
      } else if (character === ',' && !quote) {
        result.push(current.trim());
        current = '';
      } else {
        current += character;
      }
    }
    if (value.length > 0) {
      result.push($.trim(current));
    }

    return result;
  } 
  
  function splitHiddenValues(value) {
    var ret = [];
    try {
      if (value !== '') {
        ret = 'JSON' in window ? JSON.parse(value) : $.parseJSON(value);
      }
    } 
    catch (e) { console.error(e); }
    return ret;
  }

  function extractLastTerm(terms) {
    return autocomplete.splitValues(terms).pop();
  }

  function searchHandler(event) {
    var options = autocomplete.options;

    if (options.isComposing) {
      return false;
    }
    
    var term = autocomplete.extractLastTerm(event.target.value);

    if (term.length > 0 && options.firstCharacterBlacklist.indexOf(term[0]) !== -1) {
      return false;
    }

    return term.length >= options.minLength;
  }

  function sourceData(request, response) {
    var elementId = this.element.attr('id');

    if (!(elementId in autocomplete.cache)) {
      autocomplete.cache[elementId] = {};
    }

    function showSuggestions(suggestions) {
      var tagged = autocomplete.splitValues(request.term);
      var il = tagged.length;
      for (var i = 0; i < il; i++) {
        var index = suggestions.indexOf(tagged[i]);
        if (index >= 0) {
          suggestions.splice(index, 1);
        }
      }
      response(suggestions);
    }

    var term = autocomplete.extractLastTerm(request.term);

    function sourceCallbackHandler(data) {
      autocomplete.cache[elementId][term] = data;

      showSuggestions(data);
    }

    if (autocomplete.cache[elementId].hasOwnProperty(term)) {
      showSuggestions(autocomplete.cache[elementId][term]);
    } else {
      var options = $.extend({ success: sourceCallbackHandler, data: { q: term } }, autocomplete.ajax);
      $.ajax(this.element.attr('data-autocomplete-path'), options);
    }
  }

  function focusHandler() {
    return false;
  }

  function selectHandler(event, ui) {
    helper = autocomplete.getHelper(event.target);
    var terms = autocomplete.splitHiddenValues(helper.value);
    terms.pop();
    terms.push(ui.item.value);
    helper.value = JSON.stringify(terms);
    
    var visibleTerms = autocomplete.splitValues(event.target.value);
    visibleTerms.pop();
    visibleTerms.push(ui.item.value.name);
    event.target.value = visibleTerms.join(', ');

    return false;
  }

  function renderItem(ul, item) {
    return $('<li>').append($('<a>').html(item.label)).appendTo(ul);
  }
  
  
  Drupal.behaviors.autocomplete = {
    attach: function attach(context) {
      var $autocomplete = $(context).find('input.form-autocomplete-thron').once('autocomplete');
      if ($autocomplete.length) {
        var blacklist = $autocomplete.attr('data-autocomplete-first-character-blacklist');
        $.extend(autocomplete.options, {
          firstCharacterBlacklist: blacklist || ''
        });

        $autocomplete.autocomplete(autocomplete.options).each(function () {
          if (this.value !== '') {
            try {
              var parsed = 'JSON' in window ? JSON.parse(this.value) : $.parseJSON(this.value);
              var helper = autocomplete.getHelper(this);
              helper.value = this.value;
              
              var showcase = [];
              for (var i = 0; i < parsed.length; i++) {
                var tag = parsed[i];
                showcase.push(tag.name);
              }
              this.value = showcase.join(', ');
            } 
            catch (e) { }
          }
          
          $(this).data('ui-autocomplete')._renderItem = autocomplete.options.renderItem;
        });

        $autocomplete.on('compositionstart.autocomplete', function () {
          autocomplete.options.isComposing = true;
        });
        $autocomplete.on('compositionend.autocomplete', function () {
          autocomplete.options.isComposing = false;
        });
      }
    },
    detach: function detach(context, settings, trigger) {
      if (trigger === 'unload') {
        $(context).find('input.form-autocomplete-thron').removeOnce('autocomplete').autocomplete('destroy');
      }
    }
  };
  
  autocomplete = {
    cache: {},
    
    getHelper: getHelper,
    splitValues: splitValues,
    splitHiddenValues: splitHiddenValues,
    extractLastTerm: extractLastTerm,

    options: {
      source: sourceData,
      focus: focusHandler,
      search: searchHandler,
      select: selectHandler,
      renderItem: renderItem,
      minLength: 1,

      firstCharacterBlacklist: '',

      isComposing: false
    },
    ajax: {
      dataType: 'json'
    }
  };

  Drupal.autocomplete = autocomplete;
})(jQuery, Drupal);