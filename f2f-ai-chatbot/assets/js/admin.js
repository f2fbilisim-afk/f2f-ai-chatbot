(function ($) {
  'use strict';
  $(function () {
    if ($.fn.wpColorPicker) {
      $('.f2f-color-picker').wpColorPicker();
    }

    var frame;
    $('#f2f_avatar_upload').on('click', function (e) {
      e.preventDefault();
      if (frame) {
        frame.open();
        return;
      }
      frame = wp.media({
        title: 'Profil fotoğrafı seç',
        button: { text: 'Kullan' },
        multiple: false,
      });
      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        $('#f2f_avatar_id').val(att.id);
        $('#f2f_avatar_preview').attr(
          'src',
          att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url
        );
        $('#f2f_avatar_url').val('');
      });
      frame.open();
    });

    $('#f2f_avatar_clear').on('click', function (e) {
      e.preventDefault();
      $('#f2f_avatar_id').val('0');
      $('#f2f_avatar_url').val('');
    });

    $('#f2f_reindex_btn').on('click', function (e) {
      e.preventDefault();
      if (!window.f2fAiAdmin) return;
      var $btn = $(this);
      var $status = $('#f2f_reindex_status');
      $btn.prop('disabled', true);
      $status.text(f2fAiAdmin.i18n.scanning);
      $.post(f2fAiAdmin.ajaxUrl, {
        action: 'f2f_ai_reindex_site',
        nonce: f2fAiAdmin.nonce,
      })
        .done(function (res) {
          if (res && res.success && res.data) {
            $status.text(
              f2fAiAdmin.i18n.done +
                ' (' +
                res.data.count +
                ' içerik — ' +
                res.data.updated +
                ')'
            );
          } else {
            $status.text(f2fAiAdmin.i18n.fail);
          }
        })
        .fail(function () {
          $status.text(f2fAiAdmin.i18n.fail);
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });
  });
})(jQuery);
