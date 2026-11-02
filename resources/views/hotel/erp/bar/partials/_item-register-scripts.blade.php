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
      var unit = packageLabel($row.find('.js-bar-sell-package'));
      $row.find('.js-bar-sell-unit-hint').text('piece in 1 ' + unit);
    }

    function reindexSellOptions($scope) {
      var prefix = $scope.data('sellPrefix');
      if (!prefix) return;
      $scope.find('.js-bar-sell-option-row').each(function (sIndex) {
        $(this).find('[data-field]').each(function () {
          var field = $(this).data('field');
          $(this).attr('name', prefix + '[' + sIndex + '][' + field + ']');
        });
      });
      var count = $scope.find('.js-bar-sell-option-row').length;
      $scope.find('.js-bar-remove-sell-option').prop('disabled', count <= 1);
    }

    function reindexRegisterItems() {
      $('.js-bar-register-items .bar-item-register-row').each(function (itemIndex) {
        var $row = $(this);
        $row.attr('data-item-index', itemIndex);
        $row.find('.js-bar-item-row-number').text(itemIndex + 1);

        $row.find('[data-field]').each(function () {
          var field = $(this).data('field');
          $(this).attr('name', 'items[' + itemIndex + '][' + field + ']');
        });

        var $sellBlock = $row.find('.bar-item-sell-options');
        var sellPrefix = 'items[' + itemIndex + '][selling_options]';
        $sellBlock.attr('data-sell-prefix', sellPrefix);
        reindexSellOptions($sellBlock);

        var showRemove = $('.js-bar-register-items .bar-item-register-row').length > 1;
        $row.find('.js-bar-remove-register-item').toggle(showRemove);
      });
    }

    $(document).on('change', '.js-bar-receive-package', function () {
      updateReceiveLabels($(this).closest('.bar-item-details-form'));
    });

    $(document).on('change', '.js-bar-sell-package', function () {
      updateSellRowHint($(this).closest('.js-bar-sell-option-row'));
    });

    $(document).on('click', '.js-bar-add-sell-option', function () {
      var $block = $(this).closest('.bar-item-sell-options');
      var $body = $block.find('.js-bar-sell-options-body');
      var $row = $($block.find('.js-bar-sell-option-template').html());
      $body.append($row);
      reindexSellOptions($block);
    });

    $(document).on('click', '.js-bar-remove-sell-option', function () {
      var $block = $(this).closest('.bar-item-sell-options');
      $(this).closest('.js-bar-sell-option-row').remove();
      reindexSellOptions($block);
    });

    $(document).on('click', '.js-bar-add-register-item', function () {
      var tpl = document.getElementById('barItemRegisterRowTemplate');
      if (!tpl || !tpl.content) return;
      var index = $('.js-bar-register-items .bar-item-register-row').length;
      var html = tpl.innerHTML.replace(/__INDEX__/g, String(index));
      $('.js-bar-register-items').append(html);
      reindexRegisterItems();
      var $newRow = $('.js-bar-register-items .bar-item-register-row').last();
      updateReceiveLabels($newRow);
      $newRow.find('.js-bar-sell-option-row').each(function () {
        updateSellRowHint($(this));
      });
    });

    $(document).on('click', '.js-bar-remove-register-item', function () {
      $(this).closest('.bar-item-register-row').remove();
      reindexRegisterItems();
    });

    $(function () {
      reindexRegisterItems();
      $('.bar-item-details-form').each(function () {
        updateReceiveLabels($(this));
        $(this).find('.js-bar-sell-option-row').each(function () {
          updateSellRowHint($(this));
        });
      });
    });
  })(jQuery);
</script>
