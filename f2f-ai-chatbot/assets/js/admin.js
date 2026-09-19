(function ($) {
  'use strict';

  var countdownTimer = null;
  var expiresAtSec = 0;
  var skewMs = 0;
  var currentStep = 1;

  function pad2(n) {
    n = Math.max(0, parseInt(n, 10) || 0);
    return n < 10 ? '0' + n : String(n);
  }

  function renderCountdown() {
    if (!expiresAtSec) {
      $('#f2f_quota_time').prop('hidden', true);
      return;
    }
    var nowSec = Math.floor((Date.now() - skewMs) / 1000);
    var left = Math.max(0, expiresAtSec - nowSec);
    var days = Math.floor(left / 86400);
    var hours = Math.floor((left % 86400) / 3600);
    var mins = Math.floor((left % 3600) / 60);
    var secs = left % 60;
    $('#f2f_cd_days').text(String(days));
    $('#f2f_cd_hours').text(pad2(hours));
    $('#f2f_cd_mins').text(pad2(mins));
    $('#f2f_cd_secs').text(pad2(secs));
    $('#f2f_quota_time').prop('hidden', false);
  }

  function startCountdown(expiresAt, serverNow) {
    expiresAtSec = expiresAt ? parseInt(expiresAt, 10) || 0 : 0;
    if (serverNow) {
      skewMs = Date.now() - parseInt(serverNow, 10) * 1000;
    }
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
    renderCountdown();
    if (expiresAtSec) {
      countdownTimer = setInterval(renderCountdown, 1000);
    }
  }

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
    $('#f2f_quota_meta').text(bits.length ? bits.join(' · ') : data.message || '');
    $('#f2f_quota_hint').text(data.message || '');
    if (data.updated_at) {
      $('#f2f_quota_updated').text('Son güncelleme: ' + data.updated_at);
    }

    if (data.premium && data.expires_at) {
      if (data.activated_label) {
        $('#f2f_activated_label').prop('hidden', false).text('Başlangıç: ' + data.activated_label);
      } else {
        $('#f2f_activated_label').prop('hidden', true).text('');
      }
      $('#f2f_expires_label').text(data.expires_at_label ? 'Bitiş: ' + data.expires_at_label : '');
      $('#f2f_time_bar').css('width', (data.time_percent_used || 0) + '%');
      $card.attr('data-expires-at', String(data.expires_at));
      startCountdown(data.expires_at, data.server_now);
    } else {
      $('#f2f_quota_time').prop('hidden', true);
      startCountdown(0, data.server_now);
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
        if (res && res.success && res.data) applyQuota(res.data);
        else $statusHint.text(f2fAiAdmin.i18n.refreshFail);
      })
      .fail(function () {
        $statusHint.text(f2fAiAdmin.i18n.refreshFail);
      })
      .always(function () {
        if ($btn) $btn.prop('disabled', false);
      });
  }

  function setStatus(msg, isError) {
    var $el = $('#f2f_wizard_status');
    if (!msg) {
      $el.prop('hidden', true).text('');
      return;
    }
    $el.prop('hidden', false).toggleClass('is-error', !!isError).text(msg);
  }

  function goStep(step) {
    step = Math.max(1, Math.min(5, parseInt(step, 10) || 1));
    currentStep = step;
    $('.f2f-panel').removeClass('is-active');
    $('.f2f-panel[data-panel="' + step + '"]').addClass('is-active');
    $('.f2f-steps__item').removeClass('is-active');
    $('.f2f-steps__item').each(function () {
      var n = parseInt($(this).data('step'), 10);
      $(this).toggleClass('is-active', n === step);
      $(this).toggleClass('is-done', n < step);
    });
  }

  function collectStep(step) {
    var data = {};
    if (step === 1) {
      data.license_key = $('#wiz_license_key').val() || '';
      data.enabled = $('#wiz_enabled').is(':checked') ? '1' : '0';
    } else if (step === 2) {
      data.bot_name = $('#wiz_bot_name').val() || '';
      data.launcher_label = $('#wiz_launcher_label').val() || '';
      data.primary_color = $('#wiz_primary_color').val() || '#22C55E';
      data.position = $('input[name="wiz_position"]:checked').val() || 'right';
      data.show_teaser = $('#wiz_show_teaser').is(':checked') ? '1' : '0';
      data.teaser_title = $('#wiz_teaser_title').val() || '';
      data.teaser_message = $('#wiz_teaser_message').val() || '';
      data.avatar_id = $('#f2f_avatar_id').val() || '0';
      data.avatar_url = $('#wiz_avatar_url').val() || '';
    } else if (step === 3) {
      data.discover_headline = $('#wiz_discover_headline').val() || '';
      data.discover_subtext = $('#wiz_discover_subtext').val() || '';
      data.featured_label = $('#wiz_featured_label').val() || '';
      data.featured_subtitle = $('#wiz_featured_subtitle').val() || '';
      data.service_1_label = $('#wiz_service_1_label').val() || '';
      data.service_2_label = $('#wiz_service_2_label').val() || '';
      data.service_3_label = $('#wiz_service_3_label').val() || '';
      data.service_4_label = $('#wiz_service_4_label').val() || '';
    } else if (step === 4) {
      data.lead_headline = $('#wiz_lead_headline').val() || '';
      data.lead_subtext = $('#wiz_lead_subtext').val() || '';
      data.lead_btn = $('#wiz_lead_btn').val() || '';
      data.lead_cancel = $('#wiz_lead_cancel').val() || '';
      data.chat_welcome = $('#wiz_chat_welcome').val() || '';
    } else if (step === 5) {
      data.whatsapp_phone = $('#wiz_whatsapp_phone').val() || '';
      data.whatsapp_btn_label = $('#wiz_whatsapp_btn_label').val() || '';
      data.whatsapp_message = $('#wiz_whatsapp_message').val() || '';
      data.business_notes = $('#wiz_business_notes').val() || '';
      data.auto_reindex = $('#wiz_auto_reindex').is(':checked') ? '1' : '0';
    }
    return data;
  }

  function saveStep(step, thenGo) {
    if (!window.f2fAiAdmin) return;
    var payload = collectStep(step);
    if (step === 1 && !String(payload.license_key || '').trim()) {
      setStatus(f2fAiAdmin.i18n.needLicense, true);
      return;
    }
    setStatus(f2fAiAdmin.i18n.saving, false);
    $.post(f2fAiAdmin.ajaxUrl, {
      action: 'f2f_ai_wizard_save',
      nonce: f2fAiAdmin.nonce,
      step: thenGo || step,
      settings: payload,
    })
      .done(function (res) {
        if (!res || !res.success) {
          setStatus(f2fAiAdmin.i18n.saveFail, true);
          return;
        }
        if (res.data && res.data.quota) applyQuota(res.data.quota);
        setStatus(f2fAiAdmin.i18n.saved, false);
        if (thenGo) goStep(thenGo);
        setTimeout(function () {
          setStatus('', false);
        }, 1200);
      })
      .fail(function () {
        setStatus(f2fAiAdmin.i18n.saveFail, true);
      });
  }

  function finishWizard() {
    if (!window.f2fAiAdmin) return;
    var payload = collectStep(5);
    setStatus(f2fAiAdmin.i18n.saving, false);
    $.post(f2fAiAdmin.ajaxUrl, {
      action: 'f2f_ai_wizard_save',
      nonce: f2fAiAdmin.nonce,
      step: 5,
      settings: payload,
    })
      .done(function (saveRes) {
        if (saveRes && saveRes.success && saveRes.data && saveRes.data.quota) {
          applyQuota(saveRes.data.quota);
        }
        return $.post(f2fAiAdmin.ajaxUrl, {
          action: 'f2f_ai_wizard_finish',
          nonce: f2fAiAdmin.nonce,
        });
      })
      .done(function (res) {
        if (res && res.success) {
          $('#f2f_done_box').prop('hidden', false);
          $('#f2f_admin_root').attr('data-setup-done', '1');
          setStatus('Kurulum tamam! Siteyi açıp widget’ı deneyin.', false);
          if (res.data && res.data.quota) applyQuota(res.data.quota);
        } else {
          setStatus(f2fAiAdmin.i18n.saveFail, true);
        }
      })
      .fail(function () {
        setStatus(f2fAiAdmin.i18n.saveFail, true);
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
        $('#wiz_avatar_url').val('');
      });
      frame.open();
    });

    $('#f2f_avatar_clear').on('click', function (e) {
      e.preventDefault();
      $('#f2f_avatar_id').val('0');
      $('#wiz_avatar_url').val('');
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
            $status.text(f2fAiAdmin.i18n.done + ' (' + res.data.count + ' içerik)');
            $('#f2f_kb_summary').text(
              'Son tarama: ' + res.data.count + ' içerik — ' + (res.data.updated || 'şimdi')
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

    $('.f2f-steps__item').on('click', function () {
      goStep($(this).data('step'));
    });

    $('.f2f-next').on('click', function () {
      var next = parseInt($(this).data('next'), 10);
      saveStep(currentStep, next);
    });

    $('.f2f-prev').on('click', function () {
      goStep($(this).data('prev'));
    });

    $('#f2f_finish_wizard').on('click', function (e) {
      e.preventDefault();
      finishWizard();
    });

    $('#f2f_restart_wizard').on('click', function (e) {
      e.preventDefault();
      $('#f2f_done_box').prop('hidden', true);
      goStep(1);
      setStatus('Sihirbaz başa alındı. Adım adım ilerleyin.', false);
    });

    var $card = $('#f2f_quota_card');
    if ($card.length) {
      startCountdown($card.attr('data-expires-at'), $card.attr('data-server-now'));
      setInterval(function () {
        refreshQuota(null);
      }, 30000);
    }

    var start =
      ($('#f2f_admin_root').data('start-step') ||
        (window.f2fAiAdmin && f2fAiAdmin.startStep) ||
        1);
    goStep(start);
  });
})(jQuery);
