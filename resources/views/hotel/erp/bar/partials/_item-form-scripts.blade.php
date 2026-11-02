<script>
  (function ($) {
    function packageLabel($select) {
      var label = $select.find('option:selected').data('unitLabel');
      if (!label) {
        label = $.trim($select.find('option:selected').text());
      }
      return label || 'unit';
    }

    function updateReceiveLabels($form) {
      var $recv = $form.find('.js-bar-receive-package').first();
      if (!$recv.length) return;
      var unit = packageLabel($recv);
      $form.find('.js-bar-receive-pieces-label').html('Pieces in 1 ' + unit + ' <span class="required-mark">*</span>');
    }

    function updateSellRowHint($row) {
      updateSellRowHintRow($row);
    }

    function updateSellRowHintRow($row) {
      var unit = packageLabel($row.find('.js-bar-sell-package'));
      $row.find('.js-bar-sell-unit-hint').text('piece in 1 ' + unit);
    }

    function reindexSellOptions($scope) {
      var prefix = $scope.data('sellPrefix');
      if (!prefix) {
        $scope.find('.js-bar-sell-option-row').each(function (index) {
          $(this).find('[name]').each(function () {
            var name = $(this).attr('name');
            if (!name) return;
            $(this).attr('name', name.replace(/selling_options\[\d+]/, 'selling_options[' + index + ']'));
          });
        });
      } else {
        $scope.find('.js-bar-sell-option-row').each(function (sIndex) {
          $(this).find('[data-field]').each(function () {
            var field = $(this).data('field');
            $(this).attr('name', prefix + '[' + sIndex + '][' + field + ']');
          });
        });
      }
      var count = $scope.find('.js-bar-sell-option-row').length;
      $scope.find('.js-bar-remove-sell-option').prop('disabled', count <= 1);
    }

    $(document).on('change', '.js-bar-receive-package', function () {
      updateReceiveLabels($(this).closest('.bar-item-details-form'));
    });

    $(document).on('change', '.js-bar-sell-package', function () {
      updateSellRowHintRow($(this).closest('.js-bar-sell-option-row'));
    });

    $(document).on('click', '.js-bar-add-sell-option', function () {
      var $block = $(this).closest('.bar-item-sell-options');
      var $body = $block.find('.js-bar-sell-options-body');
      var html = $block.find('.js-bar-sell-option-template').html();
      var index = $body.find('.js-bar-sell-option-row').length;
      if ($block.data('sellPrefix')) {
        $body.append($(html));
      } else {
        $body.append(html.replace(/selling_options\[\d+\]/g, 'selling_options[' + index + ']').replace(/__INDEX__/g, String(index)));
      }
      reindexSellOptions($block);
    });

    $(document).on('click', '.js-bar-remove-sell-option', function () {
      var $block = $(this).closest('.bar-item-sell-options');
      $(this).closest('.js-bar-sell-option-row').remove();
      reindexSellOptions($block);
    });

    $(function () {
      $('.bar-item-details-form').each(function () {
        updateReceiveLabels($(this));
        $(this).find('.js-bar-sell-option-row').each(function () {
          updateSellRowHintRow($(this));
        });
      });
    });
  })(jQuery);
</script>
