(function ($) {
  'use strict';

  function applyQuota(data) {
    if (!data) return;
    var $card = $('#f2f_quota_card');
    if (!$card.length) return;

    var left = data.messages_left;
    var limit = data.messages_limit;
    var used = data.messages_used;
    var pct = data.percent_used || 0;
    var can = !!data.can_chat;
    var status = data.status || 'missing';

    $card
      .removeClass('is-ok is-warn is-bad')
      .addClass(can ? 'is-ok' : status === 'exhausted' ? 'is-warn' : 'is-bad')
      .attr('data-status', status);

    if (left !== null && left !== undefined && limit !== null && limit !== undefined) {
      $('#f2f_quota_left').text(left);
      $('#f2f_quota_slash').prop('hidden', false);
      $('#f2f_quota_limit').prop('hidden', false).text(limit);
      $('#f2f_quota_unit').text('konuşma kaldı');
      $('#f2f_quota_bar_wrap').prop('hidden', false);
      $('#f2f_quota_bar').css('width', pct + '%');
    } else {
      $('#f2f_quota_left').text('—');
      $('#f2f_quota_slash').prop('hidden', true);
      $('#f2f_quota_limit').prop('hidden', true);
      $('#f2f_quota_unit').text('lisans girilmedi');
      $('#f2f_quota_bar_wrap').prop('hidden', true);
    }

    var bits = [];
    if (data.plan_label) bits.push(data.plan_label);
    if (used !== null && used !== undefined && limit !== null && limit !== undefined) {
      bits.push('Kullanılan: ' + used + ' / ' + limit);
    }
    if (data.days_left !== null && data.days_left !== undefined && data.premium) {
      bits.push(data.days_left + ' gün kaldı');
    }
    $('#f2f_quota_meta').text(bits.length ? bits.join(' · ') : data.message || '');
    $('#f2f_quota_hint').text(data.message || '');
    if (data.updated_at) {
      $('#f2f_quota_updated').text('Son güncelleme: ' + data.updated_at);
    }
  }

  function refreshQuota($btn) {
    if (!window.f2fAiAdmin) return;
    var $statusHint = $('#f2f_quota_updated');
    if ($btn) $btn.prop('disabled', true);
    $statusHint.text(f2fAiAdmin.i18n.refreshing);
    $.post(f2fAiAdmin.ajaxUrl, {
      action: 'f2f_ai_license_quota',
      nonce: f2fAiAdmin.nonce,
    })
      .done(function (res) {
        if (res && res.success && res.data) {
          applyQuota(res.data);
        } else if ($statusHint.length) {
          $statusHint.text(f2fAiAdmin.i18n.refreshFail);
        }
      })
      .fail(function () {
        $statusHint.text(f2fAiAdmin.i18n.refreshFail);
      })
      .always(function () {
        if ($btn) $btn.prop('disabled', false);
      });
  }

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

    $('#f2f_quota_refresh').on('click', function (e) {
      e.preventDefault();
      refreshQuota($(this));
    });

    if ($('#f2f_quota_card').length) {
      setInterval(function () {
        refreshQuota(null);
      }, 30000);
    }
  });
})(jQuery);
