<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Authentication Processing</title>

    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --success: #16a34a;
            --danger: #dc2626;
            --gray: #6b7280;
            --bg: #f3f4f6;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .card {
            background: white;
            padding: 36px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
            text-align: center;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 10px;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            margin: 20px auto;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        h2 {
            margin: 0;
            font-size: 22px;
            color: #111827;
        }

        p {
            color: var(--gray);
            font-size: 14px;
            margin-top: 8px;
        }

        .status {
            font-size: 13px;
            margin-top: 16px;
            color: var(--gray);
        }

        .error-box {
            display: none;
            margin-top: 20px;
            text-align: left;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 16px;
        }

        .error-title {
            font-weight: 600;
            color: var(--danger);
            margin-bottom: 6px;
        }

        .error-text {
            font-size: 13px;
            color: #7f1d1d;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .btn {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        pre {
            font-size: 12px;
            background: #fff;
            padding: 10px;
            border-radius: 6px;
            overflow: auto;
        }
    </style>
</head>

<body>

    <div class="card">
        <div class="logo">SSO Authentication</div>

        <div class="spinner" id="spinner" @if(isset($serverError)) style="display:none" @endif></div>

        <h2 id="title" @if(isset($serverError)) style="display:none" @endif>
            Signing you in...
        </h2>

        <p id="subtitle" @if(isset($serverError)) style="display:none" @endif>
            Please wait while we securely authenticate your account.
        </p>

        <div class="status" id="statusText" @if(isset($serverError)) style="display:none" @endif>
            Processing token...
        </div>

        <div class="error-box" id="errorBox" @if(isset($serverError)) style="display:block" @endif>
            <div class="error-title">Authentication Failed</div>

            <div class="error-text" id="errorText">
                @if(isset($serverError))
                {{ $serverError }}
                @else
                No access token found in URL
                @endif
            </div>

            @if(isset($serverErrorContext))
            <pre>{{ json_encode($serverErrorContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif

            <div class="actions">
                <a href="{{ route(config('iam.login_route_name', 'login')) }}" class="btn btn-primary">
                    Retry Login
                </a>
                <a href="/" class="btn btn-secondary">
                    Home
                </a>
            </div>
        </div>
    </div>

    @unless(isset($serverError))
    <script>
        (function() {
            const statusText = document.getElementById('statusText');

            statusText.innerText = "Reading authentication data...";

            const hash = window.location.hash.substring(1);
            const params = new URLSearchParams(hash);
            let accessToken = params.get('access_token');

            if (!accessToken) {
                statusText.innerText = "Checking alternative source...";
                const queryParams = new URLSearchParams(window.location.search);
                accessToken = queryParams.get('access_token');
            }

            if (accessToken) {
                statusText.innerText = "Validating token...";

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("iam.sso.callback") }}';

                const fields = {
                    token: accessToken,
                    access_token: accessToken,
                    _token: '{{ csrf_token() }}'
                };

                Object.entries(fields).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                });

                document.body.appendChild(form);

                setTimeout(() => {
                    statusText.innerText = "Signing you in...";
                    form.submit();
                }, 300);
            } else {
                document.getElementById('spinner').style.display = 'none';
                document.getElementById('title').style.display = 'none';
                document.getElementById('subtitle').style.display = 'none';
                document.getElementById('statusText').style.display = 'none';
                document.getElementById('errorBox').style.display = 'block';
            }
        })();
    </script>
    @endunless

</body>

</html>