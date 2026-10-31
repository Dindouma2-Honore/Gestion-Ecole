<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bienvenue - Ambassadors Système</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <style>
        :root {
            --navy: #020b2f;
            --ink: #ffffff;
            --blue: #0a43b5;
            --red: #c9202f;
            --gold: #f0b93f;
        }

        * {
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at 50% 20%, #123c91 0, #06194e 35%, var(--navy) 75%);
        }

        .hero {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(22rem, .9fr);
            align-items: center;
            gap: clamp(2rem, 6vw, 6.5rem);
            width: min(1180px, calc(100% - 48px));
            min-height: 100vh;
            margin: 0 auto;
            padding: clamp(2rem, 6vw, 5rem) 0;
        }

        .hero::after {
            position: fixed;
            inset: 0;
            z-index: 0;
            content: "";
            pointer-events: none;
            opacity: .12;
            background-image:
                linear-gradient(#fff 1px, transparent 1px),
                linear-gradient(90deg, #fff 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: linear-gradient(to bottom, #000, transparent 82%);
        }

        .hero::before {
            position: absolute;
            inset: 7% auto auto -9%;
            width: 18rem;
            height: 18rem;
            content: "";
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 999px;
        }

        .copy {
            position: relative;
            z-index: 2;
            max-width: 610px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            margin: 0 0 1.35rem;
            color: var(--gold);
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            width: 2.7rem;
            height: 2px;
            content: "";
            background: var(--gold);
        }

        h1 {
            margin: 0;
            color: #ffffff;
            font-size: clamp(2.7rem, 7vw, 6.6rem);
            font-weight: 900;
            line-height: .95;
            letter-spacing: 0;
        }

        h1 span {
            display: block;
            color: #8fb7ff;
        }

        h1 strong {
            display: block;
            margin-top: .12em;
            color: var(--gold);
            font-family: Georgia, "Times New Roman", serif;
            font-size: .58em;
            font-weight: 800;
            line-height: 1.1;
        }

        .lead {
            max-width: 54rem;
            margin: 1.8rem 0 0;
            color: #c7d3ec;
            font-size: clamp(1.04rem, 2vw, 1.32rem);
            line-height: 1.7;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2.2rem;
        }

        .begin {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .8rem;
            min-height: 3.35rem;
            padding: 0 1.35rem;
            border: 1px solid rgba(10, 67, 181, .12);
            border-radius: 8px;
            color: #fff;
            background: linear-gradient(135deg, var(--blue), #06276f);
            box-shadow: 0 18px 42px rgba(0, 0, 0, .28), 0 0 0 1px rgba(255, 255, 255, .08);
            font-weight: 800;
            text-decoration: none;
        }

        .begin svg {
            width: 1.15rem;
            height: 1.15rem;
        }

        .motto {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            min-height: 3.35rem;
            color: var(--gold);
            font-weight: 800;
        }

        .motto::before {
            width: .55rem;
            height: .55rem;
            content: "";
            border-radius: 999px;
            background: var(--gold);
            box-shadow: 0 0 0 6px rgba(240, 185, 63, .18);
        }

        .visual {
            position: relative;
            display: grid;
            place-items: center;
            min-height: min(38rem, 70vh);
        }

        .orbit {
            position: absolute;
            width: min(37rem, 84vw);
            aspect-ratio: 1;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 999px;
            animation: float 7s ease-in-out infinite;
        }

        .orbit::before,
        .orbit::after {
            position: absolute;
            content: "";
            border-radius: 999px;
        }

        .orbit::before {
            inset: 9%;
            border: 1px dashed rgba(201, 32, 47, .19);
        }

        .orbit::after {
            top: 8%;
            right: 14%;
            width: .9rem;
            height: .9rem;
            background: var(--gold);
            box-shadow: -19rem 15rem 0 -.2rem rgba(10, 67, 181, .8);
        }

        .logo-shell {
            position: relative;
            display: grid;
            place-items: center;
            width: min(31rem, 74vw);
            aspect-ratio: 1;
            border-radius: 999px;
            perspective: 1200px;
            background:
                radial-gradient(circle at 45% 40%, rgba(255, 255, 255, .98), rgba(255, 255, 255, .78) 45%, rgba(10, 67, 181, .18) 72%, rgba(240, 185, 63, .22));
            box-shadow:
                0 2rem 5rem rgba(0, 0, 0, .34),
                inset 0 0 0 1px rgba(255, 255, 255, .78);
        }

        .logo-shell::before {
            position: absolute;
            inset: -1.1rem;
            content: "";
            border-radius: inherit;
            background: conic-gradient(from 120deg, rgba(10, 67, 181, .05), rgba(10, 67, 181, .55), rgba(240, 185, 63, .75), rgba(201, 32, 47, .5), rgba(10, 67, 181, .05));
            filter: blur(18px);
            opacity: .75;
            animation: spin 13s linear infinite;
        }

        .logo {
            position: relative;
            z-index: 1;
            width: 82%;
            height: auto;
            aspect-ratio: 1;
            object-fit: contain;
            transform-style: preserve-3d;
            filter: drop-shadow(0 1rem 1.7rem rgba(1, 8, 44, .22));
            animation: logo-planet-spin 22s linear infinite;
            backface-visibility: visible;
            will-change: transform;
        }

        .values {
            position: absolute;
            right: 0;
            bottom: clamp(1rem, 5vh, 4rem);
            left: 0;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .85rem;
            max-width: 36rem;
            margin: 0 auto;
        }

        .value {
            display: grid;
            place-items: center;
            min-height: 3.35rem;
            padding: .7rem .85rem;
            border: 1px solid rgba(10, 67, 181, .1);
            border-radius: 8px;
            color: var(--blue);
            background: rgba(255, 255, 255, .9);
            box-shadow: 0 .8rem 1.8rem rgba(0, 0, 0, .16);
            font-size: .88rem;
            font-weight: 900;
            backdrop-filter: blur(14px);
        }

        @keyframes logo-planet-spin {
            to {
                transform: rotateY(-360deg);
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-1rem);
            }
        }

        @media (max-width: 900px) {
            .hero {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                width: min(100% - 32px, 42rem);
                padding-top: 2rem;
                text-align: center;
            }

            .copy {
                margin: 0 auto;
            }

            .eyebrow {
                justify-content: center;
            }

            .actions {
                justify-content: center;
            }

            .visual {
                min-height: 28rem;
            }

            .values {
                position: relative;
                bottom: auto;
                margin-top: -2rem;
            }
        }

        @media (max-width: 560px) {
            .hero {
                width: min(100% - 24px, 28rem);
                padding-bottom: 1.5rem;
            }

            h1 {
                font-size: clamp(2.35rem, 15vw, 4.2rem);
            }

            .lead {
                font-size: 1rem;
            }

            .begin {
                width: 100%;
            }

            .motto {
                width: 100%;
                justify-content: center;
                min-height: auto;
            }

            .visual {
                min-height: 23rem;
            }

            .values {
                grid-template-columns: 1fr;
                max-width: 18rem;
                margin-top: -1.2rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .logo,
            .logo-shell::before,
            .orbit {
                animation: none;
            }
        }
    </style>
</head>
<body>
    <main class="hero" aria-labelledby="welcome-title">
        <section class="copy">
            <p class="eyebrow">Faith - Vision - Discipline</p>
            <h1 id="welcome-title">
                Bienvenue sur
                <span>Ambassadors</span>
                <strong>Système</strong>
            </h1>
            <p class="lead">
                Votre espace de pilotage scolaire pour suivre les élèves, les enseignants,
                les finances, la pédagogie et la vie de l'établissement avec clarté.
            </p>
            <div class="actions">
                <a class="begin" href="{{ url('/accueil') }}">
                    Entrer dans la plateforme
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 12h14m-6-6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <span class="motto">Foi, Vision, Discipline</span>
            </div>
        </section>

        <section class="visual" aria-label="Logo Ambassadors Educational Complex animé">
            <div class="orbit" aria-hidden="true"></div>
            <div class="logo-shell">
                <img class="logo" src="{{ asset('images/logo.png') }}" alt="Ambassadors Educational Complex">
            </div>
            <div class="values" aria-hidden="true">
                <span class="value">Foi</span>
                <span class="value">Vision</span>
                <span class="value">Discipline</span>
            </div>
        </section>
    </main>
</body>
</html>
