<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>نكته سريعه</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root {
      color-scheme: dark;
      --bg-1: #0b1020;
      --bg-2: #141a33;
      --accent: #ff7a18;
      --accent-2: #ffb347;
      --text: #f8fafc;
      --muted: #cbd5e1;
      --card: rgba(15, 23, 42, 0.72);
      --border: rgba(255, 255, 255, 0.12);
      --shadow: 0 24px 80px rgba(0, 0, 0, 0.42);
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      min-height: 100%;
      font-family: 'Cairo', sans-serif;
      background:
        radial-gradient(circle at top right, rgba(255, 122, 24, 0.24), transparent 28%),
        radial-gradient(circle at bottom left, rgba(255, 179, 71, 0.18), transparent 24%),
        linear-gradient(160deg, var(--bg-1), var(--bg-2));
      color: var(--text);
    }

    body {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
    }

    .shell {
      width: min(100%, 520px);
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 28px;
      box-shadow: var(--shadow);
      overflow: hidden;
      backdrop-filter: blur(18px);
    }

    .hero {
      padding: 28px 24px 18px;
      position: relative;
      isolation: isolate;
    }

    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(255, 122, 24, 0.16), transparent 60%);
      z-index: -1;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(255, 122, 24, 0.16);
      color: var(--accent-2);
      font-weight: 700;
      font-size: 14px;
      margin-bottom: 18px;
    }

    h1 {
      margin: 0;
      font-size: clamp(30px, 4vw, 42px);
      line-height: 1.15;
      letter-spacing: -0.02em;
    }

    p {
      margin: 16px 0 0;
      color: var(--muted);
      font-size: 18px;
      line-height: 1.9;
    }

    .body {
      padding: 0 24px 28px;
      display: grid;
      gap: 16px;
    }

    .quote {
      padding: 18px 18px;
      border-radius: 22px;
      border: 1px solid rgba(255, 122, 24, 0.22);
      background: rgba(15, 23, 42, 0.7);
      font-size: 20px;
      line-height: 2;
      color: #fff;
    }

    .actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    button {
      appearance: none;
      border: 0;
      border-radius: 16px;
      padding: 14px 18px;
      font: inherit;
      font-weight: 800;
      cursor: pointer;
      transition: transform 0.18s ease, filter 0.18s ease, background 0.18s ease;
    }

    button:hover { transform: translateY(-1px); }
    button:active { transform: translateY(0); }

    .close {
      flex: 1;
      min-width: 160px;
      color: #0b1020;
      background: linear-gradient(135deg, var(--accent-2), var(--accent));
    }

    .hint {
      font-size: 13px;
      color: #94a3b8;
      text-align: center;
      padding-bottom: 22px;
    }

    @media (max-width: 480px) {
      .shell { border-radius: 22px; }
      .hero, .body { padding-left: 18px; padding-right: 18px; }
      .quote { font-size: 18px; }
    }
  </style>
</head>
<body>
  <main class="shell">
    <section class="hero">
      <div class="badge">نكته سريعه</div>
      <h1>جاي عندي تدور نكته؟</h1>
      <p>روح دور بعيد، هنا ما عندنا نكتة تتعب نفسك عشانها.</p>
    </section>

    <section class="body">
      <div class="quote">النكتة الوحيدة اليوم: إنك حسبت نفسك جاي بمكان يضحك لك.</div>
      <div class="actions">
        <button class="close" type="button" id="closeBtn">إغلاق</button>
      </div>
    </section>

    <div class="hint">تعمل داخل Telegram Web App</div>
  </main>

  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <script>
    const webApp = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
    if (webApp) {
      webApp.ready();
      webApp.expand();
    }

    document.getElementById('closeBtn').addEventListener('click', () => {
      if (webApp) {
        webApp.close();
      } else {
        window.close();
      }
    });
  </script>
</body>
</html>
