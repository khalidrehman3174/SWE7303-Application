<?php
// FinPay End-User Presentation Homepage
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="FinPay - One app for all your money. Send, receive, invest in crypto, and manage your finances with bank-grade security.">
    <title>FinPay - One app, all things money</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== CENTRAL BRAND DESIGN SYSTEM =====
         * Single source of truth for all colors.
         * Use these variables everywhere — do not hardcode colors.
         * Brand accent: Emerald Green (#10b981)
         * ======================================= */
        :root {
            --brand-accent:       #10b981;   /* primary brand green — logo, CTAs, badges */
            --brand-accent-glow:  rgba(16,185,129,0.18);
            --brand-accent-dim:   rgba(16,185,129,0.1);
            --brand-dark:         #09090b;   /* near-black for hero panels & CTA blocks */
            --r-black: #191c1f;
            --r-dark: #000000;
            --r-white: #ffffff;
            --r-gray-50:  #f9fafb;
            --r-gray-100: #f3f4f6;
            --r-gray-200: #e5e7eb;
            --r-gray-300: #d1d5db;
            --r-gray-text: #6b7280;
            --r-blue: #2563eb;
            --r-blue-light: #eff6ff;
            --r-green: #10b981;
            --r-purple: #8b5cf6;
        }

        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        
        body {
            background-color: var(--r-white);
            color: var(--r-black);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            background-image:
                radial-gradient(ellipse at 50% 0%, rgba(16,185,129,0.07) 0%, transparent 55%),
                radial-gradient(ellipse at 90% 0%, rgba(16,185,129,0.04) 0%, transparent 45%);
        }

        /* Global Mesh — unified with brand accent (emerald green) */
        .global-mesh {
            position: absolute; top: 0; left: 0; width: 100%; height: 1100px;
            background:
                radial-gradient(ellipse at 10% 0%,  rgba(16,185,129,0.06), transparent 55%),
                radial-gradient(ellipse at 90% 5%,  rgba(16,185,129,0.04), transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(16,185,129,0.02), transparent 50%);
            z-index: -1; pointer-events: none;
        }

        /* Subtle professional underline accent instead of rainbow */
        .accent-word {
            position: relative;
            display: inline-block;
        }
        .accent-word::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #2563eb, #4f46e5);
            border-radius: 3px;
            opacity: 0.35;
            z-index: -1;
        }
        /* Remove the old animated rainbow */
        .gradient-word {
            color: var(--r-dark);
        }

        /* Essential Typography */
        h1, h2, h3, h4 { font-weight: 800; letter-spacing: -0.04em; color: var(--r-dark); }
        .headline-super { font-size: clamp(2.8rem, 8vw, 6.5rem); line-height: 0.95; padding: 0 1rem; }
        .headline-section { font-size: clamp(2.5rem, 5vw, 4.5rem); line-height: 1.05; margin-bottom: 1.5rem; }
        .headline-card { font-size: clamp(1.8rem, 3vw, 2.5rem); line-height: 1.1; margin-bottom: 1rem; }
        .text-body-large { font-size: 1.25rem; color: var(--r-gray-text); font-weight: 500; line-height: 1.5; }
        .text-body { font-size: 1rem; color: var(--r-gray-text); font-weight: 500; line-height: 1.6; }

        /* Minimalist Navbar */
        .navbar-rev {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            padding: 1.2rem 2rem; position: fixed; width: 100%; top: 0; z-index: 1000;
            display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.03);
        }
        
        @media (max-width: 768px) {
            .navbar-rev { padding: 1rem; justify-content: space-between; }
        }

        .nav-logo { font-size: 1.5rem; font-weight: 900; color: var(--r-dark); text-decoration: none; letter-spacing: -1px; display: flex; align-items: center; gap: 8px; }
        .nav-logo-dot { width: 8px; height: 8px; background: var(--brand-accent); border-radius: 50%; box-shadow: 0 0 8px var(--brand-accent-glow); animation: pulse-dot 2s ease-in-out infinite; }
        @keyframes pulse-dot { 0%, 100% { box-shadow: 0 0 6px var(--brand-accent-glow); } 50% { box-shadow: 0 0 14px rgba(16,185,129,0.9); } }
        .nav-links a { color: var(--r-black); font-weight: 600; font-size: 0.95rem; margin: 0 1.2rem; text-decoration: none; transition: opacity 0.2s; }
        .nav-links a:hover { opacity: 0.6; }
        .btn-rev {
            background: var(--r-dark); color: var(--r-white); padding: 0.8rem 1.8rem;
            border-radius: 100px; font-weight: 700; font-size: 0.95rem; text-decoration: none;
            transition: transform 0.2s, background 0.2s, box-shadow 0.2s; border: none; display: inline-block;
        }
        .btn-rev:hover { transform: scale(1.03); background: #1c1c1e; color: var(--r-white); box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
        
        .btn-rev-light {
            background: rgba(255,255,255,0.1); color: var(--r-white); padding: 0.8rem 1.8rem;
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);
            border-radius: 100px; font-weight: 700; font-size: 0.95rem; text-decoration: none;
            display: inline-block; transition: all 0.2s; 
        }
        .btn-rev-light:hover { background: var(--r-white); color: var(--r-dark); transform: scale(1.02); }

        /* Blocks & Grids */
        .presentation-wrap { max-width: 1400px; margin: 0 auto; padding: 0 1.5rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        @media (max-width: 991px) { .grid-2 { grid-template-columns: 1fr; } }

        /* Premium Subtle Gradients injected into Structural Blocks */
        .p-block {
            background: linear-gradient(145deg, var(--r-white) 0%, var(--r-gray-200) 100%);
            border-radius: 40px; padding: 4rem; position: relative; overflow: hidden; display: flex; flex-direction: column;
            min-height: 650px; text-decoration: none; color: inherit; perspective: 1200px;
        }
        .p-block-dark { 
            background: radial-gradient(circle at top right, rgba(37,99,235,0.15) 0%, transparent 60%), 
                        radial-gradient(circle at bottom left, rgba(16,185,129,0.08) 0%, transparent 50%), 
                        var(--r-dark); 
            color: var(--r-white); 
        }
        .p-block-dark h2, .p-block-dark h3 { color: var(--r-white); }
        .p-block-dark .text-body-large { color: #a1a1aa; }
        
        .p-block-blue { background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%); }

        @media (max-width: 768px) {
            .p-block { padding: 2.5rem 1.5rem; border-radius: 28px; }
            .grid-2 .p-block { min-height: auto !important; display: flex; flex-direction: column; gap: 1.5rem; justify-content: flex-start; }
            .grid-2 .text-max-500 { padding-bottom: 0px; position: relative; margin-bottom: 0; }
            .grid-2 .ui-interactive { position: relative !important; right: auto !important; left: auto !important; bottom: auto !important; top: auto !important; transform: none !important; margin: 0 auto !important; width: 100% !important; max-width: 320px !important; }
            .hide-mobile, .mock-cc-light { display: none !important; }
            .headline-card { font-size: 1.5rem; line-height: 1.2; }
        }

        /* 3D JS Interactive Mockups */
        .ui-interactive {
            position: absolute; box-shadow: 0 24px 60px rgba(0,0,0,0.08);
            transition: transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
            transform-style: preserve-3d; will-change: transform;
        }

        /* Orbital Widget Container */
        .mock-orbital-container {
            position: relative; width: 100%; max-width: 600px; height: 350px; 
            margin: 3rem auto 0 auto; transform-style: preserve-3d;
        }
        
        /* Inner Cards */
        .mock-bal-main {
            width: 380px; background: rgba(255,255,255,0.95); backdrop-filter: blur(16px);
            border-radius: 32px; padding: 2.5rem; border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 30px 60px rgba(0,0,0,0.25); 
            left: 50%; top: 50%; transform: translate(-50%, -50%); z-index: 3;
        }
        .mock-bal-stat { font-size: 3.2rem; font-weight: 900; color: var(--r-dark); letter-spacing: -2px; line-height: 1; margin: 15px 0 25px 0; }
        
        .mock-tx-orb-1 { width: 250px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 1.2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2); left: -5%; top: 10%; --base-rot: -6deg; transform: rotate(var(--base-rot)); z-index: 2; }
        .mock-tx-orb-2 { width: 260px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 1.2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2); right: -8%; bottom: 10%; --base-rot: 8deg; transform: rotate(var(--base-rot)); z-index: 4; }

        @media (max-width: 768px) {
            .mock-orbital-container { transform: scale(0.85); margin-top: 1rem; }
            .mock-tx-orb-1 { left: 0%; top: 0%; }
            .mock-tx-orb-2 { right: 0%; bottom: 0%; }
        }

        .p-block:hover .mock-tx-orb-1 { transform: rotate(-10deg) translateX(-10px) scale(1.05); }
        .p-block:hover .mock-tx-orb-2 { transform: rotate(12deg) translateX(10px) translateY(10px) scale(1.05); }

        /* Other Utilities */
        .mock-btn { flex: 1; height: 50px; background: var(--r-blue); border-radius: 100px; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; }
        .mock-btn.secondary { background: var(--r-gray-200); color: var(--r-dark); }
        .mock-av { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; }
        
        /* Transfer Card */
        .mock-transfer-card { background: var(--r-white); border-radius: 24px; padding: 1.5rem; width: 320px; right: 40px; bottom: 50px; z-index: 3;}
        @media (max-width: 768px) { .mock-transfer-card { width: calc(100% - 40px); right: 20px; bottom: 20px; } }
        .mock-amt { font-size: 3rem; font-weight: 900; letter-spacing: -2px; color: var(--r-dark); margin: 10px 0; border-bottom: 1px solid var(--r-gray-300); padding-bottom: 10px; }
        
        /* Credit Cards */
        .mock-cc { width: 320px; height: 200px; background: linear-gradient(135deg, #111, #333); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; color: white; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; right: -20px; bottom: 80px; --base-rot: -10deg; transform: rotate(var(--base-rot)); transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1); }
        .mock-cc-light { background: linear-gradient(135deg, #fff, #f8f9fa); color: var(--r-dark); border: 1px solid rgba(0,0,0,0.05); --base-rot: 5deg; bottom: 40px; right: 20px; z-index: -1; }
        .p-block:hover .mock-cc { transform: rotate(0deg) translateY(-20px) scale(1.05); }
        .p-block:hover .mock-cc-light { transform: rotate(15deg) translateX(30px) translateY(-10px) scale(0.95); }
        @media (max-width: 768px) { .mock-cc { width: 280px; height: 175px; right: 5%; bottom: 60px; } .mock-cc-light { right: 10%; bottom: 20px;} }

        /* The Bag of Crypto Orbital Cloud */
        .mock-crypto { width: 70px; height: 70px; border-radius: 20px; color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; position: absolute; box-shadow: 0 20px 40px rgba(0,0,0,0.3); transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1); border: 1px solid rgba(255,255,255,0.15); font-weight: 800; font-family: monospace; z-index: 5; }
        
        .c-btc { background: linear-gradient(135deg, #fbc531, #f7931a); right: 20%; top: 35%; --base-rot: 12deg; transform: scale(1.3) rotate(var(--base-rot)); z-index: 10; }
        .c-eth { background: linear-gradient(135deg, #819bfa, #627eea); left: 75%; bottom: 25%; --base-rot: -8deg; transform: scale(1.1) rotate(var(--base-rot)); z-index: 8; }
        .c-usdt { background: linear-gradient(135deg, #10b981, #059669); right: 10%; bottom: 35%; --base-rot: -15deg; transform: scale(0.9) rotate(var(--base-rot)); z-index: 6; }
        .c-sol { background: linear-gradient(135deg, #14F195, #9945FF); right: 40%; top: 20%; --base-rot: 20deg; transform: scale(0.85) rotate(var(--base-rot)); z-index: 4; }
        .c-ada { background: linear-gradient(135deg, #0ea5e9, #0284c7); left: 80%; top: 15%; --base-rot: 5deg; transform: scale(0.8) rotate(var(--base-rot)); z-index: 3; }
        .c-doge { background: linear-gradient(135deg, #fbbf24, #d97706); right: 25%; top: 8%; --base-rot: -25deg; transform: scale(0.65) rotate(var(--base-rot)); z-index: 2; opacity: 0.8; filter: blur(2px); }
        .c-shib { background: linear-gradient(135deg, #ef4444, #b91c1c); right: 30%; bottom: 8%; --base-rot: 15deg; transform: scale(0.6) rotate(var(--base-rot)); z-index: 1; opacity: 0.7; filter: blur(3px); }

        .p-block:hover .c-btc { transform: rotate(0deg) scale(1.4) translateY(-20px); }
        .p-block:hover .c-eth { transform: rotate(0deg) scale(1.2) translateY(10px); }
        .p-block:hover .c-sol { transform: rotate(5deg) scale(0.9) translateX(-15px); }

        @media (max-width: 768px) {
            .mock-crypto { transform: scale(0.6) !important; filter: none !important; opacity: 1 !important; }
            .c-sol, .c-ada, .c-doge, .c-shib { display: none; /* Hide noise on mobile */ }
        }

        /* Infinite Marquee */
        .marquee-container { width: 100%; overflow: hidden; white-space: nowrap; position: relative; padding: 4rem 0 2rem; mask-image: linear-gradient(to right, transparent, black 10%, black 90%, transparent); -webkit-mask-image: linear-gradient(to right, transparent, black 10%, black 90%, transparent); }
        .marquee-content { display: inline-flex; gap: 4rem; animation: scroll-left 30s linear infinite; align-items: center; }
        .marquee-content:hover { animation-play-state: paused; }
        .marquee-item { font-size: 1.5rem; font-weight: 800; color: var(--r-gray-text); display: flex; align-items: center; gap: 0.75rem; opacity: 0.4; transition: opacity 0.3s, color 0.3s, transform 0.3s; cursor: default; }
        .marquee-item:hover { opacity: 1; color: var(--r-dark); transform: scale(1.05); }
        @keyframes scroll-left { from { transform: translateX(0); } to { transform: translateX(calc(-50% - 2rem)); } }

        /* Security Block Responsive */
        @media (max-width: 991px) { .security-grid { grid-template-columns: 1fr !important; gap: 2rem !important; padding: 2rem 1.5rem !important; } }

        /* Chart Animations */
        .chart-bar { transform-origin: bottom; transform: scaleY(0.1); transition: transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1); }
        .reveal.active .chart-bar { transform: scaleY(1); }

        /* Section Eyebrow Labels */
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: 0.78rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; padding: 6px 16px; border-radius: 100px; background: rgba(37,99,235,0.08); color: var(--r-blue); margin-bottom: 1.25rem; }
        .eyebrow.green { background: rgba(16,185,129,0.1); color: var(--r-green); }
        .eyebrow.dark { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.7); }

        /* Hero Sub */
        .hero-sub { font-size: clamp(1rem, 2vw, 1.25rem); color: var(--r-gray-text); font-weight: 500; max-width: 600px; margin: 0 auto; line-height: 1.6; }

        /* Component Transitions */
        .reveal { opacity: 0; transform: translateY(40px); transition: 1s cubic-bezier(0.2,0.8,0.2,1); }
        .reveal.active { opacity: 1; transform: translateY(0); }
        
        /* Layout formatting */
        .text-max-800 { max-width: 800px; margin: 0 auto; position: relative; z-index: 10; }
        .text-max-500 { max-width: 500px; position: relative; z-index: 10; padding-bottom: 20px; }
        .padding-section { padding: 140px 0; }
        .pt-hero { padding-top: 220px; padding-bottom: 100px; }

        /* Big terminal CTA */
        .cta-terminal {
            background: var(--r-dark);
            border-radius: 40px;
            padding: 5rem 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta-terminal::before {
            content: '';
            position: absolute;
            top: -30%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 300px;
            background: radial-gradient(ellipse, rgba(37,99,235,0.3) 0%, transparent 70%);
            filter: blur(40px);
            pointer-events: none;
        }
        .cta-terminal::after {
            content: '';
            position: absolute;
            bottom: -20%;
            right: -10%;
            width: 400px;
            height: 300px;
            background: radial-gradient(ellipse, rgba(139,92,246,0.2) 0%, transparent 70%);
            filter: blur(50px);
            pointer-events: none;
        }
        .btn-rev-outline {
            background: transparent; color: rgba(255,255,255,0.7); padding: 0.9rem 2rem;
            border-radius: 100px; font-weight: 700; font-size: 1rem; text-decoration: none;
            border: 1.5px solid rgba(255,255,255,0.2); display: inline-block; transition: all 0.2s;
        }
        .btn-rev-outline:hover { background: rgba(255,255,255,0.05); color: white; border-color: rgba(255,255,255,0.4); }

        /* Revolut-style Hub Section */
        .hub-section {
            background: #09090b;
            border-radius: 40px;
            padding: 4rem;
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            min-height: 600px;
            margin-bottom: 1.5rem;
        }
        .hub-section::before {
            content: '';
            position: absolute;
            top: -200px; left: -200px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(37,99,235,0.18) 0%, transparent 65%);
            filter: blur(60px);
            pointer-events: none;
        }
        .hub-section::after {
            content: '';
            position: absolute;
            bottom: -150px; right: -100px;
            width: 500px; height: 400px;
            background: radial-gradient(circle, rgba(16,185,129,0.12) 0%, transparent 60%);
            filter: blur(80px);
            pointer-events: none;
        }
        .hub-phone {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        /* Glass phone frame */
        .phone-frame {
            width: 260px;
            background: linear-gradient(145deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03));
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 40px;
            padding: 2rem 1.5rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 40px 80px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.08);
            position: relative;
        }
        .phone-notch {
            width: 80px; height: 8px; background: rgba(255,255,255,0.1);
            border-radius: 100px; margin: 0 auto 1.5rem;
        }
        .phone-balance {
            text-align: center; margin-bottom: 1.5rem;
        }
        .phone-balance-label { font-size: 0.7rem; color: rgba(255,255,255,0.4); font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
        .phone-balance-amount { font-size: 2.6rem; font-weight: 900; color: #fff; letter-spacing: -2px; line-height: 1; margin: 6px 0; }
        .phone-balance-change { font-size: 0.8rem; color: #10b981; font-weight: 700; }
        .phone-actions {
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 1.5rem;
        }
        .phone-action-btn {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px; padding: 12px 6px; text-align: center;
        }
        .phone-action-btn i { color: #fff; font-size: 0.9rem; display: block; margin-bottom: 4px; }
        .phone-action-btn span { font-size: 0.65rem; color: rgba(255,255,255,0.5); font-weight: 700; }
        .phone-tx { padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
        .phone-tx:last-child { border-bottom: none; }
        .phone-tx-icon { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
        .phone-tx-name { font-size: 0.8rem; font-weight: 700; color: #fff; }
        .phone-tx-date { font-size: 0.65rem; color: rgba(255,255,255,0.35); }
        .phone-tx-amt { font-size: 0.85rem; font-weight: 800; }
        /* Floating chips around phone */
        .hub-chip {
            position: absolute;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 12px 16px;
            backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            z-index: 3;
            box-shadow: 0 16px 40px rgba(0,0,0,0.4);
        }
        .hub-chip-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .hub-chip-title { font-size: 0.8rem; font-weight: 800; color: #fff; }
        .hub-chip-sub { font-size: 0.7rem; color: rgba(255,255,255,0.5); font-weight: 600; }
        @media (max-width: 900px) {
            .hub-section { grid-template-columns: 1fr; gap: 3rem; padding: 2.5rem 1.5rem; min-height: auto; }
            .hub-chip { display: none; }
        }

        /* grey.co inspired Feature Row */
        .feature-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            padding: 5rem 0;
        }
        @media (max-width: 768px) { .feature-row { grid-template-columns: 1fr; padding: 3rem 0; } }
        .feature-item { padding: 0 1rem; }
        .feature-item-icon {
            width: 52px; height: 52px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; margin-bottom: 1.25rem;
            background: var(--r-gray-100);
        }
        .feature-item h4 { font-size: 1.2rem; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 0.5rem; color: var(--r-dark); }
        .feature-item p { font-size: 0.95rem; color: var(--r-gray-text); font-weight: 500; line-height: 1.6; }

        /* Testimonials */
        .testimonial-card {
            background: var(--r-white);
            border: 1px solid var(--r-gray-300);
            border-radius: 24px;
            padding: 2rem;
            flex: 1 1 280px;
            max-width: 340px;
        }
        /* === PREMIUM DARK HERO === */
        .hero-dark {
            background: #09090b;
            border-radius: 40px;
            padding: 8rem 2rem 5rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        .hero-grid-overlay {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            mask-image: radial-gradient(ellipse at center top, rgba(0,0,0,0.5) 0%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse at center top, rgba(0,0,0,0.5) 0%, transparent 70%);
        }
        .hero-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            pointer-events: none;
        }
        .hero-orb-1 { width: 600px; height: 400px; background: rgba(99,102,241,0.18); top: -10%; left: 50%; transform: translateX(-60%); }
        .hero-orb-2 { width: 400px; height: 300px; background: rgba(16,185,129,0.1); bottom: 0; right: 5%; }
        .hero-accent {
            background: linear-gradient(100deg, #fff 30%, rgba(255,255,255,0.55));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-badge { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.45); display: flex; align-items: center; gap: 6px; }
        .hero-badge-sep { color: rgba(255,255,255,0.15); font-size: 0.75rem; }
        /* Hero Metric Strip */
        .hero-metric-strip {
            display: inline-flex; align-items: center; gap: 0;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 20px;
            padding: 1.25rem 2.5rem;
            margin-top: 4rem;
            gap: 2.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .hero-metric-val { font-size: 1.8rem; font-weight: 900; color: #fff; letter-spacing: -2px; line-height: 1; }
        .hero-metric-label { font-size: 0.75rem; color: rgba(255,255,255,0.35); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
        .hero-metric-divider { width: 1px; height: 40px; background: rgba(255,255,255,0.08); flex-shrink: 0; }
        @media (max-width: 768px) {
            .hero-dark { padding: 6rem 1.5rem 3rem; border-radius: 28px; }
            .hero-metric-strip { gap: 1.5rem; padding: 1rem 1.5rem; }
            .hero-metric-divider { display: none; }
            .hero-badge-sep { display: none; }
        }

        /* === SPLIT-SCREEN HERO === */
        .hero-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            padding: 8rem 0 5rem;
            min-height: 90vh;
        }
        @media (max-width: 991px) {
            .hero-split { grid-template-columns: 1fr; padding: 7rem 0 3rem; min-height: auto; }
            .hero-right-panel { display: none; }
        }
        .hero-right-panel {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 500px;
        }
        /* Main account card */
        .hero-card-main {
            width: 310px;
            background: #09090b;
            border-radius: 32px;
            padding: 2.5rem 2rem;
            box-shadow: 0 40px 80px rgba(0,0,0,0.18), 0 0 0 1px rgba(0,0,0,0.06);
            position: relative;
            z-index: 3;
        }
        .hero-card-label { font-size: 0.7rem; color: rgba(255,255,255,0.35); font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
        .hero-card-amount { font-size: 3rem; font-weight: 900; color: #fff; letter-spacing: -3px; line-height: 1; margin: 10px 0 6px; }
        .hero-card-change { font-size: 0.82rem; color: #10b981; font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 1.75rem; }
        .hero-card-divider { height: 1px; background: rgba(255,255,255,0.06); margin: 0 0 1.5rem; }
        .hero-card-tx { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .hero-card-tx:last-child { border-bottom: none; }
        .hero-card-tx-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
        .hero-card-tx-name { font-size: 0.82rem; font-weight: 700; color: rgba(255,255,255,0.85); }
        .hero-card-tx-date { font-size: 0.65rem; color: rgba(255,255,255,0.3); }
        /* Floating mini-card: FX rate */
        .hero-float-fx {
            position: absolute;
            bottom: 10%;
            left: -10%;
            width: 210px;
            background: #fff;
            border-radius: 20px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.12);
            z-index: 4;
        }
        .hero-float-label { font-size: 0.65rem; font-weight: 700; color: var(--r-gray-text); letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 0.5rem; }
        .hero-float-rate { font-size: 1.6rem; font-weight: 900; color: var(--r-dark); letter-spacing: -1.5px; line-height: 1; }
        .hero-float-sub { font-size: 0.75rem; color: var(--r-green); font-weight: 700; margin-top: 4px; }
        /* Floating chip: Send success */
        .hero-float-chip {
            position: absolute;
            top: 10%;
            right: -6%;
            background: #fff;
            border-radius: 100px;
            padding: 10px 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 4;
            white-space: nowrap;
        }
        .hero-float-chip-dot { width: 10px; height: 10px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px rgba(16,185,129,0.6); }
        /* Decorative background for right panel */
        .hero-panel-bg {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at 70% 20%, rgba(99,102,241,0.08) 0%, transparent 60%),
                radial-gradient(ellipse at 20% 80%, rgba(16,185,129,0.05) 0%, transparent 50%);
            border-radius: 40px;
            z-index: 0;
        }

        /* === PROFESSIONAL FEATURE ICON === */
        .feat-icon-wrap {
            width: 52px; height: 52px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.25rem;
            flex-shrink: 0;
        }

        /* ===== LIGHT FOOTER ===== */
        .footer-dark {
            background: var(--r-gray-50);
            padding: 5rem 0 0;
            border-top: 1px solid var(--r-gray-200);
        }
        .footer-link {
            color: var(--r-gray-text);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            display: block;
            margin-bottom: 0.75rem;
            transition: color 0.2s;
        }
        .footer-link:hover { color: var(--r-dark); }
        .footer-col-head {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 1.25rem;
        }
        .footer-bottom {
            border-top: 1px solid var(--r-gray-200);
            padding: 2rem 0;
            margin-top: 4rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        .footer-reg-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--brand-accent-dim);
            border: 1px solid rgba(16,185,129,0.2);
            border-radius: 100px;
            padding: 5px 14px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--brand-accent);
        }

        /* ===== MOBILE RESPONSIVE OVERRIDES ===== */
        @media (max-width: 768px) {
            .footer-grid-cols { grid-template-columns: 1fr 1fr !important; gap: 2.5rem !important; }
            .footer-brand-col { grid-column: 1 / -1; }
            .footer-bottom { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .hero-split { padding: 5rem 0 2rem !important; }
            .hub-section { padding: 2rem 1.5rem !important; border-radius: 24px !important; }
            .presentation-wrap { padding: 0 1.25rem; }
            .cta-terminal { border-radius: 24px !important; }
            .p-block { min-height: auto !important; padding: 2rem 1.5rem !important; border-radius: 28px !important; }
        }
        @media (max-width: 480px) {
            .footer-grid-cols { grid-template-columns: 1fr !important; }
            .hero-metric-strip { padding: 1rem !important; gap: 1rem !important; }
        }
    </style>
</head>
<body>

    <!-- Catchy Global Mesh -->
    <div class="global-mesh"></div>

    <nav class="navbar-rev" id="mainNav">
        <a href="index.php" class="nav-logo">
            <div class="nav-logo-dot"></div>
            finpay
        </a>
        
        <div class="nav-links d-none d-md-flex">
            <a href="#features">Features</a>
            <a href="#cards">Cards</a>
            <a href="#crypto">Crypto</a>
            <a href="#wealth">Wealth</a>
        </div>

        <div class="d-flex align-items-center gap-3">
            <a href="auth/login.php" class="nav-links d-none d-sm-block text-decoration-none fw-bold" style="color: var(--r-dark);">Log in</a>
            <a href="auth/signup.php" class="btn-rev">Get started</a>
        </div>
    </nav>

    <main class="presentation-wrap">
        
        <!-- Split-screen Hero: Left type, Right floating UI -->
        <section class="hero-split reveal" id="hero">

            <!-- LEFT COLUMN: Copy + CTAs -->
            <div>
                <div class="eyebrow mb-4" style="background: rgba(16,185,129,0.08); color: var(--r-green);"><i class="fas fa-bolt"></i> Introducing FinPay</div>
                <h1 class="headline-super" style="padding: 0; text-align: left;">One app,<br><span class="accent-word">all things</span><br>money.</h1>
                <p class="hero-sub mt-4" style="text-align: left; max-width: 480px;">FinPay is the all-in-one financial super app. Send money globally, invest in crypto, manage your cards, and track your wealth &mdash; all in one beautifully designed account.</p>
                <div class="d-flex align-items-center flex-wrap gap-3 mt-5">
                    <a href="auth/signup.php" class="btn-rev" style="padding: 1rem 2.5rem; font-size: 1.05rem;">Open a free account <i class="fas fa-arrow-right ms-2"></i></a>
                    <a href="auth/login.php" style="font-weight: 700; color: var(--r-dark); text-decoration: none; font-size: 0.95rem;">Log in <i class="fas fa-arrow-right ms-1" style="font-size: 0.8rem;"></i></a>
                </div>
                <!-- Trust row -->
                <div class="d-flex align-items-center flex-wrap gap-4 mt-5 pt-2" style="border-top: 1px solid var(--r-gray-300);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-shield-halved" style="color: var(--r-green); font-size: 1rem;"></i>
                        <span style="font-size: 0.82rem; font-weight: 700; color: var(--r-gray-text);">FCA Regulated</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-lock" style="color: var(--r-blue); font-size: 1rem;"></i>
                        <span style="font-size: 0.82rem; font-weight: 700; color: var(--r-gray-text);">256-bit Encryption</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="color: #f59e0b; font-size: 0.75rem;"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                        <span style="font-size: 0.82rem; font-weight: 700; color: var(--r-gray-text);">4.9 App Store</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Floating Product UI -->
            <div class="hero-right-panel">
                <div class="hero-panel-bg"></div>

                <!-- Floating success chip -->
                <div class="hero-float-chip">
                    <div class="hero-float-chip-dot"></div>
                    <span style="font-size: 0.82rem; font-weight: 800; color: var(--r-dark);">Payment sent &mdash; £340</span>
                </div>

                <!-- Main dark card: Account overview -->
                <div class="hero-card-main">
                    <div class="hero-card-label">Total Balance</div>
                    <div class="hero-card-amount">&pound;18,340<span style="font-size: 1.4rem; opacity: 0.35;">.00</span></div>
                    <div class="hero-card-change"><i class="fas fa-arrow-trend-up"></i> +6.2% this month</div>
                    <div class="hero-card-divider"></div>
                    <div class="hero-card-tx">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="hero-card-tx-icon" style="background:#111;"><i class="fab fa-apple" style="color:#fff;"></i></div>
                            <div><div class="hero-card-tx-name">Apple Store</div><div class="hero-card-tx-date">Today, 9:42 AM</div></div>
                        </div>
                        <div style="font-size:0.85rem;font-weight:800;color:#ef4444;">-&pound;12.99</div>
                    </div>
                    <div class="hero-card-tx">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="hero-card-tx-icon" style="background:linear-gradient(135deg,#fbc531,#f7931a);"><i class="fab fa-bitcoin" style="color:#fff;font-size:0.75rem;"></i></div>
                            <div><div class="hero-card-tx-name">Bitcoin</div><div class="hero-card-tx-date">Yesterday</div></div>
                        </div>
                        <div style="font-size:0.85rem;font-weight:800;color:#10b981;">+&pound;340</div>
                    </div>
                    <div class="hero-card-tx">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="hero-card-tx-icon" style="background:#6366f1;"><i class="fas fa-plane" style="color:#fff;"></i></div>
                            <div><div class="hero-card-tx-name">Ryanair</div><div class="hero-card-tx-date">Mon, 24 Mar</div></div>
                        </div>
                        <div style="font-size:0.85rem;font-weight:800;color:#ef4444;">-&pound;89.00</div>
                    </div>
                </div>

                <!-- Floating FX rate card -->
                <div class="hero-float-fx">
                    <div class="hero-float-label">Live Rate</div>
                    <div style="display:flex;align-items:baseline;gap:4px;">
                        <span style="font-size:0.75rem;font-weight:700;color:var(--r-gray-text);">GBP &rarr; EUR</span>
                    </div>
                    <div class="hero-float-rate">1.168</div>
                    <div class="hero-float-sub"><i class="fas fa-arrow-trend-up me-1"></i>Interbank rate &bull; No fees</div>
                </div>
            </div>

        </section>

        <!-- Partner Marquee -->
        <div class="marquee-container reveal">
            <div class="marquee-content">
                <!-- Set 1 -->
                <div class="marquee-item"><i class="fab fa-apple"></i> Apple Pay</div>
                <div class="marquee-item"><i class="fab fa-google"></i> Google Pay</div>
                <div class="marquee-item"><i class="fab fa-cc-visa"></i> Visa</div>
                <div class="marquee-item"><i class="fab fa-cc-mastercard"></i> Mastercard</div>
                <div class="marquee-item"><i class="fab fa-stripe"></i> Stripe</div>
                <div class="marquee-item"><i class="fab fa-paypal"></i> PayPal</div>
                <div class="marquee-item"><i class="fab fa-bitcoin"></i> Crypto</div>
                <!-- Set 2 (Duplicated) -->
                <div class="marquee-item"><i class="fab fa-apple"></i> Apple Pay</div>
                <div class="marquee-item"><i class="fab fa-google"></i> Google Pay</div>
                <div class="marquee-item"><i class="fab fa-cc-visa"></i> Visa</div>
                <div class="marquee-item"><i class="fab fa-cc-mastercard"></i> Mastercard</div>
                <div class="marquee-item"><i class="fab fa-stripe"></i> Stripe</div>
                <div class="marquee-item"><i class="fab fa-paypal"></i> PayPal</div>
                <div class="marquee-item"><i class="fab fa-bitcoin"></i> Crypto</div>
            </div>
        </div>



        <!-- Revolut-style Hub Section: pure dark + glassmorphic floating UI -->
        <section class="hub-section reveal mb-4" id="hub">

            <!-- LEFT: Text Content -->
            <div style="position: relative; z-index: 2;">
                <div class="eyebrow dark" style="margin-bottom: 1.5rem;"><i class="fas fa-mobile-screen"></i> Your financial hub</div>
                <h2 style="font-size: clamp(2rem, 4vw, 3.5rem); font-weight: 900; color: #fff; letter-spacing: -2px; line-height: 1.05; margin-bottom: 1.5rem;">Everything you need, beautifully in one place.</h2>
                <p style="color: rgba(255,255,255,0.55); font-size: 1.05rem; font-weight: 500; line-height: 1.75; margin-bottom: 2rem; max-width: 440px;">FinPay replaces your bank account, investment app, and crypto wallet. Check your real-time portfolio, send payments instantly, and invest in global markets &mdash; from a single screen.</p>
                <!-- Three bullet points -->
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2.5rem;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 32px; height: 32px; border-radius: 10px; background: rgba(16,185,129,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-check" style="color: #10b981; font-size: 0.75rem;"></i></div>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.95rem; font-weight: 600;">Real-time balance and portfolio tracking</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 32px; height: 32px; border-radius: 10px; background: rgba(37,99,235,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-check" style="color: #2563eb; font-size: 0.75rem;"></i></div>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.95rem; font-weight: 600;">Instant payments to 50+ countries</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 32px; height: 32px; border-radius: 10px; background: rgba(139,92,246,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i class="fas fa-check" style="color: #8b5cf6; font-size: 0.75rem;"></i></div>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.95rem; font-weight: 600;">Crypto, stocks, and FX — all in one dashboard</span>
                    </div>
                </div>
                <a href="auth/signup.php" class="btn-rev" style="padding: 1rem 2.2rem;">Open your account <i class="fas fa-arrow-right ms-2"></i></a>
            </div>

            <!-- RIGHT: Glassmorphic Phone Mockup -->
            <div class="hub-phone">

                <!-- Floating Chip: Salary -->
                <div class="hub-chip" style="top: 5%; left: -8%;">
                    <div class="hub-chip-icon" style="background: rgba(16,185,129,0.2);"><i class="fas fa-briefcase" style="color: #10b981;"></i></div>
                    <div>
                        <div class="hub-chip-title">Salary</div>
                        <div class="hub-chip-sub">+£4,800 today</div>
                    </div>
                </div>

                <!-- Phone Frame -->
                <div class="phone-frame">
                    <div class="phone-notch"></div>
                    <div class="phone-balance">
                        <div class="phone-balance-label">Total Balance</div>
                        <div class="phone-balance-amount">£18,340</div>
                        <div class="phone-balance-change"><i class="fas fa-arrow-trend-up me-1"></i>+6.2% this month</div>
                    </div>
                    <div class="phone-actions">
                        <div class="phone-action-btn"><i class="fas fa-arrow-up"></i><span>Send</span></div>
                        <div class="phone-action-btn"><i class="fas fa-arrow-down"></i><span>Receive</span></div>
                        <div class="phone-action-btn"><i class="fas fa-plus"></i><span>Top Up</span></div>
                    </div>
                    <div class="phone-tx">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="phone-tx-icon" style="background:#111;"><i class="fab fa-apple" style="color:#fff;"></i></div>
                            <div><div class="phone-tx-name">Apple Store</div><div class="phone-tx-date">Today, 9:42 AM</div></div>
                        </div>
                        <div class="phone-tx-amt" style="color:#ef4444;">-£12.99</div>
                    </div>
                    <div class="phone-tx">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="phone-tx-icon" style="background:linear-gradient(135deg,#fbc531,#f7931a);"><i class="fab fa-bitcoin" style="color:#fff;font-size:0.7rem;"></i></div>
                            <div><div class="phone-tx-name">Bitcoin</div><div class="phone-tx-date">Yesterday</div></div>
                        </div>
                        <div class="phone-tx-amt" style="color:#10b981;">+£340</div>
                    </div>
                    <div class="phone-tx">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="phone-tx-icon" style="background:#6366f1;"><i class="fas fa-shopping-bag" style="color:#fff;"></i></div>
                            <div><div class="phone-tx-name">Amazon</div><div class="phone-tx-date">Mon, 14 Mar</div></div>
                        </div>
                        <div class="phone-tx-amt" style="color:#ef4444;">-£67.50</div>
                    </div>
                </div>

                <!-- Floating Chip: Crypto -->
                <div class="hub-chip" style="bottom: 8%; right: -10%;">
                    <div class="hub-chip-icon" style="background: rgba(251,197,49,0.15);"><i class="fab fa-bitcoin" style="color: #f7931a;"></i></div>
                    <div>
                        <div class="hub-chip-title">BTC Portfolio</div>
                        <div class="hub-chip-sub">£2,840 &uarr; 3.1%</div>
                    </div>
                </div>

            </div>
        </section>


        <!-- Feature Row: More than just banking (professional icon treatment) -->
        <section class="reveal" style="padding: 5rem 0; border-top: 1px solid var(--r-gray-300);">
            <div class="text-center mb-5">
                <div class="eyebrow mx-auto mb-3" style="background: rgba(99,102,241,0.08); color: #6366f1;"><i class="fas fa-layer-group"></i> Built for the modern world</div>
                <h2 style="font-size: clamp(1.8rem, 4vw, 3rem); font-weight: 900; letter-spacing: -1.5px;">More than just banking.</h2>
                <p style="color: var(--r-gray-text); max-width: 480px; margin: 0.75rem auto 0; font-size: 1rem; font-weight: 500;">A complete financial operating system — built for speed, security, and global reach.</p>
            </div>
            <div class="feature-row">

                <div class="feature-item">
                    <div class="feat-icon-wrap" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 8px 24px rgba(37,99,235,0.25);">
                        <i class="fas fa-user-plus" style="color:#fff; font-size:1.1rem;"></i>
                    </div>
                    <h4>Open an account in minutes</h4>
                    <p>No queues, no paperwork. Sign up, verify, and you're live. The fastest path to a fully functional bank account &mdash; anywhere in the world.</p>
                </div>

                <div class="feature-item">
                    <div class="feat-icon-wrap" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 8px 24px rgba(16,185,129,0.25);">
                        <i class="fas fa-earth-americas" style="color:#fff; font-size:1.1rem;"></i>
                    </div>
                    <h4>Send money across 50+ countries</h4>
                    <p>Real exchange rates, zero hidden fees. Your money arrives fast, wherever it needs to go &mdash; from London to Lagos in seconds.</p>
                </div>

                <div class="feature-item">
                    <div class="feat-icon-wrap" style="background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 8px 24px rgba(245,158,11,0.25);">
                        <i class="fas fa-chart-line" style="color:#fff; font-size:1.1rem;"></i>
                    </div>
                    <h4>Grow with crypto &amp; FX</h4>
                    <p>Buy Bitcoin with just &pound;1, or swap 30+ fiat currencies at interbank rates. Investing has never been this accessible.</p>
                </div>

                <div class="feature-item">
                    <div class="feat-icon-wrap" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); box-shadow: 0 8px 24px rgba(139,92,246,0.25);">
                        <i class="fas fa-shield-halved" style="color:#fff; font-size:1.1rem;"></i>
                    </div>
                    <h4>Bank-grade security, 24/7</h4>
                    <p>AES-256 encryption, biometric login, AI fraud monitoring, and one-tap card freeze. Every layer of your money is protected.</p>
                </div>

                <div class="feature-item">
                    <div class="feat-icon-wrap" style="background: linear-gradient(135deg, #ec4899, #db2777); box-shadow: 0 8px 24px rgba(236,72,153,0.25);">
                        <i class="fas fa-credit-card" style="color:#fff; font-size:1.1rem;"></i>
                    </div>
                    <h4>Premium virtual &amp; physical cards</h4>
                    <p>Generate virtual cards instantly for safer online spending. Order a sleek physical card with smart spending controls built in.</p>
                </div>

                <div class="feature-item">
                    <div class="feat-icon-wrap" style="background: linear-gradient(135deg, #14b8a6, #0d9488); box-shadow: 0 8px 24px rgba(20,184,166,0.25);">
                        <i class="fas fa-chart-bar" style="color:#fff; font-size:1.1rem;"></i>
                    </div>
                    <h4>Smart spending insights</h4>
                    <p>Transactions auto-categorize into food, travel, subscriptions, and more. See your money clearly &mdash; and build smarter habits.</p>
                </div>

            </div>
        </section>

        <!-- Feature Grid (2x2) -->
        <div class="grid-2" id="features">
            
            <!-- Send & Receive -->
            <div class="p-block reveal interactive-block">
                <div class="eyebrow"><i class="fas fa-paper-plane"></i> Instant Transfers</div>
                <div class="text-max-500">
                    <h3 class="headline-card">Send money anywhere in seconds.</h3>
                    <p class="text-body">Paying a friend back for dinner? Sending money to family overseas? FinPay processes payments in real-time — no delays, no hidden fees, no paperwork. Just tap and it's done.</p>
                </div>
                
                <!-- Main Transfer UI -->
                <div class="ui-interactive mock-transfer-card shadow-sm border border-light target-3d" style="--base-rot: 0deg;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div style="font-weight: 700; color: var(--r-gray-text); font-size: 0.85rem;">SEND TO</div>
                        <div style="background: rgba(37,99,235,0.1); color: var(--r-blue); padding: 4px 10px; border-radius: 100px; font-weight: 700; font-size: 0.75rem;">UK (GBP)</div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="mock-av" style="background: linear-gradient(135deg, #fbcfe8, #ec4899);"><i class="fas fa-user text-white opacity-75"></i></div>
                        <div class="ms-3">
                            <div style="font-weight: 700; color: var(--r-dark);">Sarah Jenkins</div>
                            <div style="font-size: 0.8rem; color: var(--r-gray-text);">@sarahj</div>
                        </div>
                    </div>
                    <div class="mock-amt">£150.00</div>
                    <div class="mock-btn mt-3 w-100" style="height: 44px; border-radius: 12px; transition: transform 0.2s;">Send Instantly <i class="fas fa-bolt ms-2"></i></div>
                </div>

                <!-- Secondary Orbital Card filling the void -->
                <div class="ui-interactive hide-mobile shadow-sm border target-3d" style="background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); padding: 1.2rem; width: 260px; border-radius: 20px; right: 80px; bottom: 220px; --base-rot: -8deg; transform: rotate(-8deg); z-index: 1;">
                    <div style="font-weight: 700; color: var(--r-gray-text); font-size: 0.75rem; margin-bottom: 5px; letter-spacing: 1px;">REQUEST FROM</div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="mock-av" style="background: var(--r-purple); width: 30px; height: 30px; font-size: 0.8rem;">MJ</div>
                        <div class="ms-2" style="font-weight: 700; font-size: 0.85rem;">Mike J.</div>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: var(--r-dark);">£50.00</div>
                </div>
            </div>

            <!-- Virtual Cards -->
            <div class="p-block p-block-blue reveal interactive-block" id="cards">
                <div class="eyebrow" style="background: rgba(99,102,241,0.08); color: #6366f1;"><i class="fas fa-credit-card"></i> Smart Cards</div>
                <div class="text-max-500">
                    <h3 class="headline-card">Your card, your rules.</h3>
                    <p class="text-body">Get virtual cards instantly for safer online spending, or order a premium physical card delivered to your door. Freeze, unfreeze, set limits — you're always in control.</p>
                </div>
                
                <!-- 3D Stacking Cards -->
                <div class="ui-interactive mock-cc mock-cc-light target-3d-slow">
                    <div class="d-flex justify-content-between">
                        <div style="font-weight: 900; font-size: 1.2rem; letter-spacing: -1px;">finpay</div>
                        <i class="fas fa-wifi" style="transform: rotate(90deg);"></i>
                    </div>
                    <div>
                        <div style="font-family: monospace; font-size: 1.1rem; letter-spacing: 2px;">**** **** **** 4921</div>
                        <div class="d-flex justify-content-between align-items-end mt-2">
                            <div style="font-size: 0.8rem; font-weight: 600;">J. DOE</div>
                            <i class="fab fa-cc-visa fs-2"></i>
                        </div>
                    </div>
                </div>
                <div class="ui-interactive mock-cc shadow-lg target-3d">
                    <div class="d-flex justify-content-between">
                        <div style="font-weight: 900; font-size: 1.2rem; letter-spacing: -1px; color: white;">finpay</div>
                        <i class="fas fa-wifi" style="transform: rotate(90deg); color: white;"></i>
                    </div>
                    <div>
                        <div style="font-family: monospace; font-size: 1.1rem; letter-spacing: 2px; color: white;">**** **** **** 8832</div>
                        <div class="d-flex justify-content-between align-items-end mt-2">
                            <div style="font-size: 0.8rem; font-weight: 600; color: white;">JAMES DOE</div>
                            <i class="fab fa-cc-mastercard fs-2 text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics -->
            <div class="p-block reveal interactive-block" id="wealth">
                <div class="eyebrow green"><i class="fas fa-chart-bar"></i> Spending Analytics</div>
                <div class="text-max-500">
                    <h3 class="headline-card">Know exactly where every penny goes.</h3>
                    <p class="text-body">FinPay automatically categorizes your transactions — food, travel, subscriptions, shopping. See the full picture of your spending at a glance and build better financial habits.</p>
                </div>
                
                <div class="ui-interactive mock-transfer-card border-0 shadow-lg target-3d" style="width: 300px; background: white; --base-rot: 0deg;">
                    <div style="font-weight: 700; font-size: 1.2rem; margin-bottom: 20px; color: var(--r-dark);">Spent this month</div>
                    <div style="font-size: 2.5rem; font-weight: 900; color: var(--r-dark); margin-bottom: 30px; letter-spacing: -2px;">£1,420</div>
                    
                    <div style="height: 120px; display: flex; align-items: flex-end; gap: 15px; border-bottom: 2px solid var(--r-gray-200); padding-bottom: 10px;">
                        <div class="chart-bar" style="width: 40px; height: 40%; background: var(--r-gray-300); border-radius: 8px 8px 0 0; transition-delay: 0.1s;"></div>
                        <div class="chart-bar" style="width: 40px; height: 70%; background: var(--r-gray-300); border-radius: 8px 8px 0 0; transition-delay: 0.2s;"></div>
                        <div class="chart-bar" style="width: 40px; height: 30%; background: var(--r-gray-300); border-radius: 8px 8px 0 0; transition-delay: 0.3s;"></div>
                        <div class="chart-bar" style="width: 40px; height: 90%; background: linear-gradient(to top, var(--r-blue), #60a5fa); border-radius: 8px 8px 0 0; box-shadow: 0 5px 20px rgba(37,99,235,0.3); transition-delay: 0.4s;"></div>
                        <div class="chart-bar" style="width: 40px; height: 50%; background: var(--r-gray-300); border-radius: 8px 8px 0 0; transition-delay: 0.5s;"></div>
                    </div>
                </div>

                <!-- Floating Tag — bottom-right to avoid eyebrow -->
                <div class="ui-interactive hide-mobile shadow-lg target-3d" style="background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); padding: 12px 20px; border-radius: 100px; color: var(--r-dark); font-weight: 800; right: 30px; bottom: 180px; --base-rot: 8deg; transform: rotate(8deg); display: flex; align-items: center; gap: 10px; z-index: 10;">
                    <div style="width: 12px; height: 12px; background: var(--r-pink); border-radius: 50%;"></div> Groceries
                </div>
            </div>

            <!-- Currency/FX -->
            <div class="p-block reveal interactive-block" style="background: radial-gradient(circle at top left, #fef3c7, #fde68a);">
                <div class="eyebrow" style="background: rgba(217,119,6,0.1); color: #d97706;"><i class="fas fa-globe"></i> Global Exchange</div>
                <div class="text-max-500">
                    <h3 class="headline-card">Hold 30+ currencies. Exchange in real-time.</h3>
                    <p class="text-body" style="color: #92400e;">Traveling? Working abroad? FinPay gives you interbank exchange rates with zero markup. Hold dollars, euros, pounds, and swap between them instantly — no bank branch required.</p>
                </div>
                
                <div class="ui-interactive mock-transfer-card shadow-lg border-0 target-3d" style="--base-rot: 0deg;">
                    <div style="background: var(--r-gray-100); border-radius: 16px; padding: 15px; margin-bottom: 10px;">
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--r-gray-text);">YOU SELL</div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div style="font-size: 2rem; font-weight: 900; color: var(--r-dark); letter-spacing: -2px;">£1,000</div>
                            <div style="font-weight: 700; background: white; padding: 8px 12px; border-radius: 100px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); color: var(--r-dark);">GBP <i class="fas fa-chevron-down ms-1 fs-6"></i></div>
                        </div>
                    </div>
                    <div style="text-align: center; margin: -15px 0; position: relative; z-index: 2;">
                        <div style="width: 36px; height: 36px; background: var(--r-blue); color: white; border-radius: 50%; display: inline-flex; justify-content: center; align-items: center; border: 4px solid white;"><i class="fas fa-arrow-down"></i></div>
                    </div>
                    <div style="background: var(--r-gray-100); border-radius: 16px; padding: 15px;">
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--r-gray-text);">YOU GET</div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div style="font-size: 2rem; font-weight: 900; color: var(--r-blue); letter-spacing: -2px;">€1,168</div>
                            <div style="font-weight: 700; background: white; padding: 8px 12px; border-radius: 100px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); color: var(--r-dark);">EUR <i class="fas fa-chevron-down ms-1 fs-6"></i></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>
        <section class="p-block p-block-dark mb-5 reveal interactive-block" style="min-height: 600px; background: radial-gradient(circle at right center, rgba(37,99,235,0.1), transparent 50%), radial-gradient(circle at left bottom, rgba(236,72,153,0.05), transparent 40%), var(--r-dark);" id="crypto">
            <div class="text-max-500" style="pointer-events: none;">
                <div class="eyebrow dark"><i class="fab fa-bitcoin"></i> 100+ Crypto Assets</div>
                <h2 class="headline-section text-white">The world's top cryptocurrencies, in your pocket.</h2>
                <p class="text-body-large text-white opacity-75">Buy Bitcoin, sell Ethereum, stake Solana — or just hold. FinPay gives you real-time prices, portfolio tracking, and instant swaps across 100+ tokens. No confusing wallets, no jargon. Just crypto made simple.</p>
                <div class="mt-4 pointer-events-auto">
                    <a href="auth/signup.php" class="btn-rev-light">Start investing with £1 <i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            </div>
            
            <!-- Professional Orbital Bag of Cryptos — real logos -->
            <div class="ui-interactive mock-crypto c-btc target-3d">
                <img src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/btc.png" alt="Bitcoin" style="width:44px;height:44px;object-fit:contain;">
            </div>
            <div class="ui-interactive mock-crypto c-eth target-3d">
                <img src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/eth.png" alt="Ethereum" style="width:44px;height:44px;object-fit:contain;">
            </div>
            <div class="ui-interactive mock-crypto c-usdt target-3d-slow">
                <img src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/usdt.png" alt="Tether" style="width:38px;height:38px;object-fit:contain;">
            </div>
            <div class="ui-interactive mock-crypto c-sol target-3d">
                <img src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/sol.png" alt="Solana" style="width:42px;height:42px;object-fit:contain;">
            </div>
            <div class="ui-interactive mock-crypto c-ada target-3d-slow">
                <img src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/ada.png" alt="Cardano" style="width:38px;height:38px;object-fit:contain;">
            </div>
            <div class="ui-interactive mock-crypto c-doge target-3d">
                <img src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/doge.png" alt="Dogecoin" style="width:36px;height:36px;object-fit:contain;">
            </div>
            <div class="ui-interactive mock-crypto c-shib target-3d-slow">
                <img src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/bnb.png" alt="BNB" style="width:34px;height:34px;object-fit:contain;">
            </div>
        </section>


    <!-- CTA — tight editorial, two lines of content only -->
    <section class="reveal" style="padding: 3rem 0 5rem;">
        <div class="presentation-wrap">
            <div class="cta-terminal" style="padding: 4rem 4.5rem;">
                <div style="position: relative; z-index: 2; max-width: 600px; margin: 0 auto; text-align: center;">
                    <h2 style="font-size: clamp(2rem, 5vw, 3.8rem); font-weight: 900; letter-spacing: -2px; color: #fff; line-height: 1.05; margin-bottom: 1.25rem;">
                        One account.<br>Every financial tool you need.
                    </h2>
                    <p style="font-size: 1rem; color: rgba(255,255,255,0.45); font-weight: 500; margin-bottom: 2.5rem; line-height: 1.65;">
                        Banking, crypto, FX, and cards — open free in under 2 minutes.
                    </p>
                    <a href="auth/signup.php" class="btn-rev" style="padding: 1rem 2.8rem; font-size: 1rem; display: inline-flex; align-items: center; gap: 10px;">
                        Open a free account <i class="fas fa-arrow-right"></i>
                    </a>
                    <div style="margin-top: 2rem; display: flex; align-items: center; justify-content: center; gap: 2rem; flex-wrap: wrap;">
                        <span style="font-size: 0.8rem; color: rgba(255,255,255,0.28); font-weight: 600; display:flex; align-items:center; gap:7px;"><i class="fas fa-shield-halved" style="color:var(--brand-accent);"></i> FCA Authorised</span>
                        <span style="font-size: 0.8rem; color: rgba(255,255,255,0.28); font-weight: 600; display:flex; align-items:center; gap:7px;"><i class="fas fa-lock" style="color:rgba(255,255,255,0.4);"></i> Bank-grade security</span>
                        <span style="font-size: 0.8rem; color: rgba(255,255,255,0.28); font-weight: 600;">No credit card required</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Light Footer -->
    <footer class="footer-dark">
        <div class="presentation-wrap">

            <!-- Top: Brand + Links grid -->
            <div class="footer-grid-cols" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 4rem; padding-bottom: 4rem;">

                <!-- Brand -->
                <div class="footer-brand-col">
                    <a href="index.php" class="nav-logo" style="color: var(--r-dark); display: inline-flex; margin-bottom: 1.5rem;"><div class="nav-logo-dot"></div>finpay</a>
                    <p style="font-size: 0.875rem; color: var(--r-gray-text); font-weight: 500; line-height: 1.8; max-width: 280px; margin-bottom: 2rem;">The financial super app for the modern world. Banking, crypto, FX, and cards &mdash; all in one account, free to open in 2 minutes.</p>
                    <!-- Social icons -->
                    <div class="d-flex gap-2">
                        <a href="#" style="width:38px;height:38px;border-radius:12px;background:var(--r-gray-100);border:1px solid var(--r-gray-200);display:flex;align-items:center;justify-content:center;color:var(--r-gray-text);text-decoration:none;transition:all 0.2s;" onmouseover="this.style.background='#e5e7eb';this.style.color='#111'" onmouseout="this.style.background='var(--r-gray-100)';this.style.color='var(--r-gray-text)'">
                            <i class="fab fa-x-twitter" style="font-size:0.85rem;"></i>
                        </a>
                        <a href="#" style="width:38px;height:38px;border-radius:12px;background:var(--r-gray-100);border:1px solid var(--r-gray-200);display:flex;align-items:center;justify-content:center;color:var(--r-gray-text);text-decoration:none;transition:all 0.2s;" onmouseover="this.style.background='#e5e7eb';this.style.color='#111'" onmouseout="this.style.background='var(--r-gray-100)';this.style.color='var(--r-gray-text)'">
                            <i class="fab fa-linkedin-in" style="font-size:0.85rem;"></i>
                        </a>
                        <a href="#" style="width:38px;height:38px;border-radius:12px;background:var(--r-gray-100);border:1px solid var(--r-gray-200);display:flex;align-items:center;justify-content:center;color:var(--r-gray-text);text-decoration:none;transition:all 0.2s;" onmouseover="this.style.background='#e5e7eb';this.style.color='#111'" onmouseout="this.style.background='var(--r-gray-100)';this.style.color='var(--r-gray-text)'">
                            <i class="fab fa-instagram" style="font-size:0.85rem;"></i>
                        </a>
                    </div>
                </div>

                <!-- Product -->
                <div>
                    <div class="footer-col-head">Product</div>
                    <a href="#features" class="footer-link">Transfers</a>
                    <a href="#cards" class="footer-link">Cards</a>
                    <a href="#crypto" class="footer-link">Crypto</a>
                    <a href="#wealth" class="footer-link">Analytics</a>
                    <a href="auth/signup.php" class="footer-link">Open account</a>
                </div>

                <!-- Company -->
                <div>
                    <div class="footer-col-head">Company</div>
                    <a href="#" class="footer-link">About us</a>
                    <a href="#" class="footer-link">Careers</a>
                    <a href="#" class="footer-link">Newsroom</a>
                    <a href="#" class="footer-link">Blog</a>
                    <a href="#" class="footer-link">Contact</a>
                </div>

                <!-- Legal -->
                <div>
                    <div class="footer-col-head">Legal</div>
                    <a href="#" class="footer-link">Privacy Policy</a>
                    <a href="#" class="footer-link">Terms of Service</a>
                    <a href="#" class="footer-link">Cookie Policy</a>
                    <a href="#" class="footer-link">Accessibility</a>
                    <a href="#" class="footer-link">Security</a>
                </div>

            </div>

            <!-- Regulatory bar -->
            <div style="border-top: 1px solid var(--r-gray-200); padding: 1.5rem 0;">
                <div class="footer-reg-badge mb-3"><i class="fas fa-shield-halved"></i> FCA Authorised &amp; Regulated &bull; Firm Reference: 987654</div>
                <p style="font-size: 0.73rem; color: #9ca3af; line-height: 1.9; max-width: 900px; margin: 0;">
                    FinPay is a financial technology company, not a bank. Banking services are provided by licensed banking partners. The FinPay Card is issued pursuant to licence by Visa/Mastercard International. Cryptocurrency products and services are offered by FinPay Digital Assets Ltd and involve significant risk, including the risk of total loss. Past performance is not indicative of future results. Investments may go up as well as down. FinPay is registered in England &amp; Wales. Registered office: 25 Canary Wharf, London E14 5AB.
                </p>
            </div>

            <!-- Bottom copyright bar -->
            <div class="footer-bottom">
                <div style="font-size: 0.78rem; color: #9ca3af; font-weight: 600;">
                    &copy; <?php echo date('Y'); ?> FinPay Inc. All rights reserved.
                </div>
                <div style="display:flex;gap:2rem;flex-wrap:wrap;">
                    <a href="#" class="footer-link" style="margin:0;">Privacy</a>
                    <a href="#" class="footer-link" style="margin:0;">Terms</a>
                    <a href="#" class="footer-link" style="margin:0;">Cookies</a>
                    <a href="#" class="footer-link" style="margin:0;">Sitemap</a>
                </div>
            </div>

        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // ── Enhanced Multi-layer Parallax ──
            const mesh       = document.querySelector('.global-mesh');
            const heroText   = document.querySelector('.pt-hero');
            const statStrip  = document.querySelector('.reveal[style*="padding: 4rem"]');
            const photoBlock = document.querySelector('.p-block-dark.mb-4');
            const pBlocks    = document.querySelectorAll('.p-block:not(.p-block-dark)'); // light feature cards
            const cryptoSection = document.getElementById('crypto');

            let ticking = false;
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        const s = window.scrollY;

                        // Mesh moves down at 40% scroll speed (parallax depth)
                        if (mesh) mesh.style.transform = `translateY(${s * 0.4}px)`;

                        // Hero text floats up slowly and fades
                        if (heroText && s < 800) {
                            heroText.style.transform = `translateY(${s * -0.1}px)`;
                            heroText.style.opacity   = Math.max(0, 1 - (s / 500));
                        }

                        // Stat strip floats up gently
                        if (statStrip) {
                            const offset = statStrip.getBoundingClientRect().top + s;
                            const rel    = s - offset + window.innerHeight;
                            if (rel > 0) statStrip.style.transform = `translateY(${rel * -0.04}px)`;
                        }

                        // Photo block has its own subtle translate
                        if (photoBlock) {
                            const rect = photoBlock.getBoundingClientRect();
                            const mid  = rect.top + rect.height / 2 - window.innerHeight / 2;
                            photoBlock.querySelector('img').style.transform = `translateY(${mid * 0.06}px) scale(1.04)`;
                        }

                        // Feature cards each offset at a slightly different rate
                        pBlocks.forEach((b, i) => {
                            const rect = b.getBoundingClientRect();
                            if (rect.top < window.innerHeight && rect.bottom > 0) {
                                const mid = rect.top + rect.height / 2 - window.innerHeight / 2;
                                const factor = i % 2 === 0 ? 0.025 : 0.04;
                                b.style.transform = `translateY(${mid * -factor}px)`;
                            }
                        });

                        // Crypto section: coins drift independently at different speeds
                        if (cryptoSection) {
                            const cRect  = cryptoSection.getBoundingClientRect();
                            if (cRect.top < window.innerHeight && cRect.bottom > 0) {
                                const progress = (window.innerHeight - cRect.top) / (window.innerHeight + cRect.height);
                                const coins = cryptoSection.querySelectorAll('.mock-crypto');
                                coins.forEach((c, idx) => {
                                    const drift = (progress - 0.5) * (idx % 2 === 0 ? 40 : -30);
                                    const existingRot = getComputedStyle(c).getPropertyValue('--base-rot') || '0deg';
                                    c.style.transform = `translateY(${drift}px) rotate(${existingRot}) scale(${c.classList.contains('c-btc') ? 1.3 : 1})`;
                                });
                            }
                        }

                        ticking = false;
                    });
                    ticking = true;
                }
            });

            // 1. Initial CSS Object Intersection Animations (Fades only, no numbers)
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });

            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

            // 2. High-Fidelity 3D Pointer Events (Hover Parallax)
            // Desktop only checking
            if(window.innerWidth > 768) {
                const interactiveBlocks = document.querySelectorAll('.interactive-block');
                
                interactiveBlocks.forEach(block => {
                    let targets = Array.from(block.querySelectorAll('.target-3d, .target-3d-slow'));
                    
                    block.addEventListener('mousemove', (e) => {
                        const rect = block.getBoundingClientRect();
                        
                        // Calculate mouse position relative to center [-1 to 1]
                        const x = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
                        const y = ((e.clientY - rect.top) / rect.height - 0.5) * 2;
                        
                        // Request animation frame for buttery smooth hardware acceleration
                        window.requestAnimationFrame(() => {
                            targets.forEach(target => {
                                // Strip existing transforms and apply dynamic 3d perspective
                                const intensity = target.classList.contains('target-3d-slow') ? 4 : 10;
                                
                                // Recover base rotation if it exists
                                const computedStyle = window.getComputedStyle(target);
                                const baseRot = computedStyle.getPropertyValue('--base-rot') || '0deg';
                                
                                target.style.transform = `perspective(1000px) rotateX(${-y * intensity}deg) rotateY(${x * intensity}deg) translateZ(${intensity * 2}px) rotate(${baseRot})`;
                                target.style.transition = 'transform 0.1s linear';
                            });
                        });
                    });

                    block.addEventListener('mouseleave', () => {
                        window.requestAnimationFrame(() => {
                            targets.forEach(target => {
                                const computedStyle = window.getComputedStyle(target);
                                const baseRot = computedStyle.getPropertyValue('--base-rot') || '0deg';
                                target.style.transition = 'transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1)';
                                target.style.transform = `rotate(${baseRot})`;
                            });
                        });
                    });
                });
            }
        });
    </script>
</body>
</html>
