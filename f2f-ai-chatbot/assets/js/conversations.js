(function ($) {
  'use strict';

  function toast(msg, isError) {
    var $t = $('#f2f_conv_toast');
    if (!$t.length) return;
    $t.prop('hidden', !msg)
      .toggleClass('is-error', !!isError)
      .text(msg || '');
    if (msg) {
      clearTimeout(toast._timer);
      toast._timer = setTimeout(function () {
        $t.prop('hidden', true);
      }, 1800);
    }
  }

  function applyFilters() {
    var filter = $('.f2f-conv__chip.is-active').data('filter') || 'all';
    var q = String($('#f2f_conv_search').val() || '')
      .toLowerCase()
      .trim();
    var visible = 0;
    $('.f2f-lead-card').each(function () {
      var $card = $(this);
      var status = String($card.data('status') || '');
      var hay = String($card.data('search') || '');
      var okStatus = filter === 'all' || status === filter;
      var okSearch = !q || hay.indexOf(q) !== -1;
      var show = okStatus && okSearch;
      $card.toggleClass('is-hidden', !show);
      if (show) visible += 1;
    });
    $('#f2f_conv_filter_empty').prop('hidden', visible > 0);
  }

  $(function () {
    $('.f2f-conv__chip').on('click', function () {
      $('.f2f-conv__chip').removeClass('is-active');
      $(this).addClass('is-active');
      applyFilters();
    });

    $('#f2f_conv_search').on('input', applyFilters);

    $('.f2f-conv__status').on('change', function () {
      var $el = $(this);
      var $card = $el.closest('.f2f-lead-card');
      if (!window.f2fAiConv) return;
      var status = $el.val();
      $.post(f2fAiConv.ajaxUrl, {
        action: 'f2f_ai_update_lead_status',
        nonce: f2fAiConv.nonce,
        id: $el.data('id'),
        status: status,
      })
        .done(function (res) {
          if (res && res.success) {
            $card.attr('data-status', status);
            var label = $el.find('option:selected').text();
            $card
              .find('[data-badge]')
              .attr('class', 'f2f-lead-card__badge f2f-lead-card__badge--' + status)
              .text(label);
            toast(f2fAiConv.i18n.saved, false);
            applyFilters();
          } else {
            toast(f2fAiConv.i18n.fail, true);
          }
        })
        .fail(function () {
          toast(f2fAiConv.i18n.fail, true);
        });
    });
  });
})(jQuery);
