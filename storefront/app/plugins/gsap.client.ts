import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

export default defineNuxtPlugin((nuxtApp) => {
  if (import.meta.server) return

  gsap.registerPlugin(ScrollTrigger)

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (reduceMotion) return

  let pageContext: gsap.Context | undefined
  let compactTimeline: gsap.core.Timeline | undefined
  let scrollHandler: (() => void) | undefined
  let resizeHandler: (() => void) | undefined
  let headerElements: {
    utility: HTMLElement
    headerMain: HTMLElement
    navInner: HTMLElement
    categoryButton: HTMLElement | null
  } | undefined

  const clearHeaderMotion = () => {
    compactTimeline?.progress(0).kill()
    compactTimeline = undefined

    if (scrollHandler) window.removeEventListener('scroll', scrollHandler)
    if (resizeHandler) window.removeEventListener('resize', resizeHandler)
    scrollHandler = undefined
    resizeHandler = undefined

    if (headerElements) {
      const { utility, headerMain, navInner, categoryButton } = headerElements
      gsap.set([utility, headerMain], {
        clearProps: 'maxHeight,overflow,opacity,visibility,transform,willChange,pointerEvents',
      })
      gsap.set(navInner, { clearProps: 'minHeight,willChange' })
      if (categoryButton) {
        gsap.set(categoryButton, { clearProps: 'padding,fontSize,willChange' })
      }
    }
    headerElements = undefined
  }

  const setupHeaderMotion = () => {
    clearHeaderMotion()

    const header = document.querySelector<HTMLElement>('.site-header')
    const utility = header?.querySelector<HTMLElement>('.utility-bar')
    const headerMain = header?.querySelector<HTMLElement>('.header-main')
    const navInner = header?.querySelector<HTMLElement>('.main-nav__inner')
    const categoryButton = header?.querySelector<HTMLElement>('.category-toggle')

    if (!header || !utility || !headerMain || !navInner) return

    const isMobile = window.matchMedia('(max-width: 767px)').matches
    const utilityHeight = Math.ceil(utility.getBoundingClientRect().height)
    const headerMainHeight = Math.ceil(headerMain.getBoundingClientRect().height)
    const navHeight = Math.ceil(navInner.getBoundingClientRect().height)

    // Keep the header in normal document flow and sticky as one unit.
    // Only collapse the upper rows; the navigation row remains visible.
    gsap.set(utility, {
      maxHeight: utilityHeight,
      overflow: 'hidden',
      willChange: 'max-height, opacity',
    })

    if (!isMobile) {
      gsap.set(headerMain, {
        maxHeight: headerMainHeight,
        overflow: 'hidden',
        willChange: 'max-height, opacity',
      })
    }

    compactTimeline = gsap.timeline({
      paused: true,
      defaults: { ease: 'power2.inOut' },
    })

    compactTimeline.to(utility, {
      maxHeight: 0,
      autoAlpha: 0,
      duration: 0.24,
      pointerEvents: 'none',
    }, 0)

    if (!isMobile) {
      compactTimeline.to(headerMain, {
        maxHeight: 0,
        autoAlpha: 0,
        duration: 0.28,
        pointerEvents: 'none',
      }, 0)
    }

    compactTimeline.to(navInner, {
      minHeight: Math.max(44, navHeight - 7),
      duration: 0.24,
    }, 0)

    if (categoryButton) {
      compactTimeline.to(categoryButton, {
        padding: '8px 12px',
        fontSize: '11.5px',
        duration: 0.24,
      }, 0)
    }

    headerElements = { utility, headerMain, navInner, categoryButton }

    scrollHandler = () => {
      if (window.scrollY > 90) compactTimeline?.play()
      else compactTimeline?.reverse()
    }

    resizeHandler = () => setupHeaderMotion()

    window.addEventListener('scroll', scrollHandler, { passive: true })
    window.addEventListener('resize', resizeHandler, { passive: true })
    scrollHandler()
  }

  const animatePage = () => {
    pageContext?.revert()

    pageContext = gsap.context(() => {
      const heroCopy = document.querySelector('.home-hero__copy')
      const heroArt = document.querySelector('.home-hero__art')

      if (heroCopy) {
        gsap.fromTo(heroCopy,
          { autoAlpha: 0, y: 22 },
          { autoAlpha: 1, y: 0, duration: 0.65, ease: 'power3.out', clearProps: 'all' },
        )
      }

      if (heroArt) {
        gsap.fromTo(heroArt,
          { autoAlpha: 0, x: 24, scale: 0.985 },
          { autoAlpha: 1, x: 0, y: 0, scale: 1, duration: 0.72, delay: 0.08, ease: 'power3.out', clearProps: 'all' },
        )
      }

      const revealTargets = gsap.utils.toArray<HTMLElement>(
        '.content-section, .collection-feature, .value-card, .home-track-panel, .home-newsletter, .catalogue-heading, .product-card, .flash-deal-card, .category-card, .product-detail-card, .cart-layout, .checkout-layout, [data-gsap-reveal]',
      )

      revealTargets.forEach((element) => {
        if (element.closest('.home-hero')) return

        gsap.fromTo(element,
          { autoAlpha: 0, y: 18 },
          {
            autoAlpha: 1,
            y: 0,
            duration: 0.52,
            ease: 'power2.out',
            clearProps: 'opacity,visibility,transform',
            scrollTrigger: {
              trigger: element,
              start: 'top 92%',
              once: true,
            },
          },
        )
      })
    })

    ScrollTrigger.refresh()
  }

  const boot = async () => {
    await nextTick()
    setupHeaderMotion()
    animatePage()
  }

  void boot()
  nuxtApp.hook('page:finish', () => {
    void nextTick().then(() => {
      animatePage()
      ScrollTrigger.refresh()
    })
  })

  nuxtApp.hook('app:beforeUnmount', () => {
    pageContext?.revert()
    clearHeaderMotion()
    ScrollTrigger.getAll().forEach(trigger => trigger.kill())
  })
})
