(function ($) {
  'use strict';
  $(function () {
    $('.f2f-conv__status').on('change', function () {
      var $el = $(this);
      if (!window.f2fAiConv) return;
      $.post(f2fAiConv.ajaxUrl, {
        action: 'f2f_ai_update_lead_status',
        nonce: f2fAiConv.nonce,
        id: $el.data('id'),
        status: $el.val(),
      });
    });
  });
})(jQuery);
