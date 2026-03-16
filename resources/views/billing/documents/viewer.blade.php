<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <meta name="robots" content="noindex">
    <title>{{ config('app.name', 'Pterodactyl') }} - {{ $title }}</title>
    @include('partials.branding.favicon')
    <style>
        html, body {
            height: 100%;
            margin: 0;
            background: #eef1f5;
            color: #1f2937;
            font-family: Inter, Arial, sans-serif;
        }
        .shell {
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 18px;
            background: #ffffff;
            border-bottom: 1px solid #d8dee8;
            box-sizing: border-box;
        }
        .title {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .subtitle {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
        }
        .actions a {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 10px;
            background: #f0b90b;
            color: #111827;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }
        .viewer {
            flex: 1;
            padding: 16px;
            box-sizing: border-box;
        }
        .frame {
            width: 100%;
            height: calc(100vh - 88px);
            border: 1px solid #d8dee8;
            border-radius: 14px;
            background: #ffffff;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div>
                <div class="title">{{ $title }}</div>
                <div class="subtitle">{{ $documentTitle }}</div>
            </div>
            <div class="actions">
                <a href="{{ $rawUrl }}" target="_blank" rel="noreferrer">Open Raw PDF</a>
            </div>
        </div>
        <div class="viewer">
            <iframe class="frame" src="{{ $rawUrl }}" title="{{ $title }}"></iframe>
        </div>
    </div>
</body>
</html>
