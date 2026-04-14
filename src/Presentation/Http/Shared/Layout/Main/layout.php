<?php

declare(strict_types=1);

use App\Presentation\Http\Shared\Layout\Main\MainAsset;

/**
 * @var \App\Shared\ApplicationParams $applicationParams
 * @var Yiisoft\Aliases\Aliases $aliases
 * @var Yiisoft\Assets\AssetManager $assetManager
 * @var string $content
 * @var string|null $csrf
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\CurrentRoute $currentRoute
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 */

$assetManager->register(MainAsset::class);

$this->addCssFiles($assetManager->getCssFiles());
$this->addCssStrings($assetManager->getCssStrings());
$this->addJsFiles($assetManager->getJsFiles());
$this->addJsStrings($assetManager->getJsStrings());
$this->addJsVars($assetManager->getJsVars());

$this->beginPage()
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Кажется, вы заблудились...</title>
  <link href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.16/index.css" rel="stylesheet">
  <style>
      * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
      }

      body {
          font-family: 'Inter', sans-serif;
          min-height: 100vh;
          background: #0f0f1a;
          color: #e0e0e0;
          overflow: hidden;
          display: flex;
          align-items: center;
          justify-content: center;
      }

      .fog {
          position: fixed;
          inset: 0;
          z-index: 0;
          pointer-events: none;
      }

      .fog-layer {
          position: absolute;
          inset: -50%;
          background: radial-gradient(ellipse at center, rgba(100, 100, 180, 0.08) 0%, transparent 70%);
          animation: drift 20s ease-in-out infinite alternate;
      }

      .fog-layer:nth-child(2) {
          background: radial-gradient(ellipse at 30% 60%, rgba(140, 100, 200, 0.06) 0%, transparent 60%);
          animation-duration: 25s;
          animation-direction: alternate-reverse;
      }

      .fog-layer:nth-child(3) {
          background: radial-gradient(ellipse at 70% 30%, rgba(80, 120, 180, 0.07) 0%, transparent 65%);
          animation-duration: 30s;
      }

      @keyframes drift {
          0% { transform: translate(0, 0) scale(1); }
          33% { transform: translate(30px, -20px) scale(1.05); }
          66% { transform: translate(-20px, 15px) scale(0.95); }
          100% { transform: translate(15px, -10px) scale(1.02); }
      }

      .stars {
          position: fixed;
          inset: 0;
          z-index: 0;
          pointer-events: none;
      }

      .star {
          position: absolute;
          width: 2px;
          height: 2px;
          background: #fff;
          border-radius: 50%;
          animation: twinkle var(--duration) ease-in-out infinite;
          opacity: 0;
      }

      @keyframes twinkle {
          0%, 100% { opacity: 0.1; transform: scale(0.5); }
          50% { opacity: var(--max-opacity); transform: scale(1); }
      }

      .container {
          position: relative;
          z-index: 1;
          text-align: center;
          padding: 2rem;
          max-width: 700px;
      }

      .scene {
          position: relative;
          width: 320px;
          height: 280px;
          margin: 0 auto 2rem;
      }

      /* Road */
      .road {
          position: absolute;
          bottom: 40px;
          left: 50%;
          transform: translateX(-50%);
          width: 200px;
          height: 8px;
          background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
          border-radius: 4px;
      }

      .road::before,
      .road::after {
          content: '';
          position: absolute;
          bottom: 12px;
          width: 4px;
          height: 4px;
          background: rgba(255, 200, 100, 0.4);
          border-radius: 50%;
      }

      .road::before { left: 30px; }
      .road::after { right: 30px; }

      /* Signpost */
      .signpost {
          position: absolute;
          bottom: 48px;
          left: 50%;
          transform: translateX(-50%);
      }

      .pole {
          width: 4px;
          height: 100px;
          background: linear-gradient(to bottom, #5a4a3a, #3a2a1a);
          margin: 0 auto;
          border-radius: 2px;
          position: relative;
      }

      .sign {
          position: absolute;
          top: 0;
          left: 50%;
          transform: translateX(-50%);
          background: #2a3a5a;
          color: #aabbcc;
          font-size: 11px;
          font-weight: 600;
          padding: 6px 12px;
          border-radius: 4px;
          white-space: nowrap;
          letter-spacing: 0.5px;
          animation: sway 4s ease-in-out infinite;
          transform-origin: center top;
          border: 1px solid rgba(255,255,255,0.1);
      }

      .sign span {
          display: block;
          font-size: 9px;
          opacity: 0.6;
          margin-top: 2px;
          letter-spacing: 1px;
      }

      @keyframes sway {
          0%, 100% { transform: translateX(-50%) rotate(-1deg); }
          50% { transform: translateX(-50%) rotate(1deg); }
      }

      /* Character */
      .character {
          position: absolute;
          bottom: 56px;
          left: 50%;
          transform: translateX(-30px);
          animation: float 6s ease-in-out infinite;
      }

      @keyframes float {
          0%, 100% { transform: translateX(-30px) translateY(0); }
          50% { transform: translateX(-30px) translateY(-8px); }
      }

      .char-body {
          width: 30px;
          height: 40px;
          background: linear-gradient(135deg, #6a5acd, #4a3a9d);
          border-radius: 10px 10px 6px 6px;
          position: relative;
      }

      .char-head {
          width: 28px;
          height: 28px;
          background: linear-gradient(135deg, #f0c8a0, #d4a878);
          border-radius: 50%;
          position: absolute;
          top: -26px;
          left: 50%;
          transform: translateX(-50%);
      }

      .char-eyes {
          position: absolute;
          top: 10px;
          left: 50%;
          transform: translateX(-50%);
          display: flex;
          gap: 6px;
      }

      .char-eye {
          width: 4px;
          height: 5px;
          background: #2a1a3a;
          border-radius: 50%;
          animation: blink 4s ease-in-out infinite;
      }

      @keyframes blink {
          0%, 45%, 55%, 100% { transform: scaleY(1); }
          50% { transform: scaleY(0.1); }
      }

      .char-mouth {
          position: absolute;
          bottom: 6px;
          left: 50%;
          transform: translateX(-50%);
          width: 8px;
          height: 4px;
          border-bottom: 2px solid #a06040;
          border-radius: 0 0 4px 4px;
      }

      /* Legs */
      .char-legs {
          position: absolute;
          bottom: -14px;
          left: 50%;
          transform: translateX(-50%);
          display: flex;
          gap: 4px;
      }

      .char-leg {
          width: 8px;
          height: 14px;
          background: #3a2a5a;
          border-radius: 0 0 4px 4px;
      }

      .char-leg:first-child {
          animation: legMove 2s ease-in-out infinite;
      }

      .char-leg:last-child {
          animation: legMove 2s ease-in-out infinite 0.5s;
      }

      @keyframes legMove {
          0%, 100% { transform: rotate(0); }
          50% { transform: rotate(5deg); }
      }

      /* Lantern */
      .lantern {
          position: absolute;
          top: -20px;
          right: -22px;
      }

      .lantern-body {
          width: 14px;
          height: 18px;
          background: linear-gradient(135deg, #d4a020, #b8860b);
          border-radius: 3px;
          position: relative;
          animation: lanternSwing 3s ease-in-out infinite;
          transform-origin: top center;
      }

      .lantern-top {
          width: 8px;
          height: 4px;
          background: #8b7530;
          margin: 0 auto;
          border-radius: 2px 2px 0 0;
      }

      .lantern-handle {
          width: 12px;
          height: 8px;
          border: 2px solid #8b7530;
          border-bottom: none;
          border-radius: 6px 6px 0 0;
          margin: 0 auto;
      }

      .lantern-glow {
          position: absolute;
          inset: -20px;
          background: radial-gradient(circle, rgba(255, 200, 80, 0.3) 0%, transparent 70%);
          border-radius: 50%;
          animation: glowPulse 2s ease-in-out infinite;
      }

      @keyframes lanternSwing {
          0%, 100% { transform: rotate(-5deg); }
          50% { transform: rotate(5deg); }
      }

      @keyframes glowPulse {
          0%, 100% { opacity: 0.6; transform: scale(1); }
          50% { opacity: 1; transform: scale(1.1); }
      }

      /* Trees */
      .tree {
          position: absolute;
          bottom: 40px;
      }

      .tree-left {
          left: 20px;
      }

      .tree-right {
          right: 15px;
      }

      .tree-trunk {
          width: 8px;
          height: 40px;
          background: linear-gradient(to right, #2a1a0a, #3a2a1a);
          margin: 0 auto;
          border-radius: 2px;
      }

      .tree-top {
          width: 0;
          height: 0;
          border-left: 25px solid transparent;
          border-right: 25px solid transparent;
          border-bottom: 40px solid #1a3a2a;
          position: relative;
          margin-bottom: -5px;
      }

      .tree-top::before {
          content: '';
          position: absolute;
          top: 15px;
          left: -20px;
          border-left: 20px solid transparent;
          border-right: 20px solid transparent;
          border-bottom: 35px solid #1e4430;
      }

      .tree-left .tree-top {
          border-bottom-color: #1a3a2a;
          animation: treeSway 8s ease-in-out infinite;
          transform-origin: bottom center;
      }

      .tree-left .tree-top::before {
          border-bottom-color: #1e4430;
      }

      .tree-right .tree-top {
          border-bottom-color: #183228;
          animation: treeSway 8s ease-in-out infinite 2s;
          transform-origin: bottom center;
      }

      .tree-right .tree-top::before {
          border-bottom-color: #1a3830;
      }

      @keyframes treeSway {
          0%, 100% { transform: rotate(0); }
          25% { transform: rotate(0.5deg); }
          75% { transform: rotate(-0.5deg); }
      }

      /* Fireflies */
      .firefly {
          position: absolute;
          width: 4px;
          height: 4px;
          background: rgba(180, 255, 100, 0.8);
          border-radius: 50%;
          box-shadow: 0 0 8px rgba(180, 255, 100, 0.4);
          animation: fireflyMove var(--fly-duration) ease-in-out infinite;
      }

      @keyframes fireflyMove {
          0% { transform: translate(0, 0); opacity: 0; }
          10% { opacity: 1; }
          50% { transform: translate(var(--fly-x), var(--fly-y)); opacity: 0.6; }
          90% { opacity: 1; }
          100% { transform: translate(var(--fly-end-x), var(--fly-end-y)); opacity: 0; }
      }

      /* Moon */
      .moon {
          position: absolute;
          top: 20px;
          right: 40px;
          width: 50px;
          height: 50px;
          background: radial-gradient(circle at 35% 35%, #f5f0d0, #e0d8a0);
          border-radius: 50%;
          box-shadow: 0 0 30px rgba(240, 230, 180, 0.2), 0 0 60px rgba(240, 230, 180, 0.1);
          animation: moonGlow 5s ease-in-out infinite;
      }

      .moon::after {
          content: '';
          position: absolute;
          top: 8px;
          left: 12px;
          width: 35px;
          height: 35px;
          background: radial-gradient(circle, transparent 40%, rgba(200, 190, 150, 0.15));
          border-radius: 50%;
      }

      @keyframes moonGlow {
          0%, 100% { box-shadow: 0 0 30px rgba(240, 230, 180, 0.2), 0 0 60px rgba(240, 230, 180, 0.1); }
          50% { box-shadow: 0 0 40px rgba(240, 230, 180, 0.3), 0 0 80px rgba(240, 230, 180, 0.15); }
      }

      /* Content */
      .error-code {
          font-size: clamp(80px, 15vw, 120px);
          font-weight: 800;
          line-height: 1;
          background: linear-gradient(135deg, #6a5acd, #a78bfa, #6a5acd);
          background-size: 200% 200%;
          -webkit-background-clip: text;
          -webkit-text-fill-color: transparent;
          background-clip: text;
          animation: gradientShift 6s ease-in-out infinite;
          margin-bottom: 0.5rem;
          letter-spacing: -3px;
      }

      @keyframes gradientShift {
          0%, 100% { background-position: 0% 50%; }
          50% { background-position: 100% 50%; }
      }

      .title {
          font-size: clamp(1.4rem, 4vw, 2rem);
          font-weight: 600;
          color: #e0d8f0;
          margin-bottom: 0.8rem;
          animation: fadeInUp 1s ease-out 0.3s both;
      }

      .subtitle {
          font-size: clamp(0.9rem, 2.5vw, 1.1rem);
          color: #8888aa;
          line-height: 1.6;
          margin-bottom: 2.5rem;
          animation: fadeInUp 1s ease-out 0.5s both;
      }

      @keyframes fadeInUp {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
      }

      .btn-group {
          display: flex;
          gap: 1rem;
          justify-content: center;
          flex-wrap: wrap;
          animation: fadeInUp 1s ease-out 0.7s both;
      }

      .btn {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          padding: 14px 32px;
          font-size: 1rem;
          font-weight: 600;
          font-family: 'Inter', sans-serif;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          transition: all 0.3s ease;
          text-decoration: none;
      }

      .btn-primary {
          background: linear-gradient(135deg, #6a5acd, #7c6adf);
          color: #fff;
          box-shadow: 0 4px 20px rgba(106, 90, 205, 0.3);
      }

      .btn-primary:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 30px rgba(106, 90, 205, 0.45);
          background: linear-gradient(135deg, #7b6dd4, #8d7ff0);
      }

      .btn-primary:active {
          transform: translateY(0);
      }

      .btn-secondary {
          background: rgba(255, 255, 255, 0.06);
          color: #b0b0cc;
          border: 1px solid rgba(255, 255, 255, 0.1);
          backdrop-filter: blur(10px);
      }

      .btn-secondary:hover {
          background: rgba(255, 255, 255, 0.1);
          color: #d0d0e8;
          transform: translateY(-2px);
          border-color: rgba(255, 255, 255, 0.2);
      }

      .btn-secondary:active {
          transform: translateY(0);
      }

      .btn svg {
          width: 18px;
          height: 18px;
          flex-shrink: 0;
      }

      /* Compass animation */
      .compass {
          position: absolute;
          bottom: 10px;
          left: 50%;
          transform: translateX(-50%);
          width: 40px;
          height: 40px;
          opacity: 0.15;
      }

      .compass-ring {
          width: 40px;
          height: 40px;
          border: 2px solid rgba(255,255,255,0.2);
          border-radius: 50%;
          position: relative;
          animation: compassSpin 20s linear infinite;
      }

      .compass-needle {
          position: absolute;
          top: 50%;
          left: 50%;
          width: 2px;
          height: 16px;
          background: linear-gradient(to top, #ff6b6b, #fff);
          transform-origin: bottom center;
          transform: translate(-50%, -100%);
          border-radius: 1px;
      }

      .compass-needle::after {
          content: '';
          position: absolute;
          bottom: -16px;
          left: 0;
          width: 2px;
          height: 16px;
          background: linear-gradient(to top, #aaa, #fff);
          border-radius: 1px;
      }

      @keyframes compassSpin {
          0% { transform: rotate(0deg); }
          25% { transform: rotate(15deg); }
          50% { transform: rotate(-10deg); }
          75% { transform: rotate(20deg); }
          100% { transform: rotate(0deg); }
      }

      /* Particles on hover */
      .particle {
          position: fixed;
          pointer-events: none;
          width: 4px;
          height: 4px;
          border-radius: 50%;
          z-index: 100;
          animation: particleFade 1s ease-out forwards;
      }

      @keyframes particleFade {
          0% { opacity: 1; transform: scale(1); }
          100% { opacity: 0; transform: scale(0) translateY(-40px); }
      }

      /* Responsive */
      @media (max-width: 480px) {
          .scene {
              width: 260px;
              height: 230px;
          }
          .tree-left { left: 5px; }
          .tree-right { right: 5px; }
          .moon { width: 35px; height: 35px; top: 10px; right: 20px; }
      }
  </style>
</head>
<body>
<div class="fog">
  <div class="fog-layer"></div>
  <div class="fog-layer"></div>
  <div class="fog-layer"></div>
</div>

<div class="stars" id="stars"></div>

<div class="container">
  <div class="scene">
    <div class="moon"></div>

    <div class="tree tree-left">
      <div class="tree-top"></div>
      <div class="tree-trunk"></div>
    </div>

    <div class="tree tree-right">
      <div class="tree-top"></div>
      <div class="tree-trunk"></div>
    </div>

    <div class="signpost">
      <div class="pole">
        <div class="sign">
          ← Яндекс
          <span>404</span>
        </div>
      </div>
    </div>

    <div class="character">
      <div class="lantern">
        <div class="lantern-handle"></div>
        <div class="lantern-top"></div>
        <div class="lantern-body">
          <div class="lantern-glow"></div>
        </div>
      </div>
      <div class="char-head">
        <div class="char-eyes">
          <div class="char-eye"></div>
          <div class="char-eye"></div>
        </div>
        <div class="char-mouth"></div>
      </div>
      <div class="char-body"></div>
      <div class="char-legs">
        <div class="char-leg"></div>
        <div class="char-leg"></div>
      </div>
    </div>

    <div class="road"></div>
    <div class="compass">
      <div class="compass-ring">
        <div class="compass-needle"></div>
      </div>
    </div>

    <!-- Fireflies -->
    <div class="firefly" style="top:60px;left:80px;--fly-duration:7s;--fly-x:30px;--fly-y:-20px;--fly-end-x:-10px;--fly-end-y:15px;"></div>
    <div class="firefly" style="top:100px;right:70px;--fly-duration:9s;--fly-x:-25px;--fly-y:30px;--fly-end-x:15px;--fly-end-y:-10px;"></div>
    <div class="firefly" style="top:140px;left:60px;--fly-duration:11s;--fly-x:20px;--fly-y:-30px;--fly-end-x:-15px;--fly-end-y:20px;"></div>
    <div class="firefly" style="top:80px;right:100px;--fly-duration:8s;--fly-x:-15px;--fly-y:20px;--fly-end-x:25px;--fly-end-y:-15px;"></div>
    <div class="firefly" style="top:160px;left:140px;--fly-duration:10s;--fly-x:35px;--fly-y:-10px;--fly-end-x:-20px;--fly-end-y:25px;"></div>
  </div>

  <div class="error-code">404</div>
  <h1 class="title">Кажется, вы заблудились...</h1>
  <p class="subtitle">Страница, которую вы ищете, не найдена. Возможно, она была удалена, переименована или никогда не существовала. Но не волнуйтесь — мы поможем вам найти дорогу!</p>

  <div class="btn-group">
    <a href="https://ya.ru" class="btn btn-primary">
      Яндекс
    </a>
    <button class="btn btn-secondary" onclick="history.back()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"/>
        <polyline points="12 19 5 12 12 5"/>
      </svg>
      Назад
    </button>
  </div>
</div>

<script>
    // Generate stars
    const starsContainer = document.getElementById('stars');
    const starCount = 80;

    for (let i = 0; i < starCount; i++) {
        const star = document.createElement('div');
        star.className = 'star';
        star.style.left = Math.random() * 100 + '%';
        star.style.top = Math.random() * 100 + '%';
        star.style.setProperty('--duration', (3 + Math.random() * 5) + 's');
        star.style.setProperty('--max-opacity', (0.3 + Math.random() * 0.7));
        star.style.animationDelay = Math.random() * 5 + 's';
        star.style.width = (1 + Math.random() * 2) + 'px';
        star.style.height = star.style.width;
        starsContainer.appendChild(star);
    }

    // Mouse trail particles
    let lastParticleTime = 0;
    document.addEventListener('mousemove', function(e) {
        const now = Date.now();
        if (now - lastParticleTime < 80) return;
        lastParticleTime = now;

        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = e.clientX + 'px';
        particle.style.top = e.clientY + 'px';

        const colors = ['#6a5acd', '#a78bfa', '#818cf8', '#c4b5fd'];
        particle.style.background = colors[Math.floor(Math.random() * colors.length)];

        document.body.appendChild(particle);

        setTimeout(() => {
            particle.remove();
        }, 1000);
    });

    // Parallax effect on mouse move
    const scene = document.querySelector('.scene');
    document.addEventListener('mousemove', function(e) {
        const xRatio = (e.clientX / window.innerWidth - 0.5) * 2;
        const yRatio = (e.clientY / window.innerHeight - 0.5) * 2;

        scene.style.transform = `translate(${xRatio * -8}px, ${yRatio * -5}px)`;
    });
</script>
</body>
</html>