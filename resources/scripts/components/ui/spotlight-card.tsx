import React, { ReactNode, useEffect, useRef } from 'react';

interface GlowCardProps {
    children?: ReactNode;
    className?: string;
    glowColor?: 'blue' | 'purple' | 'green' | 'red' | 'orange';
    size?: 'sm' | 'md' | 'lg';
    width?: string | number;
    height?: string | number;
    customSize?: boolean;
    orbit?: boolean;
    orbitDurationMs?: number;
    orbitDirection?: 1 | -1;
    orbitStartOffset?: number;
    activeFrom?: number;
    activeTo?: number;
    activeFade?: number;
    hoverGlow?: boolean;
}

const glowColorMap = {
    blue: { base: 220, spread: 200 },
    purple: { base: 280, spread: 300 },
    green: { base: 83, spread: 0 },
    red: { base: 0, spread: 200 },
    orange: { base: 30, spread: 200 },
};

const sizeMap = {
    sm: 'w-48 h-64',
    md: 'w-64 h-80',
    lg: 'w-80 h-96',
};

const GlowCard: React.FC<GlowCardProps> = ({
    children,
    className = '',
    glowColor = 'blue',
    size = 'md',
    width,
    height,
    customSize = false,
    orbit = true,
    orbitDurationMs = 3200,
    orbitDirection = 1,
    orbitStartOffset = 0,
    activeFrom = 0,
    activeTo = 1,
    activeFade = 0.05,
    hoverGlow = false,
}) => {
    const cardRef = useRef<HTMLDivElement>(null);
    const innerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const clamp01 = (n: number) => Math.max(0, Math.min(1, n));
        const smoothstep = (edge0: number, edge1: number, x: number) => {
            const t = clamp01((x - edge0) / Math.max(0.0001, edge1 - edge0));
            return t * t * (3 - 2 * t);
        };
        const inWindow = (phase: number, start: number, end: number) =>
            start <= end ? phase >= start && phase <= end : phase >= start || phase <= end;
        const distanceToWindowEdge = (phase: number, start: number, end: number) => {
            const cyclicDist = (a: number, b: number) => {
                const d = Math.abs(a - b);
                return Math.min(d, 1 - d);
            };
            if (!inWindow(phase, start, end)) return 0;
            const dStart = cyclicDist(phase, start);
            const dEnd = cyclicDist(phase, end);
            return Math.min(dStart, dEnd);
        };

        if (!orbit) {
            const syncPointer = (e: PointerEvent) => {
                const { clientX: x, clientY: y } = e;
                if (!cardRef.current) return;
                cardRef.current.style.setProperty('--x', x.toFixed(2));
                cardRef.current.style.setProperty('--xp', (x / window.innerWidth).toFixed(2));
                cardRef.current.style.setProperty('--y', y.toFixed(2));
                cardRef.current.style.setProperty('--yp', (y / window.innerHeight).toFixed(2));
            };

            document.addEventListener('pointermove', syncPointer);
            return () => document.removeEventListener('pointermove', syncPointer);
        }

        let frame = 0;
        let raf = 0;

        const animate = () => {
            frame += 16.67;
            const node = cardRef.current;
            if (node) {
                const rect = node.getBoundingClientRect();
                const phase = ((frame % orbitDurationMs) / orbitDurationMs + orbitStartOffset) % 1;
                const t = phase * Math.PI * 2 * orbitDirection;
                const cx = rect.left + rect.width / 2;
                const cy = rect.top + rect.height / 2;
                const rx = Math.max(16, rect.width / 2 + 2);
                const ry = Math.max(16, rect.height / 2 + 2);
                const x = cx + Math.cos(t) * rx;
                const y = cy + Math.sin(t) * ry;
                const visible = inWindow(phase, activeFrom, activeTo);
                const edgeDist = distanceToWindowEdge(phase, activeFrom, activeTo);
                const alpha = visible ? smoothstep(0, activeFade, edgeDist) : 0;

                node.style.setProperty('--x', x.toFixed(2));
                node.style.setProperty('--xp', (x / window.innerWidth).toFixed(2));
                node.style.setProperty('--y', y.toFixed(2));
                node.style.setProperty('--yp', (y / window.innerHeight).toFixed(2));
                node.style.setProperty('--track-alpha', alpha.toFixed(3));
            }

            raf = requestAnimationFrame(animate);
        };

        raf = requestAnimationFrame(animate);

        return () => cancelAnimationFrame(raf);
    }, [orbit, orbitDurationMs, orbitDirection, orbitStartOffset, activeFrom, activeTo, activeFade]);

    const { base, spread } = glowColorMap[glowColor];

    const getSizeClasses = () => {
        if (customSize) return '';
        return sizeMap[size];
    };

    const getInlineStyles = (): React.CSSProperties & Record<string, string | number> => {
        const baseStyles: React.CSSProperties & Record<string, string | number> = {
            '--base': base,
            '--spread': spread,
            '--radius': '14',
            '--border': '2',
            '--backdrop': 'hsl(0 0% 0% / 1)',
            '--backup-border': 'hsl(0 0% 15% / 1)',
            '--size': '180',
            '--outer': '1',
            '--saturation': '100',
            '--lightness': '53',
            '--bg-spot-opacity': '0.22',
            '--border-spot-opacity': '1',
            '--border-light-opacity': '0.88',
            '--border-size': 'calc(var(--border, 2) * 1px)',
            '--spotlight-size': 'calc(var(--size, 150) * 1px)',
            '--hue': 'calc(var(--base) + (var(--xp, 0) * var(--spread, 0)))',
            '--track-alpha': '1',
            backgroundImage: `radial-gradient(
                var(--spotlight-size) var(--spotlight-size) at
                calc(var(--x, 0) * 1px)
                calc(var(--y, 0) * 1px),
                hsl(var(--hue, 120) calc(var(--saturation, 100) * 1%) calc(var(--lightness, 60) * 1%) / calc(var(--bg-spot-opacity, 0.16) * var(--track-alpha, 1) * var(--glow-enabled, 1))), transparent
            )`,
            backgroundColor: 'var(--backdrop, transparent)',
            backgroundSize: 'calc(100% + (2 * var(--border-size))) calc(100% + (2 * var(--border-size)))',
            backgroundPosition: '50% 50%',
            backgroundAttachment: 'fixed',
            border: 'var(--border-size) solid var(--backup-border)',
            position: 'relative',
            touchAction: 'none',
        };

        if (!hoverGlow) {
            baseStyles['--glow-enabled'] = '1';
        }

        if (width !== undefined) baseStyles.width = typeof width === 'number' ? `${width}px` : width;
        if (height !== undefined) baseStyles.height = typeof height === 'number' ? `${height}px` : height;

        return baseStyles;
    };

    const beforeAfterStyles = `
        [data-glow]::before,
        [data-glow]::after {
            pointer-events: none;
            content: '';
            position: absolute;
            inset: calc(var(--border-size) * -1);
            border: var(--border-size) solid transparent;
            border-radius: calc(var(--radius) * 1px);
            background-attachment: fixed;
            background-size: calc(100% + (2 * var(--border-size))) calc(100% + (2 * var(--border-size)));
            background-repeat: no-repeat;
            background-position: 50% 50%;
            -webkit-mask: linear-gradient(transparent, transparent), linear-gradient(white, white);
            -webkit-mask-clip: padding-box, border-box;
            -webkit-mask-composite: xor;
            mask: linear-gradient(transparent, transparent), linear-gradient(white, white);
            mask-clip: padding-box, border-box;
            mask-composite: intersect;
        }

        [data-glow]::before {
            background-image: radial-gradient(
                calc(var(--spotlight-size) * 0.78) calc(var(--spotlight-size) * 0.78) at
                calc(var(--x, 0) * 1px)
                calc(var(--y, 0) * 1px),
                hsl(var(--hue, 83) calc(var(--saturation, 100) * 1%) 53% / var(--border-spot-opacity, 1)), transparent 100%
            );
            filter: brightness(1.2) drop-shadow(0 0 14px hsl(var(--hue, 83) 100% 53% / 0.95));
            opacity: calc(var(--track-alpha, 1) * var(--glow-enabled, 1));
        }

        [data-glow]::after {
            background-image: radial-gradient(
                calc(var(--spotlight-size) * 0.5) calc(var(--spotlight-size) * 0.5) at
                calc(var(--x, 0) * 1px)
                calc(var(--y, 0) * 1px),
                hsl(0 100% 100% / var(--border-light-opacity, 0.75)), transparent 100%
            );
            filter: blur(0.4px);
            opacity: calc(var(--track-alpha, 1) * var(--glow-enabled, 1));
        }

        [data-glow] [data-glow] {
            position: absolute;
            inset: 0;
            will-change: filter;
            opacity: var(--outer, 1);
            border-radius: calc(var(--radius) * 1px);
            border-width: calc(var(--border-size) * 20);
            filter: blur(calc(var(--border-size) * 10));
            background: none;
            pointer-events: none;
            border: none;
        }

        [data-glow] > [data-glow]::before {
            inset: -10px;
            border-width: 10px;
        }

        [data-glow][data-hover-glow='true'] {
            --glow-enabled: 0;
        }

        [data-glow][data-hover-glow='true']:hover {
            --glow-enabled: 1;
        }
    `;

    return (
        <>
            <style dangerouslySetInnerHTML={{ __html: beforeAfterStyles }} />
            <div
                ref={cardRef}
                data-glow
                data-hover-glow={hoverGlow ? 'true' : 'false'}
                style={getInlineStyles()}
                className={`
                    ${getSizeClasses()}
                    ${!customSize ? 'aspect-[3/4]' : ''}
                    rounded-2xl
                    relative
                    p-0
                    shadow-[0_1rem_2rem_-1rem_black]
                    backdrop-blur-[5px]
                    ${className}
                `}
            >
                <div ref={innerRef} data-glow />
                <div className='relative z-[1] h-full w-full'>{children}</div>
            </div>
        </>
    );
};

export { GlowCard };
