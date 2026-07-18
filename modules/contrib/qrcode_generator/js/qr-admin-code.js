(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.admin_js = {
        attach: function (context, settings) {
            // Please contribute if any features can be enhanced.
            var icon = $('.qr_preview .admin-qr');
            icon.css({'background':icon.attr('data-bg'), 'color':icon.attr('data-color')});
            $('select[name="floating_icon_type"]').on("change", function (e) {
                $('.qr_preview').removeClass('sidebarhover floatingonscreen blockasitis').addClass($(this).val());
            })
            $('select[name="floating_icon"]').on("change", function (e) {
                $('.qr_preview').removeClass('lefttop leftbottom righttop rightcenter leftcenter rightbottom').addClass($(this).val());
            })
            $('input[name="floating_icon_bg"]').on("input", function (e) {
                $('.qr_preview i').attr('data-bg',$(this).val());
                icon.css({'background':icon.attr('data-bg'), 'color':icon.attr('data-color')});
            })
            $('input[name="floating_icon_color"]').on("input", function (e) {
                $('.qr_preview i').attr('data-color',$(this).val());
                icon.css({'background':icon.attr('data-bg'), 'color':icon.attr('data-color')});
            })
            // qrEditOptions();
            $('.add_page_qr_configs .qr_icon_type').on('change', function () {
                ADDQROptions();
            })
            $('.edit_page_qr_configs .qr_icon_type').on('change', function () {
                EditQROptions();
            })
        }
    };
    function ADDQROptions() {
        if($('.add_page_qr_configs .qr_icon_type').val() == 'floatingonscreen') {
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-position').hide();
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-face-color').show();
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-bg-color').show();
        } else if($('.add_page_qr_configs .qr_icon_type').val() == 'blockasitis') {
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-position').hide();
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-face-color').hide();
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-bg-color').hide();
        } else if($('.add_page_qr_configs .qr_icon_type').val() == 'sidebarhover') {
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-position').show();
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-face-color').show();
            $('.add_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-bg-color').show();
        }
    }
    function EditQROptions() {
        if($('.edit_page_qr_configs .qr_icon_type').val() == 'floatingonscreen') {
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-position').hide();
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-face-color').show();
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-bg-color').show();
        } else if($('.edit_page_qr_configs .qr_icon_type').val() == 'blockasitis') {
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-position').hide();
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-face-color').hide();
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-bg-color').hide();
        } else if($('.edit_page_qr_configs .qr_icon_type').val() == 'sidebarhover') {
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-position').show();
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-face-color').show();
            $('.edit_page_qr_configs .js-form-item-qr-code-fieldset-0-icon-bg-color').show();
        }
    }
})(jQuery, Drupal, drupalSettings);
