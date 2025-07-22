/**
 * FineLive Multi LP Display - Frontend JavaScript
 */

(function() {
    'use strict';

    // グローバル変数
    var FLP_Frontend = {
        sliders: [],
        clickTracking: true,
        touchSupported: 'ontouchstart' in window,
        isLoading: false
    };

    /**
     * DOM読み込み完了時の初期化
     */
    document.addEventListener('DOMContentLoaded', function() {
        FLP_Frontend.init();
    });

    /**
     * 初期化処理
     */
    FLP_Frontend.init = function() {
        this.initSliders();
        this.initButtonTracking();
        this.initImageLazyLoading();
        this.initTouchSupport();
        this.initAccessibility();
        this.initAnimations();
        
        console.log('FLP Frontend initialized');
    };

    /**
     * スライダー機能の初期化
     */
    FLP_Frontend.initSliders = function() {
        var sliderElements = document.querySelectorAll('.flp_slider');
        
        sliderElements.forEach(function(sliderElement, index) {
            var sliderId = 'flp_slider_' + index;
            var slider = new FLP_Frontend.Slider(sliderElement, sliderId);
            FLP_Frontend.sliders.push(slider);
        });
    };

    /**
     * スライダークラス
     */
    FLP_Frontend.Slider = function(element, id) {
        this.element = element;
        this.id = id;
        this.currentIndex = 0;
        this.slidesContainer = element.querySelector('.flp_slides_container');
        this.slides = element.querySelectorAll('.flp_slide_img');
        this.dots = element.querySelectorAll('.flp_slider_dot');
        this.slideCount = this.slides.length;
        this.intervalTime = parseInt(element.getAttribute('data-interval')) || 4000;
        this.autoplayInterval = null;
        this.isPlaying = true;
        this.touchStartX = 0;
        this.touchEndX = 0;
        this.minSwipeDistance = 50;

        if (this.slideCount <= 1) {
            return;
        }

        this.init();
    };

    /**
     * スライダーの初期化
     */
    FLP_Frontend.Slider.prototype.init = function() {
        this.setupEventListeners();
        this.updateDots();
        this.startAutoplay();
        this.preloadImages();

        // アクセシビリティ属性の設定
        this.element.setAttribute('role', 'region');
        this.element.setAttribute('aria-label', 'Image carousel');
        this.element.setAttribute('aria-live', 'polite');
    };

    /**
     * イベントリスナーの設定
     */
    FLP_Frontend.Slider.prototype.setupEventListeners = function() {
        var self = this;

        // ドットクリック
        this.dots.forEach(function(dot, index) {
            dot.addEventListener('click', function(e) {
                e.stopPropagation();
                self.goToSlide(index);
                self.restartAutoplay();
            });

            // キーボードサポート
            dot.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    self.goToSlide(index);
                    self.restartAutoplay();
                }
            });
        });

        // スライダークリック
        this.element.addEventListener('click', function() {
            self.goToNextSlide();
            self.restartAutoplay();
        });

        // タッチイベント
        if (FLP_Frontend.touchSupported) {
            this.element.addEventListener('touchstart', function(e) {
                self.handleTouchStart(e);
            }, { passive: true });

            this.element.addEventListener('touchend', function(e) {
                self.handleTouchEnd(e);
            }, { passive: true });
        }

        // マウスイベント
        this.element.addEventListener('mouseenter', function() {
            self.pauseAutoplay();
        });

        this.element.addEventListener('mouseleave', function() {
            self.resumeAutoplay();
        });

        // キーボードナビゲーション
        this.element.addEventListener('keydown', function(e) {
            switch (e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    self.goToPrevSlide();
                    self.restartAutoplay();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    self.goToNextSlide();
                    self.restartAutoplay();
                    break;
                case ' ':
                    e.preventDefault();
                    self.toggleAutoplay();
                    break;
            }
        });

        // Intersection Observer for performance
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        if (!self.isPlaying) {
                            self.resumeAutoplay();
                        }
                    } else {
                        self.pauseAutoplay();
                    }
                });
            }, { threshold: 0.5 });

            observer.observe(this.element);
        }
    };

    /**
     * タッチ開始処理
     */
    FLP_Frontend.Slider.prototype.handleTouchStart = function(e) {
        this.touchStartX = e.touches[0].clientX;
        this.pauseAutoplay();
    };

    /**
     * タッチ終了処理
     */
    FLP_Frontend.Slider.prototype.handleTouchEnd = function(e) {
        this.touchEndX = e.changedTouches[0].clientX;
        this.handleSwipe();
        this.resumeAutoplay();
    };

    /**
     * スワイプ処理
     */
    FLP_Frontend.Slider.prototype.handleSwipe = function() {
        var swipeDistance = this.touchStartX - this.touchEndX;
        
        if (Math.abs(swipeDistance) < this.minSwipeDistance) {
            return;
        }

        if (swipeDistance > 0) {
            this.goToNextSlide();
        } else {
            this.goToPrevSlide();
        }
    };

    /**
     * 指定スライドに移動
     */
    FLP_Frontend.Slider.prototype.goToSlide = function(index) {
        if (index < 0 || index >= this.slideCount) {
            return;
        }

        this.currentIndex = index;
        var translateX = -this.currentIndex * 100;
        
        this.slidesContainer.style.transform = 'translateX(' + translateX + '%)';
        this.updateDots();
        this.updateAriaLabel();
    };

    /**
     * 次のスライドに移動
     */
    FLP_Frontend.Slider.prototype.goToNextSlide = function() {
        var nextIndex = (this.currentIndex + 1) % this.slideCount;
        this.goToSlide(nextIndex);
    };

    /**
     * 前のスライドに移動
     */
    FLP_Frontend.Slider.prototype.goToPrevSlide = function() {
        var prevIndex = this.currentIndex === 0 ? this.slideCount - 1 : this.currentIndex - 1;
        this.goToSlide(prevIndex);
    };

    /**
     * ドットの更新
     */
    FLP_Frontend.Slider.prototype.updateDots = function() {
        var self = this;
        
        this.dots.forEach(function(dot, index) {
            if (index === self.currentIndex) {
                dot.classList.add('active');
                dot.setAttribute('aria-pressed', 'true');
            } else {
                dot.classList.remove('active');
                dot.setAttribute('aria-pressed', 'false');
            }
        });
    };

    /**
     * aria-labelの更新
     */
    FLP_Frontend.Slider.prototype.updateAriaLabel = function() {
        var label = 'Slide ' + (this.currentIndex + 1) + ' of ' + this.slideCount;
        this.element.setAttribute('aria-label', label);
    };

    /**
     * オートプレイ開始
     */
    FLP_Frontend.Slider.prototype.startAutoplay = function() {
        var self = this;
        
        this.autoplayInterval = setInterval(function() {
            self.goToNextSlide();
        }, this.intervalTime);
        
        this.isPlaying = true;
    };

    /**
     * オートプレイ停止
     */
    FLP_Frontend.Slider.prototype.pauseAutoplay = function() {
        if (this.autoplayInterval) {
            clearInterval(this.autoplayInterval);
            this.autoplayInterval = null;
        }
        this.isPlaying = false;
    };

    /**
     * オートプレイ再開
     */
    FLP_Frontend.Slider.prototype.resumeAutoplay = function() {
        if (!this.isPlaying && this.slideCount > 1) {
            this.startAutoplay();
        }
    };

    /**
     * オートプレイ再開（即座に開始）
     */
    FLP_Frontend.Slider.prototype.restartAutoplay = function() {
        this.pauseAutoplay();
        this.startAutoplay();
    };

    /**
     * オートプレイの切り替え
     */
    FLP_Frontend.Slider.prototype.toggleAutoplay = function() {
        if (this.isPlaying) {
            this.pauseAutoplay();
        } else {
            this.resumeAutoplay();
        }
    };

    /**
     * 画像のプリロード
     */
    FLP_Frontend.Slider.prototype.preloadImages = function() {
        this.slides.forEach(function(slide, index) {
            var img = slide;
            if (index > 0) { // 最初の画像以外をプリロード
                var tempImg = new Image();
                tempImg.src = img.src;
            }
        });
    };

    /**
     * ボタンクリック追跡の初期化
     */
    FLP_Frontend.initButtonTracking = function() {
        if (!this.clickTracking || typeof window.flp_vars === 'undefined') {
            return;
        }

        var buttons = document.querySelectorAll('.flp_btn');
        
        buttons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                var buttonId = this.getAttribute('data-btn');
                var lpId = this.getAttribute('data-lp-id');
                var href = this.href;

                if (!buttonId || !lpId) {
                    // データが不足している場合は通常の遷移
                    if (href) {
                        window.location.href = href;
                    }
                    return;
                }

                FLP_Frontend.trackClick(buttonId, lpId, href);
            });
        });
    };

    /**
     * クリック追跡の実行
     */
    FLP_Frontend.trackClick = function(buttonId, lpId, href) {
        if (this.isLoading) {
            return;
        }

        this.isLoading = true;

        var data = new URLSearchParams({
            action: 'flp_lp_track_click',
            btn: buttonId,
            lp_id: lpId,
            _wpnonce: window.flp_vars.nonce
        });

        // ローディング状態の表示
        var button = document.querySelector('[data-btn="' + buttonId + '"]');
        if (button) {
            button.classList.add('loading');
        }

        fetch(window.flp_vars.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: data
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (window.flp_vars.debug && data.success) {
                console.log('Click tracked successfully');
            }
        })
        .catch(function(error) {
            if (window.flp_vars.debug) {
                console.error('Click tracking error:', error);
            }
        })
        .finally(function() {
            FLP_Frontend.isLoading = false;
            
            if (button) {
                button.classList.remove('loading');
            }
            
            // ページ遷移
            if (href) {
                setTimeout(function() {
                    window.location.href = href;
                }, 100);
            }
        });
    };

    /**
     * 画像の遅延読み込み初期化
     */
    FLP_Frontend.initImageLazyLoading = function() {
        if (!('IntersectionObserver' in window)) {
            // Intersection Observer がサポートされていない場合は即座に読み込み
            this.loadAllImages();
            return;
        }

        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    FLP_Frontend.loadImage(img);
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });

        var lazyImages = document.querySelectorAll('.flp_static_image[loading="lazy"]');
        lazyImages.forEach(function(img) {
            imageObserver.observe(img);
        });
    };

    /**
     * 単一画像の読み込み
     */
    FLP_Frontend.loadImage = function(img) {
        img.addEventListener('load', function() {
            this.classList.add('loaded');
        });

        img.addEventListener('error', function() {
            this.classList.add('error');
            console.warn('Failed to load image:', this.src);
        });
    };

    /**
     * 全画像の即座読み込み（フォールバック）
     */
    FLP_Frontend.loadAllImages = function() {
        var images = document.querySelectorAll('.flp_static_image');
        images.forEach(function(img) {
            FLP_Frontend.loadImage(img);
        });
    };

    /**
     * タッチサポートの初期化
     */
    FLP_Frontend.initTouchSupport = function() {
        if (!this.touchSupported) {
            return;
        }

        // タッチデバイス向けのスタイル調整
        document.body.classList.add('flp-touch-device');

        // タッチイベントの重複防止
        var buttons = document.querySelectorAll('.flp_btn');
        buttons.forEach(function(button) {
            var clickTimeout;
            
            button.addEventListener('touchend', function(e) {
                clearTimeout(clickTimeout);
                clickTimeout = setTimeout(function() {
                    // タッチ後のクリックイベントを無効化
                }, 300);
            });
        });
    };

    /**
     * アクセシビリティの初期化
     */
    FLP_Frontend.initAccessibility = function() {
        // キーボードナビゲーションの改善
        var focusableElements = document.querySelectorAll('.flp_btn, .flp_slider_dot');
        
        focusableElements.forEach(function(element) {
            element.setAttribute('tabindex', '0');
            
            if (element.classList.contains('flp_slider_dot')) {
                element.setAttribute('role', 'button');
                element.setAttribute('aria-label', 'Go to slide');
            }
        });

        // スクリーンリーダー用の説明テキスト
        var sliders = document.querySelectorAll('.flp_slider');
        sliders.forEach(function(slider) {
            var description = document.createElement('div');
            description.className = 'flp-sr-only';
            description.textContent = 'Use arrow keys to navigate between slides, or press space to pause autoplay';
            slider.appendChild(description);
        });

        // reducedMotion対応
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            FLP_Frontend.disableAnimations();
        }
    };

    /**
     * アニメーションの無効化
     */
    FLP_Frontend.disableAnimations = function() {
        var style = document.createElement('style');
        style.textContent = `
            .flp_lp_wrap *,
            .flp_lp_wrap *::before,
            .flp_lp_wrap *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            .flp_slides_container {
                transition: none !important;
            }
        `;
        document.head.appendChild(style);

        // スライダーのオートプレイも停止
        FLP_Frontend.sliders.forEach(function(slider) {
            slider.pauseAutoplay();
        });
    };

    /**
     * アニメーションの初期化
     */
    FLP_Frontend.initAnimations = function() {
        // Intersection Observer for scroll animations
        if (!('IntersectionObserver' in window)) {
            return;
        }

        var animationObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('flp-animate-in');
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        var animatableElements = document.querySelectorAll('.flp_block, .flp_btn_wrap');
        animatableElements.forEach(function(element) {
            animationObserver.observe(element);
        });
    };

    /**
     * ページの可視性変更時の処理
     */
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // ページが非表示になったらスライダーを停止
            FLP_Frontend.sliders.forEach(function(slider) {
                slider.pauseAutoplay();
            });
        } else {
            // ページが表示されたらスライダーを再開
            FLP_Frontend.sliders.forEach(function(slider) {
                slider.resumeAutoplay();
            });
        }
    });

    /**
     * ウィンドウリサイズ時の処理
     */
    window.addEventListener('resize', FLP_Frontend.debounce(function() {
        // スライダーの位置を再調整
        FLP_Frontend.sliders.forEach(function(slider) {
            slider.goToSlide(slider.currentIndex);
        });
    }, 250));

    /**
     * デバウンス関数
     */
    FLP_Frontend.debounce = function(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    /**
     * パフォーマンス監視
     */
    FLP_Frontend.monitorPerformance = function() {
        if (!window.performance || !window.flp_vars.debug) {
            return;
        }

        window.addEventListener('load', function() {
            setTimeout(function() {
                var timing = window.performance.timing;
                var loadTime = timing.loadEventEnd - timing.navigationStart;
                
                console.log('FLP Page Load Time:', loadTime + 'ms');
                
                // Core Web Vitals
                if ('PerformanceObserver' in window) {
                    var observer = new PerformanceObserver(function(list) {
                        list.getEntries().forEach(function(entry) {
                            if (entry.entryType === 'largest-contentful-paint') {
                                console.log('FLP LCP:', entry.startTime);
                            }
                        });
                    });
                    
                    observer.observe({ entryTypes: ['largest-contentful-paint'] });
                }
            }, 0);
        });
    };

    /**
     * エラーハンドリング
     */
    window.addEventListener('error', function(e) {
        if (window.flp_vars && window.flp_vars.debug) {
            console.error('FLP Frontend Error:', e.error);
        }
    });

    // パフォーマンス監視の開始
    FLP_Frontend.monitorPerformance();

    // グローバルアクセス用
    window.FLP_Frontend = FLP_Frontend;

    /**
     * 外部API: スライダー制御
     */
    window.FLP_ControlSlider = function(sliderId, action, value) {
        var slider = FLP_Frontend.sliders.find(function(s) {
            return s.id === sliderId;
        });

        if (!slider) {
            console.warn('Slider not found:', sliderId);
            return;
        }

        switch (action) {
            case 'goTo':
                slider.goToSlide(parseInt(value));
                break;
            case 'next':
                slider.goToNextSlide();
                break;
            case 'prev':
                slider.goToPrevSlide();
                break;
            case 'play':
                slider.resumeAutoplay();
                break;
            case 'pause':
                slider.pauseAutoplay();
                break;
            case 'toggle':
                slider.toggleAutoplay();
                break;
        }
    };

    /**
     * 外部API: 統計情報取得
     */
    window.FLP_GetStats = function() {
        return {
            slidersCount: FLP_Frontend.sliders.length,
            clickTrackingEnabled: FLP_Frontend.clickTracking,
            touchSupported: FLP_Frontend.touchSupported
        };
    };

})();

/**
 * jQuery統合（jQueryが利用可能な場合）
 */
if (typeof jQuery !== 'undefined') {
    jQuery(function($) {
        // jQuery用のショートハンド
        $.fn.flpSlider = function(action, value) {
            return this.each(function() {
                var sliderId = this.id || ('flp_slider_' + Math.random().toString(36).substr(2, 9));
                window.FLP_ControlSlider(sliderId, action, value);
            });
        };
    });
}