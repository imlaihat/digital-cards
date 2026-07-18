(function ($) {
    "use strict";

/*=============================================
    =             Preloader                  =
=============================================*/
function preloader() {
    $('.preloader').delay(700).fadeOut();
};

$(window).on('load', function () {
    preloader();
});

/*===========================================
    =            Mobile Menu                  =
=============================================*/
//SubMenu Dropdown Toggle
if ($('.aptn-__wrap li.menu-item-has-children ul').length) {
    $('.aptn-__wrap .navigation li.menu-item-has-children').append('<div class="dropdown-btn"><span class="plus-line"></span></div>');
}

//Mobile Nav Hide Show
if ($('.aptn-mobile__menu').length) {

    var mobileMenuContent = $('.aptn-__wrap .aptn-__main-menu').html();
    $('.aptn-mobile__menu .aptn-mobile__menu-box .aptn-mobile__menu-outer').append(mobileMenuContent);

    //Dropdown Button
    $('.aptn-mobile__menu li.menu-item-has-children .dropdown-btn').on('click', function () {
        $(this).toggleClass('open');
        $(this).prev('ul').slideToggle(300);
    });
    //Menu Toggle Btn
    $('.mobile-nav-toggler').on('click', function () {
        $('body').addClass('mobile-menu-visible');
    });

    //Menu Toggle Btn
    $('.aptn-mobile__menu-backdrop, .aptn-mobile__menu .close-btn').on('click', function () {
        $('body').removeClass('mobile-menu-visible');
    });
};

/*===========================================
    =     Menu sticky & Scroll to top      =
=============================================*/
$(window).on('scroll', function () {
    var scroll = $(window).scrollTop();
    if (scroll < 245) {
        $("#sticky-header").removeClass("sticky-menu");
        $('.scroll-to-target').removeClass('open');
        $("#header-fixed-height").removeClass("active-height");

    } else {
        $("#sticky-header").addClass("sticky-menu");
        $('.scroll-to-target').addClass('open');
        $("#header-fixed-height").addClass("active-height");
    }
});

/*===========================================
       =         Sticky Menu     =
=============================================*/
function stickyHeader() {

    var $window = $(window);
    var lastScrollTop = 0;
    var $headerID = $('#sticky-header');
    var headerHeight = $headerID.outerHeight() + 30;

    $window.scroll(function () {
        var windowTop = $window.scrollTop();

        if (windowTop >= headerHeight) {
            $headerID.addClass('aptn-sticky-menu');
        } else {
            $headerID.removeClass('aptn-sticky-menu');
            $headerID.removeClass('sticky-menu__show');
        }

        if ($headerID.hasClass('aptn-sticky-menu')) {
            if (windowTop < lastScrollTop) {
                $headerID.addClass('sticky-menu__show');
            } else {
                $headerID.removeClass('sticky-menu__show');
            }
        }

        lastScrollTop = windowTop;
    });
};
stickyHeader();

/*=============================================
    =            Header Search            =
=============================================*/
$(".search-open-btn").on("click", function () {
    $(".search__popup").addClass("search-opened");
    $(".search-popup-overlay").addClass("search-popup-overlay-open");
});
$(".search-popup-overlay, .search-close-btn").on("click", function () {
    $(".search__popup").removeClass("search-opened");
    $(".search-popup-overlay").removeClass("search-popup-overlay-open");
});

/*=============================================
=     Offcanvas Menu      =
=============================================*/
$(".menu-tigger").on("click", function () {
    $(".aspn-sidebar-menu__info, .aspn-sidebar-menu__overly").addClass("active");
    return false;
});
$(".menu-close, .aspn-sidebar-menu__overly").on("click", function () {
    $(".aspn-sidebar-menu__info, .aspn-sidebar-menu__overly").removeClass("active");
});

$('.sidebar-nav .dropdown-btn').on('click', function () {
    $(this).toggleClass('open');
    $(this).closest('li').children('ul').first().slideToggle(300);
});

//>> Scroll Js Start <<//
const scrollPath = document.querySelector(".scroll-up path");
if (scrollPath) {
		const pathLength = scrollPath.getTotalLength();
		scrollPath.style.transition = scrollPath.style.WebkitTransition = "none";
		scrollPath.style.strokeDasharray = pathLength + " " + pathLength;
		scrollPath.style.strokeDashoffset = pathLength;
		scrollPath.getBoundingClientRect();
		scrollPath.style.transition = scrollPath.style.WebkitTransition = "stroke-dashoffset 10ms linear";

		const updatescroll = function () {
				let scrolltotal = $(window).scrollTop();
				let height = $(document).height() - $(window).height();
				let scrolltotalheight = pathLength - (scrolltotal * pathLength) / height;
				scrollPath.style.strokeDashoffset = scrolltotalheight;
		};

		$(window).scroll(updatescroll);
		updatescroll();
}
const offset = 50;
const duration = 650;

$(window).on("scroll", function () {
    if (jQuery(this).scrollTop() > offset) {
        jQuery(".scroll-up").addClass("active-scroll");
    } else {
        jQuery(".scroll-up").removeClass("active-scroll");
    }
});

$(".scroll-up").on("click", function (event) {
    event.preventDefault();
    jQuery("html, body").animate({
            scrollTop: 0,
        },
        duration
    );
    return false;
});

})(jQuery);
