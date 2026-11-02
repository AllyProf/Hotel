(function ($) {
  'use strict';

  var toastIconMap = {
    success: 'success',
    error: 'error',
    warning: 'warning',
    info: 'info',
  };

  /** SweetAlert2 toast (top-end). SW v1 `swal` stays for modal confirm/delete. */
  function showToast(type, title, message, options) {
    options = options || {};

    if (typeof Swal === 'undefined') {
      if (typeof swal === 'function') {
        swal(title || 'Notice', message || title || '', type === 'error' ? 'error' : 'success');
      }
      return { dismiss: function () {} };
    }

    var duration = typeof options.duration === 'number' ? options.duration : 4200;
    var icon = toastIconMap[type] || 'info';
    var toastTitle = '';

    if (title && message) {
      toastTitle = title + ' — ' + message;
    } else {
      toastTitle = message || title || '';
    }

    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: icon,
      title: toastTitle,
      showConfirmButton: false,
      timer: duration,
      timerProgressBar: true,
      showCloseButton: true,
      closeButtonHtml: '&times;',
      customClass: {
        popup: 'app-swal-toast',
        title: 'app-swal-toast__title',
        timerProgressBar: 'app-swal-toast__timer',
        closeButton: 'app-swal-toast__close',
      },
      didOpen: function (toast) {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
      },
    });

    return {
      dismiss: function () {
        Swal.close();
      },
    };
  }

  window.AppAlerts = {
    /** SweetAlert v1 modal — blocking success/error. */
    success: function (title, text) {
      swal(title || 'Success', text || '', 'success');
    },
    error: function (title, text) {
      swal(title || 'Error', text || '', 'error');
    },
    confirm: function (options, onConfirm) {
      var title = options.title || 'Are you sure?';
      var text = options.text || '';
      var confirmText = options.confirmText || 'Yes';
      var cancelText = options.cancelText || 'Cancel';
      var swalType = options.type || 'warning';
      var swal2Icon = swalType === 'error' ? 'error' : (swalType === 'success' ? 'success' : (swalType === 'info' ? 'info' : 'warning'));

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: title,
          text: text,
          icon: swal2Icon,
          showCancelButton: true,
          confirmButtonText: confirmText,
          cancelButtonText: cancelText,
          focusCancel: true,
        }).then(function (result) {
          if (result.isConfirmed && typeof onConfirm === 'function') {
            onConfirm();
          }
        });
        return;
      }

      if (typeof swal !== 'function') {
        if (window.confirm(title + (text ? '\n\n' + text : '')) && typeof onConfirm === 'function') {
          onConfirm();
        }
        return;
      }

      swal({
        title: title,
        text: text,
        type: swalType,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        closeOnConfirm: true,
        closeOnCancel: true,
      }, function (isConfirm) {
        if (isConfirm !== true || typeof onConfirm !== 'function') {
          return;
        }
        onConfirm();
      });
    },

    /** SweetAlert2 sleek toasts — saves, updates, session flash. */
    toast: {
      show: showToast,
      success: function (title, message, options) {
        return showToast('success', title, message, options);
      },
      error: function (title, message, options) {
        return showToast('error', title, message, options);
      },
      warning: function (title, message, options) {
        return showToast('warning', title, message, options);
      },
      info: function (title, message, options) {
        return showToast('info', title, message, options);
      },
    },
  };

  function btnData($btn, key) {
    var value = $btn.attr('data-' + key);
    return value === undefined ? '' : value;
  }

  function resolveSubmitButton($form, nativeEvent) {
    var submitter = nativeEvent && nativeEvent.submitter;

    if (submitter && $form[0] && $form[0].contains(submitter)) {
      return $(submitter).closest('button, input[type="submit"]');
    }

    return $form.find('button[type=submit], button.js-swal-confirm-btn, input[type=submit]').first();
  }

  function submitFormAfterConfirm($form) {
    var el = $form[0];

    if (!el) {
      return;
    }

    el.setAttribute('data-swal-confirmed', '1');

    if (typeof el.requestSubmit === 'function') {
      el.requestSubmit();
      return;
    }

    $form.off('submit');
    el.submit();
  }

  function confirmTextForButton($btn, $form) {
    var text = btnData($btn, 'text') || '';
    var name = $.trim(
      $form.find('[name="name"]').val()
      || $form.find('[name="package_name"]').val()
      || ''
    );

    if (name && text.indexOf(':name') !== -1) {
      text = text.replace(':name', name);
    }

    return text;
  }

  function openSwalConfirmForForm($form, $btn, defaults) {
    if ($form.data('swal-open')) {
      return;
    }

    if ($form[0] && typeof $form[0].checkValidity === 'function' && !$form[0].checkValidity()) {
      $form[0].reportValidity();
      return;
    }

    $form.data('swal-open', true);
    $form.data('swal-trigger-btn', $btn);

    AppAlerts.confirm({
      title: btnData($btn, 'title') || defaults.title,
      text: confirmTextForButton($btn, $form) || defaults.text,
      type: btnData($btn, 'type') || defaults.type || 'warning',
      confirmText: btnData($btn, 'confirm') || defaults.confirmText,
      cancelText: btnData($btn, 'cancel') || defaults.cancelText,
    }, function () {
      $form.removeData('swal-trigger-btn');
      submitFormAfterConfirm($form);
    });

    setTimeout(function () {
      $form.removeData('swal-open');
    }, 400);
  }

  function bindSwalForm(formSelector, defaults) {
    $(document).on('submit', formSelector, function (event) {
      var $form = $(this);

      if ($form.attr('data-swal-confirmed') === '1') {
        $form.removeAttr('data-swal-confirmed');
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();

      var $btn = resolveSubmitButton($form, event.originalEvent);
      if (!$btn.length) {
        $btn = $form.data('swal-trigger-btn') || $form.find('button[data-title], input[type="submit"][data-title]').first();
      }

      openSwalConfirmForForm($form, $btn, defaults);
    });
  }

  function bindSwalButtonClick(formSelector, defaults) {
    $(document).on('click', formSelector + ' button[data-title], ' + formSelector + ' input[type="submit"][data-title]', function (event) {
      var $btn = $(this);
      if ($btn.attr('type') === 'submit') {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      var $form = $btn.closest('form');
      if (!$form.length) {
        return;
      }

      openSwalConfirmForForm($form, $btn, defaults);
    });
  }

  $(function () {
    bindSwalForm('form.js-swal-delete', {
      title: 'Delete?',
      text: 'This action cannot be undone.',
      confirmText: 'Yes, delete it',
      cancelText: 'Cancel',
    });

    bindSwalForm('form.js-swal-add', {
      title: 'Add?',
      text: 'Add this item?',
      confirmText: 'Yes, add it',
      cancelText: 'Cancel',
    });

    bindSwalForm('form.js-swal-confirm', {
      title: 'Are you sure?',
      text: '',
      confirmText: 'Yes',
      cancelText: 'Cancel',
    });

    bindSwalButtonClick('form.js-swal-delete', {
      title: 'Delete?',
      text: 'This action cannot be undone.',
      confirmText: 'Yes, delete it',
      cancelText: 'Cancel',
    });
    bindSwalButtonClick('form.js-swal-add', {
      title: 'Add?',
      text: 'Add this item?',
      confirmText: 'Yes, add it',
      cancelText: 'Cancel',
    });
    bindSwalButtonClick('form.js-swal-confirm', {
      title: 'Are you sure?',
      text: '',
      confirmText: 'Yes',
      cancelText: 'Cancel',
    });
  });
})(jQuery);

window.cghAdminShowLoader = window.cghAdminShowLoader || function () {};
window.cghAdminHideLoader = window.cghAdminHideLoader || function () {};
