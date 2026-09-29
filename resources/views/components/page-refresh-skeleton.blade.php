<div id="amb-skeleton-root">
    <style id="maxit-skeleton-styles">
        #amb-skeleton-overlay-container {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 9999999 !important;
            background-color: #f4f5f8 !important;
            display: flex !important;
            flex-direction: row !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            transition: opacity 0.5s ease-out, transform 0.5s ease-out;
        }

        #amb-skeleton-overlay-container.amb-fade-out {
            opacity: 0 !important;
            pointer-events: none !important;
            transform: scale(0.98) !important;
        }

        .maxit-wave-container {
            position: relative !important;
            overflow: hidden !important;
            will-change: transform !important;
        }

        .maxit-wave-bar {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.85) 50%,
                rgba(255, 255, 255, 0) 100%
            ) !important;
            pointer-events: none !important;
            will-change: transform !important;
        }

        .maxit-wave-dark {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.3) 50%,
                rgba(255, 255, 255, 0) 100%
            ) !important;
            pointer-events: none !important;
            will-change: transform !important;
        }

        .maxit-magnifier-spot {
            position: absolute !important;
            z-index: 50 !important;
            pointer-events: none !important;
            width: 4.5rem !important;
            height: 4.5rem !important;
            transition: top 0.75s cubic-bezier(0.4, 0, 0.2, 1), left 0.75s cubic-bezier(0.4, 0, 0.2, 1) !important;
            will-change: top, left !important;
        }

        .maxit-sonar-orange {
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            border: 2px solid rgba(255, 121, 0, 0.8);
            will-change: transform, opacity !important;
        }

        .maxit-sonar-blue {
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            border: 2px solid rgba(37, 99, 235, 0.8);
            will-change: transform, opacity !important;
        }

        @media (max-width: 768px) {
            .maxit-sidebar-skeleton {
                display: none !important;
            }
            .maxit-content-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
            }
        }
    </style>

    <div id="amb-skeleton-overlay-container">
        <!-- SIDEBAR SKELETON -->
        <div class="maxit-sidebar-skeleton" style="width: 16rem; background-color: #0b1736; height: 100vh; display: flex; flex-direction: column; padding: 1.25rem 1rem; gap: 1.5rem; border-right: 1px solid rgba(255,255,255,0.06); box-sizing: border-box; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.875rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
                <div class="maxit-wave-container" style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; background-color: rgba(255,255,255,0.14);">
                    <div class="maxit-wave-dark"></div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.375rem;">
                    <div class="maxit-wave-container" style="width: 7.5rem; height: 0.875rem; background-color: rgba(255,255,255,0.22); border-radius: 0.25rem;">
                        <div class="maxit-wave-dark"></div>
                    </div>
                    <div class="maxit-wave-container" style="width: 5rem; height: 0.625rem; background-color: rgba(255,255,255,0.12); border-radius: 0.25rem;">
                        <div class="maxit-wave-dark"></div>
                    </div>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-top: 0.25rem;">
                <div class="maxit-wave-container" style="width: 6.5rem; height: 0.625rem; background-color: rgba(255,255,255,0.2); border-radius: 0.25rem;">
                    <div class="maxit-wave-dark"></div>
                </div>
                @for ($n = 0; $n < 6; $n++)
                    <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.625rem; border-radius: 0.5rem; background-color: {{ $n === 0 ? 'rgba(255,255,255,0.08)' : 'transparent' }};">
                        <div class="maxit-wave-container" style="width: 1.25rem; height: 1.25rem; border-radius: 0.25rem; background-color: rgba(255,255,255,0.2);">
                            <div class="maxit-wave-dark"></div>
                        </div>
                        <div class="maxit-wave-container" style="width: {{ [8, 10, 7, 9, 8, 6][$n] }}rem; height: 0.75rem; border-radius: 0.25rem; background-color: rgba(255,255,255,0.2);">
                            <div class="maxit-wave-dark"></div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        <!-- MAIN PANEL AREA -->
        <div style="flex: 1 1 0%; height: 100vh; overflow-y: auto; background-color: #f4f5f8; display: flex; flex-direction: column; box-sizing: border-box;">
            <!-- Header Navbar -->
            <div style="height: 4rem; width: 100%; background-color: #ffffff; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; box-sizing: border-box; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 0.875rem;">
                    <div class="maxit-wave-container" style="width: 2rem; height: 2rem; border-radius: 9999px; background-color: #cbd5e1;">
                        <div class="maxit-wave-bar"></div>
                    </div>
                    <div class="maxit-wave-container" style="width: 11rem; height: 1rem; border-radius: 0.375rem; background-color: #cbd5e1;">
                        <div class="maxit-wave-bar"></div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="maxit-wave-container" style="width: 6rem; height: 0.875rem; border-radius: 0.25rem; background-color: #cbd5e1;">
                        <div class="maxit-wave-bar"></div>
                    </div>
                    <div class="maxit-wave-container" style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; background-color: #0b1736;">
                        <div class="maxit-wave-dark"></div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div style="flex: 1; padding: 1.5rem 2rem; display: flex; flex-direction: column; gap: 1.25rem; box-sizing: border-box; max-width: 80rem; width: 100%; margin: 0 auto;">
                <div class="maxit-wave-container" style="width: 100%; height: 3.25rem; border-radius: 0.75rem; background-color: #eff6ff; border: 1px solid #dbeafe; display: flex; align-items: center; padding: 0 1.25rem; gap: 0.75rem; box-sizing: border-box; flex-shrink: 0;">
                    <div class="maxit-wave-bar"></div>
                    <div style="width: 1.25rem; height: 1.25rem; border-radius: 9999px; background-color: #93c5fd; flex-shrink: 0; z-index: 2;"></div>
                    <div style="width: 55%; height: 0.875rem; border-radius: 9999px; background-color: #93c5fd; z-index: 2;"></div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; flex-shrink: 0; margin-top: 0.25rem;">
                    <div class="maxit-wave-container" style="width: 18rem; height: 1.75rem; border-radius: 0.5rem; background-color: #94a3b8;">
                        <div class="maxit-wave-bar"></div>
                    </div>
                    <div class="maxit-wave-container" style="width: 28rem; height: 0.938rem; border-radius: 0.375rem; background-color: #cbd5e1;">
                        <div class="maxit-wave-bar"></div>
                    </div>
                </div>

                <div style="position: relative; width: 100%; margin-top: 0.5rem;">
                    <!-- Floating Orange Max It Radar Lens -->
                    <div class="maxit-magnifier-spot" style="top: 20%; left: 17%;">
                        <div style="position: relative; display: flex; align-items: center; justify-content: center; width: 4.5rem; height: 4.5rem;">
                            <div class="maxit-sonar-orange"></div>
                            <div class="maxit-sonar-blue"></div>
                            <div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,121,0,0.45), rgba(37,99,235,0.45)); border-radius: 9999px; filter: blur(16px); transform: scale(1.6);"></div>
                            <div style="position: absolute; inset: -6px; background: linear-gradient(to top right, rgba(255,121,0,0.35), rgba(56,189,248,0.35)); border-radius: 9999px; filter: blur(8px);"></div>
                            <div style="position: relative; width: 3.75rem; height: 3.75rem; border-radius: 9999px; background: linear-gradient(135deg, rgba(255,121,0,0.25), rgba(37,99,235,0.25)); backdrop-filter: blur(8px); border: 1.5px solid rgba(255, 121, 0, 0.7); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 30px rgba(255,121,0,0.5);">
                                <svg width="26" height="26" fill="none" stroke="#ff7900" stroke-width="2.8" viewBox="0 0 24 24">
                                    <circle cx="11" cy="11" r="6.5" />
                                    <path stroke-linecap="round" d="M20 20l-4.2-4.2" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="maxit-content-grid" style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.25rem; width: 100%;">
                        @for ($i = 0; $i < 6; $i++)
                            <div class="maxit-animated-card" style="background-color: #ffffff; border-radius: 1rem; padding: 1.25rem; border: 1px solid #cbd5e1; display: flex; flex-direction: column; gap: 0.875rem; box-shadow: 0 4px 14px rgba(0,0,0,0.05);">
                                <!-- Image Box with Horizontal Wave -->
                                <div class="maxit-wave-container" style="width: 100%; height: 7.5rem; background-color: #94a3b8; border-radius: 0.75rem;">
                                    <div class="maxit-wave-bar"></div>
                                </div>
                                <!-- Text Line 1 with Horizontal Wave -->
                                <div class="maxit-wave-container" style="width: 70%; height: 0.875rem; background-color: #cbd5e1; border-radius: 9999px; margin-top: 0.25rem;">
                                    <div class="maxit-wave-bar"></div>
                                </div>
                                <!-- Text Line 2 with Horizontal Wave -->
                                <div class="maxit-wave-container" style="width: 48%; height: 0.875rem; background-color: #e2e8f0; border-radius: 9999px;">
                                    <div class="maxit-wave-bar"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            function launchMaxitSkeleton() {
                const container = document.getElementById('amb-skeleton-overlay-container');
                if (!container) return;

                if (container.parentNode !== document.body) {
                    document.body.appendChild(container);
                }

                // Ensure style element is inside document.head
                const styleElem = document.getElementById('maxit-skeleton-styles');
                if (styleElem && styleElem.parentNode !== document.head) {
                    document.head.appendChild(styleElem);
                }

                container.style.display = 'flex';
                container.classList.remove('amb-fade-out');

                // 1. Web Animations API for Horizontal Wave Bars (Guaranteed 60 FPS in all browsers)
                const waveBars = container.querySelectorAll('.maxit-wave-bar, .maxit-wave-dark');
                waveBars.forEach((bar, idx) => {
                    if (bar._waveAnim) {
                        bar._waveAnim.cancel();
                    }
                    bar._waveAnim = bar.animate([
                        { transform: 'translateX(-100%)' },
                        { transform: 'translateX(100%)' }
                    ], {
                        duration: 1400,
                        iterations: Infinity,
                        easing: 'ease-in-out',
                        delay: (idx % 8) * 110
                    });
                });

                // 2. Web Animations API for Sonar Radar Rings
                const orangeRing = container.querySelector('.maxit-sonar-orange');
                if (orangeRing) {
                    if (orangeRing._sonarAnim) orangeRing._sonarAnim.cancel();
                    orangeRing._sonarAnim = orangeRing.animate([
                        { transform: 'scale(0.6)', opacity: 1, borderColor: 'rgba(255, 121, 0, 0.9)' },
                        { transform: 'scale(2.4)', opacity: 0, borderColor: 'rgba(255, 121, 0, 0)' }
                    ], {
                        duration: 1800,
                        iterations: Infinity,
                        easing: 'cubic-bezier(0.215, 0.61, 0.355, 1)'
                    });
                }

                const blueRing = container.querySelector('.maxit-sonar-blue');
                if (blueRing) {
                    if (blueRing._sonarAnim) blueRing._sonarAnim.cancel();
                    blueRing._sonarAnim = blueRing.animate([
                        { transform: 'scale(0.6)', opacity: 1, borderColor: 'rgba(37, 99, 235, 0.9)' },
                        { transform: 'scale(2.4)', opacity: 0, borderColor: 'rgba(37, 99, 235, 0)' }
                    ], {
                        duration: 1800,
                        iterations: Infinity,
                        easing: 'cubic-bezier(0.215, 0.61, 0.355, 1)',
                        delay: 600
                    });
                }

                // 3. Move loupe through card centers: 0 -> 1 -> 2 -> 5 -> 4 -> 3 -> 0
                const loupe = container.querySelector('.maxit-magnifier-spot');
                const points = [
                    { top: '20%', left: '17%' },
                    { top: '20%', left: '50%' },
                    { top: '20%', left: '83%' },
                    { top: '68%', left: '83%' },
                    { top: '68%', left: '50%' },
                    { top: '68%', left: '17%' }
                ];
                let step = 0;

                clearInterval(window.__maxitLoupeInterval);
                if (loupe) {
                    loupe.style.top = points[0].top;
                    loupe.style.left = points[0].left;
                    window.__maxitLoupeInterval = setInterval(() => {
                        step = (step + 1) % points.length;
                        loupe.style.top = points[step].top;
                        loupe.style.left = points[step].left;
                    }, 800);
                }

                clearTimeout(window.__maxitSkeletonTimer);
                window.__maxitSkeletonTimer = setTimeout(() => {
                    clearInterval(window.__maxitLoupeInterval);
                    container.classList.add('amb-fade-out');
                    setTimeout(() => {
                        container.style.display = 'none';
                    }, 500);
                }, 5000);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', launchMaxitSkeleton);
            } else {
                launchMaxitSkeleton();
            }

            document.addEventListener('livewire:navigated', launchMaxitSkeleton);
            document.addEventListener('livewire:init', function() {
                if (typeof Livewire !== 'undefined' && Livewire.hook) {
                    Livewire.hook('navigate', () => launchMaxitSkeleton());
                }
            });
        })();
    </script>
</div>
