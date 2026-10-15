<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - Burhan Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #F5E7C6; --card: #FEF9E1; --text: #4B3A2F; --dim: rgba(75, 58, 47, .72); --brand: #742220; --line: #2D4A3E; --border: #C8BCA0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.75 "Geist Mono", ui-monospace, Menlo, Consolas, monospace; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 40px 16px 60px; }
        header { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
        header img { width: 56px; height: 56px; border-radius: .75rem; object-fit: contain; }
        header a { color: inherit; text-decoration: none; font-weight: 800; font-size: 18px; color: var(--brand); }
        main { background: var(--card); border: 2px solid var(--line); box-shadow: 6px 6px 0 var(--line); border-radius: 16px; padding: 28px 30px; }
        h1 { margin: 0 0 4px; color: var(--brand); font-size: 26px; }
        h2 { margin: 30px 0 8px; font-size: 16px; color: var(--brand); border-bottom: 1px solid var(--border); padding-bottom: 6px; }
        p, li { color: var(--text); }
        ul { padding-left: 20px; }
        .muted { color: var(--dim); font-size: 13px; }
        .note { background: #F5EFD5; border-left: 3px solid var(--brand); padding: 10px 14px; border-radius: 6px; margin: 14px 0; }
        a { color: var(--brand); }
        footer { text-align: center; margin-top: 24px; font-size: 12px; color: var(--dim); }
        @media (max-width: 600px) { main { padding: 20px 16px; } h1 { font-size: 22px; } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        @if(filled(config('app.logo')))<img src="{{ config('app.logo') }}" alt="Burhan Console logo">@endif
        <a href="{{ config('app.url') }}">Burhan Console</a>
    </header>

    <main>
        <h1>Privacy Policy</h1>
        <p class="muted">Effective date: 26 September 2026</p>

        <p>This policy explains how Burhan ("we", "us") collects, uses and protects information when you use the Burhan Console game server hosting panel at <a href="{{ config('app.url') }}">{{ preg_replace('#^https?://#', '', config('app.url')) }}</a>, its AI assistant Anney, and our Discord bot.</p>

        <h2>1. Information we collect</h2>
        <ul>
            <li><strong>Account details:</strong> username, email address, first and last name, password (stored only as a secure hash), and an optional profile picture.</li>
            <li><strong>Sign-in with Google or Discord:</strong> if you choose to link or sign in with these services, we receive your account ID, email address, display name and avatar from that provider. We do not receive your Google or Discord password.</li>
            <li><strong>Security and activity records:</strong> IP address, browser user agent, login attempts and actions you take in the panel (for example starting a server or changing a file), kept to protect your account and investigate abuse.</li>
            <li><strong>Game servers:</strong> the files, configuration, worlds, logs and backups on the servers you create, and details of players who connect to them where the game reports it.</li>
            <li><strong>Billing:</strong> billing profile (name, address, phone, company details if provided), orders, invoices, subscriptions and coupon use. Payments are processed by our payment providers; we do not store your full card or bank details.</li>
            <li><strong>Support:</strong> the content of support tickets, chats and attachments you send us.</li>
            <li><strong>Anney AI assistant:</strong> your chat messages, attachments you send to Anney, short notes Anney saves about your preferences (for example your preferred language), and records of actions you approve.</li>
            <li><strong>Cloud Storage:</strong> files you upload to Cloud Storage or attach in the Anney chat, their names and sizes, and who you share them with.</li>
            <li><strong>Cookies:</strong> essential cookies for your login session and security (CSRF protection). We also use Cloudflare Turnstile to block automated abuse on sign-in forms.</li>
        </ul>

        <h2>2. How we use information</h2>
        <ul>
            <li>To provide, run and secure your game servers and your account.</li>
            <li>To process orders, renewals and invoices, and to send service and billing emails (for example password reset PINs and renewal reminders).</li>
            <li>To provide support and to answer your questions through Anney.</li>
            <li>To detect and prevent fraud, abuse and security incidents.</li>
            <li>To comply with legal obligations.</li>
        </ul>
        <p>We do not sell your personal information and we do not use it for advertising.</p>

        <h2>3. Anney, our AI assistant</h2>
        <p>When you chat with Anney, your message, the relevant conversation history, and any file content Anney reads are sent to the third-party AI model provider selected by our administrators to generate a reply. Anney can only read and, with your explicit approval of each action, change files on servers you have access to. Anney remembers short preference notes about you so it can help you in the panel and on Discord; you can ask Anney to forget them at any time.</p>
        <p>On Discord, Anney replies when you mention it, reply to it or message it directly. Billing information is only discussed in direct messages, never in public channels.</p>

        <h2>4. Cloud Storage and Google user data</h2>
        <p>Files you upload to Cloud Storage are stored in Google Drive accounts owned by Burhan, in a folder set aside for your account. Access to a file is limited to you, our administrators for support and abuse handling, and people who open a share link you create (they must be signed in to download it). You can stop sharing or delete a file at any time.</p>
        <div class="note">
            <p style="margin:0">Burhan Console's use and transfer of information received from Google APIs to any other app will adhere to the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
        </div>
        <p>When you sign in with Google we use only your basic profile (name, email, profile picture) to create and secure your account. The panel's Google Drive connection uses the restricted <code>drive.file</code> permission, which only lets the panel access files it created itself; it cannot see other files in the Drive. We do not use Google user data to develop or train AI models, and we do not sell it or use it for advertising.</p>

        <h2>5. Sharing with service providers</h2>
        <p>We share information only as needed to run the service, with:</p>
        <ul>
            <li>hosting and network infrastructure, including Cloudflare;</li>
            <li>payment providers that process your payments;</li>
            <li>Google (sign-in and Drive storage) and Discord (sign-in, community and bot features);</li>
            <li>AI model providers used by Anney;</li>
            <li>our email provider for account and billing emails;</li>
            <li>authorities, when required by law.</li>
        </ul>

        <h2>6. Retention</h2>
        <p>We keep account and server data while your account is active. Deleted servers and files are removed from our systems, subject to short-lived backups. Billing records are kept for as long as required for accounting and tax purposes. Application logs are kept for a limited period. You can delete Anney chats and Cloud Storage files yourself at any time.</p>

        <h2>7. Security</h2>
        <p>We use HTTPS, hashed passwords, optional two-factor authentication, encrypted storage of sensitive credentials, and access controls that keep each customer's servers and files separate. No system is perfectly secure, so please use a strong, unique password.</p>

        <h2>8. Your rights and choices</h2>
        <p>You can view and update your account details in the panel, unlink Google or Discord, delete your files and chats, and ask us to access, correct or delete your personal data by contacting us. We will respond within a reasonable time and as required by applicable law, including Malaysia's Personal Data Protection Act 2010.</p>

        <h2>9. Children</h2>
        <p>The service is not intended for children under 13. If you believe a child has given us personal information, contact us and we will delete it.</p>

        <h2>10. Changes to this policy</h2>
        <p>We may update this policy. We will change the effective date above and, for significant changes, notify you in the panel or by email.</p>

        <h2>11. Contact</h2>
        <p>Questions or requests: <a href="mailto:support@burhan.my">support@burhan.my</a></p>
    </main>

    <footer>&copy; {{ date('Y') }} Burhan. All rights reserved.</footer>
</div>
</body>
</html>
