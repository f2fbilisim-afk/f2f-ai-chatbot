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
        $('#f2f_avatar_preview').attr('src', att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
        $('#f2f_avatar_url').val('');
      });
      frame.open();
    });

    $('#f2f_avatar_clear').on('click', function (e) {
      e.preventDefault();
      $('#f2f_avatar_id').val('0');
      $('#f2f_avatar_url').val('');
      $('#f2f_avatar_preview').attr('src', $('#f2f_avatar_preview').data('default') || $('#f2f_avatar_preview').attr('src'));
    });
  });
})(jQuery);
