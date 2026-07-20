/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Helpers
 */
const q = (root, selector) =>
  root.querySelector(`:scope ${selector}`) || root.querySelector(selector);

const cleanupExistingSwiper = ref => {
  if (ref.swiper) {
    ref.swiper.destroy(true, true);
  }
};

const ensureSwiperAvailable = () => {
  if (typeof Swiper === 'undefined') {
    console.warn('Swiper is not available on window');
    return false;
  }
  return true;
};

/**
 * Behavior option builders
 */
const buildMarqueeOptions = (ref, baseOptions) => {
  const wrapper = q(ref, '.wp-block-vandrekalender-slider-slides');
  if (!wrapper) return null;

  // Remove old duplicates if re-init happens
  wrapper.querySelectorAll('.is-duplicate').forEach(n => n.remove());
  const originals = Array.from(wrapper.children);
  if (originals.length === 0) return null;

  // Duplicate slides until track >= 2x container width
  const targetWidth = ref.clientWidth * 2;
  let safety = 0;

  while (wrapper.scrollWidth < targetWidth && safety < 10) {
    originals.forEach(slide => {
      const clone = slide.cloneNode(true);
      clone.classList.add('is-duplicate');
      clone.setAttribute('aria-hidden', 'true');
      wrapper.appendChild(clone);
    });
    safety++;
  }

  return {
    ...baseOptions,
    slidesPerView: 'auto',
    speed: 6000,
    watchOverflow: false,
    allowTouchMove: false,

    // keeps motion smooth/non-snappy (no user interaction added)
    freeMode: { enabled: true, momentum: false },

    autoplay: {
      delay: 1,
      disableOnInteraction: false,
    },
    on: {
      init(swiper) {
        if (swiper.rtlTranslate) {
          swiper.params.autoplay.reverseDirection = true;
          swiper.autoplay.stop();
          swiper.autoplay.start();
        }
      },
    },
  };
};

const buildNormalOptions = (ref, baseOptions, ctx) => {
  const { slidesPerViewDesktop = 2.5, slidesPerViewMobile = 1.5 } = ctx;

  const prevEl = q(ref, '.swiper-button-prev');
  const nextEl = q(ref, '.swiper-button-next');
  const scrollbarEl = q(ref, '.swiper-scrollbar');

  return {
    ...baseOptions,
    slidesPerGroup: 1,
    watchOverflow: true,
    scrollbar: scrollbarEl ? { el: scrollbarEl, draggable: false } : false,
    navigation: prevEl && nextEl ? { prevEl, nextEl } : false,
    breakpoints: {
      0: {
        slidesPerView: slidesPerViewMobile,
        spaceBetween: 16,
      },
      782: {
        slidesPerView: slidesPerViewDesktop,
        spaceBetween: 24,
      },
    },
  };
};

