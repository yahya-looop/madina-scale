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
    utilityInner: HTMLElement
    navInner: HTMLElement
    categoryButton: HTMLElement | null
  } | undefined
  let isCompact = false

  const clearHeaderMotion = () => {
    compactTimeline?.kill()
    compactTimeline = undefined

    if (scrollHandler) window.removeEventListener('scroll', scrollHandler)
    if (resizeHandler) window.removeEventListener('resize', resizeHandler)
    scrollHandler = undefined
    resizeHandler = undefined

    if (headerElements) {
      const { utility, utilityInner, navInner, categoryButton } = headerElements
      gsap.set(utility, { clearProps: 'height,maxHeight,overflow,willChange,pointerEvents' })
      gsap.set(utilityInner, { clearProps: 'height,minHeight,opacity,transform,willChange' })
      gsap.set(navInner, { clearProps: 'minHeight,willChange' })
      if (categoryButton) gsap.set(categoryButton, { clearProps: 'padding,fontSize,willChange' })
    }
    headerElements = undefined
    isCompact = false
  }

  const setupHeaderMotion = () => {
    clearHeaderMotion()

    const header = document.querySelector<HTMLElement>('.site-header')
    const utility = header?.querySelector<HTMLElement>('.utility-bar')
    const utilityInner = utility?.querySelector<HTMLElement>('.utility-bar__inner')
    const navInner = header?.querySelector<HTMLElement>('.main-nav__inner')
    const categoryButton = navInner?.querySelector<HTMLElement>('.category-toggle') ?? null

    if (!header || !utility || !utilityInner || !navInner) return

    const utilityHeight = Math.ceil(utility.getBoundingClientRect().height)
    const navHeight = Math.ceil(navInner.getBoundingClientRect().height)

    // Animate both the wrapper and its min-height-constrained inner row.
    // This prevents the inner min-height from keeping the utility bar open
    // until GSAP suddenly toggles visibility at the end of the tween.
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
      willChange: 'height, opacity, transform',
      transformOrigin: 'top center',
    })

    compactTimeline = gsap.timeline({
      paused: true,
      defaults: { ease: 'power2.inOut' },
    })

    compactTimeline.to(utilityInner, {
      height: 0,
      minHeight: 0,
      opacity: 0,
      y: -10,
      duration: 0.38,
      ease: 'power2.inOut',
    }, 0)

    compactTimeline.to(utility, {
      height: 0,
      maxHeight: 0,
      duration: 0.38,
      ease: 'power2.inOut',
      pointerEvents: 'none',
    }, 0)

    compactTimeline.to(navInner, {
      minHeight: Math.max(42, navHeight - 7),
      duration: 0.38,
    }, 0)

    if (categoryButton) {
      compactTimeline.to(categoryButton, {
        padding: '8px 11px',
        fontSize: '11px',
        duration: 0.38,
      }, 0)
    }

    headerElements = { utility, utilityInner, navInner, categoryButton }

    // Hysteresis prevents flicker around the threshold.
    scrollHandler = () => {
      const y = window.scrollY
      if (!isCompact && y >= 100) {
        isCompact = true
        compactTimeline?.play()
      } else if (isCompact && y <= 55) {
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
