(function ($) {
  'use strict';

  window.AppAlerts = {
    success: function (title, text) {
      swal(title || 'Success', text || '', 'success');
    },
    error: function (title, text) {
      swal(title || 'Error', text || '', 'error');
    },
    confirm: function (options, onConfirm) {
      swal({
        title: options.title || 'Are you sure?',
        text: options.text || '',
        type: options.type || 'warning',
        showCancelButton: true,
        confirmButtonText: options.confirmText || 'Yes',
        cancelButtonText: options.cancelText || 'Cancel',
        closeOnConfirm: true,
        closeOnCancel: true,
      }, function (isConfirm) {
        if (isConfirm && typeof onConfirm === 'function') {
          onConfirm();
        }
      });
    },
  };

  $(function () {
    $('.js-swal-delete').on('submit', function (event) {
      event.preventDefault();
      var $form = $(this);
      var $btn = $form.find('[type=submit]');

      AppAlerts.confirm({
        title: $btn.data('title') || 'Delete?',
        text: $btn.data('text') || 'This action cannot be undone.',
        confirmText: $btn.data('confirm') || 'Yes, delete it',
        cancelText: $btn.data('cancel') || 'Cancel',
      }, function () {
        $form.off('submit').submit();
      });
    });

    $('.js-swal-confirm').on('submit', function (event) {
      event.preventDefault();
      var $form = $(this);
      var $btn = $form.find('[type=submit]');

      AppAlerts.confirm({
        title: $btn.data('title') || 'Are you sure?',
        text: $btn.data('text') || '',
        type: $btn.data('type') || 'warning',
        confirmText: $btn.data('confirm') || 'Yes',
        cancelText: $btn.data('cancel') || 'Cancel',
      }, function () {
        $form.off('submit').submit();
      });
    });
  });
})(jQuery);
