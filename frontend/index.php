<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Usuario autenticado
|--------------------------------------------------------------------------
|
| El landing solo se muestra a visitantes.
| Si ya existe una sesión, enviamos al usuario a su panel.
|
*/

if (is_authenticated()) {

    if (is_admin()) {
        redirect_to('/frontend/admin/dashboard.php');
    }

    redirect_to('/frontend/conductor/dashboard.php');
}

/*
|--------------------------------------------------------------------------
| Mensajes y modo inicial del modal
|--------------------------------------------------------------------------
*/

$flash = consume_flash();

$authMode = (
    isset($_GET['auth'])
    && $_GET['auth'] === 'register'
)
    ? 'register'
    : 'login';

?>
<!doctype html>
<html lang="es">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        DEVIOZ PARKING | Tu lugar, en el momento exacto
    </title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <!-- CSS global -->
    <link
        href="<?= htmlspecialchars(
            app_url('/frontend/assets/css/styles.css'),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        rel="stylesheet"
    >

    <style>

        :root {
            --landing-bg: #070c18;
            --landing-panel: #101c38;
            --landing-border: #1c2c4d;
            --landing-text: #eaf1fb;
            --landing-muted: #94a3b8;
            --landing-muted-2: #64748b;

            --landing-gold: #ffb020;
            --landing-gold-dark: #c98a12;

            --landing-cyan: #3ddcff;
            --landing-green: #2ee59d;
        }


        html {
            scroll-behavior: smooth;
        }


        body {
            margin: 0;
            background: var(--landing-bg);
            color: var(--landing-text);

            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        body.landing-modal-open {
            overflow: hidden;
        }


        .landing-page {
            min-height: 100vh;
            overflow-x: hidden;

            background:
                radial-gradient(
                    circle at 20% 15%,
                    rgba(61, 220, 255, 0.08),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 80% 12%,
                    rgba(255, 176, 32, 0.08),
                    transparent 24%
                ),
                var(--landing-bg);
        }


        .landing-container {
            width: min(1180px, calc(100% - 40px));
            margin: 0 auto;
        }


        /*
        |--------------------------------------------------------------------------
        | Navbar
        |--------------------------------------------------------------------------
        */

        .landing-navbar {
            position: fixed;

            top: 0;
            right: 0;
            left: 0;

            z-index: 1040;

            transition:
                background-color .25s ease,
                box-shadow .25s ease,
                backdrop-filter .25s ease;
        }


        .landing-navbar.scrolled {
            background: rgba(7, 12, 24, 0.86);

            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);

            border-bottom:
                1px solid rgba(28, 44, 77, .75);

            box-shadow:
                0 10px 30px rgba(0, 0, 0, .18);
        }


        .landing-navbar-inner {
            min-height: 74px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 22px;
        }


        .landing-brand {
            display: inline-flex;

            align-items: center;

            gap: 10px;

            text-decoration: none;

            color: #ffffff;

            font-size: .9rem;
            font-weight: 900;

            letter-spacing: .08em;
        }


        .landing-brand:hover {
            color: #ffffff;
        }


        .landing-brand-mark {
            width: 34px;
            height: 34px;

            display: grid;
            place-items: center;

            border-radius: 10px;

            color: #1a1200;

            background:
                linear-gradient(
                    135deg,
                    var(--landing-gold),
                    var(--landing-gold-dark)
                );

            box-shadow:
                0 10px 28px rgba(255, 176, 32, .2);
        }


        .landing-nav-links {
            display: flex;

            align-items: center;

            gap: 28px;
        }


        .landing-nav-links a {
            color: #a8b3c7;

            text-decoration: none;

            font-size: .78rem;
            font-weight: 700;

            letter-spacing: .12em;

            text-transform: uppercase;

            transition: color .2s ease;
        }


        .landing-nav-links a:hover {
            color: var(--landing-gold);
        }


        .landing-auth-actions {
            display: flex;

            align-items: center;

            gap: 12px;
        }


        .btn-auth-link,
        .btn-auth-primary {
            border: 0;

            border-radius: 999px;

            font-size: .86rem;
            font-weight: 700;

            transition:
                transform .2s ease,
                background-color .2s ease,
                color .2s ease;
        }


        .btn-auth-link {
            padding: 10px 12px;

            color: #d2d9e5;

            background: transparent;
        }


        .btn-auth-link:hover {
            color: var(--landing-gold);
        }


        .btn-auth-primary {
            padding: 10px 19px;

            color: #1a1200;

            background: var(--landing-gold);
        }


        .btn-auth-primary:hover {
            color: #1a1200;

            background: var(--landing-gold-dark);

            transform: translateY(-1px);
        }


        .landing-menu-btn {
            display: none;

            border: 0;

            background: transparent;

            color: #cbd5e1;

            font-size: 1.45rem;
        }


        .landing-mobile-menu {
            display: none;

            border-top:
                1px solid var(--landing-border);

            background:
                rgba(7, 12, 24, .97);

            backdrop-filter: blur(16px);
        }


        .landing-mobile-menu.open {
            display: block;
        }


        .landing-mobile-menu-inner {
            width:
                min(1180px, calc(100% - 40px));

            margin: 0 auto;

            padding: 16px 0 20px;

            display: grid;

            gap: 10px;
        }


        .landing-mobile-menu a {
            padding: 8px 0;

            color: #a8b3c7;

            text-decoration: none;

            font-size: .78rem;

            letter-spacing: .1em;

            text-transform: uppercase;
        }


        .landing-mobile-auth {
            margin-top: 4px;

            padding-top: 12px;

            display: grid;

            gap: 10px;

            border-top:
                1px solid var(--landing-border);
        }


        /*
        |--------------------------------------------------------------------------
        | Hero
        |--------------------------------------------------------------------------
        */

        .landing-hero {
            min-height: 100vh;

            position: relative;

            display: grid;
            place-items: center;

            padding:
                120px 20px 80px;

            overflow: hidden;

            background:
                #070c18;
        }


        .landing-hero-image {
            position: absolute;

            inset: 0;

            width: 100%;
            height: 100%;

            object-fit: cover;

            z-index: 0;
        }


        .landing-hero::before {
            content: "";

            z-index: 1;

            position: absolute;

            inset: 0;

            background:

                linear-gradient(
                    180deg,
                    rgba(7, 12, 24, .30) 0%,
                    rgba(7, 12, 24, .45) 55%,
                    rgba(7, 12, 24, .92) 100%
                ),

                radial-gradient(
                    circle at 22% 16%,
                    rgba(61, 220, 255, .12),
                    transparent 24%
                ),

                linear-gradient(
                    90deg,
                    rgba(7, 12, 24, .48) 0%,
                    rgba(7, 12, 24, .18) 36%,
                    rgba(7, 12, 24, .42) 100%
                );
        }


        .landing-hero-grid {
            position: absolute;

            z-index: 1;

            inset: 0;

            opacity: .18;

            background-image:

                linear-gradient(
                    rgba(255, 255, 255, .05) 1px,
                    transparent 1px
                ),

                linear-gradient(
                    90deg,
                    rgba(255, 255, 255, .05) 1px,
                    transparent 1px
                );

            background-size:
                58px 58px;

            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, .9),
                    transparent 82%
                );
        }


        .landing-hero-content {
            position: relative;

            z-index: 2;

            max-width: 900px;

            text-align: center;
        }


        .landing-eyebrow {
            margin-bottom: 22px;

            color: var(--landing-gold);

            font-size: .78rem;
            font-weight: 800;

            letter-spacing: .28em;

            text-transform: uppercase;
        }


        .landing-hero h1 {
            margin: 0;

            color: #ffffff;

            font-size:
                clamp(3rem, 8vw, 6.8rem);

            line-height: .94;

            font-weight: 950;

            letter-spacing: -.055em;

            text-shadow:
                0 0 45px
                rgba(255, 176, 32, .11);
        }


        .landing-hero h1 span {
            color: var(--landing-gold);
        }


        .landing-hero-subtitle {
            max-width: 660px;

            margin:
                28px auto 8px;

            color: #cbd5e1;

            font-size:
                clamp(1rem, 2vw, 1.2rem);
        }


        .landing-hero-meta {
            margin: 0;

            color: var(--landing-cyan);

            font-size: .82rem;
            font-weight: 700;

            letter-spacing: .14em;

            text-transform: uppercase;
        }


        .landing-hero-actions {
            margin-top: 38px;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 12px;

            flex-wrap: wrap;
        }


        .landing-cta-primary,
        .landing-cta-secondary {
            border-radius: 999px;

            padding:
                12px 25px;

            font-size: .8rem;
            font-weight: 800;

            letter-spacing: .12em;

            text-transform: uppercase;

            text-decoration: none;

            transition:
                transform .2s ease,
                border-color .2s ease,
                color .2s ease,
                background-color .2s ease;
        }


        .landing-cta-primary {
            color: #1a1200;

            background:
                var(--landing-gold);

            border:
                1px solid var(--landing-gold);
        }


        .landing-cta-primary:hover {
            color: #1a1200;

            background:
                var(--landing-gold-dark);

            border-color:
                var(--landing-gold-dark);

            transform:
                translateY(-2px);
        }


        .landing-cta-secondary {
            color: #cbd5e1;

            border:
                1px solid #475569;

            background:
                rgba(255, 255, 255, .02);
        }


        .landing-cta-secondary:hover {
            color:
                var(--landing-gold);

            border-color:
                var(--landing-gold);

            transform:
                translateY(-2px);
        }


        /*
        |--------------------------------------------------------------------------
        | Secciones generales
        |--------------------------------------------------------------------------
        */

        .landing-section {
            position: relative;

            padding:
                100px 0;
        }


        .landing-divider {
            width:
                min(760px, calc(100% - 40px));

            height: 1px;

            margin:
                0 auto;

            opacity: .35;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    var(--landing-gold),
                    transparent
                );
        }


        .landing-section-heading {
            max-width: 720px;

            margin:
                0 auto 50px;

            text-align: center;
        }


        .landing-accent-line {
            width: 58px;
            height: 3px;

            margin:
                0 auto 20px;

            background:
                linear-gradient(
                    90deg,
                    var(--landing-gold),
                    transparent
                );
        }


        .landing-section-heading h2,
        .landing-two-col h2 {
            margin-bottom: 18px;

            color: #ffffff;

            font-size:
                clamp(2rem, 4vw, 3rem);

            font-weight: 900;

            letter-spacing: -.03em;
        }


        .landing-section-heading p,
        .landing-two-col p {
            color:
                var(--landing-muted);

            line-height: 1.8;
        }


        /*
        |--------------------------------------------------------------------------
        | Qué es Devioz
        |--------------------------------------------------------------------------
        */

        .landing-two-col {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 70px;

            align-items: center;
        }


        .landing-highlight {
            color:
                var(--landing-gold);

            font-weight: 800;
        }


        .landing-about-visual {
            min-height: 420px;

            position: relative;

            overflow: hidden;

            border:
                1px solid var(--landing-border);

            border-radius: 22px;

            background:
                rgba(11, 18, 34, .7);

            box-shadow:
                0 28px 70px rgba(0, 0, 0, .18);
        }


        .landing-about-visual::after {
            content: "";

            position: absolute;

            inset: 0;

            background:
                linear-gradient(
                    180deg,
                    rgba(7, 12, 24, .06),
                    rgba(7, 12, 24, .14)
                );
        }


        .landing-about-image {
            position: relative;

            display: block;

            width: 100%;
            height: 100%;

            object-fit: cover;
        }


        /*
        |--------------------------------------------------------------------------
        | Cómo funciona
        |--------------------------------------------------------------------------
        */

        .landing-steps {
            position: relative;

            max-width: 920px;

            margin: 0 auto;
        }


        .landing-steps::before {
            content: "";

            position: absolute;

            top: 8px;
            bottom: 8px;
            left: 50%;

            width: 1px;

            background:
                linear-gradient(
                    to bottom,
                    var(--landing-gold),
                    var(--landing-border),
                    transparent
                );
        }


        .landing-step {
            position: relative;

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 50px;

            margin-bottom: 58px;
        }


        .landing-step:last-child {
            margin-bottom: 0;
        }


        .landing-step-card {
            padding: 26px;

            border:
                1px solid
                var(--landing-border);

            border-radius: 18px;

            background:
                rgba(16, 28, 56, .45);
        }


        .landing-step:nth-child(odd)
        .landing-step-card {
            grid-column: 1;

            text-align: right;
        }


        .landing-step:nth-child(even)
        .landing-step-card {
            grid-column: 2;
        }


        .landing-step-number {
            margin-bottom: 10px;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            color:
                var(--landing-gold);

            font-size: .78rem;
            font-weight: 800;

            letter-spacing: .1em;

            text-transform: uppercase;
        }


        .landing-step-card h3 {
            color: #ffffff;

            font-size: 1.25rem;
            font-weight: 800;
        }


        .landing-step-card p {
            margin: 0;

            color:
                var(--landing-muted);

            line-height: 1.7;

            font-size: .92rem;
        }


        /*
        |--------------------------------------------------------------------------
        | Tecnología
        |--------------------------------------------------------------------------
        */

        .landing-feature-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }


        .landing-feature-card {
            min-height: 230px;

            padding: 28px;

            border:
                1px solid
                var(--landing-border);

            border-radius: 20px;

            background:
                rgba(16, 28, 56, .38);

            transition:
                transform .25s ease,
                box-shadow .25s ease,
                border-color .25s ease;
        }


        .landing-feature-card:hover {
            transform:
                translateY(-5px);

            border-color:
                rgba(255, 176, 32, .35);

            box-shadow:
                0 22px 45px
                rgba(0, 0, 0, .18);
        }


        .landing-feature-icon {
            width: 54px;
            height: 54px;

            margin-bottom: 22px;

            display: grid;
            place-items: center;

            border-radius: 14px;

            color:
                var(--landing-gold);

            background:
                rgba(255, 176, 32, .10);

            font-size: 1.35rem;
        }


        .landing-feature-card:nth-child(2)
        .landing-feature-icon {
            color:
                var(--landing-cyan);

            background:
                rgba(61, 220, 255, .10);
        }


        .landing-feature-card:nth-child(3)
        .landing-feature-icon {
            color:
                var(--landing-green);

            background:
                rgba(46, 229, 157, .10);
        }


        .landing-feature-card h3 {
            color: #ffffff;

            font-size: 1.15rem;
            font-weight: 800;
        }


        .landing-feature-card p {
            margin-bottom: 0;

            color:
                var(--landing-muted);

            line-height: 1.7;

            font-size: .9rem;
        }


        /*
        |--------------------------------------------------------------------------
        | Seguridad
        |--------------------------------------------------------------------------
        */

        .landing-security-panel {
            display: grid;

            grid-template-columns:
                1.05fr .95fr;

            gap: 28px;

            align-items: stretch;
        }


        .landing-camera-demo,
        .landing-security-copy {
            overflow: hidden;

            border:
                1px solid
                var(--landing-border);

            border-radius: 22px;

            background:
                rgba(16, 28, 56, .42);

            box-shadow:
                0 22px 40px rgba(2, 6, 23, .18);
        }


        .landing-camera-demo {
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }


        .landing-camera-topbar {
            min-height: 48px;

            padding:
                0 16px;

            display: flex;

            align-items: center;

            gap: 10px;

            border-bottom:
                1px solid
                var(--landing-border);

            background:
                #101c38;
        }


        .landing-dot {
            width: 9px;
            height: 9px;

            border-radius: 50%;

            background:
                #334155;
        }


        .landing-camera-screen {
            position: relative;

            min-height: 360px;

            overflow: hidden;

            padding: 0;
        }


        .landing-camera-image {
            display: block;

            width: 100%;
            height: 100%;

            min-height: 360px;

            object-fit: cover;
        }


        .landing-camera-overlay {
            position: absolute;

            inset: 0;

            display: flex;

            flex-direction: column;

            align-items: flex-start;
            justify-content: flex-end;

            padding: 24px 28px;

            text-align: left;

            background:
                linear-gradient(
                    180deg,
                    rgba(7, 12, 24, .08) 0%,
                    rgba(7, 12, 24, .72) 100%
                );
        }


        .landing-camera-overlay i {
            margin-bottom: 12px;

            color:
                var(--landing-cyan);

            font-size: 2.2rem;
        }


        .landing-camera-overlay strong {
            display: block;

            color: #ffffff;

            font-size:
                clamp(1.4rem, 2vw, 1.9rem);

            font-weight: 900;
        }


        .landing-camera-overlay span {
            display: block;

            max-width: 500px;

            margin-top: 8px;

            color: #d1d5db;

            line-height: 1.6;
        }


        .landing-security-copy {
            display: flex;
            flex-direction: column;
            justify-content: center;

            padding: 34px;
        }


        .landing-security-copy h2 {
            margin-bottom: 18px;

            color: #ffffff;

            font-size: 2.2rem;
            font-weight: 900;
        }


        .landing-security-copy p {
            color:
                var(--landing-muted);

            line-height: 1.8;
        }


        .landing-security-list {
            margin-top: 24px;

            display: grid;

            gap: 12px;
        }


        .landing-security-item {
            display: flex;

            align-items: flex-start;

            gap: 10px;

            color: #cbd5e1;

            font-size: .92rem;
        }


        .landing-security-item i {
            margin-top: 2px;

            color:
                var(--landing-green);
        }


        /*
        |--------------------------------------------------------------------------
        | Proyecto
        |--------------------------------------------------------------------------
        */

        .landing-project-card {
            max-width: 960px;

            margin: 0 auto;

            padding: 34px;

            border:
                1px solid
                var(--landing-border);

            border-radius: 22px;

            background:
                rgba(16, 28, 56, .35);
        }


        .landing-project-grid {
            display: grid;

            grid-template-columns: 1fr;

            gap: 18px;
        }


        .landing-project-card h3 {
            color:
                var(--landing-gold);

            font-size: 1.5rem;
            font-weight: 900;
        }


        .landing-project-card p {
            color:
                var(--landing-muted);

            line-height: 1.8;
        }


        /*
        |--------------------------------------------------------------------------
        | CTA final
        |--------------------------------------------------------------------------
        */

        .landing-final-cta {
            position: relative;

            overflow: hidden;

            padding:
                110px 20px;

            text-align: center;

            background:

                radial-gradient(
                    circle at 50% 30%,
                    rgba(255, 176, 32, .12),
                    transparent 34%
                ),

                linear-gradient(
                    to bottom,
                    var(--landing-bg),
                    #0b1326,
                    var(--landing-bg)
                );
        }


        .landing-final-cta h2 {
            color: #ffffff;

            font-size:
                clamp(2.2rem, 5vw, 4rem);

            font-weight: 950;

            letter-spacing: -.04em;
        }


        .landing-final-cta h2 span {
            color:
                var(--landing-gold);
        }


        .landing-final-cta p {
            max-width: 620px;

            margin:
                18px auto 30px;

            color:
                var(--landing-muted);
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .landing-footer {
            padding:
                34px 0;

            border-top:
                1px solid
                var(--landing-border);
        }


        .landing-footer-inner {
            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 24px;

            flex-wrap: wrap;
        }


        .landing-footer small {
            color:
                var(--landing-muted-2);
        }


        .landing-footer a {
            color:
                var(--landing-muted);

            text-decoration: none;

            font-size: .8rem;
            font-weight: 700;

            letter-spacing: .08em;

            text-transform: uppercase;
        }


        .landing-footer a:hover {
            color:
                var(--landing-gold);
        }


        /*
        |--------------------------------------------------------------------------
        | Animaciones
        |--------------------------------------------------------------------------
        */

        .landing-fade {
            opacity: 0;

            transform:
                translateY(26px);

            transition:
                opacity .6s ease,
                transform .6s ease;
        }


        .landing-fade.visible {
            opacity: 1;

            transform:
                translateY(0);
        }


        /*
        |--------------------------------------------------------------------------
        | Modal autenticación
        |--------------------------------------------------------------------------
        */

        .landing-auth-modal {
            position: fixed;

            inset: 0;

            z-index: 1100;

            display: none;

            align-items: center;
            justify-content: center;

            padding: 18px;

            background:
                rgba(7, 12, 24, .90);

            backdrop-filter:
                blur(10px);
        }


        .landing-auth-modal.open {
            display: flex;
        }


        .landing-auth-card {
            width:
                min(460px, 100%);

            max-height:
                calc(100vh - 36px);

            overflow-y: auto;

            border:
                1px solid
                var(--landing-border);

            border-radius: 22px;

            background:
                #101c38;

            box-shadow:
                0 30px 90px
                rgba(0, 0, 0, .42);
        }


        .landing-auth-card-header {
            padding:
                22px 24px 16px;

            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 14px;
        }


        .landing-auth-tabs {
            display: flex;

            gap: 8px;

            flex-wrap: wrap;
        }


        .landing-auth-tab {
            padding:
                8px 13px;

            border: 0;

            border-radius: 999px;

            color: #94a3b8;

            background:
                transparent;

            font-size: .72rem;
            font-weight: 800;

            letter-spacing: .08em;

            text-transform: uppercase;
        }


        .landing-auth-tab.active {
            color: #1a1200;

            background:
                var(--landing-gold);
        }


        .landing-auth-close {
            border: 0;

            background:
                transparent;

            color: #94a3b8;

            font-size: 1.25rem;
        }


        .landing-auth-body {
            padding:
                8px 24px 26px;
        }


        .landing-auth-intro {
            margin-bottom: 20px;
        }


        .landing-auth-intro h2 {
            color: #ffffff;

            font-size: 1.55rem;
            font-weight: 900;
        }


        .landing-auth-intro p {
            margin: 0;

            color:
                var(--landing-muted);

            font-size: .9rem;
        }


        .landing-auth-form {
            display: none;
        }


        .landing-auth-form.active {
            display: block;
        }


        .landing-auth-form .form-label {
            color: #aab6c8;

            font-size: .78rem;
            font-weight: 700;
        }


        .landing-auth-form .form-control {
            min-height: 46px;

            border:
                1px solid
                var(--landing-border);

            border-radius: 12px;

            background:
                #070c18;

            color: #ffffff;
        }


        .landing-auth-form
        .form-control::placeholder {
            color: #526078;
        }


        .landing-auth-form
        .form-control:focus {
            border-color:
                var(--landing-gold);

            box-shadow:
                0 0 0 .2rem
                rgba(255, 176, 32, .1);
        }


        .landing-password-button {
            border-color:
                var(--landing-border);

            color: #94a3b8;

            background:
                #070c18;
        }


        .landing-password-button:hover {
            color:
                var(--landing-gold);

            background:
                #0b1326;

            border-color:
                var(--landing-gold);
        }


        .landing-auth-submit {
            width: 100%;

            min-height: 48px;

            border: 0;

            border-radius: 999px;

            color: #1a1200;

            background:
                var(--landing-gold);

            font-weight: 800;
        }


        .landing-auth-submit:hover {
            background:
                var(--landing-gold-dark);
        }


        .landing-auth-note {
            margin-top: 16px;

            color:
                var(--landing-muted-2);

            text-align: center;

            font-size: .76rem;
        }


        .landing-flash {
            margin-bottom: 18px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 991px) {

            .landing-nav-links,
            .landing-auth-actions {
                display: none;
            }


            .landing-menu-btn {
                display: inline-flex;
            }


            .landing-two-col,
            .landing-security-panel,
            .landing-project-grid {
                grid-template-columns: 1fr;

                gap: 34px;
            }


            .landing-feature-grid {
                grid-template-columns: 1fr;
            }


            .landing-steps::before {
                left: 18px;
            }


            .landing-step {
                grid-template-columns: 1fr;

                gap: 0;

                padding-left: 46px;
            }


            .landing-step:nth-child(odd)
            .landing-step-card,

            .landing-step:nth-child(even)
            .landing-step-card {
                grid-column: 1;

                text-align: left;
            }

        }


        @media (max-width: 767px) {

            .landing-container {
                width:
                    min(100% - 28px, 1180px);
            }


            .landing-navbar-inner {
                min-height: 66px;
            }


            .landing-hero {
                padding-top: 105px;
            }


            .landing-hero h1 {
                font-size:
                    clamp(2.8rem, 16vw, 4.7rem);
            }


            .landing-section {
                padding:
                    74px 0;
            }


            .landing-hero-actions {
                flex-direction: column;
            }


            .landing-hero-actions a,
            .landing-hero-actions button {
                width:
                    min(320px, 100%);
            }


            .landing-security-copy,
            .landing-project-card {
                padding: 24px;
            }


            .landing-footer-inner {
                justify-content: center;

                text-align: center;
            }

        }

    </style>

</head>


<body>

<div class="landing-page">


    <!-- =========================================================
         NAVBAR
    ========================================================== -->

    <nav
        class="landing-navbar"
        id="landingNavbar"
    >

        <div class="landing-container landing-navbar-inner">

            <a
                href="#hero"
                class="landing-brand"
            >

                <span class="landing-brand-mark">
                    D
                </span>

                DEVIOZ PARKING

            </a>


            <div class="landing-nav-links">

                <a href="#about">
                    Qué es
                </a>

                <a href="#how">
                    Cómo funciona
                </a>

                <a href="#technology">
                    Tecnología
                </a>

                <a href="#project">
                    Proyecto
                </a>

            </div>


            <div class="landing-auth-actions">

                <button
                    type="button"
                    class="btn-auth-link js-open-auth"
                    data-auth-mode="login"
                >
                    Iniciar sesión
                </button>


                <button
                    type="button"
                    class="btn-auth-primary js-open-auth"
                    data-auth-mode="register"
                >
                    Crear cuenta
                </button>

            </div>


            <button
                type="button"
                class="landing-menu-btn"
                id="landingMenuButton"
                aria-label="Abrir menú"
            >

                <i class="bi bi-list"></i>

            </button>

        </div>


        <!-- Menú móvil -->

        <div
            class="landing-mobile-menu"
            id="landingMobileMenu"
        >

            <div class="landing-mobile-menu-inner">

                <a href="#about">
                    Qué es
                </a>

                <a href="#how">
                    Cómo funciona
                </a>

                <a href="#technology">
                    Tecnología
                </a>

                <a href="#project">
                    Proyecto
                </a>


                <div class="landing-mobile-auth">

                    <button
                        type="button"
                        class="btn-auth-link text-start js-open-auth"
                        data-auth-mode="login"
                    >
                        Iniciar sesión
                    </button>


                    <button
                        type="button"
                        class="btn-auth-primary js-open-auth"
                        data-auth-mode="register"
                    >
                        Crear cuenta
                    </button>

                </div>

            </div>

        </div>

    </nav>


    <!-- =========================================================
         HERO
    ========================================================== -->

    <section
        class="landing-hero"
        id="hero"
    >
    <img
    src="<?= htmlspecialchars(
        app_url('/frontend/assets/img/hero-estacionamiento.jpg'),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    alt="Estacionamiento Devioz"
    class="landing-hero-image"
>

        <div class="landing-hero-grid"></div>


        <div class="landing-hero-content">

            <p class="landing-eyebrow landing-fade">

                Gestión inteligente de estacionamiento

            </p>


            <h1 class="landing-fade">

                TU LUGAR,<br>

                EN EL MOMENTO<br>

                <span>
                    EXACTO
                </span>

            </h1>


            <p class="landing-hero-subtitle landing-fade">

                Reserva tu plaza mensual, realiza tu pago
                y administra tu estacionamiento desde un solo lugar.

            </p>


            <p class="landing-hero-meta landing-fade">

                40 plazas · Disponibilidad en tiempo real

            </p>


            <div class="landing-hero-actions landing-fade">

                <a
                    href="#how"
                    class="landing-cta-primary"
                >
                    Cómo funciona
                </a>


                <button
                    type="button"
                    class="landing-cta-secondary js-open-auth"
                    data-auth-mode="register"
                >
                    Crear cuenta
                </button>

            </div>

        </div>

    </section>


    <!-- =========================================================
         QUÉ ES DEVIOZ
    ========================================================== -->

    <section
        class="landing-section"
        id="about"
    >

        <div class="landing-container">

            <div class="landing-two-col">


                <div class="landing-fade">

                    <div
                        class="landing-accent-line"
                        style="margin-left: 0;"
                    ></div>


                    <h2>
                        Qué es Devioz
                    </h2>


                    <p>

                        <span class="landing-highlight">
                            DEVIOZ PARKING
                        </span>

                        es una plataforma web orientada
                        a la gestión digital de un
                        estacionamiento mensual.

                    </p>


                    <p>

                        El conductor puede consultar plazas
                        disponibles, registrar su vehículo,
                        crear reservas y enviar su comprobante
                        de pago.

                        El administrador gestiona usuarios,
                        espacios, reservas, pagos, cámaras
                        y auditoría desde un mismo sistema.

                    </p>

                </div>


                <div class="landing-about-visual landing-fade">

                    <img
                        src="<?= htmlspecialchars(
                            app_url('/frontend/assets/img/hero-estacionamiento.jpg'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="Estacionamiento Devioz"
                        class="landing-about-image"
                    >

                </div>

            </div>

        </div>

    </section>


    <div class="landing-divider"></div>


    <!-- =========================================================
         CÓMO FUNCIONA
    ========================================================== -->

    <section
        class="landing-section"
        id="how"
    >

        <div class="landing-container">


            <div class="landing-section-heading landing-fade">

                <div class="landing-accent-line"></div>


                <h2>
                    Cómo funciona
                </h2>


                <p>

                    Un flujo simple para administrar
                    tu estacionamiento mensual desde
                    el celular o computadora.

                </p>

            </div>


            <div class="landing-steps">


                <!-- Paso 1 -->

                <div class="landing-step landing-fade">

                    <article class="landing-step-card">

                        <div class="landing-step-number">

                            <i class="bi bi-geo-alt-fill"></i>

                            01 · Reservar

                        </div>


                        <h3>
                            Elige una plaza disponible
                        </h3>


                        <p>

                            Consulta la disponibilidad del
                            estacionamiento, selecciona una
                            plaza y registra tu reserva mensual.

                        </p>

                    </article>

                </div>


                <!-- Paso 2 -->

                <div class="landing-step landing-fade">

                    <article class="landing-step-card">

                        <div class="landing-step-number">

                            <i class="bi bi-wallet2"></i>

                            02 · Pagar

                        </div>


                        <h3>
                            Envía tu comprobante
                        </h3>


                        <p>

                            Realiza el pago, registra el
                            número de operación y sube el
                            comprobante para que el administrador
                            pueda validarlo.

                        </p>

                    </article>

                </div>


                <!-- Paso 3 -->

                <div class="landing-step landing-fade">

                    <article class="landing-step-card">

                        <div class="landing-step-number">

                            <i class="bi bi-car-front-fill"></i>

                            03 · Estacionar

                        </div>


                        <h3>
                            Utiliza tu plaza reservada
                        </h3>


                        <p>

                            El sistema mantiene el estado
                            de la plaza y permite controlar
                            la disponibilidad y ocupación
                            desde los paneles de DEVIOZ.

                        </p>

                    </article>

                </div>


            </div>

        </div>

    </section>


    <div class="landing-divider"></div>


    <!-- =========================================================
         TECNOLOGÍA
    ========================================================== -->

    <section
        class="landing-section"
        id="technology"
    >

        <div class="landing-container">


            <div class="landing-section-heading landing-fade">

                <div class="landing-accent-line"></div>


                <h2>
                    Tecnología aplicada
                </h2>


                <p>

                    Una solución web construida para
                    mantener reservas, pagos y operación
                    administrativa en una sola plataforma.

                </p>

            </div>


            <div class="landing-feature-grid">


                <!-- Gestión de espacios -->

                <article class="landing-feature-card landing-fade">

                    <div class="landing-feature-icon">

                        <i class="bi bi-grid-3x3-gap-fill"></i>

                    </div>


                    <h3>
                        Gestión de espacios
                    </h3>


                    <p>

                        Croquis administrativo con estados
                        de plazas disponibles, reservadas,
                        ocupadas y en mantenimiento.

                    </p>

                </article>


                <!-- Responsive -->

                <article class="landing-feature-card landing-fade">

                    <div class="landing-feature-icon">

                        <i class="bi bi-phone"></i>

                    </div>


                    <h3>
                        Experiencia responsive
                    </h3>


                    <p>

                        El conductor puede gestionar
                        vehículos, reservas y pagos desde
                        equipos de escritorio o móviles.

                    </p>

                </article>


                <!-- Auditoría -->

                <article class="landing-feature-card landing-fade">

                    <div class="landing-feature-icon">

                        <i class="bi bi-shield-check"></i>

                    </div>


                    <h3>
                        Control y auditoría
                    </h3>


                    <p>

                        Las acciones importantes quedan
                        registradas para facilitar el
                        seguimiento administrativo y
                        el control del sistema.

                    </p>

                </article>


            </div>

        </div>

    </section>


    <div class="landing-divider"></div>


    <!-- =========================================================
         SEGURIDAD
    ========================================================== -->

    <section class="landing-section">

        <div class="landing-container">

            <div class="landing-security-panel">


                <div class="landing-security-copy landing-fade">

                    <div
                        class="landing-accent-line"
                        style="margin-left: 0;"
                    ></div>


                    <h2>
                        Seguridad y control
                    </h2>


                    <p>

                        El administrador cuenta con
                        herramientas para supervisar
                        la operación diaria y revisar
                        los movimientos importantes
                        del sistema.

                    </p>


                    <div class="landing-security-list">


                        <div class="landing-security-item">

                            <i class="bi bi-check-circle-fill"></i>

                            Gestión de reservas y estados
                            de plazas.

                        </div>


                        <div class="landing-security-item">

                            <i class="bi bi-check-circle-fill"></i>

                            Validación administrativa
                            de pagos.

                        </div>


                        <div class="landing-security-item">

                            <i class="bi bi-check-circle-fill"></i>

                            Módulo de cámaras para
                            vigilancia.

                        </div>


                        <div class="landing-security-item">

                            <i class="bi bi-check-circle-fill"></i>

                            Auditoría de acciones
                            relevantes.

                        </div>


                    </div>

                </div>


                <div class="landing-camera-demo landing-fade">

                    <div class="landing-camera-topbar">

                        <span class="landing-dot"></span>
                        <span class="landing-dot"></span>
                        <span class="landing-dot"></span>


                        <span
                            class="ms-auto small"
                            style="color: #64748b;"
                        >
                            Monitoreo
                        </span>

                    </div>


                    <div class="landing-camera-screen">

                        <img
                            src="<?= htmlspecialchars(
                                app_url('/frontend/assets/img/camaras-vivo.jpg'),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            alt="Videovigilancia Devioz"
                            class="landing-camera-image"
                        >

                        <div class="landing-camera-overlay">

                            <i class="bi bi-camera-video-fill"></i>

                            <strong>
                                Centro de monitoreo
                            </strong>

                            <span>
                                Visualización centralizada de cámaras
                                para apoyo al monitoreo del estacionamiento.
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <div class="landing-divider"></div>


    <!-- =========================================================
         PROYECTO
    ========================================================== -->

    <section
        class="landing-section"
        id="project"
    >

        <div class="landing-container">


            <div class="landing-section-heading landing-fade">

                <div class="landing-accent-line"></div>


                <h2>
                    Sobre el proyecto
                </h2>


                <p>

                    DEVIOZ busca digitalizar el proceso
                    de administración de un estacionamiento
                    físico con una experiencia simple
                    para conductores y administradores.

                </p>

            </div>


            <div class="landing-project-card landing-fade">

                <div class="landing-project-grid">

                    <div>

                        <h3>
                            Estacionamiento Devioz
                        </h3>


                        <p>

                            El sistema centraliza usuarios,
                            vehículos, reservas, pagos,
                            espacios, cámaras y auditoría.

                            Actualmente trabaja con una
                            operación mensual de 40 plazas.

                        </p>


                        <p class="mb-0">

                            La aplicación está orientada
                            a reemplazar procesos manuales
                            por una gestión web organizada,
                            segura y trazable.

                        </p>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =========================================================
         CTA FINAL
    ========================================================== -->

    <section class="landing-final-cta">

        <div class="landing-container">

            <div class="landing-fade">


                <h2>

                    Tu estacionamiento,<br>

                    en

                    <span>
                        modo control total
                    </span>

                </h2>


                <p>

                    Crea tu cuenta de conductor
                    y comienza a gestionar vehículos,
                    reservas y pagos desde DEVIOZ PARKING.

                </p>


                <div class="landing-hero-actions">


                    <button
                        type="button"
                        class="landing-cta-primary js-open-auth"
                        data-auth-mode="register"
                    >
                        Crear cuenta
                    </button>


                    <button
                        type="button"
                        class="landing-cta-secondary js-open-auth"
                        data-auth-mode="login"
                    >
                        Iniciar sesión
                    </button>


                </div>

            </div>

        </div>

    </section>


    <!-- =========================================================
         FOOTER
    ========================================================== -->

    <footer class="landing-footer">

        <div class="landing-container landing-footer-inner">


            <a
                href="#hero"
                class="landing-brand"
            >

                <span class="landing-brand-mark">
                    D
                </span>

                DEVIOZ PARKING

            </a>


            <small>

                © <?= date('Y') ?>
                Devioz Parking ·
                Todos los derechos reservados

            </small>


            <a href="#hero">
                Volver arriba
            </a>


        </div>

    </footer>


</div>


<!-- =============================================================
     MODAL DE AUTENTICACIÓN
============================================================== -->

<div
    class="landing-auth-modal <?= $flash ? 'open' : '' ?>"
    id="authModal"
    aria-hidden="<?= $flash ? 'false' : 'true' ?>"
>


    <div class="landing-auth-card">


        <!-- Header modal -->

        <div class="landing-auth-card-header">


            <div class="landing-auth-tabs">


                <button
                    type="button"
                    class="landing-auth-tab <?= $authMode === 'login' ? 'active' : '' ?>"
                    data-auth-tab="login"
                >
                    Iniciar sesión
                </button>


                <button
                    type="button"
                    class="landing-auth-tab <?= $authMode === 'register' ? 'active' : '' ?>"
                    data-auth-tab="register"
                >
                    Crear cuenta
                </button>


            </div>


            <button
                type="button"
                class="landing-auth-close"
                id="authModalClose"
                aria-label="Cerrar"
            >

                <i class="bi bi-x-lg"></i>

            </button>


        </div>


        <!-- Body modal -->

        <div class="landing-auth-body">


            <!-- Flash -->

            <?php if ($flash): ?>

                <div
                    class="alert alert-<?= htmlspecialchars(
                        (string) ($flash['type'] ?? 'danger'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?> landing-flash"
                >

                    <?= htmlspecialchars(
                        (string) ($flash['message'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- =====================================================
                 LOGIN
            ====================================================== -->

            <div
                class="landing-auth-form <?= $authMode === 'login' ? 'active' : '' ?>"
                data-auth-form="login"
            >


                <div class="landing-auth-intro">

                    <h2>
                        Bienvenido
                    </h2>


                    <p>

                        Ingresa tus credenciales
                        para acceder al sistema.

                    </p>

                </div>


                <form
                    method="post"
                    action="<?= htmlspecialchars(
                        app_url('/backend/auth/login.php'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="vstack gap-3"
                >

                    <?= csrf_field() ?>


                    <!-- Email -->

                    <div>

                        <label class="form-label">

                            Correo electrónico

                        </label>


                        <input
                            class="form-control"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="correo@ejemplo.com"
                        >

                    </div>


                    <!-- Password -->

                    <div>

                        <label class="form-label">

                            Contraseña

                        </label>


                        <div class="input-group">

                            <input
                                class="form-control"
                                id="landingLoginPassword"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Tu contraseña"
                            >


                            <button
                                class="btn landing-password-button"
                                type="button"
                                data-landing-toggle-password="#landingLoginPassword"
                                aria-label="Mostrar contraseña"
                            >

                                <i class="bi bi-eye"></i>

                            </button>

                        </div>

                    </div>


                    <button
                        type="submit"
                        class="landing-auth-submit mt-2"
                    >

                        Ingresar

                    </button>


                </form>


            </div>


            <!-- =====================================================
                 REGISTRO
            ====================================================== -->

            <div
                class="landing-auth-form <?= $authMode === 'register' ? 'active' : '' ?>"
                data-auth-form="register"
            >


                <div class="landing-auth-intro">


                    <h2>
                        Crea tu cuenta
                    </h2>


                    <p>

                        Regístrate como conductor
                        para comenzar a utilizar DEVIOZ.

                    </p>


                </div>


                <form
                    method="post"
                    action="<?= htmlspecialchars(
                        app_url('/backend/auth/register.php'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="vstack gap-3"
                >

                    <?= csrf_field() ?>


                    <!-- Nombre -->

                    <div>

                        <label class="form-label">
                            Nombre
                        </label>


                        <input
                            class="form-control"
                            type="text"
                            name="name"
                            required
                            autocomplete="name"
                            placeholder="Tu nombre"
                        >

                    </div>


                    <!-- Email -->

                    <div>

                        <label class="form-label">
                            Correo electrónico
                        </label>


                        <input
                            class="form-control"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="correo@ejemplo.com"
                        >

                    </div>


                    <!-- Password -->

                    <div>

                        <label class="form-label">
                            Contraseña
                        </label>


                        <div class="input-group">

                            <input
                                class="form-control"
                                id="landingRegisterPassword"
                                type="password"
                                name="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Mínimo 8 caracteres"
                            >


                            <button
                                class="btn landing-password-button"
                                type="button"
                                data-landing-toggle-password="#landingRegisterPassword"
                                aria-label="Mostrar contraseña"
                            >

                                <i class="bi bi-eye"></i>

                            </button>

                        </div>

                    </div>


                    <!-- Confirmación -->

                    <div>

                        <label class="form-label">

                            Confirmar contraseña

                        </label>


                        <div class="input-group">

                            <input
                                class="form-control"
                                id="landingRegisterPasswordConfirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Repite tu contraseña"
                            >


                            <button
                                class="btn landing-password-button"
                                type="button"
                                data-landing-toggle-password="#landingRegisterPasswordConfirmation"
                                aria-label="Mostrar contraseña"
                            >

                                <i class="bi bi-eye"></i>

                            </button>

                        </div>

                    </div>


                    <button
                        type="submit"
                        class="landing-auth-submit mt-2"
                    >

                        Crear cuenta

                    </button>


                </form>


                <p class="landing-auth-note">

                    Las cuentas creadas desde
                    esta página se registran
                    como conductores.

                </p>


            </div>


        </div>

    </div>

</div>


<!-- Bootstrap -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | Elementos
        |--------------------------------------------------------------------------
        */

        const navbar =
            document.getElementById(
                'landingNavbar'
            );


        const menuButton =
            document.getElementById(
                'landingMenuButton'
            );


        const mobileMenu =
            document.getElementById(
                'landingMobileMenu'
            );


        const authModal =
            document.getElementById(
                'authModal'
            );


        const authModalClose =
            document.getElementById(
                'authModalClose'
            );


        const openAuthButtons =
            document.querySelectorAll(
                '.js-open-auth'
            );


        const authTabs =
            document.querySelectorAll(
                '[data-auth-tab]'
            );


        const authForms =
            document.querySelectorAll(
                '[data-auth-form]'
            );


        /*
        |--------------------------------------------------------------------------
        | Navbar con blur
        |--------------------------------------------------------------------------
        */

        function updateNavbar() {

            if (!navbar) {
                return;
            }


            navbar.classList.toggle(
                'scrolled',
                window.scrollY > 40
            );

        }


        updateNavbar();


        window.addEventListener(
            'scroll',
            updateNavbar,
            {
                passive: true
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Menú móvil
        |--------------------------------------------------------------------------
        */

        if (
            menuButton
            && mobileMenu
        ) {


            menuButton.addEventListener(
                'click',
                function () {

                    mobileMenu
                        .classList
                        .toggle('open');

                }
            );


            mobileMenu
                .querySelectorAll('a')
                .forEach(
                    function (link) {

                        link.addEventListener(
                            'click',
                            function () {

                                mobileMenu
                                    .classList
                                    .remove('open');

                            }
                        );

                    }
                );

        }


        /*
        |--------------------------------------------------------------------------
        | Cambio Login / Registro
        |--------------------------------------------------------------------------
        */

        function changeAuthMode(mode) {


            authTabs.forEach(
                function (tab) {

                    tab.classList.toggle(
                        'active',
                        tab.dataset.authTab
                            === mode
                    );

                }
            );


            authForms.forEach(
                function (form) {

                    form.classList.toggle(
                        'active',
                        form.dataset.authForm
                            === mode
                    );

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Abrir modal
        |--------------------------------------------------------------------------
        */

        function openAuth(mode) {


            if (!authModal) {
                return;
            }


            changeAuthMode(
                mode || 'login'
            );


            authModal
                .classList
                .add('open');


            authModal.setAttribute(
                'aria-hidden',
                'false'
            );


            document.body
                .classList
                .add(
                    'landing-modal-open'
                );


            if (mobileMenu) {

                mobileMenu
                    .classList
                    .remove('open');

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Cerrar modal
        |--------------------------------------------------------------------------
        */

        function closeAuth() {


            if (!authModal) {
                return;
            }


            authModal
                .classList
                .remove('open');


            authModal.setAttribute(
                'aria-hidden',
                'true'
            );


            document.body
                .classList
                .remove(
                    'landing-modal-open'
                );

        }


        /*
        |--------------------------------------------------------------------------
        | Botones abrir modal
        |--------------------------------------------------------------------------
        */

        openAuthButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        openAuth(
                            button.dataset.authMode
                                || 'login'
                        );

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Tabs login / registro
        |--------------------------------------------------------------------------
        */

        authTabs.forEach(
            function (tab) {

                tab.addEventListener(
                    'click',
                    function () {

                        changeAuthMode(
                            tab.dataset.authTab
                                || 'login'
                        );

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Botón cerrar
        |--------------------------------------------------------------------------
        */

        if (authModalClose) {

            authModalClose.addEventListener(
                'click',
                closeAuth
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Cerrar haciendo clic afuera
        |--------------------------------------------------------------------------
        */

        if (authModal) {

            authModal.addEventListener(
                'click',
                function (event) {

                    if (
                        event.target
                        === authModal
                    ) {

                        closeAuth();

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | ESC
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                    && authModal
                    && authModal
                        .classList
                        .contains('open')
                ) {

                    closeAuth();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Mostrar contraseña
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '[data-landing-toggle-password]'
            )
            .forEach(
                function (button) {


                    button.addEventListener(
                        'click',
                        function () {


                            const selector =
                                button.getAttribute(
                                    'data-landing-toggle-password'
                                );


                            if (!selector) {
                                return;
                            }


                            const input =
                                document.querySelector(
                                    selector
                                );


                            if (!input) {
                                return;
                            }


                            const isPassword =
                                input.type
                                === 'password';


                            input.type =
                                isPassword
                                    ? 'text'
                                    : 'password';


                            const icon =
                                button.querySelector(
                                    'i'
                                );


                            if (icon) {

                                icon.className =
                                    isPassword
                                        ? 'bi bi-eye-slash'
                                        : 'bi bi-eye';

                            }


                        }
                    );


                }
            );


        /*
        |--------------------------------------------------------------------------
        | Animaciones al hacer scroll
        |--------------------------------------------------------------------------
        */

        const fadeElements =
            document.querySelectorAll(
                '.landing-fade'
            );


        if (
            'IntersectionObserver'
            in window
        ) {


            const observer =
                new IntersectionObserver(

                    function (entries) {


                        entries.forEach(
                            function (entry) {


                                if (
                                    entry.isIntersecting
                                ) {

                                    entry.target
                                        .classList
                                        .add(
                                            'visible'
                                        );


                                    observer
                                        .unobserve(
                                            entry.target
                                        );

                                }


                            }
                        );


                    },

                    {
                        threshold: .08,
                        rootMargin:
                            '0px 0px -30px 0px'
                    }

                );


            fadeElements.forEach(
                function (element) {

                    observer.observe(
                        element
                    );

                }
            );


        } else {


            fadeElements.forEach(
                function (element) {

                    element
                        .classList
                        .add('visible');

                }
            );


        }


        /*
        |--------------------------------------------------------------------------
        | Estado inicial del modal
        |--------------------------------------------------------------------------
        */

        const flashExists =
            <?= $flash ? 'true' : 'false' ?>;


        const initialMode =
            <?= json_encode(
                $authMode,
                JSON_UNESCAPED_UNICODE
            ) ?>;


        if (flashExists) {

            openAuth(
                initialMode
            );

        }


    }
);

</script>


</body>

</html>