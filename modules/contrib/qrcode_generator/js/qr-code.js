(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.per_page_qr = {
        attach: function (context, settings) {
            $(document).ready(function () {
                var qrBtn = $("#qr-float-icon");
                var modal = $("#qr-popup");
                // implement hiding button on click and key press esc to close the pop - Pending
                var modalClose = $("#qr-popup .close");
                qrBtn.click(function () {
                    qrBtn.hide();
                    modal.show(250);
                })
                modalClose.click(function () {
                    modal.hide(250);
                    qrBtn.show();
                })
                $('.copyingBtn').click(function () {
                    copyData(pageUrl);
                    $(this).animate({
                        'zoom': 0.9
                     }, 100, "linear", function () {
                        $(this).animate({
                            'zoom': 1
                        }, 100);
                    });
                    $('.copied-success').css('opacity','1');
                    setTimeout(function () {
                        $('.copied-success').css('opacity','0');
                    }, 5000);
                })
                var jsModal = document.querySelector("#qr-popup");
                document.addEventListener('keydown', function (event) {
                    if(event.key === 'Escape') {
                        if(jsModal && getComputedStyle(jsModal).display !== 'none')
                        {
                            if(modalClose) {
                                modalClose.click();
                            }
                        }
                    }
                })
                $( "li.sms-link" ).hover(
                    function () {
                        $(this).parents('.social-media-section').find('.qr-tab-info').fadeIn();
                    }, function () {
                        $(this).parents('.social-media-section').find('.qr-tab-info').fadeOut();
                    }
                );
                $('li.sms-link').on('focusin',function () {
                    $(this).parents('.social-media-section').find('.qr-tab-info').fadeIn();
                })
                $('li.sms-link').on('focusout',function () {
                    $(this).parents('.social-media-section').find('.qr-tab-info').fadeOut();
                })
            })
        }
    };
    function copyData(containerid) {
        var range = document.createRange();
        range.selectNode(containerid); //changed here
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        document.execCommand("copy");
        window.getSelection().removeAllRanges();
    }
})(jQuery, Drupal, drupalSettings);
