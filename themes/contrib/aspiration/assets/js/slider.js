(function ($) {

    // IMAGE ADD FROM DATA SRC
    $('[data-img-src]').each(function () {
        var Url = $(this).data('img-src');
        $(this).css('background-image', 'url(' + Url + ')');
    });

    var interleaveOffset = 0.4;
    new Swiper('.hero-slide-container', {
        loop: 'TRUE',
        autoplay: {
          delay: 5000, // 5 seconds
          disableOnInteraction: 'FALSE', // Keeps autoplay after user interaction
        },
        speed:1200,
        grabCursor: 'TRUE',
        watchSlidesProgress: 'TRUE',
        mousewheelControl: 'TRUE',
        keyboardControl: 'TRUE',
        resistance : 'TRUE',
        resistanceRatio : 0.5,
        parallax:'FALSE',
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        pagination: {
          el: ".hero-pagination",
          clickable: 'TRUE',
          renderBullet: function (index, className) {
            return '<span class="' + className + '" data-index="0' + (index + 1) + '">0' + (index + 1) + "</span>";
          },
        },
        on: {
            progress: function () {
              var swiper = this;
              for (var i = 0; i < swiper.slides.length; i++) {
                var slideProgress = swiper.slides[i].progress;
                var innerOffset = swiper.width * interleaveOffset;
                var innerTranslate = slideProgress * innerOffset;
                swiper.slides[i].querySelector(".slide_bg").style.transform =
                  "translate3d(" + innerTranslate + "px, 0, 0)";
              }
            },
            touchStart: function () {
              var swiper = this;
              for (var i = 0; i < swiper.slides.length; i++) {
                swiper.slides[i].style.transition = "";
              }
            },
            setTransition: function (speed) {
              var swiper = this;
              for (var i = 0; i < swiper.slides.length; i++) {
                swiper.slides[i].style.transition = speed + "ms";
                swiper.slides[i].querySelector(".slide_bg").style.transition = speed + "ms";
              }
            }
        }
    });
})(jQuery);
