/* assets/faq-search.js */
;(function ($) {
  $(function () {
    var SUGGESTION_LIMIT = 5;
    var SEARCH_DELAY = 300;
    var SCROLL_OFFSET = 50;

    var $tabBtns        = $('.faq-tab-btn');
    var $tabContents    = $('.faq-tab-content');
    var $input          = $('.faq-search-input');
    var $suggest        = $('.faq-suggestions');
    var $clear          = $('.faq-search-clear');
    var $mobileSections = $('.faq-mobile-section');
    var $search         = $('.faq-search');
    var $nav            = $('.faq-mobile-nav');

    var searchTimer;

    // init
    $suggest.hide();
    bindAccordions();
    openFirstAccordionInAllTabs();
    openFirstAccordionInMobile();

    function bindAccordions() {
      $('.faq-answer').hide();
      $('.faq-question')
        .removeClass('open')
        .off('click')
        .on('click', function () {
          $(this).toggleClass('open').next('.faq-answer').slideToggle(200);
          $(this).closest('.faq-item').toggleClass('open');
        });
    }

    function openFirstAccordionInAllTabs() {
      $tabContents.each(function () {
        var $firstQ = $(this).find('.faq-question').first();
        $firstQ.addClass('open').next('.faq-answer').show();
        $firstQ.closest('.faq-item').addClass('open');
      });
    }

    function openFirstAccordionInMobile() {
      if ($(window).width() <= 768) {
        $mobileSections.each(function () {
          var $firstQ = $(this).find('.faq-question').first();
          if (!$firstQ.hasClass('open')) {
            $firstQ.addClass('open').next('.faq-answer').show();
            $firstQ.closest('.faq-item').addClass('open');
          }
        });
      }
    }

    function escRe(s) {
      return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // tabs
    $tabBtns.on('click', function () {
      var tab = $(this).data('tab');
      $tabBtns.removeClass('active');
      $(this).addClass('active');
      $tabContents.removeClass('active').hide();
      $('#' + tab).addClass('active').show();
      bindAccordions();
      openFirstAccordionInAllTabs();
    });

    // search input
    $input.on('keyup', function (e) {
      if (e.key === 'Enter') return;
      var keyword = this.value.trim();
      clearTimeout(searchTimer);

      if (keyword.length < 1) {
        $suggest.empty().hide();
        $clear.hide();
        return;
      }

      $clear.show();
      $suggest.empty().show().append('<li class="searching">Searching...</li>');

      searchTimer = setTimeout(function () {
        // INFIX match: plain substring regex (case-insensitive)
        var re = new RegExp(escRe(keyword), 'i');
        var reAll = new RegExp(escRe(keyword), 'ig');
        var keywordLC = keyword.toLowerCase();
        var matches = [];

        $('.faq-tab-content .faq-item').each(function () {
          var $item = $(this);
          var $q = $item.find('.faq-question');
          var $a = $item.find('.faq-answer');
          var title = ($q.text() || '').trim();
          var content = ($a.text() || '').trim();

          var inQ = re.test(title);
          var inA = re.test(content);

          if (inQ || inA) {
            var cat = $item.closest('.faq-tab-content').attr('id');
            var snippet = content;

            if (inA) {
              var idx = content.toLowerCase().indexOf(keywordLC);
              if (idx > -1) {
                var start = Math.max(0, idx - 10);
                var end = Math.min(content.length, idx + keyword.length + 90);
                snippet = content.substring(start, end) + (end < content.length ? '…' : '');
              }
            } else {
              // fallback small snippet from title only
              snippet = title;
            }

            var hQ = title.replace(reAll, '<strong>$&</strong>');
            var hA = snippet.replace(reAll, '<strong>$&</strong>');

            matches.push({ question: title, cat: cat, hQ: hQ, hA: hA });
          }
        });

        $suggest.empty();
        if (matches.length) {
          matches.slice(0, SUGGESTION_LIMIT).forEach(function (m) {
            $suggest.append(
              '<li data-q="' +
                m.question.replace(/"/g, '&quot;') +
                '" data-cat="' +
                m.cat.replace(/"/g, '&quot;') +
                '">' +
                '<div class="sug-q">' +
                m.hQ +
                '</div>' +
                '<div class="sug-a">' +
                m.hA +
                '</div>' +
                '</li>'
            );
          });
        } else {
          $suggest.append('<li class="no-results">No results found</li>');
        }
      }, SEARCH_DELAY);
    });

    // pick a suggestion
    $suggest.on('click', 'li', function () {
      var qText = $(this).data('q');
      var cat = $(this).data('cat');
      if (!qText || !cat) return;

      if ($(window).width() <= 768) {
        var $q = $mobileSections.find('.faq-question').filter(function () {
          return $(this).text().trim() === qText;
        });
        if ($q.length) {
          bindAccordions();
          $q.addClass('open').next('.faq-answer').slideDown(200);
          $q.closest('.faq-item').addClass('open');
          var top = $q.offset().top - ($search.outerHeight(true) + $nav.outerHeight(true) + SCROLL_OFFSET);
          $('html,body').animate({ scrollTop: top }, 300);
        }
      } else {
        var $tabBtn = $('.faq-tab-btn[data-tab="' + cat + '"]');
        if ($tabBtn.length) $tabBtn.click();

        var $q2 = $('#' + cat)
          .find('.faq-question')
          .filter(function () {
            return $(this).text().trim() === qText;
          });

        if ($q2.length) {
          bindAccordions();
          $q2.addClass('open').next('.faq-answer').slideDown(200);
          $q2.closest('.faq-item').addClass('open');
          var top2 = $q2.offset().top - ($search.outerHeight(true) + $nav.outerHeight(true) + SCROLL_OFFSET);
          $('html,body').animate({ scrollTop: top2 }, 300);
        }
      }

      $suggest.hide();
    });

    // enter key selects first suggestion
    $input.on('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var $first = $suggest.find('li').first();
        if ($first.length && !$first.hasClass('no-results')) $first.click();
      }
    });

    // clear
    $clear.on('click', function () {
      $input.val('');
      $clear.hide();
      $suggest.empty().hide();
      $input.focus();
    });

    // click outside hides
    $(document).on('click', function (e) {
      if (!$(e.target).closest('.faq-search, .faq-suggestions').length) $suggest.hide();
    });

    // mobile nav
    $('.faq-mobile-nav button').on('click', function () {
      var tgt = $(this).data('target');
      var $sec = $mobileSections.filter('#' + tgt);
      if (!$sec.length) return;
      bindAccordions();
      var $first = $sec.find('.faq-question').first();
      $first.addClass('open').next('.faq-answer').show();
      var top = $sec.offset().top - ($search.outerHeight(true) + $nav.outerHeight(true) + SCROLL_OFFSET);
      $('html,body').animate({ scrollTop: top }, 300);
    });
  });
})(jQuery);
