import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

export default defineNuxtPlugin((nuxtApp) => {
  if (import.meta.server) return

  gsap.registerPlugin(ScrollTrigger)

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (reduceMotion) return

  // One shared timeline controls all three header bands.
  const HEADER_DURATION = 0.24
  const HEADER_EASE = 'power2.inOut'

  let pageContext: gsap.Context | undefined
  let compactTimeline: gsap.core.Timeline | undefined
  let scrollHandler: (() => void) | undefined
  let resizeHandler: (() => void) | undefined
  let isCompact = false

  let headerElements: {
    utility: HTMLElement
    utilityInner: HTMLElement
    headerMain: HTMLElement
    headerMainInner: HTMLElement
    brand: HTMLElement | null
    search: HTMLElement | null
    searchButton: HTMLElement | null
    account: HTMLElement | null
    cart: HTMLElement | null
    mainNav: HTMLElement | null
    navInner: HTMLElement | null
    categoryButton: HTMLElement | null
  } | undefined

  const clearHeaderMotion = () => {
    compactTimeline?.kill()
    compactTimeline = undefined

    if (scrollHandler) window.removeEventListener('scroll', scrollHandler)
    if (resizeHandler) window.removeEventListener('resize', resizeHandler)
    scrollHandler = undefined
    resizeHandler = undefined

    if (headerElements) {
      const {
        utility, utilityInner, headerMain, headerMainInner, brand, search,
        searchButton, account, cart, mainNav, navInner, categoryButton,
      } = headerElements

      gsap.set(utility, { clearProps: 'height,maxHeight,overflow,willChange' })
      gsap.set(utilityInner, { clearProps: 'height,minHeight,opacity,transform,willChange' })
      gsap.set(headerMain, { clearProps: 'height,willChange' })
      gsap.set(headerMainInner, { clearProps: 'height,minHeight,gridTemplateRows,willChange' })
      if (mainNav) gsap.set(mainNav, { clearProps: 'height,willChange' })
      if (navInner) gsap.set(navInner, { clearProps: 'height,minHeight,willChange' })

      ;[brand, search, searchButton, account, cart].forEach((element) => {
        if (element) gsap.set(element, { clearProps: 'height,width,minWidth,scale,transform,willChange' })
      })
      if (categoryButton) gsap.set(categoryButton, { clearProps: 'padding,fontSize,willChange' })
    }

    headerElements = undefined
    isCompact = false
  }

  const setupHeaderMotion = () => {
    clearHeaderMotion()

    const header = document.querySelector<HTMLElement>('.site-header')
    const utility = header?.querySelector<HTMLElement>('.utility-bar') ?? null
    const utilityInner = utility?.querySelector<HTMLElement>('.utility-bar__inner') ?? null
    const headerMain = header?.querySelector<HTMLElement>('.header-main') ?? null
    const headerMainInner = headerMain?.querySelector<HTMLElement>('.header-main__inner') ?? null
    const brand = header?.querySelector<HTMLElement>('.brand-lockup') ?? null
    const search = header?.querySelector<HTMLElement>('.header-search') ?? null
    const searchButton = search?.querySelector<HTMLElement>('button') ?? null
    const account = header?.querySelector<HTMLElement>('.header-login-link') ?? null
    const cart = header?.querySelector<HTMLElement>('.header-cart') ?? null
    const mainNav = header?.querySelector<HTMLElement>('.main-nav') ?? null
    const navInner = mainNav?.querySelector<HTMLElement>('.main-nav__inner') ?? null
    const categoryButton = navInner?.querySelector<HTMLElement>('.category-toggle') ?? null

    if (!utility || !utilityInner || !headerMain || !headerMainInner) return

    const utilityHeight = Math.ceil(utility.getBoundingClientRect().height)
    const mainHeight = Math.ceil(headerMain.getBoundingClientRect().height)
    const mainInnerHeight = Math.ceil(headerMainInner.getBoundingClientRect().height)
    const navHeight = mainNav && window.getComputedStyle(mainNav).display !== 'none'
      ? Math.ceil(mainNav.getBoundingClientRect().height)
      : 0
    const navInnerHeight = navInner && navHeight > 0
      ? Math.ceil(navInner.getBoundingClientRect().height)
      : 0
    const isMobile = window.matchMedia('(max-width: 767px)').matches

    if (utilityHeight <= 0 || mainHeight <= 0) return

    // Set measurable starting heights. The outer and inner rows animate together,
    // so min-height constraints cannot hold the top bar open until the last frame.
    gsap.set(utility, {
      height: utilityHeight,
      maxHeight: utilityHeight,
      overflow: 'hidden',
      willChange: 'height',
    })
    gsap.set(utilityInner, {
      height: utilityHeight,
      minHeight: utilityHeight,
      overflow: 'hidden',
      willChange: 'height, min-height, opacity, transform',
      transformOrigin: 'top center',
    })
    gsap.set(headerMain, { height: mainHeight, willChange: 'height' })
    gsap.set(headerMainInner, {
      height: mainInnerHeight,
      minHeight: mainInnerHeight,
      willChange: 'height, min-height',
    })

    if (mainNav && navHeight > 0 && navInner && navInnerHeight > 0) {
      gsap.set(mainNav, { height: navHeight, willChange: 'height' })
      gsap.set(navInner, {
        height: navInnerHeight,
        minHeight: navInnerHeight,
        willChange: 'height, min-height',
      })
    }

    compactTimeline = gsap.timeline({
      paused: true,
      defaults: { duration: HEADER_DURATION, ease: HEADER_EASE },
    })

    // Band 1: utility/top bar smoothly collapses to zero.
    compactTimeline.to(utilityInner, {
      height: 0,
      minHeight: 0,
      opacity: 0,
      y: -7,
    }, 0)
    compactTimeline.to(utility, {
      height: 0,
      maxHeight: 0,
    }, 0)

    // Band 2: logo + search row becomes shorter, but search stays available.
    const compactMainHeight = isMobile
      ? Math.max(70, mainHeight - 18)
      : Math.max(66, mainHeight - 18)
    const compactInnerHeight = isMobile
      ? Math.max(70, mainInnerHeight - 18)
      : Math.max(66, mainInnerHeight - 18)

    compactTimeline.to(headerMain, { height: compactMainHeight }, 0)
    compactTimeline.to(headerMainInner, {
      height: compactInnerHeight,
      minHeight: compactInnerHeight,
      gridTemplateRows: isMobile ? '42px 34px' : undefined,
    }, 0)

    if (brand) compactTimeline.to(brand, {
      scale: 0.86,
      transformOrigin: 'left center',
    }, 0)
    if (search) compactTimeline.to(search, {
      height: isMobile ? 34 : 37,
    }, 0)
    if (searchButton) compactTimeline.to(searchButton, {
      width: isMobile ? 38 : 41,
      minWidth: isMobile ? 38 : 41,
    }, 0)
    if (account) compactTimeline.to(account, { scale: 0.9 }, 0)
    if (cart) compactTimeline.to(cart, { scale: 0.9 }, 0)

    // Band 3: navigation row compacts on the exact same timeline.
    if (mainNav && navHeight > 0 && navInner && navInnerHeight > 0) {
      const compactNavHeight = Math.max(44, navHeight - 10)
      const compactNavInnerHeight = Math.max(42, navInnerHeight - 10)
      compactTimeline.to(mainNav, { height: compactNavHeight }, 0)
      compactTimeline.to(navInner, {
        height: compactNavInnerHeight,
        minHeight: compactNavInnerHeight,
      }, 0)
      if (categoryButton) compactTimeline.to(categoryButton, {
        padding: '8px 11px',
        fontSize: '11px',
      }, 0)
    }

    headerElements = {
      utility, utilityInner, headerMain, headerMainInner, brand, search,
      searchButton, account, cart, mainNav, navInner, categoryButton,
    }

    // Hysteresis avoids flicker. Reverse uses the same timeline and easing.
    scrollHandler = () => {
      const scrollY = window.scrollY
      if (!isCompact && scrollY >= 100) {
        isCompact = true
        compactTimeline?.play()
      } else if (isCompact && scrollY <= 55) {
        isCompact = false
        compactTimeline?.reverse()
      }
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
      setupHeaderMotion()
      animatePage()
      ScrollTrigger.refresh()
    })
  })

  const cleanupPlugin = () => {
    clearHeaderMotion()
    pageContext?.revert()
    pageContext = undefined
    ScrollTrigger.getAll().forEach((trigger) => trigger.kill())
    window.removeEventListener('pagehide', cleanupPlugin)
  }

  window.addEventListener('pagehide', cleanupPlugin, { once: true })
})
