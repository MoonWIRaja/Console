<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Burhan Console - Game server hosting control panel</title>
    <meta name="description" content="Burhan Console is the control panel for Burhan game server hosting: create, manage and pay for Minecraft and other game servers, with an AI assistant and cloud file storage.">
    @if(filled(config('app.google_site_verification')))<meta name="google-site-verification" content="{{ config('app.google_site_verification') }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #F5E7C6; --card: #FEF9E1; --text: #4B3A2F; --dim: rgba(75, 58, 47, .72); --brand: #742220; --line: #2D4A3E; --border: #C8BCA0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.75 "Geist Mono", ui-monospace, Menlo, Consolas, monospace; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 36px 16px 60px; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 28px; }
        .brand { display: flex; align-items: center; gap: 12px; color: var(--brand); font-weight: 800; font-size: 18px; text-decoration: none; }
        .brand img { width: 56px; height: 56px; border-radius: .75rem; object-fit: contain; }
        .btn { display: inline-block; padding: 9px 18px; border-radius: 10px; border: 2px solid var(--line); box-shadow: 3px 3px 0 var(--line); font-weight: 800; text-decoration: none; }
        .btn-primary { background: var(--brand); color: var(--card); }
        .btn-plain { background: var(--card); color: var(--text); }
        .hero { background: var(--card); border: 2px solid var(--line); box-shadow: 6px 6px 0 var(--line); border-radius: 18px; padding: 34px 30px; }
        h1 { margin: 0 0 10px; color: var(--brand); font-size: 30px; line-height: 1.25; }
        .lead { font-size: 15px; color: var(--dim); max-width: 700px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-top: 24px; }
        .card { background: var(--card); border: 2px solid var(--line); box-shadow: 4px 4px 0 var(--line); border-radius: 14px; padding: 18px 20px; }
        .card h2 { margin: 0 0 6px; font-size: 15px; color: var(--brand); }
        .card p { margin: 0; color: var(--dim); font-size: 13px; }
        .google { margin-top: 24px; }
        .google h2 { margin: 0 0 8px; font-size: 16px; color: var(--brand); }
        footer { display: flex; justify-content: center; gap: 18px; flex-wrap: wrap; margin-top: 30px; font-size: 12px; color: var(--dim); }
        footer a { color: var(--brand); }
        @media (max-width: 600px) { h1 { font-size: 23px; } .hero { padding: 24px 18px; } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <a class="brand" href="{{ route('legal.about') }}">
            @if(filled(config('app.logo')))<img src="{{ config('app.logo') }}" alt="Burhan Console logo">@endif
            <span>Burhan Console</span>
        </a>
        <a class="btn btn-plain" href="{{ url('/auth/login') }}">Log in</a>
    </header>

    <section class="hero">
        <h1>The control panel for Burhan game server hosting</h1>
        <p class="lead">Burhan Console is where Burhan customers create, run and pay for their game servers: Minecraft (Forge, NeoForge, Fabric, Paper and more), Project Zomboid, Palworld, Terraria, FiveM and others. It is built on the open-source Pterodactyl panel.</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/auth/login') }}">Sign up or log in</a>
            <a class="btn btn-plain" href="{{ url('/auth/login') }}">Log in</a>
        </div>
    </section>

    <div class="grid">
        <div class="card">
            <h2>Manage your servers</h2>
            <p>Start, stop and restart servers, use the live console, edit files, schedule tasks, manage databases, backups, ports and sub-users.</p>
        </div>
        <div class="card">
            <h2>Order and billing</h2>
            <p>Pick a location and the CPU, RAM and disk you need, see the monthly price, pay invoices and renew or upgrade your subscription.</p>
        </div>
        <div class="card">
            <h2>Anney, the AI assistant</h2>
            <p>Ask Anney about your server's logs, configuration, lag or crashes. In Agent mode it can propose fixes, and nothing changes until you approve each one.</p>
        </div>
        <div class="card">
            <h2>Cloud Storage</h2>
            <p>Each customer gets personal cloud storage for files such as world backups and modpacks, with share links that require signing in.</p>
        </div>
        <div class="card">
            <h2>Support</h2>
            <p>Open support tickets or chat with our team, and link your Discord account to get help in our community.</p>
        </div>
    </div>

    <section class="card google">
        <h2>How we use Google sign-in and Google Drive</h2>
        <p>You can sign in with your Google account; we use only your name, email address and profile picture to create and secure your panel account. Files uploaded to Cloud Storage are kept in Google Drive using the restricted <code>drive.file</code> permission, which only lets the panel access files it created. See our <a href="{{ route('legal.privacy') }}">Privacy Policy</a> for details, including our compliance with the Google API Services User Data Policy and its Limited Use requirements.</p>
    </section>

    <footer>
        <span>&copy; {{ date('Y') }} Burhan</span>
        <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
        <a href="mailto:support@burhan.my">support@burhan.my</a>
    </footer>
</div>
</body>
</html>
