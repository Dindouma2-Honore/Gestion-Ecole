import { useEffect, useRef, useCallback } from 'react'
import { motion, useAnimation, Variants } from 'framer-motion'

const AnimatedLoadingSkeleton = () => {
    const controls = useAnimation()
    const containerRef = useRef<HTMLDivElement | null>(null)
    const cardRefs = useRef<(HTMLDivElement | null)[]>([])

    const updatePath = useCallback(() => {
        if (!containerRef.current) return

        const containerRect = containerRef.current.getBoundingClientRect()
        // Scanning path through grid cards: 0 -> 1 -> 2 -> 5 -> 4 -> 3 -> 0
        const sequence = [0, 1, 2, 5, 4, 3, 0]
        const xPoints: number[] = []
        const yPoints: number[] = []
        const MAG_HALF = 32 // Half of 64px container size

        sequence.forEach((cardIdx) => {
            const cardEl = cardRefs.current[cardIdx]
            if (cardEl) {
                const rect = cardEl.getBoundingClientRect()
                xPoints.push(rect.left - containerRect.left + rect.width / 2 - MAG_HALF)
                yPoints.push(rect.top - containerRect.top + rect.height / 2 - MAG_HALF)
            }
        })

        if (xPoints.length > 0) {
            controls.start({
                x: xPoints,
                y: yPoints,
                transition: {
                    duration: 8,
                    repeat: Infinity,
                    ease: 'easeInOut',
                },
            })
        }
    }, [controls])

    useEffect(() => {
        const timer = setTimeout(() => {
            updatePath()
        }, 100)

        const handleResize = () => updatePath()
        window.addEventListener('resize', handleResize)

        return () => {
            clearTimeout(timer)
            window.removeEventListener('resize', handleResize)
        }
    }, [updatePath])

    const frameVariants: Variants = {
        hidden: { opacity: 0, scale: 0.98 },
        visible: { opacity: 1, scale: 1, transition: { duration: 0.5 } },
    }

    const cardVariants: Variants = {
        hidden: { y: 15, opacity: 0 },
        visible: (i: number) => ({
            y: 0,
            opacity: 1,
            transition: { delay: i * 0.08, duration: 0.4 },
        }),
    }

    return (
        <motion.div
            className="w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8 bg-[#f4f5f8] rounded-3xl border border-gray-200/70 shadow-sm"
            variants={frameVariants}
            initial="hidden"
            animate="visible"
        >
            <div ref={containerRef} className="relative overflow-hidden p-2 sm:p-4">
                {/* Search Magnifier with Radial Blue Glow */}
                <motion.div
                    className="absolute z-20 pointer-events-none top-0 left-0"
                    animate={controls}
                >
                    <motion.div
                        className="relative flex items-center justify-center w-16 h-16"
                        animate={{ scale: [1, 1.08, 1] }}
                        transition={{ duration: 1.5, repeat: Infinity, ease: 'easeInOut' }}
                    >
                        {/* Soft Outer Blue Aura */}
                        <div className="absolute inset-0 bg-blue-500/25 rounded-full blur-xl scale-150" />
                        <div className="absolute -inset-2 bg-gradient-to-tr from-blue-600/20 to-sky-400/20 rounded-full blur-md" />

                        {/* Lens Disc */}
                        <div className="relative w-14 h-14 rounded-full bg-blue-500/15 backdrop-blur-md border border-blue-400/40 flex items-center justify-center shadow-[0_0_25px_rgba(59,130,246,0.35)]">
                            <svg
                                className="w-6 h-6 text-blue-600"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2.5"
                                viewBox="0 0 24 24"
                            >
                                <circle cx="11" cy="11" r="6.5" />
                                <path strokeLinecap="round" d="M20 20l-4.2-4.2" />
                            </svg>
                        </div>
                    </motion.div>
                </motion.div>

                {/* 3x2 Skeleton Cards Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    {[...Array(6)].map((_, i) => (
                        <motion.div
                            key={i}
                            variants={cardVariants}
                            initial="hidden"
                            animate="visible"
                            custom={i}
                            className="bg-white rounded-2xl p-5 shadow-[0_2px_8px_rgba(0,0,0,0.02)] border border-gray-100 flex flex-col gap-3"
                        >
                            {/* Card Image Box */}
                            <div
                                ref={(el) => {
                                    cardRefs.current[i] = el
                                }}
                                className="w-full h-36 sm:h-40 bg-[#e5e7eb] rounded-xl relative overflow-hidden"
                            >
                                <motion.div
                                    className="absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent -translate-x-full"
                                    animate={{ translateX: ['-100%', '100%'] }}
                                    transition={{
                                        duration: 2,
                                        repeat: Infinity,
                                        ease: 'linear',
                                        delay: i * 0.15,
                                    }}
                                />
                            </div>

                            {/* Text Bar 1 */}
                            <div className="w-[70%] h-3.5 bg-[#e5e7eb] rounded-full relative overflow-hidden mt-1">
                                <motion.div
                                    className="absolute inset-0 bg-gradient-to-r from-transparent via-white/50 to-transparent -translate-x-full"
                                    animate={{ translateX: ['-100%', '100%'] }}
                                    transition={{
                                        duration: 2,
                                        repeat: Infinity,
                                        ease: 'linear',
                                        delay: i * 0.15 + 0.1,
                                    }}
                                />
                            </div>

                            {/* Text Bar 2 */}
                            <div className="w-[48%] h-3.5 bg-[#e5e7eb] rounded-full relative overflow-hidden">
                                <motion.div
                                    className="absolute inset-0 bg-gradient-to-r from-transparent via-white/50 to-transparent -translate-x-full"
                                    animate={{ translateX: ['-100%', '100%'] }}
                                    transition={{
                                        duration: 2,
                                        repeat: Infinity,
                                        ease: 'linear',
                                        delay: i * 0.15 + 0.2,
                                    }}
                                />
                            </div>
                        </motion.div>
                    ))}
                </div>
            </div>
        </motion.div>
    )
}

export default AnimatedLoadingSkeleton
