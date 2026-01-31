(function (Drupal, $) {
  'use strict';

  Drupal.behaviors.constructionRentalProductSelection = {
    attach: function (context, settings) {
      var $form = $('form#construction-rental-product-selection', context);
      
      if ($form.length === 0) {
        return;
      }

  var $searchInput = $('#product-search-input', context);
  var $addButton = $('.add-product-button', context);

      // Handle Enter key in autocomplete field.
      $searchInput.on('keydown', function (e) {
        if (e.keyCode === 13) { // Enter key
          e.preventDefault();
          $addButton.trigger('mousedown');
        }
      });

      // Enable add button when autocomplete value is selected.
      $searchInput.on('autocompleteclose', function () {
        var value = $(this).val();
        if (value && value.indexOf('[ID:') !== -1) {
          $addButton.prop('disabled', false);
        }
      });

      // When rental days change, update rental end date accordingly.
      $(document).on('change', '.rental-days', function () {
        var $row = $(this).closest('.selected-product-item');
        var days = parseInt($(this).val(), 10) || 0;
        var $start = $row.find('.rental-start-date input');
        var $end = $row.find('.rental-end-date input');

        if ($start.length && $end.length && days > 0) {
          var startDate = new Date($start.val());
          if (!isNaN(startDate.getTime())) {
            var newEnd = new Date(startDate);
            newEnd.setDate(newEnd.getDate() + days);
            // Format as ISO without timezone offset to match Drupal widget.
            var iso = newEnd.toISOString().slice(0, 19);
            $end.val(iso);
          }
        }
      });

      // When end date changes, update rental days.
      $(document).on('change', '.rental-end-date input', function () {
        var $row = $(this).closest('.selected-product-item');
        var $start = $row.find('.rental-start-date input');
        var $days = $row.find('.rental-days');

        if ($start.length && $days.length) {
          var startDate = new Date($start.val());
          var endDate = new Date($(this).val());
          if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
            var diff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
            if (diff < 1) {
              diff = 1;
            }
            $days.val(diff);
          }
        }
      });
    }
  };

})(Drupal, jQuery);