const buildVerticalOptions = (ref, baseOptions) => {
  const getHeadingTextFromSlide = slideEl => {
    if (!slideEl) return '';
    const heading = slideEl.querySelector('h1,h2,h3,h4,h5,h6');
    return heading ? (heading.textContent || '').trim() : '';
  };

  const buildTextNav = swiper => {
    const navRoot = q(ref, '.swiper-text-nav');
    if (!navRoot) return;

    const scrollTabIntoView = (navRoot, li) => {
      if (!navRoot || !li) return;

      const navRect = navRoot.getBoundingClientRect();
      const liRect = li.getBoundingClientRect();

      const current = navRoot.scrollLeft;

      const liLeftInNav = liRect.left - navRect.left + current;

      let target = liLeftInNav - (navRoot.clientWidth / 2 - li.clientWidth / 2);

      const max = navRoot.scrollWidth - navRoot.clientWidth;
      target = Math.max(0, Math.min(target, max));

      navRoot.scrollTo({ left: target, behavior: 'smooth' });
    };

    const realSlides = Array.from(
      swiper.el.querySelectorAll('.swiper-slide')
    ).filter(s => !s.classList.contains('swiper-slide-duplicate'));

    const labels = realSlides.map((slideEl, i) => {
      const text = getHeadingTextFromSlide(slideEl);
      return text || `Slide ${i + 1}`;
    });

    navRoot.innerHTML = '';
    labels.forEach((label, i) => {
      const li = document.createElement('li');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'swiper-text-nav__btn';
      btn.dataset.slideIndex = i;
      btn.textContent = label;
      li.appendChild(btn);
      btn.addEventListener('click', () => {
        scrollTabIntoView(navRoot, li);
        swiper.slideToLoop(i);
      });
      navRoot.appendChild(li);
    });

    const buttons = Array.from(
      navRoot.querySelectorAll('.swiper-text-nav__btn')
    );

    const setActive = () => {
      const active = swiper.realIndex;
      buttons.forEach((btn, i) => {
        btn.classList.toggle('is-active', i === active);
        btn.setAttribute('aria-current', i === active ? 'true' : 'false');
      });
    };

    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const i = Number(btn.dataset.slideIndex);
        if (!Number.isNaN(i)) swiper.slideToLoop(i);
      });
    });

    swiper.on('slideChange', setActive);
    setActive();
  };

  return {
    ...baseOptions,
    direction: 'vertical',
    slidesPerView: 'auto',
    slidesPerGroup: 1,
    watchOverflow: false,
    spaceBetween: 32,
    slidesOffsetAfter: 80,

    noSwiping: true,
    noSwipingClass: 'swiper-no-swiping',

    mousewheel: {
      forceToAxis: true,
      sensitivity: 0.5,
      thresholdDelta: 20,
      thresholdTime: 200,
      releaseOnEdges: true,
    },

    breakpoints: {
      0: {
        direction: 'horizontal',
        mousewheel: false,
        spaceBetween: 0,
        slidesOffsetAfter: 0,
        centeredSlidesBounds: true,
        autoHeight: true,
      },
      1024: {
        direction: 'vertical',
      },
    },

    on: {
      init(swiper) {
        buildTextNav(swiper);
      },
      breakpoint(swiper) {
        // if your text nav depends on real slides, rebuild on direction swap
        buildTextNav(swiper);
      },
    },
  };
};

const buildHeroProgressOptions = (ref, baseOptions) => {
  const prevEl = q(ref, '.swiper-button-prev');
  const nextEl = q(ref, '.swiper-button-next');
  const progressBar = q(ref, '.swiper-progress-bar');
  const pageCounter = q(ref, '.swiper-page-counter');

  let dots = [];
  let totalSlides = 0;

  const countRealSlides = swiper =>
    Array.from(swiper.slides).filter(
      s => !s.classList.contains('swiper-slide-duplicate')
    ).length;

  const initProgressBar = swiper => {
    totalSlides = countRealSlides(swiper);

    if (progressBar) {
      progressBar.innerHTML = '';
      dots = [];
      for (let i = 0; i < totalSlides; i++) {
        const dot = document.createElement('span');
        dot.className = 'swiper-progress-bar__dot';
        progressBar.appendChild(dot);
        dots.push(dot);
      }
    }

    updateActiveDot(swiper.realIndex);
  };

  const updateActiveDot = index => {
    dots.forEach((dot, i) => {
      dot.classList.toggle('is-active', i === index);
    });
  };

  const updateUI = swiper => {
    const current = swiper.realIndex;

    updateActiveDot(current);

    // Page counter
    if (pageCounter) {
      pageCounter.textContent = `${current + 1} / ${totalSlides}`;
    }
  };

  return {
    ...baseOptions,
    slidesPerView: 1,
    spaceBetween: 0,
    slidesPerGroup: 1,
    watchOverflow: true,
    navigation: prevEl && nextEl ? { prevEl, nextEl } : false,
    on: {
      init(swiper) {
        initProgressBar(swiper);
        updateUI(swiper);
      },
      slideChange(swiper) {
        updateUI(swiper);
      },
    },
  };
};

const BEHAVIORS = {
  marquee: buildMarqueeOptions,
  normal: buildNormalOptions,
  vertical: buildVerticalOptions,
  'hero-progress': buildHeroProgressOptions,
};

store('vandrekalender/slider', {
  callbacks: {
    setup() {
      const { ref } = getElement();
      const ctx = getContext();

      if (!ref || ref.nodeType !== 1) return;
      if (!ensureSwiperAvailable()) return;

      cleanupExistingSwiper(ref);

      const { behavior = 'normal' } = ctx;

      const baseOptions = {
        wrapperClass: 'wp-block-vandrekalender-slider-slides',
        slideClass: 'wp-block-vandrekalender-slider-slide',
        loop: behavior === 'normal' || behavior === 'hero-progress',
      };

      const builder = BEHAVIORS[behavior] || BEHAVIORS.normal;
      const options = builder(ref, baseOptions, ctx);
      if (!options) return;

      new Swiper(ref, options);
    },
  },
});
