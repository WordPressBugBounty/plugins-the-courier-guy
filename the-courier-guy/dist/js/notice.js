jQuery(function ($) {
  $(document).on('click', '.notice-the-courier-guy .notice-dismiss', function () {
    var type = $(this).closest('.notice-the-courier-guy').data('notice')
    $.ajax(ajaxurl, {
      type: 'POST',
      data: {
        action: 'dismissed_notice_handler',
        type: type,
      }
    })
  })
})
